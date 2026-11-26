'use strict';
const { BASE, navigateur } = require('./commun');
(async () => {
  const { browser, contexte } = await navigateur();
  const page = await contexte.newPage();
  await page.goto(BASE + '/', { waitUntil: 'load' });
  console.log('liste rechargée, carte de l offre présente :', await page.evaluate(() => [...document.querySelectorAll('#liste-offres .glisse .titre')].some((t) => /Rue du Marché/.test(t.textContent))));
  await page.goto(BASE + '/offres/3', { waitUntil: 'load' });
  console.log('offre 3 rechargée, ligne 37 visible :', await page.evaluate(() => { const l = document.getElementById('l37'); return !!l && !l.hidden; }));
  await browser.close();
})();
