// Recette par l'interface des gestes sans rechargement (lots 2 et 3), sur le banc local, téléphone, réseau donné.
// Usage : node recette-gestes.js [profil]. Chaque geste : au toucher (l'écran change), confirmé (la file est vide),
// requêtes et octets, page rechargée ou non, erreurs JavaScript. Captures dans captures/recette-*.png.
'use strict';
const fs = require('fs');
const path = require('path');
const { execSync } = require('child_process');
const { BASE, CAPTURES, navigateur, reseau, compteur } = require('./commun');

const profil = process.argv[2] || 'chantier';
const remettre = () => execSync('wsl -d Ubuntu -u root -e sh /home/xavie/offreGen-fluidite-banc/base.sh remettre', { stdio: 'pipe' });
const resultats = [];

async function ouvrir(contexte, adresse) {
  const page = await contexte.newPage();
  const erreurs = [];
  page.on('pageerror', (e) => erreurs.push(String(e.message)));
  page.on('console', (m) => { if (m.type() === 'error') erreurs.push('console : ' + m.text()); });
  page.on('dialog', (d) => d.accept());
  await reseau(page, profil);
  await page.goto(BASE + adresse, { waitUntil: 'load', timeout: 90000 });
  await page.waitForTimeout(400);
  return { page, erreurs };
}

const calme = (page) => page.waitForFunction(() => window.fluide && !window.fluide.occupe(), null, { timeout: 60000 });

async function geste(nom, page, erreurs, faire, vu, arg = null) {
  const c = compteur(page);
  const origine = await page.evaluate(() => performance.timeOrigin);
  c.debut();
  const t0 = Date.now();
  await faire();
  await page.waitForFunction(vu, arg, { timeout: 30000, polling: 16 });
  const auToucher = Date.now() - t0;
  await calme(page);
  const confirme = Date.now() - t0;
  const m = await c.fin();
  const recharge = await page.evaluate((o) => performance.timeOrigin !== o, origine);
  await page.screenshot({ path: path.join(CAPTURES, 'recette-' + nom + '.png') });
  const r = { geste: nom, profil, au_toucher_ms: auToucher, confirme_ms: confirme, requetes: m.requetes, octets: m.octets, recharge, erreurs: [...erreurs],
    detail: m.detail.map((d) => `${d.methode} ${d.url.slice(0, 70)} ${d.octets}`) };
  resultats.push(r);
  console.log(`${nom.padEnd(26)} toucher ${String(auToucher).padStart(5)} ms  confirmé ${String(confirme).padStart(5)} ms  ${String(m.requetes).padStart(2)} req ${String(Math.round(m.octets / 102.4) / 10).padStart(5)} Ko  ${recharge ? 'RECHARGÉE' : 'sans rechargement'}${erreurs.length ? '  ERREURS ' + erreurs.join(' | ') : ''}`);
  erreurs.length = 0;
  return r;
}

(async () => {
  remettre();
  const { browser, contexte } = await navigateur();
  // Les fichiers arrivent une fois, comme sur un téléphone qui a déjà ouvert l'application (règles du lot 1).
  const p0 = await contexte.newPage();
  await p0.goto(BASE + '/', { waitUntil: 'load' });
  await p0.close();

  let { page, erreurs } = await ouvrir(contexte, '/offres/3');
  // « C'est juste » sur la première ligne à vérifier.
  const flag = await page.locator('#lignes .row.flag').first().getAttribute('id');
  await geste('offre-juste', page, erreurs, () => page.click('#' + flag + ' form.note.warn button'),
    (id) => !document.getElementById(id).classList.contains('flag'), flag);
  // La corbeille : la ligne part, « Annuler » paraît.
  const cible = await page.locator('#lignes .row:not(.flag):not(.miss)').nth(2).getAttribute('id');
  await geste('offre-corbeille', page, erreurs, () => page.click('#' + cible + ' button.del'),
    (id) => document.getElementById(id).hidden && !!document.querySelector('.app > form.snack[data-disparait]'), cible);
  await geste('offre-annuler', page, erreurs, () => page.click('.app > form.snack[data-disparait] button'),
    (id) => !document.getElementById(id).hidden, cible);
  // Deux corbeilles vite : deux « Annuler » (défaut du 2026-11-23 : un seul).
  const deux = await page.locator('#lignes .row:not(.flag):not(.miss)').evaluateAll((rs) => rs.slice(4, 6).map((r) => r.id));
  await geste('offre-deux-corbeilles', page, erreurs, async () => {
    await page.click('#' + deux[0] + ' button.del');
    await page.waitForTimeout(300);
    await page.click('#' + deux[1] + ' button.del');
  }, () => document.querySelectorAll('.app > form.snack[data-disparait]').length >= 2);
  const snacks = await page.locator('.app > form.snack[data-disparait]').count();
  console.log('  « Annuler » à l\'écran :', snacks);
  // Une quantité : le montant de la ligne s'estime au toucher.
  const q = await page.locator('#lignes .row:not(.flag):not(.miss)').nth(8).getAttribute('id');
  await geste('offre-quantite', page, erreurs, async () => {
    await page.fill('#' + q + ' input[name=quantite]', '7');
    await page.locator('#' + q + ' input[name=quantite]').press('Tab');
  }, (id) => document.querySelector('#' + id + ' .tt').classList.contains('provisoire') || document.querySelector('#' + id + ' input[name=quantite]').defaultValue === '7', q);
  // La ligne en fenêtre au téléphone : désignation changée, « Enregistrer ».
  const l = await page.locator('#lignes .row:not(.flag):not(.miss)').nth(10).getAttribute('id');
  await page.click('#' + l + ' a.nm');
  await page.waitForSelector('[data-feuille-page].ouvert #ligne, [data-feuille-page].ouvert form[action$="' + l.slice(1) + '"]', { timeout: 30000 });
  await page.waitForTimeout(400);
  await page.fill('[data-feuille-page] input[name=designation]', 'Ligne renommée au banc');
  await geste('ligne-enregistrer', page, erreurs, () => page.click('[data-feuille-page] .sh-pied button[type=submit], [data-feuille-page] button[type=submit][form]'),
    (id) => !!document.querySelector('#' + id + ' a.nm') && document.querySelector('#' + id + ' a.nm').textContent.includes('Ligne renommée au banc'), l);
  // « + Ligne » : la fenêtre préparée s'ouvre sans requête ; trois produits ; « Terminé ».
  await page.waitForTimeout(2500);
  await geste('ajout-ouvrir', page, erreurs, () => page.click('a.btn.ajout'), () => !!document.querySelector('[data-feuille-page].ouvert #res'));
  const avant = await page.locator('#lignes .row').count();
  for (let i = 0; i < 3; i++) {
    await geste('ajout-produit-' + (i + 1), page, erreurs, () => page.locator('[data-feuille-page] #res form:not([hidden]) button.pick').nth(i).click(),
      (n) => document.querySelectorAll('#lignes .row').length >= n, avant + i + 1);
  }
  await geste('ajout-termine', page, erreurs, () => page.click('[data-feuille-page] .sh-pied a.btn.primary'), () => !document.querySelector('[data-feuille-page].ouvert'));
  await page.close();

  // Relecture : la ligne suivante tout de suite.
  ({ page, erreurs } = await ouvrir(contexte, '/offres/3/relire'));
  const premiere = await page.locator('section[data-relu]:not([hidden])').getAttribute('data-relu');
  await page.fill('section[data-relu="' + premiere + '"] input[name=prix]', '48');
  await geste('relire-juste', page, erreurs, () => page.click('[data-relu-gestes] button[type=submit]'),
    (id) => document.querySelector('section[data-relu="' + id + '"]').hidden, premiere);
  await page.close();

  // Liste : archiver en glissant, puis « Annuler ».
  ({ page, erreurs } = await ouvrir(contexte, '/'));
  const carte = await page.locator('#liste-offres .glisse').first().getAttribute('id');
  const boite = await page.locator('#' + carte + ' .ocard').boundingBox();
  await page.mouse.move(boite.x + boite.width - 20, boite.y + boite.height / 2);
  await page.mouse.down();
  for (let x = 0; x <= 220; x += 20) await page.mouse.move(boite.x + boite.width - 20 - x, boite.y + boite.height / 2);
  await page.mouse.up();
  await page.waitForSelector('#' + carte + '.ouverte');
  await geste('liste-archiver', page, erreurs, () => page.click('#' + carte + ' button.arch'),
    (id) => document.getElementById(id).hidden && !!document.querySelector('.app > form.snack[data-disparait]'), carte);
  await geste('liste-annuler', page, erreurs, () => page.click('.app > form.snack[data-disparait] button'), (id) => !document.getElementById(id).hidden, carte);
  await page.close();

  await browser.close();
  fs.writeFileSync(path.join(__dirname, 'recette-gestes-' + profil + '.json'), JSON.stringify(resultats, null, 1));
})().catch((e) => { console.error(e); process.exit(1); });
