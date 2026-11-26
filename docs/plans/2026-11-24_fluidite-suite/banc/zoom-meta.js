// Contrôle de sansZoomAuToucher (app.js, 2026-11-25) : maximum-scale=1 posé dans Safari sur iPhone, jamais dans Chrome
// sur iPhone ni sur Android ; texte de l'offre à 16 px ; aucune erreur JavaScript. Usage : node zoom-meta.js.
// Résultat : zoom-meta.json.
'use strict';
const fs = require('fs');
const path = require('path');
const { pw, BASE, SESSION, navigateur } = require('./commun');

(async () => {
  const e = JSON.parse(fs.readFileSync(SESSION, 'utf8'));
  e.cookies.forEach((c) => { c.secure = false; });
  const res = [];
  const essai = async (nom, ouvrir) => {
    const { browser, contexte } = await ouvrir();
    const page = await contexte.newPage();
    const erreurs = [];
    page.on('pageerror', (x) => erreurs.push(String(x.message)));
    await page.goto(BASE + '/offres/3', { waitUntil: 'load' });
    await page.waitForTimeout(800);
    const vu = await page.evaluate(() => ({
      viewport: document.querySelector('meta[name="viewport"]').content,
      texteOffre: getComputedStyle(document.querySelector('.texte-offre textarea')).fontSize,
    }));
    res.push({ nom, ...vu, erreurs });
    console.log(nom.padEnd(26), JSON.stringify({ ...vu, erreurs }));
    await browser.close();
  };
  const iphone = pw.devices['iPhone 13'];
  const webkit = (ua) => async () => {
    const browser = await pw.webkit.launch({ headless: true });
    return { browser, contexte: await browser.newContext({ ...iphone, userAgent: ua || iphone.userAgent, locale: 'fr-CH', storageState: e }) };
  };
  await essai('Safari, iPhone', webkit());
  await essai('Chrome, iPhone', webkit(iphone.userAgent.replace('Version/', 'CriOS/131.0.0.0 Version/')));
  await essai('Chrome, Android', () => navigateur());
  fs.writeFileSync(path.join(__dirname, 'zoom-meta.json'), JSON.stringify(res, null, 1));
})().catch((err) => { console.error(err); process.exit(1); });
