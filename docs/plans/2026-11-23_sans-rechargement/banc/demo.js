// Images de la démo « deux téléphones » de la maquette : l'offre avant le toucher, puis après le rechargement actuel.
'use strict';
const path = require('path');
const { execSync } = require('child_process');
const { BASE, CAPTURES, navigateur } = require('./commun');

const remettre = () => execSync('wsl -d Ubuntu -u root -e sh /home/xavie/offreGen-fluidite-banc/base.sh remettre', { stdio: 'pipe' });

(async () => {
  remettre();
  const { browser, contexte } = await navigateur();
  const page = await contexte.newPage();
  await page.goto(BASE + '/offres/3', { waitUntil: 'load' });
  await page.evaluate(() => document.querySelector('#l14').scrollIntoView({ block: 'center' }));
  await page.waitForTimeout(300);
  await page.screenshot({ path: path.join(CAPTURES, 'demo-avant.png') });
  const fin = page.waitForEvent('load');
  await page.click('#l14 form[action$="/juste"] button');
  await fin;
  await page.waitForTimeout(400);
  await page.screenshot({ path: path.join(CAPTURES, 'demo-apres-rechargement.png') });
  console.log('adresse après rechargement :', page.url().replace(BASE, ''), 'défilement :', await page.evaluate(() => Math.round(window.scrollY)));
  // La corbeille : avant, puis après le rechargement (le bandeau « Annuler »).
  remettre();
  await page.goto(BASE + '/offres/3', { waitUntil: 'load' });
  await page.evaluate(() => document.querySelector('#l37').scrollIntoView({ block: 'center' }));
  await page.waitForTimeout(300);
  await page.screenshot({ path: path.join(CAPTURES, 'demo-corbeille-avant.png') });
  const fin2 = page.waitForEvent('load');
  await page.click('#l37 form[action$="/supprimer"] button');
  await fin2;
  await page.waitForTimeout(400);
  await page.screenshot({ path: path.join(CAPTURES, 'demo-corbeille-apres-rechargement.png') });
  console.log('après la corbeille :', page.url().replace(BASE, ''), 'défilement :', await page.evaluate(() => Math.round(window.scrollY)));
  // La fenêtre « + Ligne » telle qu'elle est.
  remettre();
  await page.goto(BASE + '/offres/3', { waitUntil: 'load' });
  await page.click('a.btn.ajout');
  await page.waitForFunction(() => { const c = document.querySelector('[data-feuille-corps]'); return c && c.children.length > 0; });
  await page.waitForTimeout(600);
  await page.screenshot({ path: path.join(CAPTURES, 'avant-ajout.png') });
  await browser.close();
})().catch((e) => { console.error(e); process.exit(1); });
