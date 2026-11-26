// I1 (revue du 2026-11-24) : app.js introuvable ; un toucher sur le nom d'une ligne, retenu par fluide.js, repart comme
// un simple lien (page de la ligne) au lieu d'être avalé. Sans service worker, app.js refusé par le réseau.
'use strict';
const { BASE, SESSION, pw } = require('./commun');
(async () => {
  const browser = await pw.chromium.launch({ executablePath: 'C:/Users/xavie/AppData/Local/ms-playwright/chromium-1243/chrome-win64/chrome.exe' });
  const contexte = await browser.newContext({ viewport: { width: 390, height: 844 }, isMobile: true, hasTouch: true, storageState: SESSION, serviceWorkers: 'block' });
  await contexte.route(/\/js\/app\.js/, (r) => r.abort());
  const page = await contexte.newPage();
  const erreurs = []; page.on('pageerror', (e) => erreurs.push(e.message));
  await page.goto(BASE + '/offres/3', { waitUntil: 'load' });
  await page.waitForTimeout(800);
  const etat = await page.evaluate(() => ({ fluide: !!window.fluide, app: typeof window.demarrer }));
  await page.locator('#lignes .row a.nm').nth(4).tap();
  await page.waitForTimeout(2000);
  console.log('scripts', JSON.stringify(etat), '| après le toucher :', new URL(page.url()).pathname, '| erreurs', JSON.stringify(erreurs));
  await browser.close();
})().catch((e) => { console.error(e); process.exit(1); });
