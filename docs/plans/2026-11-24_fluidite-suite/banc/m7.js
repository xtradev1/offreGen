// M7 (revue du 2026-11-24) : une offre gardée avant un déploiement, rouverte sans réseau, doit garder ses scripts et
// ses gestes (Xavier, 2026-11-25). Par l'interface, Chromium téléphone :
// 1. avec du réseau, l'offre 3 ouverte et gardée ; 2. déploiement simulé (app.js, fluide.js, app.css changés, donc de
// nouvelles adresses ?v=) ; 3. la liste ouverte avec du réseau (le téléphone prend les nouvelles versions) ; 4. serveur
// coupé, l'offre 3 rouverte : style, fluide.js, app.js, et « C'est juste » sans rechargement ; 5. toujours sans réseau,
// les anciennes versions retirées du téléphone (cas des pages gardées avant ce correctif), l'offre rouverte : les
// versions récentes la servent ; 6. réseau revenu, l'offre rouverte puis le nettoyage : une seule version reste.
// Usage : node m7.js [nom]. Résultats : m7-<nom>.json. Le déploiement simulé est défait à la fin.
// Serveur du banc en mode froid par défaut (MODE=apres pour les règles du .htaccess) : sans cache HTTP, comme un iPhone
// qui a vidé le sien ; en mode apres, Chromium sert les anciens scripts depuis son cache HTTP et masque M7.
'use strict';
const fs = require('fs');
const path = require('path');
const { execSync } = require('child_process');
const { BASE, navigateur } = require('./commun');

const nom = process.argv[2] || 'apres';
const wsl = (c) => execSync('wsl -d Ubuntu -e sh -c "' + c + '"', { encoding: 'utf8', stdio: 'pipe' });
const remettre = () => execSync('wsl -d Ubuntu -u root -e sh /home/xavie/offreGen-fluidite-banc/base.sh remettre', { stdio: 'pipe' });
const couper = () => wsl('kill $(cat /home/xavie/offreGen-fluidite-banc/serveur.pid)');
const rallumer = () => wsl('sh /home/xavie/offreGen-fluidite-banc/serveur.sh ' + (process.env.MODE || 'froid'));
const FICHIERS = ['public/js/app.js', 'public/js/fluide.js', 'public/css/app.css'];
const deployer = () => wsl('cd /home/xavie/offreGen-fluidite && for f in ' + FICHIERS.join(' ') + '; do [ -f $f.m7 ] || cp $f $f.m7; printf \'\\n/* banc m7 */\\n\' >> $f; done');
const defaire = () => wsl('cd /home/xavie/offreGen-fluidite && for f in ' + FICHIERS.join(' ') + '; do [ -f $f.m7 ] && mv $f.m7 $f; done; true');
const res = [];
const noter = (cle, valeur) => { res.push({ cle, valeur }); console.log(cle.padEnd(50), JSON.stringify(valeur)); };

(async () => {
  remettre();
  rallumer();
  const { browser, contexte } = await navigateur();
  const page = await contexte.newPage();
  const erreurs = [];
  page.on('pageerror', (e) => erreurs.push(e.message));
  const versions = () => page.evaluate(async () => {
    const nomCache = (await caches.keys()).find((n) => n.startsWith('offregen-fichiers-'));
    if (!nomCache) return {};
    const v = {};
    for (const k of await (await caches.open(nomCache)).keys()) {
      const u = new URL(k.url);
      if (['/js/app.js', '/js/fluide.js', '/css/app.css'].includes(u.pathname)) (v[u.pathname] = v[u.pathname] || []).push(u.searchParams.get('v'));
    }
    return v;
  });
  const etat = async (titre) => {
    await page.waitForTimeout(1500);
    const vu = await page.evaluate(() => ({
      garde: document.body.dataset.gardeRaison || '',
      style: getComputedStyle(document.querySelector('.app')).maxWidth,
      fluide: !!window.fluide,
      app: document.documentElement.style.getPropertyValue('--bas') !== '',
      scripts: performance.getEntriesByType('resource').filter((r) => /\/js\/(app|fluide)\.js/.test(r.name)).map((r) => r.name.replace(/^.*\/js\//, '')),
    }));
    const flag = await page.locator('#lignes .row.flag').first().getAttribute('id').catch(() => null);
    let geste = 'aucune ligne à vérifier';
    if (flag) {
      await page.evaluate(() => { window.__marque = 1; });
      await page.click('#' + flag + ' form.note.warn button');
      await page.waitForTimeout(800);
      geste = await page.evaluate((id) => (window.__marque === 1 ? 'sans rechargement' : 'PAGE RECHARGÉE') + (document.getElementById(id) && !document.getElementById(id).classList.contains('flag') ? ', étiquette partie' : ', étiquette restée'), flag).catch(() => 'PAGE RECHARGÉE');
    }
    noter(titre, { ...vu, geste });
  };

  try {
    // 1. Avec du réseau : la liste, et ce qu'elle fait garder d'avance (offres, avec leur version) ; puis la fiche d'une
    // ligne, ouverte à la main (le préchargement ne la reprend jamais).
    await page.goto(BASE + '/', { waitUntil: 'load' });
    await page.evaluate(() => navigator.serviceWorker.ready.then(() => true));
    if (!(await page.evaluate(() => !!navigator.serviceWorker.controller))) await page.reload({ waitUntil: 'load' });
    noter('service worker aux commandes', await page.evaluate(() => !!navigator.serviceWorker.controller));
    const gardees = async () => page.evaluate(async () => {
      const n = (await caches.keys()).find((x) => x.startsWith('offregen-pages-'));
      return n ? (await (await caches.open(n)).keys()).map((k) => new URL(k.url).pathname) : [];
    });
    let g = [];
    for (let i = 0; i < 90; i++) { g = await gardees(); if (g.filter((c) => /^\/offres\/\d+$/.test(c)).length >= 2) break; await page.waitForTimeout(1000); }
    const offre = g.includes('/offres/3') ? '/offres/3' : g.find((c) => /^\/offres\/\d+$/.test(c));
    noter("1. offre gardée d'avance (jamais ouverte)", offre);
    await page.goto(BASE + '/offres/3/lignes/10', { waitUntil: 'load' });
    await page.waitForTimeout(7000);
    const ligne = '/offres/3/lignes/10';
    noter('1. versions gardées', await versions());

    // 2 et 3. Déploiement, puis la liste avec du réseau.
    deployer();
    await page.goto(BASE + '/', { waitUntil: 'load' });
    await page.waitForTimeout(12000);
    noter('3. versions gardées après la liste', await versions());

    // 4. Sans réseau : l'offre gardée d'avance, puis la fiche de la ligne, gardées avant le déploiement.
    couper();
    await page.goto(BASE + offre, { waitUntil: 'load' });
    await etat("4. offre gardée d'avance, sans réseau");
    await page.goto(BASE + ligne, { waitUntil: 'load' });
    await etat('4. fiche de la ligne, sans réseau');

    // 5. Les anciennes versions retirées du téléphone (pages gardées avant ce correctif), la fiche rouverte.
    const retirees = await page.evaluate(async () => {
      const cache = await caches.open((await caches.keys()).find((n) => n.startsWith('offregen-fichiers-')));
      const parChemin = {};
      for (const k of await cache.keys()) { const u = new URL(k.url); (parChemin[u.pathname] = parChemin[u.pathname] || []).push(k); }
      let n = 0;
      for (const l of Object.values(parChemin)) for (const k of l.slice(0, -1)) { await cache.delete(k); n++; }
      return n;
    });
    noter('5. anciennes versions retirées à la main', retirees);
    await page.goto(BASE + ligne, { waitUntil: 'load' });
    await etat('5. fiche de la ligne sans réseau, versions récentes');

    // 6. Réseau revenu et second déploiement : la liste ouverte, les pages gardées d'avance se reprennent avec les
    // nouvelles adresses, les versions que plus rien ne cite partent.
    rallumer();
    remettre();
    deployer();
    noter('6. versions avant la liste', await versions());
    await page.goto(BASE + '/', { waitUntil: 'load' });
    let v = {};
    for (let i = 0; i < 60; i++) {
      await page.waitForTimeout(1000);
      v = await versions();
      if (Object.keys(v).length === 3 && Object.values(v).every((l) => l.length === 1)) break;
    }
    noter('6. versions gardées après le nettoyage', v);
    noter('6. pages gardées', await page.evaluate(async () => { const n = (await caches.keys()).find((x) => x.startsWith('offregen-pages-')); return n ? (await (await caches.open(n)).keys()).length : 0; }));
  } finally {
    defaire();
    try { rallumer(); } catch (e) { /* déjà allumé */ }
    noter('erreurs JavaScript', erreurs);
    fs.writeFileSync(path.join(__dirname, `m7-${nom}.json`), JSON.stringify(res, null, 1));
    await browser.close();
  }
})().catch((e) => { defaire(); console.error(e); process.exit(1); });
