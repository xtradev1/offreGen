// Recette du chronomètre sur le site de test, par l'interface. Usage : node recette.js
// L'artisan (compte du banc de fluidité) dicte au micro ; l'administrateur (recette-e1) lit la page des dictées.
'use strict';
const fs = require('fs');
const path = require('path');
const pw = require('C:/Users/xavie/AppData/Local/npm-cache/_npx/9833c18b2d85bc59/node_modules/playwright');

const CHROMIUM = 'C:/Users/xavie/AppData/Local/ms-playwright/chromium-1243/chrome-win64/chrome.exe';
const BASE = 'https://easy-offres.xtradev.ch';
const ICI = __dirname;
const CAP = path.join(ICI, 'captures');
fs.mkdirSync(CAP, { recursive: true });
const WAV = path.join(ICI, 'dictee-peintre.wav').replace(/\\/g, '/');
const journal = [];
const note = (...a) => { const l = a.join(' '); journal.push(l); console.log(l); };
const cap = async (page, nom, plein = true) => page.screenshot({ path: path.join(CAP, nom + '.png'), fullPage: plein });
const TEL = { viewport: { width: 390, height: 844 }, deviceScaleFactor: 2, isMobile: true, hasTouch: true, locale: 'fr-CH', acceptDownloads: true };

function lireCompte(nom) {
  const t = fs.readFileSync('\\\\wsl.localhost\\Ubuntu\\home\\xavie\\.offregen-test-' + nom + '-compte', 'utf8').split(/\r?\n/);
  const courriel = t.find((l) => l.startsWith('Compte créé')).match(/[\w.+-]+@[\w.-]+\.[a-z]{2,}/)[0];
  const mdp = t.find((l) => l.startsWith('Mot de passe')).split(' : ').pop().trim();
  if (mdp.length !== 19) throw new Error('mot de passe mal lu (' + nom + ')');
  return { courriel, mdp };
}
async function connecter(page, nom) {
  const c = lireCompte(nom);
  await page.goto(BASE + '/connexion');
  await page.fill('#courriel', c.courriel);
  await page.fill('#mot_de_passe', c.mdp);
  await page.click('button[form="connexion"]');
  await page.waitForURL((u) => !u.pathname.startsWith('/connexion'), { timeout: 20000 });
  note('connecté', nom, ', page', page.url().replace(BASE, ''));
}

(async () => {
  const browser = await pw.chromium.launch({ executablePath: CHROMIUM, headless: true,
    args: ['--use-fake-ui-for-media-stream', '--use-fake-device-for-media-stream', `--use-file-for-fake-audio-capture=${WAV}%noloop`] });
  const erreurs = [];
  const reponses = [];
  const suivre = (p, qui) => {
    p.on('dialog', (d) => { note('dialogue :', d.message()); d.accept(); });
    p.on('pageerror', (e) => erreurs.push(qui + ' ' + String(e)));
    p.on('console', (m) => { if (m.type() === 'error') erreurs.push(qui + ' ' + m.text()); });
    p.on('response', (r) => { const u = r.url(); if (/\/import$|\/envoi$|\/rejouer$/.test(u)) reponses.push(`${r.request().method()} ${u.replace(BASE, '')} ${r.status()}`); });
  };

  // 1. L'artisan dicte au micro, au téléphone
  const tel = await browser.newContext(TEL);
  await tel.grantPermissions(['microphone'], { origin: BASE });
  const page = await tel.newPage();
  suivre(page, 'artisan');
  await connecter(page, 'chrono-claude');
  // Une démo neuve : « / » mène à l'assistant de bienvenue ; le micro de la barre est sur « Clients ».
  await page.goto(BASE + '/clients', { waitUntil: 'load' });
  await page.waitForTimeout(800);
  await page.locator('[data-enregistrer]:visible').first().click();
  await page.waitForSelector('[data-terminer]:visible', { timeout: 15000 });
  await page.waitForTimeout(29000);
  await cap(page, '01-tel-enregistrement', false);
  const tTerminer = Date.now();
  const envoi = page.waitForResponse((r) => r.url().endsWith('/import') && r.request().method() === 'POST', { timeout: 120000 });
  await page.click('[data-terminer]');
  const rEnvoi = await envoi;
  note('envoi /import', rEnvoi.status(), 'après', Date.now() - tTerminer, 'ms');
  await page.waitForTimeout(1500);
  await cap(page, '02-tel-suivi', false);
  await page.waitForURL(/\/offres\/\d+/, { timeout: 300000 });
  await page.waitForLoadState('load');
  note('offre ouverte', page.url().replace(BASE, ''), 'après', Date.now() - tTerminer, 'ms');
  await cap(page, '03-tel-offre', false);
  await page.waitForTimeout(2000);
  await tel.close();

  // 2. L'administrateur, au téléphone : Plus, Dictées, CSV, rapport, Rejouer
  const telAdmin = await browser.newContext(TEL);
  const pa = await telAdmin.newPage();
  suivre(pa, 'admin');
  await connecter(pa, 'recette-e1');
  await pa.goto(BASE + '/admin/plus', { waitUntil: 'load' });
  await cap(pa, '04-tel-plus');
  const lienPlus = pa.locator('a[href="/admin/dictees"]').first();
  if (await lienPlus.count()) {
    await lienPlus.click();
    await pa.waitForURL(/\/admin\/dictees$/);
    note('Dictées ouverte depuis /admin/plus');
  } else {
    note('PAS de lien Dictées sur /admin/plus, page', pa.url().replace(BASE, ''));
    await pa.goto(BASE + '/admin/dictees');
  }
  await cap(pa, '05-tel-liste');
  const premiere = pa.locator('.dictees-cartes a[href^="/admin/dictees/"]').first();
  const idDictee = (await premiere.getAttribute('href')).split('/').pop();
  note('dernière dictée de la liste :', idDictee, ':', (await premiere.innerText()).replace(/\s+/g, ' '));

  const [dl] = await Promise.all([pa.waitForEvent('download'), pa.locator('a[href^="/admin/dictees.csv"]').first().click()]);
  const csvChemin = path.join(ICI, 'dictees.csv');
  await dl.saveAs(csvChemin);
  const csv = fs.readFileSync(csvChemin);
  note('CSV', dl.suggestedFilename(), csv.length, 'octets, trois premiers octets', csv.slice(0, 3).toString('hex'));

  await premiere.click();
  await pa.waitForURL(new RegExp('/admin/dictees/' + idDictee + '$'));
  await cap(pa, '06-tel-rapport');
  const etapes = await pa.locator('.dictee-etapes tbody tr').allInnerTexts();
  note('étapes du rapport :\n  ' + etapes.map((t) => t.replace(/\s+/g, ' ')).join('\n  '));

  await pa.locator('form[action$="/rejouer"] button').click();
  await pa.waitForLoadState('load');
  await cap(pa, '07-tel-rejeu-lance');
  note('page après Rejouer :', pa.url().replace(BASE, ''));
  let rejeu = '';
  let i10 = 0;
  for (let i = 0; i < 36; i++) {
    i10 = i + 1;
    await pa.waitForTimeout(10000);
    await pa.reload({ waitUntil: 'load' });
    rejeu = (await pa.locator('table.dictees-rejeux tbody tr', { hasText: 'Rejeu du' }).first().innerText().catch(() => '')).replace(/\s+/g, ' ');
    if (rejeu && !/en cours/i.test(rejeu)) break;
  }
  note('rejeu :', rejeu, 'après', i10 * 10, 's environ');
  note('original :', (await pa.locator('table.dictees-rejeux tbody tr', { hasText: 'Original' }).first().innerText()).replace(/\s+/g, ' '));
  await cap(pa, '08-tel-rapport-apres-rejeu');

  // 3. Le même administrateur au PC
  const pc = await browser.newContext({ viewport: { width: 1366, height: 900 }, locale: 'fr-CH', storageState: await telAdmin.storageState() });
  const p2 = await pc.newPage();
  suivre(p2, 'pc');
  await p2.goto(BASE + '/admin/dictees', { waitUntil: 'load' });
  await cap(p2, '09-pc-liste');
  note('débordement horizontal, liste au PC :', await p2.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth), 'px');
  await p2.locator('.dictees-liste a[href="/admin/dictees/' + idDictee + '"]').first().click();
  await p2.waitForURL(/\/admin\/dictees\/\d+$/);
  await cap(p2, '10-pc-rapport');
  note('débordement horizontal, rapport au PC :', await p2.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth), 'px');

  note('réponses :', reponses.join(' ; '));
  note('erreurs JS :', erreurs.length ? erreurs.join(' | ') : 'aucune');
  fs.writeFileSync(path.join(ICI, 'journal.txt'), journal.join('\n') + '\n');
  await browser.close();
})().catch((e) => { console.error(e); fs.writeFileSync(path.join(ICI, 'journal.txt'), journal.join('\n') + '\nERREUR ' + e + '\n'); process.exit(1); });
