// Toucher un produit de l'offre, puis « Annuler » dans la fenêtre : aucun contour de focus ne doit rester (Xavier,
// 2026-11-24 : « un gros carré orange »). Usage : node recette-carre.js [tap|clic|clavier]
'use strict';
const path = require('path');
const { BASE, navigateur, CAPTURES } = require('./commun');
const mode = process.argv[2] || 'tap';
(async () => {
  const { browser, contexte } = await navigateur({ mobile: mode !== 'clavier' });
  const page = await contexte.newPage();
  await page.goto(BASE + '/offres/3', { waitUntil: 'load' });
  await page.waitForFunction(() => window.fluide && !window.fluide.occupe(), null, { timeout: 60000 });
  const lien = page.locator('#l37 a.nm');
  await lien.scrollIntoViewIfNeeded();
  if (mode === 'tap') await lien.tap(); else if (mode === 'clic') await lien.click(); else { await lien.focus(); await page.keyboard.press('Enter'); }
  await page.waitForTimeout(900);
  const boutons = await page.evaluate(() => [...document.querySelectorAll('.feuille button, .feuille a')].filter((b) => b.offsetParent).map((b) => (b.textContent || '').trim().slice(0, 30)));
  console.log('fenêtre :', JSON.stringify(boutons));
  await page.screenshot({ path: path.join(CAPTURES, `carre-${mode}-ouverte.png`) });
  const annuler = page.locator('.feuille').getByText('Annuler', { exact: true }).first();
  if (mode === 'tap') await annuler.tap(); else if (mode === 'clic') await annuler.click(); else { await annuler.focus(); await page.keyboard.press('Enter'); }
  await page.waitForTimeout(900);
  const etat = await page.evaluate(() => {
    const a = document.activeElement;
    const s = a ? getComputedStyle(a) : null;
    return { actif: a ? a.tagName + '.' + a.className : null, focusVisible: a ? a.matches(':focus-visible') : null, contour: s ? s.outlineStyle + ' ' + s.outlineWidth + ' ' + s.outlineColor : null };
  });
  console.log(mode, JSON.stringify(etat));
  await page.screenshot({ path: path.join(CAPTURES, `carre-${mode}-fermee.png`) });
  await browser.close();
})().catch((e) => { console.error(e); process.exit(2); });
