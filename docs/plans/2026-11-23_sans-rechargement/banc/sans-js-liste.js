// Sans JavaScript, la liste : le bouton « Archiver » d'une carte est-il atteignable ? Ce qui est sous son centre, sa
// visibilité, et l'adresse après un clic forcé. Compare la copie du chantier (8093) ; lecture seule de la base.
'use strict';
const path = require('path');
const { BASE, SESSION, CAPTURES, pw } = require('./commun');

(async () => {
  const browser = await pw.chromium.launch({ executablePath: 'C:/Users/xavie/AppData/Local/ms-playwright/chromium-1243/chrome-win64/chrome.exe', headless: true });
  const contexte = await browser.newContext({ javaScriptEnabled: false, viewport: { width: 390, height: 844 }, isMobile: true, hasTouch: true, locale: 'fr-CH', storageState: SESSION });
  const page = await contexte.newPage();
  await page.goto(BASE + '/', { waitUntil: 'load' });
  const etat = await page.evaluate(() => {
    const b = document.querySelector('form[action="/offres/3/archiver"] button');
    if (!b) return { bouton: 'absent' };
    const r = b.getBoundingClientRect();
    const dessus = document.elementFromPoint(r.left + r.width / 2, r.top + r.height / 2);
    const s = getComputedStyle(b);
    return { taille: [Math.round(r.width), Math.round(r.height)], visible: s.visibility + ' ' + s.display + ' ' + s.opacity,
      sousLeCentre: dessus ? dessus.tagName + (dessus.className ? '.' + String(dessus.className).split(' ').join('.') : '') + (dessus.closest('a') ? ' dans un lien ' + dessus.closest('a').getAttribute('href') : '') : null,
      autresArchiver: [...document.querySelectorAll('a[href*="archiv"], form[action*="archiver"]')].length };
  });
  console.log(JSON.stringify(etat, null, 1));
  await page.screenshot({ path: path.join(CAPTURES, 'sans-js-liste.png') });
  await page.goto(BASE + '/offres/3', { waitUntil: 'load' });
  console.log('page de l\'offre : formulaires d\'archivage', await page.locator('form[action="/offres/3/archiver"]').count());
  await browser.close();
})().catch((e) => { console.error(e); process.exit(1); });
