// Zoom de Safari sur iPhone à l'entrée dans un champ (Xavier, 2026-11-25) : Safari agrandit la page quand le texte du
// champ touché fait moins de 16 px. Relevé, sous WebKit en iPhone 13, de la taille réelle du texte de chaque champ
// visible ou caché des écrans courants, et des fenêtres ouvertes depuis l'offre. Usage : node zoom-champs.js.
// Résultat : zoom-champs.json (les champs sous 16 px seulement, plus le compte total).
'use strict';
const fs = require('fs');
const path = require('path');
const { pw, BASE, SESSION } = require('./commun');

(async () => {
  const e = JSON.parse(fs.readFileSync(SESSION, 'utf8'));
  e.cookies.forEach((c) => { c.secure = false; });
  const browser = await pw.webkit.launch({ headless: true });
  const contexte = await browser.newContext({ ...pw.devices['iPhone 13'], locale: 'fr-CH', storageState: e });
  const page = await contexte.newPage();
  const releve = () => page.evaluate(() => [...document.querySelectorAll('input, textarea, select, [contenteditable=""], [contenteditable="true"]')]
    .filter((c) => !['hidden', 'checkbox', 'radio', 'submit', 'button', 'file', 'range', 'color'].includes(c.type))
    .map((c) => ({
      champ: c.tagName.toLowerCase() + (c.name ? '[name=' + c.name + ']' : '') + (c.className ? '.' + String(c.className).trim().split(/\s+/).join('.') : ''),
      parent: c.parentElement ? (c.parentElement.className || c.parentElement.tagName.toLowerCase()) : '',
      px: parseFloat(getComputedStyle(c).fontSize),
      visible: !!(c.offsetWidth || c.offsetHeight),
    })));
  const ecrans = ['/offres/3', '/clients', '/clients/nouveau', '/catalogue', '/reglages', '/offres/3/entete', '/offres/3/conditions', '/offres'];
  const lien = async (sel) => { await page.goto(BASE + '/offres/3', { waitUntil: 'load' }); return page.locator(sel).first().getAttribute('href').catch(() => null); };
  const ligne = await lien('#lignes .row a.nm');
  if (ligne) ecrans.push(ligne);
  const res = { ecrans: {}, total: 0 };
  for (const u of ecrans) {
    await page.goto(BASE + u, { waitUntil: 'load' });
    await page.waitForTimeout(600);
    const champs = await releve();
    res.total += champs.length;
    res.ecrans[u] = { url: new URL(page.url()).pathname, champs: champs.length, sous16: champs.filter((c) => c.px < 16) };
    console.log(u.padEnd(34), champs.length, 'champs ;', res.ecrans[u].sous16.length, 'sous 16 px', JSON.stringify(res.ecrans[u].sous16));
  }
  fs.writeFileSync(path.join(__dirname, 'zoom-champs.json'), JSON.stringify(res, null, 1));
  await browser.close();
})().catch((err) => { console.error(err); process.exit(1); });
