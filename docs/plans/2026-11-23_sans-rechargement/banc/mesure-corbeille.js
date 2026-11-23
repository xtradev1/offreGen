// Latence de la corbeille mesurée dans la page : du clic (événement, phase de capture) au masquage de la ligne
// (MutationObserver), sans le défilement ni les contrôles de Playwright. Ligne 37 de l'offre 3, cinq passages par réseau,
// base remise avant chacun. Usage : node mesure-corbeille.js. Sortie : console et mesure-corbeille.json.
'use strict';
const fs = require('fs');
const path = require('path');
const { execSync } = require('child_process');
const { BASE, navigateur, reseau } = require('./commun');

const remettre = () => execSync('wsl -d Ubuntu -u root -e sh /home/xavie/offreGen-fluidite-banc/base.sh remettre', { stdio: 'pipe' });
const resultats = {};

(async () => {
  for (const profil of ['local', 'chantier']) {
    const { browser, contexte } = await navigateur();
    resultats[profil] = [];
    for (let i = 0; i < 5; i++) {
      remettre();
      const page = await contexte.newPage();
      await reseau(page, profil);
      await page.goto(BASE + '/offres/3', { waitUntil: 'load' });
      await page.waitForFunction(() => window.fluide && !window.fluide.occupe(), null, { timeout: 60000 });
      await page.evaluate(() => document.getElementById('l37').scrollIntoView({ block: 'center' }));
      await page.waitForTimeout(500);
      await page.evaluate(() => {
        window.__mesure = { clic: null, cache: null };
        window.addEventListener('click', () => { if (window.__mesure.clic === null) window.__mesure.clic = performance.now(); }, true);
        const ligne = document.getElementById('l37');
        new MutationObserver(() => { if (ligne.hidden && window.__mesure.cache === null) window.__mesure.cache = performance.now(); }).observe(ligne, { attributes: true });
      });
      await page.click('#l37 form[action$="/supprimer"] button');
      await page.waitForFunction(() => window.__mesure.cache !== null, null, { timeout: 10000 });
      const m = await page.evaluate(() => window.__mesure.cache - window.__mesure.clic);
      resultats[profil].push(Math.round(m * 10) / 10);
      await page.waitForFunction(() => window.fluide && !window.fluide.occupe(), null, { timeout: 60000 });
      await page.close();
    }
    await browser.close();
    console.log(profil, 'du clic au masquage (ms) :', resultats[profil].join(', '));
  }
  fs.writeFileSync(path.join(__dirname, 'mesure-corbeille.json'), JSON.stringify(resultats, null, 1));
})().catch((e) => { console.error(e); process.exit(1); });
