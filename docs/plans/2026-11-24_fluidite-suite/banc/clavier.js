// Boutons « Enregistrer » sans effet sur iPhone jusqu'à ce qu'on fasse défiler la page (Xavier, 2026-11-24). Le clavier
// d'iOS ne se simule pas au banc : on joue sa hauteur visible (la vue rétrécit puis revient) et on vérifie que la page
// défile d'un pixel et revient, et rien d'autre. Second contrôle : pendant que la fenêtre du bas redescend, le fond ne
// prend plus les touchers (témoin : la règle annulée). Usage : node clavier.js [chromium|webkit].
// Résultats : clavier-<moteur>.json.
'use strict';
const fs = require('fs');
const path = require('path');
const { pw, BASE, SESSION, navigateur } = require('./commun');

const moteur = process.argv[2] || 'chromium';
const res = [];
const noter = (cle, valeur) => { res.push({ cle, valeur }); console.log(cle.padEnd(56), JSON.stringify(valeur)); };

(async () => {
  let browser; let contexte;
  if (moteur === 'webkit') {
    const e = JSON.parse(fs.readFileSync(SESSION, 'utf8'));
    e.cookies.forEach((c) => { c.secure = false; });
    browser = await pw.webkit.launch({ headless: true });
    contexte = await browser.newContext({ ...pw.devices['iPhone 13'], locale: 'fr-CH', storageState: e });
  } else {
    ({ browser, contexte } = await navigateur());
  }
  const page = await contexte.newPage();
  const erreurs = [];
  page.on('pageerror', (e) => erreurs.push(String(e.message)));
  const large = page.viewportSize();

  await page.goto(BASE + '/offres/3', { waitUntil: 'load' });
  await page.waitForTimeout(1200);
  noter('vue de départ', large);
  noter('hauteur de la page', await page.evaluate(() => document.documentElement.scrollHeight));

  const scenario = async (nom, petite, grande) => {
    await page.setViewportSize(large);
    await page.waitForTimeout(300);
    await page.evaluate(() => window.scrollTo(0, 400));
    await page.waitForTimeout(300);
    await page.evaluate(() => { window.__defile = []; window.addEventListener('scroll', () => window.__defile.push(Math.round(window.scrollY)), { passive: true }); });
    const avant = await page.evaluate(() => Math.round(window.scrollY));
    await page.setViewportSize(petite);
    await page.waitForTimeout(400);
    await page.setViewportSize(grande);
    await page.waitForTimeout(500);
    const apres = await page.evaluate(() => ({ y: Math.round(window.scrollY), defile: window.__defile }));
    noter(nom, { avant, apres: apres.y, defilements: apres.defile });
  };
  await scenario('clavier : 844 puis 450 puis 844', { width: large.width, height: 450 }, large);
  await scenario('témoin, barre de Safari : 844 puis 760 puis 844', { width: large.width, height: large.height - 84 }, large);
  await scenario('témoin, rotation : 844 puis 390 de large', { width: large.height, height: large.width }, { width: large.height, height: large.width });
  await page.setViewportSize(large);

  // Fond de la fenêtre du bas, 60 ms après la fermeture : qui reçoit un toucher au milieu de l'écran ?
  const fond = async (nom) => {
    await page.goto(BASE + '/offres/3', { waitUntil: 'load' });
    await page.waitForTimeout(1200);
    if (nom.startsWith('témoin')) await page.evaluate(() => document.querySelectorAll('.feuille-fond').forEach((f) => { f.style.pointerEvents = 'auto'; }));
    await page.locator('#lignes .row a.nm').first().tap();
    await page.waitForSelector('.feuille-fond.ouvert', { timeout: 15000 });
    await page.waitForTimeout(500);
    const vu = await page.evaluate(() => new Promise((ok) => {
      history.back();
      setTimeout(() => {
        const f = document.querySelector('.feuille-fond:not([hidden])');
        const el = document.elementFromPoint(innerWidth / 2, innerHeight / 3);
        ok({ fondEncoreLa: !!f, ouvert: f ? f.classList.contains('ouvert') : null, touche: el ? (el.closest('.feuille-fond') ? 'le fond' : el.tagName.toLowerCase() + (el.className ? '.' + String(el.className).split(' ')[0] : '')) : null });
      }, 60);
    }));
    noter(nom, vu);
  };
  await fond('fermeture, 60 ms après');
  await fond('témoin (règle annulée), 60 ms après');

  noter('erreurs JavaScript', erreurs);
  fs.writeFileSync(path.join(__dirname, `clavier-${moteur}.json`), JSON.stringify(res, null, 1));
  await browser.close();
})().catch((e) => { console.error(e); process.exit(1); });
