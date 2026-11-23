// Captures de chaque section du comparatif, téléphone et bureau, plus un parcours cliqué : réseau « 4G » dans le
// détail, filtre « Offre », un choix sous D5, « Copier ». Usage : node regarder-comparatif.js <page.html> <dossier>.
'use strict';
const path = require('path');
const { pw } = require('./commun');

const [fichier, sortie] = process.argv.slice(2);
const url = require('url').pathToFileURL(fichier).href; // chemin UNC de WSL compris

(async () => {
  const browser = await pw.chromium.launch({ executablePath: 'C:/Users/xavie/AppData/Local/ms-playwright/chromium-1243/chrome-win64/chrome.exe', headless: true });
  const erreurs = [];
  for (const [nom, vue] of [['tel', { width: 390, height: 844 }], ['bureau', { width: 1440, height: 900 }]]) {
    const contexte = await browser.newContext({ viewport: vue, deviceScaleFactor: 1 });
    const page = await contexte.newPage();
    page.on('pageerror', (e) => erreurs.push(nom + ' : ' + e.message));
    page.on('console', (m) => { if (m.type() === 'error') erreurs.push(nom + ' console : ' + m.text()); });
    await page.goto(url, { waitUntil: 'load' });
    // check.js ne voit pas les ids en double (mémoire du projet, reference_playwright) : on les cherche ici.
    const doubles = await page.evaluate(() => {
      const n = {};
      document.querySelectorAll('[id]').forEach((el) => { n[el.id] = (n[el.id] || 0) + 1; });
      return Object.entries(n).filter(([, c]) => c > 1).map(([i, c]) => i + ' x' + c);
    });
    console.log(nom, 'ids en double :', doubles.length ? doubles.join(', ') : 'aucun');
    for (const id of ['bref', 'gestes', 'hors-reseau', 'question-d5', 'question-d1', 'question-d4', 'revue', 'detail', 'limites']) {
      await page.evaluate((i) => { const el = document.getElementById(i); window.scrollTo(0, el.getBoundingClientRect().top + window.scrollY - 60); }, id);
      await page.waitForTimeout(150);
      await page.screenshot({ path: path.join(sortie, `${nom}-${id}.png`) });
    }
    // Parcours : réseau 4G, filtre « Offre », lecture d'une cellule.
    await page.click('[data-reseau-choix="4g"]');
    await page.click('.mk-filters[data-target="#detail-corps"] button[data-v="offre"]');
    const etat = await page.evaluate(() => {
      const t = document.getElementById('detail-table');
      const visibles = [...document.querySelectorAll('#detail-corps tr')].filter((tr) => !tr.hidden);
      const cellule = visibles[0] && visibles[0].querySelector('td[data-label="Après"] .r-4g');
      return { reseau: t.getAttribute('data-reseau'), lignes: visibles.length, cellule4g: cellule ? getComputedStyle(cellule).display + ' ' + cellule.textContent.trim().slice(0, 60) : null,
        celluleChantier: visibles[0] ? getComputedStyle(visibles[0].querySelector('td[data-label="Après"] .r-chantier')).display : null,
        largeur: document.documentElement.scrollWidth };
    });
    console.log(nom, JSON.stringify(etat));
    await page.evaluate(() => { const el = document.getElementById('detail'); window.scrollTo(0, el.getBoundingClientRect().top + window.scrollY - 60); });
    await page.waitForTimeout(150);
    await page.screenshot({ path: path.join(sortie, `${nom}-detail-4g-offre.png`) });
    // Un choix sous D5, puis le récapitulatif.
    await page.click('.decision[data-q="Durée de garde dans le téléphone"] button[data-v="A"]');
    const recap = await page.evaluate(() => document.getElementById('recap').value);
    console.log(nom, 'récapitulatif :', recap.split('\n').join(' | '));
    await contexte.close();
  }
  await browser.close();
  console.log('erreurs :', erreurs.length ? erreurs.join(' ; ') : 'aucune');
})().catch((e) => { console.error(e); process.exit(1); });
