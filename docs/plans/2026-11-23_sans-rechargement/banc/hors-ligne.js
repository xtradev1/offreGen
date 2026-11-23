// Ce que voit l'artisan quand le réseau tombe ou rame, geste par geste. Captures « avant » de la maquette.
// Usage : node hors-ligne.js. La base est remise à l'instantané avant chaque cas.
'use strict';
const fs = require('fs');
const path = require('path');
const { execSync } = require('child_process');
const { BASE, ICI, CAPTURES, navigateur, reseau } = require('./commun');

const remettre = () => execSync('wsl -d Ubuntu -u root -e sh /home/xavie/offreGen-fluidite-banc/base.sh remettre', { stdio: 'pipe' });
const sql = (q) => execSync(`wsl -d Ubuntu -u root -e mariadb -N offregen_fluidite_vue -e "${q}"`, { stdio: 'pipe' }).toString().trim();
const resultats = [];
const noter = (cas, constat) => { resultats.push({ cas, ...constat }); console.log(cas, JSON.stringify(constat)); };

(async () => {
  const { browser, contexte } = await navigateur();
  const ouvrir = async (chemin, profil = 'local') => {
    remettre();
    const page = await contexte.newPage();
    page.on('dialog', (d) => d.accept());
    await reseau(page, profil);
    await page.goto(BASE + chemin, { waitUntil: 'load' });
    await page.waitForTimeout(400);
    return page;
  };
  const capture = async (page, nom) => { await page.screenshot({ path: path.join(CAPTURES, nom + '.png') }); };

  // 1. Hors réseau : « C'est juste ».
  let page = await ouvrir('/offres/3');
  await contexte.setOffline(true);
  await page.click('#l14 form[action$="/juste"] button').catch(() => {});
  await page.waitForTimeout(1500);
  noter('hors-ligne-juste', { adresse: page.url().replace(BASE, ''), titre: await page.title(), texte: (await page.evaluate(() => document.body.innerText)).slice(0, 160) });
  await capture(page, 'hors-ligne-juste');
  await contexte.setOffline(false);
  noter('hors-ligne-juste-base', { a_verifier_ligne_14: sql('SELECT a_verifier FROM ligne WHERE id = 14') });
  await page.close();

  // 2. Hors réseau : corriger une quantité, puis le réseau revient. La valeur part-elle seule ?
  page = await ouvrir('/offres/3');
  const avant = sql('SELECT quantite_milliemes FROM ligne WHERE id = 12');
  await contexte.setOffline(true);
  await page.fill('#l12 input[name=quantite]', '27');
  await page.press('#l12 input[name=quantite]', 'Enter');
  await page.waitForTimeout(1500);
  const message = await page.evaluate(() => (document.querySelector('[data-sauvegarde]') || {}).textContent);
  await capture(page, 'hors-ligne-quantite');
  await contexte.setOffline(false);
  await page.waitForTimeout(8000);
  const apresRetour = sql('SELECT quantite_milliemes FROM ligne WHERE id = 12');
  await page.goto(BASE + '/offres/2', { waitUntil: 'load' });
  await page.waitForTimeout(1500);
  const apresDepart = sql('SELECT quantite_milliemes FROM ligne WHERE id = 12');
  noter('hors-ligne-quantite', { message, avant, apres_retour_du_reseau_8s: apresRetour, apres_avoir_quitte_la_page: apresDepart });
  await page.close();

  // 3. Hors réseau : « + Ligne ».
  page = await ouvrir('/offres/3');
  await contexte.setOffline(true);
  await page.click('a.btn.ajout').catch(() => {});
  await page.waitForTimeout(2000);
  noter('hors-ligne-ajout', { adresse: page.url().replace(BASE, ''), texte: (await page.evaluate(() => document.body.innerText)).slice(0, 160) });
  await capture(page, 'hors-ligne-ajout');
  await contexte.setOffline(false);
  await page.close();

  // 4. Hors réseau : ouvrir une offre depuis la liste.
  page = await ouvrir('/');
  await contexte.setOffline(true);
  await page.click('a.ocard[href="/offres/3"]').catch(() => {});
  await page.waitForTimeout(1500);
  noter('hors-ligne-ouvrir', { adresse: page.url().replace(BASE, ''), texte: (await page.evaluate(() => document.body.innerText)).slice(0, 160) });
  await capture(page, 'hors-ligne-ouvrir');
  await contexte.setOffline(false);
  await page.close();

  // 5. Réseau de chantier : ce que voit l'artisan 1 s après « C'est juste ».
  page = await ouvrir('/offres/3', 'chantier');
  const t = Date.now();
  const charge = page.waitForEvent('load', { timeout: 60000 });
  await page.click('#l14 form[action$="/juste"] button');
  await page.waitForTimeout(1000);
  await capture(page, 'lent-juste-1s');
  const bouton = await page.evaluate(() => { const b = document.querySelector('#l14 form[action$="/juste"] button'); return b ? { desactive: b.disabled, texte: b.textContent.trim() } : 'remplacé'; }).catch(() => 'page en cours de remplacement');
  await charge;
  noter('lent-juste', { bouton_apres_1s: bouton, rendu_ms: Date.now() - t });
  await page.close();

  // 6. Réseau de chantier : une corbeille seule (témoin), puis deux corbeilles à 0,7 s d'écart (l'écran ne bouge pas,
  // on retouche). Le bandeau « Annuler » est relevé dès la page chargée (il disparaît seul après 8 s).
  const snacks = (p) => p.evaluate(() => [...document.querySelectorAll('.snack')].map((s) => s.textContent.replace(/\s+/g, ' ').trim()));
  page = await ouvrir('/offres/3', 'chantier');
  let fin = page.waitForEvent('load', { timeout: 60000 });
  await page.click('#l37 form[action$="/supprimer"] button');
  await fin;
  noter('lent-une-corbeille', { snack: await snacks(page), lignes_retirees: sql('SELECT GROUP_CONCAT(id) FROM ligne WHERE offre_id = 3 AND supprimee_le IS NOT NULL') });
  await page.close();
  page = await ouvrir('/offres/3', 'chantier');
  const dates = [];
  page.on('response', (r) => { if (r.request().method() === 'POST') dates.push(`${r.status()} ${r.url().replace(BASE, '')}`); });
  await page.click('#l37 form[action$="/supprimer"] button');
  await page.waitForTimeout(700);
  fin = page.waitForEvent('load', { timeout: 60000 });
  await page.click('#l36 form[action$="/supprimer"] button', { timeout: 3000 }).catch((e) => dates.push('second toucher impossible : ' + e.message.split('\n')[0]));
  await fin;
  const snackDouble = await snacks(page);
  await capture(page, 'lent-deux-corbeilles');
  noter('lent-deux-corbeilles', { reponses: dates, lignes_retirees: sql('SELECT GROUP_CONCAT(id) FROM ligne WHERE offre_id = 3 AND supprimee_le IS NOT NULL'), snack: snackDouble });
  await page.close();

  // 7. Réseau de chantier : glisser l'offre, « Archiver », puis retoucher « Archiver » 0,6 s plus tard.
  page = await ouvrir('/', 'chantier');
  const r7 = [];
  page.on('response', (r) => { if (r.request().method() === 'POST') r7.push(`${r.status()} ${r.url().replace(BASE, '')}`); });
  const carte = page.locator('[data-glisse]:has(form[action="/offres/2/archiver"]) .ocard');
  const b = await carte.boundingBox();
  await page.mouse.move(b.x + b.width - 30, b.y + b.height / 2);
  await page.mouse.down();
  for (let dx = 20; dx <= 220; dx += 20) await page.mouse.move(b.x + b.width - 30 - dx, b.y + b.height / 2);
  await page.mouse.up();
  await page.waitForTimeout(400);
  fin = page.waitForEvent('load', { timeout: 60000 });
  await page.click('form[action="/offres/2/archiver"] button');
  await page.waitForTimeout(600);
  await page.click('form[action="/offres/2/archiver"] button', { timeout: 3000 }).catch((e) => r7.push('second toucher impossible : ' + e.message.split('\n')[0]));
  await fin;
  await page.waitForTimeout(1500);
  const snackArch = await snacks(page);
  await capture(page, 'lent-deux-archiver');
  noter('lent-deux-archiver', { reponses: r7, adresse: page.url().replace(BASE, ''), titre: await page.title(), snack: snackArch, archivee: sql('SELECT archivee_le IS NOT NULL FROM offre WHERE id = 2') });
  await page.close();

  // 8. Session expirée (cookie perdu) : corriger une quantité. Que dit l'écran, qu'est-ce qui est enregistré ?
  page = await ouvrir('/offres/3');
  const avant8 = sql('SELECT quantite_milliemes FROM ligne WHERE id = 12');
  const cookies = await contexte.cookies();
  await contexte.clearCookies();
  await page.fill('#l12 input[name=quantite]', '33');
  await page.press('#l12 input[name=quantite]', 'Enter');
  await page.waitForTimeout(3000);
  const message8 = await page.evaluate(() => (document.querySelector('[data-sauvegarde]') || {}).textContent);
  await capture(page, 'session-expiree-quantite');
  noter('session-expiree-quantite', { message: message8, avant: avant8, apres: sql('SELECT quantite_milliemes FROM ligne WHERE id = 12'), adresse: page.url().replace(BASE, '') });
  await contexte.addCookies(cookies);
  await page.close();

  fs.writeFileSync(path.join(ICI, 'hors-ligne.json'), JSON.stringify(resultats, null, 1));
  await browser.close();
})().catch((e) => { console.error(e); process.exit(1); });
