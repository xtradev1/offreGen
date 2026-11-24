// Corbeille, annuler, corbeille encore (Xavier, 2026-11-24 : « ça resupprime mais sans rajouter la petite ligne
// annulée »). Trois cycles sur la ligne 37 de l'offre 3, puis la même chose sur une carte de la liste des offres.
// Usage : node recette-reannuler.js [profil]. Sortie : console, code 1 au premier défaut.
'use strict';
const { execSync } = require('child_process');
const { BASE, navigateur, reseau, CAPTURES } = require('./commun');
const path = require('path');

const profil = process.argv[2] || 'local';
const remettre = () => execSync('wsl -d Ubuntu -u root -e sh /home/xavie/offreGen-fluidite-banc/base.sh remettre', { stdio: 'pipe' });
const defauts = [];
const constat = (ok, texte) => { console.log((ok ? 'ok     ' : 'DEFAUT ') + texte); if (!ok) defauts.push(texte); };

(async () => {
  remettre();
  const { browser, contexte } = await navigateur();
  const page = await contexte.newPage();
  await reseau(page, profil);
  const calme = () => page.waitForFunction(() => window.fluide && !window.fluide.occupe(), null, { timeout: 60000 });
  const bandeaux = (fin) => page.evaluate((f) => [...document.querySelectorAll('.app > .snack, .bas > .snack')]
    .filter((s) => (s.getAttribute('action') || '').endsWith(f)).map((s) => s.textContent.trim()), fin);

  await page.goto(BASE + '/offres/3', { waitUntil: 'load' });
  await calme();
  for (let cycle = 1; cycle <= 3; cycle++) {
    await page.evaluate(() => document.getElementById('l37').scrollIntoView({ block: 'center' }));
    await page.click('#l37 form[action$="/supprimer"] button');
    await page.waitForTimeout(150);
    const apres = await bandeaux('/lignes/37/restaurer');
    const cachee = await page.evaluate(() => document.getElementById('l37').hidden);
    constat(cachee && apres.length === 1, `ligne, cycle ${cycle} : ligne cachée (${cachee}), bandeaux « Annuler » ${apres.length} ${JSON.stringify(apres)}`);
    await page.screenshot({ path: path.join(CAPTURES, `reannuler-ligne-${cycle}.png`) });
    if (!apres.length) break;
    // Réseau lent : « Annuler » touché avant la réponse de la suppression, dont le bandeau ne doit pas revenir.
    if (profil === 'local') await calme();
    await page.click('.snack[action$="/lignes/37/restaurer"] button');
    await calme();
    await page.waitForTimeout(300);
    const remise = await page.evaluate(() => { const l = document.getElementById('l37'); return !!l && !l.hidden; });
    const restant = await bandeaux('/lignes/37/restaurer');
    constat(remise && restant.length === 0, `ligne, cycle ${cycle} : remise (${remise}), bandeaux restants ${restant.length}`);
  }

  // La même chose sur la liste des offres : glisser n'est pas nécessaire, le formulaire de la carte suffit.
  remettre();
  await page.goto(BASE + '/', { waitUntil: 'load' });
  await calme();
  const carte = await page.evaluate(() => {
    const f = document.querySelector('#liste-offres .glisse[data-glisse] form[action$="/archiver"]');
    return f ? f.getAttribute('action') : null;
  });
  if (!carte) constat(false, 'liste : aucune carte archivable');
  else {
    const remise = carte.replace(/\/archiver$/, '/restaurer');
    for (let cycle = 1; cycle <= 2; cycle++) {
      await page.evaluate((a) => document.querySelector(`form[action="${a}"]`).requestSubmit(), carte);
      await page.waitForTimeout(150);
      const apres = await bandeaux(remise);
      constat(apres.length === 1, `carte, cycle ${cycle} : bandeaux « Annuler » ${apres.length} ${JSON.stringify(apres)}`);
      if (!apres.length) break;
      if (profil === 'local') await calme();
      await page.click(`.snack[action="${remise}"] button`);
      await calme();
      await page.waitForTimeout(300);
      constat((await bandeaux(remise)).length === 0, `carte, cycle ${cycle} : aucun bandeau revenu après « Annuler »`);
      const visible = await page.evaluate((a) => { const f = document.querySelector(`form[action="${a}"]`); return !!f && !f.closest('.glisse').hidden; }, carte);
      constat(visible, `carte, cycle ${cycle} : remise (${visible})`);
    }
  }
  await browser.close();
  console.log(defauts.length ? `${defauts.length} défaut(s)` : 'aucun défaut');
  process.exit(defauts.length ? 1 : 0);
})().catch((e) => { console.error(e); process.exit(2); });
