// SR-1 : un geste fait N secondes après l'ouverture de l'offre, sur un téléphone qui ouvre l'application (le
// préchargement du service worker part, comme à la première ouverture de l'heure) puis touche une offre de la liste.
// Relève : délai du toucher au changement d'écran, délai jusqu'à la confirmation du serveur, et les requêtes en vol au
// moment du toucher (page, fenêtre « + Ligne » préchargée, pages gardées par le service worker).
// Usage : PW_EXPERIMENTAL_SERVICE_WORKER_NETWORK_EVENTS=1 node sr1-profil.js [profil] [délais en ms...]
'use strict';
const fs = require('fs');
const path = require('path');
const { execSync } = require('child_process');
const { BASE, ICI, navigateur, reseau } = require('./commun');

const profil = process.argv[2] || 'chantier';
const delais = process.argv.slice(3).map(Number).filter(Boolean);
const DELAIS = delais.length ? delais : [800, 2000, 4000, 6000, 9000, 15000];
const remettre = () => execSync('wsl -d Ubuntu -u root -e sh /home/xavie/offreGen-fluidite-banc/base.sh remettre', { stdio: 'pipe' });

async function attendre(page, f, arg, max = 60000) {
  const t0 = Date.now();
  while (Date.now() - t0 < max) {
    try { if (await page.evaluate(f, arg)) return Date.now() - t0; } catch (e) { /* navigation */ }
    await page.waitForTimeout(10);
  }
  return null;
}

(async () => {
  const res = [];
  for (const delai of DELAIS) {
    remettre();
    const { browser, contexte } = await navigateur();
    // Fichiers déjà gardés, service worker en place (téléphone qui a déjà servi), puis préchargement remis à zéro :
    // ouverture de l'application comme à la première de l'heure.
    const p0 = await contexte.newPage();
    await p0.goto(BASE + '/', { waitUntil: 'load' });
    await p0.waitForTimeout(1500);
    await p0.evaluate(() => { Object.keys(localStorage).filter((k) => k.startsWith('offregen-garde-')).forEach((k) => localStorage.removeItem(k)); });
    await p0.evaluate(async () => { for (const n of await caches.keys()) if (n.startsWith('offregen-pages-')) await caches.delete(n); });
    await p0.close();

    const enVol = new Map();
    const journal = [];
    const t00 = Date.now();
    contexte.on('request', (r) => { enVol.set(r, Date.now()); });
    const fin = (r, ok) => { const d = enVol.get(r); enVol.delete(r); if (d) journal.push({ url: r.url().replace(BASE, ''), methode: r.method(), sw: !!r.serviceWorker(), debut: d - t00, fin: Date.now() - t00, ok }); };
    contexte.on('requestfinished', (r) => fin(r, true));
    contexte.on('requestfailed', (r) => fin(r, false));

    const page = await contexte.newPage();
    await reseau(page, profil);
    await page.goto(BASE + '/', { waitUntil: 'load', timeout: 90000 });
    await page.waitForTimeout(1200);
    const tOffre = Date.now();
    await page.tap('#liste-offres .glisse#o3 a, #o3 a', { noWaitAfter: true }).catch(async () => { await page.goto(BASE + '/offres/3', { waitUntil: 'commit' }); });
    await page.waitForURL(/\/offres\/3/, { timeout: 60000 });
    await page.waitForSelector('#lignes .row.flag form.note.warn button', { state: 'visible', timeout: 60000 });
    const visible = Date.now() - tOffre;
    const reste = delai - visible;
    if (reste > 0) await page.waitForTimeout(reste);
    const etat = await page.evaluate(() => ({ fluide: !!window.fluide, doc: document.readyState, origine: performance.timeOrigin }));
    const vol = [...enVol.entries()].map(([r, d]) => `${r.method()} ${r.url().replace(BASE, '')}${r.serviceWorker() ? ' (sw)' : ''}`);
    const id = await page.locator('#lignes .row.flag').first().getAttribute('id');
    const tT = Date.now();
    await page.tap('#' + id + ' form.note.warn button', { noWaitAfter: true });
    const vu = await attendre(page, ({ id, o }) => {
      if (!document.querySelector('#totaux')) return false;
      const r = document.getElementById(id);
      return !!r && !r.querySelector('form.note.warn:not([hidden])');
    }, { id, o: etat.origine });
    const confirme = await attendre(page, () => document.readyState === 'complete' && window.fluide && !window.fluide.occupe());
    const recharge = await page.evaluate((o) => performance.timeOrigin !== o, etat.origine).catch(() => null);
    const tTrel = tT - t00;
    const r = { profil, delai_apres_ouverture_ms: delai, offre_visible_ms: visible, scripts: etat.fluide, document: etat.doc, ecran_ms: vu, confirme_ms: (vu !== null && confirme !== null) ? vu + confirme : null, recharge, en_vol_au_toucher: vol,
      requetes_pendant: journal.filter((j) => j.fin > tTrel && j.debut < tTrel + 15000).map((j) => `${j.methode} ${j.url}${j.sw ? ' (sw)' : ''} ${j.debut - tTrel}..${j.fin - tTrel}`) };
    res.push(r);
    console.log(`${profil} geste à ${String(delai).padStart(5)} ms | écran ${String(vu).padStart(5)} ms, confirmé ${String(r.confirme_ms).padStart(5)} ms ${recharge ? 'RECHARGÉE' : 'sur place'} | en vol : ${vol.join(', ') || 'rien'}`);
    await browser.close();
  }
  fs.writeFileSync(path.join(ICI, 'sr1-profil-' + profil + '.json'), JSON.stringify(res, null, 1));
  remettre();
})().catch((e) => { console.error(e); process.exit(1); });
