// Recette du lot 3 sur les listes : trier les offres, choisir une catégorie du catalogue (sans requête), changer le prix
// d'un produit dans sa fiche (sa seule ligne revient), créer un client (la liste revient). Usage : node recette-listes.js [profil]
'use strict';
const path = require('path');
const { execSync } = require('child_process');
const { BASE, CAPTURES, navigateur, reseau, compteur } = require('./commun');

const profil = process.argv[2] || 'chantier';
const remettre = () => execSync('wsl -d Ubuntu -u root -e sh /home/xavie/offreGen-fluidite-banc/base.sh remettre', { stdio: 'pipe' });

async function mesurer(nom, page, faire, attendre, arg) {
  const c = compteur(page);
  const origine = await page.evaluate(() => performance.timeOrigin);
  c.debut();
  const t0 = Date.now();
  await faire();
  await page.waitForFunction(attendre, arg, { timeout: 30000, polling: 16 });
  const vu = Date.now() - t0;
  await page.waitForFunction(() => !window.fluide || !window.fluide.occupe(), null, { timeout: 30000 });
  const m = await c.fin();
  const recharge = await page.evaluate((o) => performance.timeOrigin !== o, origine);
  await page.screenshot({ path: path.join(CAPTURES, 'recette-' + nom + '.png') });
  console.log(`${nom.padEnd(24)} ${String(vu).padStart(5)} ms  ${String(m.requetes).padStart(2)} req ${String(Math.round(m.octets / 102.4) / 10).padStart(5)} Ko  ${recharge ? 'RECHARGÉE' : 'sans rechargement'}  ${m.detail.map((d) => d.methode + ' ' + d.url.slice(0, 50)).join(' ; ')}`);
}

(async () => {
  remettre();
  const { browser, contexte } = await navigateur();
  const erreurs = [];
  const p0 = await contexte.newPage();
  await p0.goto(BASE + '/', { waitUntil: 'load' });
  await p0.close();

  let page = await contexte.newPage();
  page.on('pageerror', (e) => erreurs.push(e.message));
  await reseau(page, profil);
  await page.goto(BASE + '/', { waitUntil: 'load' });
  const premiere = await page.locator('#liste-offres .glisse').first().getAttribute('id');
  await mesurer('liste-tri-montant', page, () => page.click('nav.fchips.tri a[href="/?tri=montant"]'),
    () => location.search === '?tri=montant' && document.querySelector('nav.fchips.tri a[aria-current]').getAttribute('href') === '/?tri=montant');
  const montants = await page.locator('#liste-offres .glisse').evaluateAll((cs) => cs.map((c) => Number(c.dataset.montant)));
  console.log('  montants dans l\'ordre :', montants.join(' '), montants.every((m, i) => i === 0 || montants[i - 1] >= m) ? '(décroissants)' : 'DÉSORDRE');
  await mesurer('liste-tri-recent', page, () => page.click('nav.fchips.tri a[href="/"]'),
    (id) => document.querySelector('#liste-offres .glisse').id === id, premiere);
  await page.close();

  page = await contexte.newPage();
  page.on('pageerror', (e) => erreurs.push(e.message));
  await reseau(page, profil);
  await page.goto(BASE + '/catalogue', { waitUntil: 'load' });
  const cat = await page.locator('#categories a').nth(2).getAttribute('href');
  await mesurer('catalogue-categorie', page, () => page.click('#categories a[href="' + cat + '"]'),
    () => [...document.querySelectorAll('#clist .crow')].every((r) => r.hidden === r.hasAttribute('data-hors-cat')) && document.querySelectorAll('#clist .crow[data-hors-cat]').length > 0);
  const visibles = await page.locator('#clist .crow:not([hidden])').count();
  console.log('  produits visibles :', visibles);
  // La fiche d'un produit : prix changé, la fiche se ferme, la ligne seule revient.
  const produit = await page.locator('#clist .crow:not([hidden])').first().getAttribute('id');
  await page.click('#' + produit);
  await page.waitForSelector('[data-feuille-page].ouvert input[name=prix]', { timeout: 30000 });
  await page.waitForTimeout(400);
  await page.fill('[data-feuille-page] input[name=prix]', '77.70');
  await mesurer('catalogue-fiche-enregistrer', page, () => page.click('[data-feuille-page] button[type=submit][form=produit]'),
    (id) => document.querySelector('#' + id + ' .num').textContent.includes('77.70'), produit);
  await page.close();

  console.log(erreurs.length ? 'ERREURS JS : ' + erreurs.join(' | ') : 'aucune erreur JavaScript');
  await browser.close();
})().catch((e) => { console.error(e); process.exit(1); });
