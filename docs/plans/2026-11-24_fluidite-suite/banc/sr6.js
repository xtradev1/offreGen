// SR-6 (recette du 2026-11-24) : « Générer l'offre », reviens, « Générer » encore sans rien changer, puis « Envoyer ».
// Constat de Xavier : l'application annonce des changements en attente alors qu'il n'y en a aucun. Ce script rejoue le
// parcours par l'interface et relève, à chaque page, la file de fluide.js et ce que le téléphone garde (IndexedDB).
// Usage : node sr6.js [chromium|webkit] [lien|historique] [offre]
'use strict';
const fs = require('fs');
const path = require('path');
const { execSync } = require('child_process');
const { pw, BASE, ICI, SESSION, CAPTURES, navigateur } = require('./commun');

const moteur = process.argv[2] || 'chromium';
const retour = process.argv[3] || 'lien';
const offre = process.argv[4] || '3';
const remettre = () => execSync('wsl -d Ubuntu -u root -e sh /home/xavie/offreGen-fluidite-banc/base.sh remettre', { stdio: 'pipe' });

const releve = (page, quoi) => page.evaluate(async (q) => {
  const file = window.fluide ? window.fluide.file.map((f) => ({ action: f.item.action, corps: f.item.corps.slice(0, 120), ici: f.item.ici, cree: f.item.cree, gardee: !!f.options.gardee })) : 'sans fluide';
  const garde = await new Promise((ok) => {
    const r = indexedDB.open('offregen-fluide', 1);
    r.onsuccess = () => { try { const t = r.result.transaction('envois', 'readonly').objectStore('envois').getAll(); t.onsuccess = () => ok(t.result.map((i) => ({ action: i.action, corps: i.corps.slice(0, 120), ici: i.ici }))); t.onerror = () => ok('erreur'); } catch (e) { ok('vide : ' + e.message); } };
    r.onerror = () => ok('pas de base');
  });
  const snacks = [...document.querySelectorAll('.app > .snack, .app > .reseau:not([hidden])')].map((s) => s.textContent.trim());
  return { quoi: q, adresse: location.pathname + location.search, file, garde, snacks };
}, quoi);

(async () => {
  remettre();
  let browser; let contexte;
  if (moteur === 'webkit') {
    const e = JSON.parse(fs.readFileSync(SESSION, 'utf8'));
    e.cookies.forEach((c) => { c.secure = false; });
    browser = await pw.webkit.launch({ headless: true });
    contexte = await browser.newContext({ ...pw.devices['iPhone 13'], locale: 'fr-CH', storageState: e });
  } else {
    ({ browser, contexte } = await navigateur());
  }
  await contexte.addInitScript(() => { navigator.canShare = () => true; navigator.share = async () => {}; });
  const page = await contexte.newPage();
  const requetes = [];
  page.on('request', (r) => { if (r.method() === 'POST') requetes.push(`${r.method()} ${r.url().replace(BASE, '')} ${(r.postData() || '').slice(0, 80)}`); });
  const erreurs = [];
  page.on('pageerror', (e) => erreurs.push(String(e.message)));
  const etapes = [];
  await page.goto(BASE + '/offres/' + offre, { waitUntil: 'load' });
  await page.waitForTimeout(2500);
  etapes.push(await releve(page, 'offre ouverte'));
  const generer = page.locator('a.btn.primary.grand:has-text("Générer l\'offre")');
  const avecFenetre = await generer.getAttribute('data-en-feuille');
  if (avecFenetre !== null) { console.log('Cette offre n\'a pas de client : la fenêtre « Pour qui » s\'ouvre. Prendre une offre avec client.'); await browser.close(); return; }
  for (const tour of [1, 2]) {
    await generer.tap();
    await page.waitForURL(/\/apercu/, { timeout: 30000 });
    await page.waitForLoadState('load');
    await page.waitForTimeout(2500);
    etapes.push(await releve(page, 'aperçu ' + tour));
    if (tour === 1) {
      if (retour === 'historique') await page.goBack(); else await page.locator('header.bar a.back').tap();
      await page.waitForURL(new RegExp('/offres/' + offre + '($|[?#])'), { timeout: 30000 });
      await page.waitForLoadState('load');
      await page.waitForTimeout(2500);
      etapes.push(await releve(page, 'offre revenue'));
    }
  }
  await page.locator('[data-partager]').first().tap();
  await page.waitForTimeout(1500);
  etapes.push(await releve(page, 'après « Envoyer »'));
  await page.screenshot({ path: path.join(CAPTURES, `sr6-${moteur}-${retour}.png`) });
  const r = { moteur, retour, offre, etapes, posts: requetes, erreurs };
  fs.writeFileSync(path.join(ICI, `sr6-${moteur}-${retour}.json`), JSON.stringify(r, null, 1));
  etapes.forEach((e) => console.log(`${e.quoi.padEnd(18)} ${e.adresse.padEnd(22)} file ${JSON.stringify(e.file)} | garde ${JSON.stringify(e.garde)} | bandeaux ${JSON.stringify(e.snacks)}`));
  console.log('POST :', requetes.join(' ; ') || 'aucun', erreurs.length ? ' ERREURS ' + erreurs.join(' | ') : '');
  await browser.close();
  remettre();
})().catch((e) => { console.error(e); process.exit(1); });
