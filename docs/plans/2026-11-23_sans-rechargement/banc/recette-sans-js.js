// Critère de la SPEC : sans JavaScript, chaque formulaire marche encore comme avant (envoi, redirection, page entière).
// Navigateur au JavaScript coupé, banc local : « C'est juste », corbeille, quantité, archiver depuis la liste.
// Usage : node recette-sans-js.js. Sortie : console et recette-sans-js.json.
'use strict';
const fs = require('fs');
const path = require('path');
const { execSync } = require('child_process');
const { BASE, SESSION, pw } = require('./commun');

const remettre = () => execSync('wsl -d Ubuntu -u root -e sh /home/xavie/offreGen-fluidite-banc/base.sh remettre', { stdio: 'pipe' });
const sql = (requete) => execSync('wsl -d Ubuntu -u root -e mariadb -N offregen_fluidite_vue -e "' + requete + '"', { encoding: 'utf8' }).trim();
const constats = [];
const noter = (cle, valeur) => { constats.push({ cle, valeur }); console.log(cle.padEnd(44), typeof valeur === 'string' ? valeur : JSON.stringify(valeur)); };

(async () => {
  remettre();
  const browser = await pw.chromium.launch({ executablePath: 'C:/Users/xavie/AppData/Local/ms-playwright/chromium-1243/chrome-win64/chrome.exe', headless: true });
  const contexte = await browser.newContext({ javaScriptEnabled: false, viewport: { width: 390, height: 844 }, isMobile: true, hasTouch: true, locale: 'fr-CH', storageState: SESSION });
  const page = await contexte.newPage();
  await page.goto(BASE + '/offres/3', { waitUntil: 'load' });
  // evaluate() de Playwright passe par le débogueur et marche même JavaScript coupé : on regarde si les scripts de la
  // page ont tourné (fluide.js pose window.fluide).
  noter('scripts de la page exécutés', await page.evaluate(() => typeof window.fluide !== 'undefined'));

  // « C'est juste » (ligne 14) : redirection, page entière, étiquette partie.
  let nav = page.waitForNavigation({ waitUntil: 'load' });
  await page.click('#l14 form.note.warn button');
  await nav;
  noter("« C'est juste » : en base (a_verifier)", sql('SELECT a_verifier FROM ligne WHERE id = 14'));
  noter("« C'est juste » : étiquette à l'écran", await page.locator('#l14 form.note.warn').count());

  // Quantité (ligne 12) : le formulaire de la ligne part avec Entrée.
  await page.fill('#l12 input[name=quantite]', '27');
  nav = page.waitForNavigation({ waitUntil: 'load' });
  await page.press('#l12 input[name=quantite]', 'Enter');
  await nav;
  noter('quantité 27 : en base (millièmes)', sql('SELECT quantite_milliemes FROM ligne WHERE id = 12'));

  // Corbeille (ligne 37) : supprimée en base, « Annuler » dans la page rechargée.
  nav = page.waitForNavigation({ waitUntil: 'load' });
  await page.click('#l37 form[action$="/supprimer"] button');
  await nav;
  noter('corbeille : supprimée en base', sql('SELECT supprimee_le IS NOT NULL FROM ligne WHERE id = 37'));
  noter('corbeille : « Annuler » à l\'écran', await page.locator('form.snack[action$="/37/restaurer"]').count());

  // Archiver depuis la liste : le bouton de la carte (sans glissé, le formulaire reste un formulaire).
  await page.goto(BASE + '/', { waitUntil: 'load' });
  const archiver = page.locator('form[action="/offres/3/archiver"] button').first();
  noter('liste : bouton archiver présent', await archiver.count());
  if (await archiver.count()) {
    nav = page.waitForNavigation({ waitUntil: 'load' });
    await archiver.click({ force: true });
    await nav;
    noter('archiver : en base (archivee_le posée)', sql('SELECT archivee_le IS NOT NULL FROM offre WHERE id = 3'));
  }
  await browser.close();
  remettre();
  const ok = constats.every((c) => !/: en base \(a_verifier\)/.test(c.cle) || c.valeur === '0');
  fs.writeFileSync(path.join(__dirname, 'recette-sans-js.json'), JSON.stringify(constats, null, 1));
  console.log(ok ? 'fin' : 'à revoir');
})().catch((e) => { console.error(e); process.exit(1); });
