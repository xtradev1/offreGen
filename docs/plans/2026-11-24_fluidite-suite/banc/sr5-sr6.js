// SR-5 puis SR-6, d'affilée comme dans la recette de Xavier du 2026-11-24 : hors réseau, une quantité changée, « Plus »,
// « Se déconnecter », « Annuler » ; puis le réseau revient (sur la page donnée) et SR-6 : « Générer l'offre », reviens,
// « Générer » encore, « Envoyer ». Relève à chaque étape la file de fluide.js et ce que le téléphone garde.
// Usage : node sr5-sr6.js [chromium|webkit] [retour du réseau : plus|offre|aucun] [offre]
'use strict';
const fs = require('fs');
const path = require('path');
const { execSync } = require('child_process');
const { pw, BASE, ICI, SESSION, CAPTURES, navigateur } = require('./commun');

const moteur = process.argv[2] || 'chromium';
const ou = process.argv[3] || 'plus';
const offre = process.argv[4] || '1';
const offreSr5 = process.argv[5] || offre; // SR-5 fait sur une autre offre que SR-6
const remettre = () => execSync('wsl -d Ubuntu -u root -e sh /home/xavie/offreGen-fluidite-banc/base.sh remettre', { stdio: 'pipe' });

const releve = (page, quoi) => page.evaluate(async (q) => {
  const file = window.fluide ? window.fluide.file.map((f) => ({ action: f.item.action, corps: f.item.corps.replace(/_csrf=[^&]*&?/, '').slice(0, 60), gardee: !!f.options.gardee })) : 'sans fluide';
  const garde = await new Promise((ok) => {
    const r = indexedDB.open('offregen-fluide', 1);
    r.onsuccess = () => { try { const t = r.result.transaction('envois', 'readonly').objectStore('envois').getAll(); t.onsuccess = () => ok(t.result.map((i) => i.action)); t.onerror = () => ok('erreur'); } catch (e) { ok('vide'); } };
    r.onerror = () => ok('pas de base');
  });
  const bandeaux = [...document.querySelectorAll('.app > .snack, .app > .reseau:not([hidden])')].map((s) => s.textContent.trim());
  return { quoi: q, adresse: location.pathname, en_ligne: navigator.onLine, file, garde, bandeaux };
}, quoi);

(async () => {
  remettre();
  let browser; let contexte;
  if (moteur === 'webkit') {
    const e = JSON.parse(fs.readFileSync(SESSION, 'utf8'));
    e.cookies.forEach((c) => { c.secure = false; });
    browser = await pw.webkit.launch({ headless: true });
    contexte = await browser.newContext({ ...pw.devices['iPhone 13'], locale: 'fr-CH', storageState: e });
  } else {
    ({ browser, contexte } = await navigateur());
  }
  await contexte.addInitScript(() => { navigator.canShare = () => true; navigator.share = async () => {}; });
  const page = await contexte.newPage();
  const posts = [];
  page.on('request', (r) => { if (r.method() === 'POST') posts.push(`${r.url().replace(BASE, '')}`); });
  page.on('response', (r) => { if (r.request().method() === 'POST') posts.push(`  -> ${r.status()}`); });
  let dialogue = '';
  page.on('dialog', (d) => { dialogue = d.message(); d.dismiss(); });
  const etapes = [];
  const noter = async (q) => { const e = await releve(page, q); etapes.push(e); console.log(`${q.padEnd(26)} ${e.adresse.padEnd(18)} ${e.en_ligne ? 'en ligne ' : 'HORS LIGNE'} file ${JSON.stringify(e.file)} garde ${JSON.stringify(e.garde)} bandeaux ${JSON.stringify(e.bandeaux)}`); };

  // Toutes les pages du parcours déjà vues avec réseau (gardées par le service worker).
  for (const a of ['/', '/plus', '/offres/' + offre, '/offres/' + offre + '/apercu', '/offres/' + offreSr5]) { await page.goto(BASE + a, { waitUntil: 'load' }); await page.waitForTimeout(1200); }
  await noter('offre, avec réseau');
  // SR-5.
  await contexte.setOffline(true);
  await page.waitForTimeout(300);
  const champ = page.locator('#lignes .row input[name=quantite]').first();
  await champ.tap();
  await champ.fill('9');
  await champ.press('Enter');
  await page.waitForTimeout(800);
  await noter('SR-5 quantité hors réseau');
  await page.locator('a[href="/plus"]').first().tap();
  await page.waitForURL(/\/plus/, { timeout: 30000 }).catch(() => {});
  await page.waitForTimeout(1500);
  await noter('SR-5 Plus');
  const deco = page.locator('form[action="/deconnexion"] button').first();
  if (await deco.count()) { await deco.tap(); await page.waitForTimeout(800); }
  await noter('SR-5 déconnexion annulée');
  console.log('question :', dialogue || '(aucune)');
  if (ou === 'plus') { await contexte.setOffline(false); await page.waitForTimeout(4000); await noter('réseau revenu sur Plus'); }
  // SR-6.
  await page.goto(BASE + '/offres/' + offre, { waitUntil: 'load' }).catch(() => {});
  await page.waitForTimeout(2500);
  if (ou === 'offre') { await contexte.setOffline(false); await page.waitForTimeout(4000); }
  await noter('SR-6 offre');
  const generer = page.locator('a.btn.primary.grand:has-text("Générer l\'offre")');
  for (const tour of [1, 2]) {
    await generer.tap();
    await page.waitForURL(/\/apercu/, { timeout: 30000 });
    await page.waitForLoadState('load');
    await page.waitForTimeout(2500);
    await noter('SR-6 aperçu ' + tour);
    if (tour === 1) {
      await page.locator('header.bar a.back').tap();
      await page.waitForURL(new RegExp('/offres/' + offre + '($|[?#])'), { timeout: 30000 });
      await page.waitForLoadState('load');
      await page.waitForTimeout(2500);
      await noter('SR-6 offre revenue');
    }
  }
  // I3 : sans toucher « Envoyer » (le retour du réseau doit refaire l'aperçu quand même).
  if (process.env.I3 !== '1') await page.locator('[data-partager]').first().tap();
  await page.waitForTimeout(1500);
  await noter('SR-6 après « Envoyer »');
  await page.screenshot({ path: path.join(CAPTURES, `sr5-sr6-${moteur}-${ou}-${offreSr5}-${offre}.png`) });
  if (process.env.RETOUR === '1') {
    // Le réseau revient : le changement de l'offre part, l'aperçu se refait avec le PDF à jour (« Envoyer » le promettait).
    const origine = await page.evaluate(() => performance.timeOrigin);
    await contexte.setOffline(false);
    await page.waitForTimeout(6000);
    const apres = await page.evaluate((o) => ({ refait: performance.timeOrigin !== o, file: window.fluide ? window.fluide.file.length : null }), origine).catch((e) => ({ erreur: String(e) }));
    console.log('retour du réseau :', JSON.stringify(apres));
  }
  const q = await page.evaluate(() => { const c = document.querySelector('#lignes .row input[name=quantite]'); return c ? c.value : null; }).catch(() => null);
  console.log('POST :', posts.join(' ') || 'aucun');
  fs.writeFileSync(path.join(ICI, `sr5-sr6-${moteur}-${ou}-${offreSr5}-${offre}.json`), JSON.stringify({ moteur, ou, offre, etapes, posts, dialogue }, null, 1));
  await browser.close();
  remettre();
})().catch((e) => { console.error(e); process.exit(1); });
