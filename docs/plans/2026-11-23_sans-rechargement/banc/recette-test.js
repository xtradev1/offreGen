// Recette légère par l'interface sur le site de test (https://easy-offres.xtradev.ch), compte de démo du chantier
// (fluidite-test@exemple.ch, données inventées). Une seule connexion, arrêt au premier refus (cinq échecs bloquent le
// courriel et l'adresse IP). Geste joué : corbeille puis « Annuler » (aucune donnée changée au bout du compte).
// Usage : node recette-test.js. Sortie : recette-test.json, captures/test-*.png.
'use strict';
const fs = require('fs');
const path = require('path');
const { CAPTURES, navigateur } = require('./commun');

const BASE = 'https://easy-offres.xtradev.ch';
const fiche = fs.readFileSync('\\\\wsl.localhost\\Ubuntu\\home\\xavie\\.offregen-test-fluidite-compte', 'utf8');
const mdp = ((/Mot de passe, affiché une seule fois : (\S+)/.exec(fiche)) || [])[1];
const courriel = ((/Compte créé : (\S+@\S+)/.exec(fiche)) || [])[1];
const constats = [];
const noter = (cle, valeur) => { constats.push({ cle, valeur }); console.log(cle.padEnd(46), typeof valeur === 'string' ? valeur : JSON.stringify(valeur)); };

(async () => {
  if (!mdp || !courriel) throw new Error('fiche du compte illisible');
  const { browser, contexte } = await navigateur({ sansSession: true });
  const erreurs = [];
  const morceaux = [];
  contexte.on('page', (p) => {
    p.on('pageerror', (e) => erreurs.push(e.message));
    p.on('console', (m) => { if (m.type() === 'error') erreurs.push('console : ' + m.text()); });
  });
  contexte.on('response', async (r) => {
    const h = r.request().headers();
    if (h['x-morceaux'] !== undefined && r.request().method() === 'POST') morceaux.push({ url: r.url().replace(BASE, ''), statut: r.status(), page: r.headers()['x-morceaux-page'] || null });
  });
  const page = await contexte.newPage();
  await page.goto(BASE + '/connexion', { waitUntil: 'load' });
  await page.fill('#courriel', courriel);
  await page.fill('#mot_de_passe', mdp);
  await page.click('button[form="connexion"]');
  try {
    await page.waitForURL((u) => !u.pathname.startsWith('/connexion'), { timeout: 20000 });
  } catch (e) {
    noter('connexion', 'REFUSÉE, arrêt (aucune autre tentative)');
    await browser.close();
    process.exit(1);
  }
  noter('connexion', 'acceptée, arrivée sur ' + new URL(page.url()).pathname);

  // Une offre : depuis la liste, sinon depuis la fiche d'un client.
  await page.goto(BASE + '/', { waitUntil: 'load' });
  let offres = await page.$$eval('a[href^="/offres/"]', (l) => [...new Set(l.map((a) => a.getAttribute('href')).filter((h) => /^\/offres\/\d+$/.test(h)))]);
  if (!offres.length) {
    await page.goto(BASE + '/clients', { waitUntil: 'load' });
    const clients = await page.$$eval('a[href^="/clients/"]', (l) => l.map((a) => a.getAttribute('href')).filter((h) => /^\/clients\/\d+$/.test(h)));
    for (const c of clients) {
      await page.goto(BASE + c, { waitUntil: 'load' });
      offres = await page.$$eval('a[href^="/offres/"]', (l) => [...new Set(l.map((a) => a.getAttribute('href')).filter((h) => /^\/offres\/\d+$/.test(h)))]);
      if (offres.length) break;
    }
  }
  noter('offres trouvées', offres);
  if (!offres.length) throw new Error('aucune offre trouvée');

  await page.goto(BASE + offres[0], { waitUntil: 'load' });
  await page.evaluate(() => navigator.serviceWorker && navigator.serviceWorker.ready.then(() => true)).catch(() => {});
  if (!(await page.evaluate(() => !!(navigator.serviceWorker && navigator.serviceWorker.controller)))) await page.reload({ waitUntil: 'load' });
  await page.waitForFunction(() => window.fluide && !window.fluide.occupe(), null, { timeout: 30000 });
  await page.waitForTimeout(1500);
  noter('service worker aux commandes', await page.evaluate(() => !!navigator.serviceWorker.controller));
  noter('caches', await page.evaluate(async () => (await caches.keys()).map((n) => n.replace(/\d+$/, 'N'))));
  noter('feuille de style versionnée', await page.evaluate(() => { const l = document.querySelector('link[rel=stylesheet][href*="/css/app.css"]'); return l ? l.getAttribute('href') : null; }));

  // Corbeille puis « Annuler ».
  const origine = await page.evaluate(() => performance.timeOrigin);
  const ligne = await page.locator('#lignes .row:has(form[action$="/supprimer"])').first().getAttribute('id');
  const nom = await page.evaluate((l) => (document.querySelector('#' + l + ' .nom, #' + l + ' [data-nom]') || document.getElementById(l)).textContent.trim().split('\n')[0].slice(0, 50), ligne);
  const t0 = Date.now();
  await page.locator('#' + ligne + ' form[action$="/supprimer"] button').first().click();
  await page.waitForFunction((l) => { const r = document.getElementById(l); return !r || r.hidden; }, ligne, { timeout: 10000 });
  noter('corbeille : ligne partie après (ms)', Date.now() - t0);
  await page.waitForFunction(() => window.fluide && !window.fluide.occupe(), null, { timeout: 30000 });
  noter('corbeille : confirmée après (ms)', Date.now() - t0);
  noter('bandeau', await page.evaluate(() => [...document.querySelectorAll('.app > .snack:not([hidden])')].map((s) => s.textContent.trim().replace(/\s+/g, ' ')).join(' | ')));
  await page.screenshot({ path: path.join(CAPTURES, 'test-corbeille.png') });
  const t1 = Date.now();
  await page.locator('.app > form.snack[action$="/restaurer"] button').first().click();
  await page.waitForFunction((l) => { const r = document.getElementById(l); return r && !r.hidden; }, ligne, { timeout: 10000 });
  noter('Annuler : ligne revenue après (ms)', Date.now() - t1);
  await page.waitForFunction(() => window.fluide && !window.fluide.occupe(), null, { timeout: 30000 });
  await page.waitForTimeout(800);
  noter('page rechargée pendant les deux gestes', await page.evaluate((o) => performance.timeOrigin !== o, origine));
  noter('envois par morceaux (POST)', morceaux);
  noter('ligne', nom);
  await page.screenshot({ path: path.join(CAPTURES, 'test-annuler.png') });

  // Déconnexion : les copies du compte partent.
  await page.goto(BASE + '/plus', { waitUntil: 'load' });
  await page.locator('form[action="/deconnexion"] button').first().click();
  await page.waitForURL((u) => u.pathname.startsWith('/connexion'), { timeout: 20000 });
  await page.waitForTimeout(1500);
  noter('après déconnexion : caches de pages', await page.evaluate(async () => (await caches.keys()).filter((n) => n.startsWith('offregen-pages-'))));
  noter('erreurs JavaScript', erreurs);
  await browser.close();
  fs.writeFileSync(path.join(__dirname, 'recette-test.json'), JSON.stringify(constats, null, 1));
})().catch((e) => { console.error(e); process.exit(1); });
