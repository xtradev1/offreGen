// Recette par l'interface : session perdue pendant un geste (quantité tapée), capture « après » pour le comparatif, puis
// reconnexion : la valeur tapée part. Usage : node recette-session.js. Sortie : recette-session.json, captures/session-apres.png.
'use strict';
const fs = require('fs');
const path = require('path');
const { execSync } = require('child_process');
const { BASE, CAPTURES, navigateur, reseau } = require('./commun');

const remettre = () => execSync('wsl -d Ubuntu -u root -e sh /home/xavie/offreGen-fluidite-banc/base.sh remettre', { stdio: 'pipe' });
const sql = (requete) => execSync('wsl -d Ubuntu -u root -e mariadb -N offregen_fluidite_vue -e "' + requete + '"', { encoding: 'utf8' }).trim();
const calme = (page) => page.waitForFunction(() => window.fluide && !window.fluide.occupe(), null, { timeout: 60000 });
const bandeau = (page) => page.evaluate(() => { const b = document.querySelector('.reseau'); return b && !b.hidden ? b.textContent.trim().replace(/\s+/g, ' ') : ''; });

(async () => {
  remettre();
  const { browser, contexte } = await navigateur();
  const erreurs = [];
  contexte.on('page', (p) => p.on('pageerror', (e) => erreurs.push(e.message)));
  const page = await contexte.newPage();
  await reseau(page, '4g');
  await page.goto(BASE + '/offres/3', { waitUntil: 'load' });
  await page.evaluate(() => navigator.serviceWorker.ready.then(() => true));
  if (!(await page.evaluate(() => !!navigator.serviceWorker.controller))) await page.reload({ waitUntil: 'load' });
  await calme(page);
  await page.waitForTimeout(800);

  const ligne = await page.locator('#lignes .row:not(.flag):not(.miss)').nth(9).getAttribute('id');
  const id = ligne.slice(1);
  const avant = sql('SELECT quantite_milliemes FROM ligne WHERE id = ' + id);
  const cookie = (await contexte.cookies()).find((c) => c.name === 'offregen');
  await contexte.clearCookies();
  const champ = page.locator('#' + ligne + ' input[name=quantite]');
  await champ.scrollIntoViewIfNeeded();
  await page.evaluate((l) => document.getElementById(l).scrollIntoView({ block: 'center' }), ligne);
  await champ.fill('7');
  await champ.press('Tab');
  await page.waitForTimeout(2500);
  const texte = await bandeau(page);
  const pendant = sql('SELECT quantite_milliemes FROM ligne WHERE id = ' + id);
  await page.screenshot({ path: path.join(CAPTURES, 'session-apres.png') });

  await contexte.addCookies([cookie]);
  await page.goto(BASE + '/offres/3', { waitUntil: 'load' });
  await calme(page);
  await page.waitForTimeout(1500);
  const apres = sql('SELECT quantite_milliemes FROM ligne WHERE id = ' + id);
  await browser.close();

  const r = {
    bandeau: texte,
    avant: Number(avant) / 1000,
    pendant: Number(pendant) / 1000,
    apres: Number(apres) / 1000 + ' en base (7 tapé sans session)',
    ok: Number(pendant) === Number(avant) && Number(apres) === 7000 && /Session expirée/.test(texte),
    erreurs,
  };
  fs.writeFileSync(path.join(__dirname, 'recette-session.json'), JSON.stringify(r, null, 1));
  console.log(JSON.stringify(r, null, 1));
})().catch((e) => { console.error(e); process.exit(1); });
