// Le même parcours que recette-carre.js, sous WebKit (moteur de Safari, iPhone 390 x 844).
'use strict';
const fs = require('fs');
const path = require('path');
const { pw, BASE, SESSION, CAPTURES } = require('./commun');
const mode = process.argv[2] || 'tap';
(async () => {
  const browser = await pw.webkit.launch({ headless: true });
  const contexte = await browser.newContext({ viewport: { width: 390, height: 844 }, deviceScaleFactor: 2, isMobile: true, hasTouch: true, locale: 'fr-CH', storageState: (() => { const e = JSON.parse(fs.readFileSync(SESSION, 'utf8')); e.cookies.forEach((c) => { c.secure = false; }); return e; })() });
  const page = await contexte.newPage();
  await page.goto(BASE + '/offres/3', { waitUntil: 'load' });
  await page.waitForFunction(() => window.fluide && !window.fluide.occupe(), null, { timeout: 60000 });
  const lien = page.locator('#l37 a.nm');
  await lien.scrollIntoViewIfNeeded();
  if (mode === 'tap') await lien.tap(); else if (mode === 'clic') await lien.click(); else { await lien.focus(); await page.keyboard.press('Enter'); }
  await page.waitForTimeout(900);
  const dans = await page.evaluate(() => { const a = document.activeElement; return { actif: a.tagName + '.' + a.className, fv: a.matches(':focus-visible'), contour: getComputedStyle(a).outlineStyle + ' ' + getComputedStyle(a).outlineColor }; });
  console.log(mode, 'ouverte', JSON.stringify(dans));
  await page.screenshot({ path: path.join(CAPTURES, `carre-webkit-${mode}-ouverte.png`) });
  const annuler = page.locator('.feuille').getByText('Annuler', { exact: true }).first();
  if (mode === 'tap') await annuler.tap(); else if (mode === 'clic') await annuler.click(); else await page.keyboard.press('Escape');
  await page.waitForTimeout(100);
  await page.screenshot({ path: path.join(CAPTURES, `carre-webkit-${mode}-pendant.png`) });
  await page.waitForTimeout(800);
  const etat = await page.evaluate(() => { const a = document.activeElement; return { actif: a.tagName + '.' + a.className, fv: a.matches(':focus-visible'), contour: getComputedStyle(a).outlineStyle + ' ' + getComputedStyle(a).outlineWidth + ' ' + getComputedStyle(a).outlineColor }; });
  console.log(mode, 'fermée', JSON.stringify(etat));
  await page.screenshot({ path: path.join(CAPTURES, `carre-webkit-${mode}-fermee.png`) });
  await browser.close();
})().catch((e) => { console.error(e); process.exit(2); });
