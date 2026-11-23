// D1 : un style modifié arrive sans vider de cache. Le téléphone a gardé app.css (service worker, fichier versionné) ; on
// ajoute une règle à app.css, on ouvre une autre page : nouvelle adresse (?v= neuf), nouvelle règle appliquée. Puis on
// remet le fichier, et l'ancienne adresse revient. Usage : node preuve-d1.js. Sortie : preuve-d1.json.
'use strict';
const fs = require('fs');
const path = require('path');
const { execSync } = require('child_process');
const { BASE, navigateur, reseau } = require('./commun');

const DEPOT = '/home/xavie/offreGen-fluidite';
const wsl = (cmd) => execSync('wsl -d Ubuntu -e sh -c "' + cmd + '"', { encoding: 'utf8' }).trim();
const lire = (page) => page.evaluate(() => {
  const lien = [...document.querySelectorAll('link[rel=stylesheet]')].find((l) => /\/css\/app\.css/.test(l.getAttribute('href')));
  return {
    adresse: lien ? lien.getAttribute('href') : null,
    regle: getComputedStyle(document.documentElement).getPropertyValue('--preuve-d1').trim(),
    controle: !!navigator.serviceWorker.controller,
  };
});

(async () => {
  if (wsl('cd ' + DEPOT + ' && git status --short public/css/app.css')) throw new Error('app.css déjà modifié : preuve annulée');
  const { browser, contexte } = await navigateur();
  const page = await contexte.newPage();
  await reseau(page, 'local');
  await page.goto(BASE + '/', { waitUntil: 'load' });
  await page.evaluate(() => navigator.serviceWorker.ready.then(() => true));
  if (!(await page.evaluate(() => !!navigator.serviceWorker.controller))) await page.reload({ waitUntil: 'load' });
  await page.goto(BASE + '/clients', { waitUntil: 'load' });
  const avant = await lire(page);
  let pendant = null;
  let apres = null;
  try {
    wsl('cd ' + DEPOT + ' && printf \'\\nhtml { --preuve-d1: 1; }\\n\' >> public/css/app.css');
    await page.goto(BASE + '/', { waitUntil: 'load' });
    pendant = await lire(page);
  } finally {
    wsl('cd ' + DEPOT + ' && git checkout -q public/css/app.css');
  }
  await page.goto(BASE + '/clients', { waitUntil: 'load' });
  apres = await lire(page);
  await browser.close();
  const propre = wsl('cd ' + DEPOT + ' && git status --short public/css/app.css') === '';
  const r = {
    quand: new Date().toLocaleString('fr-CH', { timeZone: 'Europe/Zurich' }),
    avant, pendant, apres, fichierRemis: propre,
    ok: !!(avant.adresse && pendant && pendant.adresse !== avant.adresse && pendant.regle === '1' && avant.regle === '' && apres.adresse === avant.adresse && apres.regle === '' && propre && avant.controle),
  };
  r.texte = r.ok
    ? `Téléphone qui a déjà gardé le style (service worker aux commandes). Style de départ : <code>${avant.adresse}</code>. Une règle ajoutée à app.css, page suivante : <code>${pendant.adresse}</code>, règle appliquée, sans rien vider. Fichier remis : retour à <code>${apres.adresse}</code>.`
    : 'Échec : ' + JSON.stringify({ avant, pendant, apres, propre });
  fs.writeFileSync(path.join(__dirname, 'preuve-d1.json'), JSON.stringify(r, null, 1));
  console.log(JSON.stringify(r, null, 1));
})().catch((e) => { console.error(e); process.exit(1); });
