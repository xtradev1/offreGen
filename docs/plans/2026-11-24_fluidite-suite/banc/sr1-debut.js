// SR-1 (recette du 2026-11-24) : un geste fait dès que l'offre paraît répond-il au toucher ? Comparé au même geste
// fait 10 s après l'ouverture. Relève, au moment du toucher : scripts de la page exécutés (window.fluide), état du
// document ; puis le délai jusqu'au changement d'écran et si la page s'est rechargée.
// Usage : node sr1-debut.js [profil...]
'use strict';
const fs = require('fs');
const path = require('path');
const { execSync } = require('child_process');
const { BASE, ICI, SESSION, navigateur, reseau, connecter } = require('./commun');

const profils = process.argv.slice(2).length ? process.argv.slice(2) : ['4g', 'chantier'];
const remettre = () => execSync('wsl -d Ubuntu -u root -e sh /home/xavie/offreGen-fluidite-banc/base.sh remettre', { stdio: 'pipe' });
const resultats = [];

// Attend une condition en survivant à une navigation (envoi classique : la page change sous nos pieds).
async function attendre(page, f, arg, max = 30000) {
  const t0 = Date.now();
  while (Date.now() - t0 < max) {
    try { if (await page.evaluate(f, arg)) return Date.now() - t0; } catch (e) { /* page en train de changer */ }
    await page.waitForTimeout(10);
  }
  return null;
}

const GESTES = {
  juste: {
    cible: '#lignes .row.flag form.note.warn button',
    faire: async (page) => {
      const id = await page.locator('#lignes .row.flag').first().getAttribute('id');
      await page.tap('#' + id + ' form.note.warn button', { noWaitAfter: true });
      return id;
    },
    vu: ({ id }) => { if (!document.querySelector('#totaux')) return false; const r = document.getElementById(id); return !!r && !r.querySelector('form.note.warn:not([hidden])'); },
  },
  corbeille: {
    cible: '#lignes .row button.del',
    faire: async (page) => {
      const id = await page.locator('#lignes .row:has(button.del)').nth(2).getAttribute('id');
      await page.tap('#' + id + ' button.del', { noWaitAfter: true });
      return id;
    },
    vu: ({ id }) => { if (!document.querySelector('#totaux')) return false; const r = document.getElementById(id); return !r || r.hidden; },
  },
  fiche: {
    cible: '#lignes .row a.nm',
    faire: async (page) => {
      const id = await page.locator('#lignes .row:has(a.nm)').nth(4).getAttribute('id');
      await page.tap('#' + id + ' a.nm', { noWaitAfter: true });
      return id;
    },
    vu: ({ o }) => { const f = document.querySelector('.feuille'); return (!!f && f.offsetParent !== null && !!f.querySelector('input')) || (performance.timeOrigin !== o && document.readyState !== 'loading'); },
  },
  quantite: {
    cible: '#lignes .row input[name=quantite]',
    faire: async (page) => {
      const id = await page.locator('#lignes .row:has(input[name=quantite])').nth(3).getAttribute('id');
      const champ = page.locator('#' + id + ' input[name=quantite]');
      await champ.tap({ noWaitAfter: true });
      await champ.fill('7', { noWaitAfter: true });
      await champ.press('Enter', { noWaitAfter: true });
      return id;
    },
    vu: ({ id, o }) => {
      if (!document.querySelector('#totaux')) return false;
      const r = document.getElementById(id); const tt = r && r.querySelector('.tt');
      if (tt && tt.classList.contains('provisoire')) return true;
      const q = r && r.querySelector('input[name=quantite]');
      return performance.timeOrigin !== o && !!q && q.value.trim() === '7';
    },
  },
};

async function essai(contexte, profil, nom, quand) {
  remettre();
  const page = await contexte.newPage();
  const erreurs = [];
  page.on('pageerror', (e) => erreurs.push(String(e.message)));
  await reseau(page, profil);
  const g = GESTES[nom];
  const t0 = Date.now();
  await page.goto(BASE + '/offres/3', { waitUntil: 'commit', timeout: 90000 });
  await page.waitForSelector(g.cible, { state: 'visible', timeout: 90000 });
  const visible = Date.now() - t0;
  if (quand === 'apres10s') await page.waitForTimeout(10000);
  const etat = await page.evaluate(() => ({ fluide: !!window.fluide, etat: document.readyState, origine: performance.timeOrigin }));
  const tToucher = Date.now();
  const id = await g.faire(page);
  const vu = await attendre(page, g.vu, { id, o: etat.origine });
  const auToucher = Date.now() - tToucher;
  await page.waitForLoadState('load').catch(() => {});
  await attendre(page, () => document.readyState === 'complete' && window.fluide && !window.fluide.occupe(), null, 60000);
  const confirme = Date.now() - tToucher;
  const recharge = await page.evaluate((o) => performance.timeOrigin !== o, etat.origine).catch(() => null);
  const r = { profil, geste: nom, quand, offre_visible_ms: visible, au_toucher: etat, ecran_change_ms: vu === null ? null : auToucher, confirme_ms: confirme, recharge, erreurs };
  resultats.push(r);
  console.log(`${profil.padEnd(9)} ${nom.padEnd(10)} ${quand.padEnd(9)} visible ${String(visible).padStart(5)} ms | au toucher : scripts ${etat.fluide ? 'oui' : 'NON'}, ${etat.etat.padEnd(11)} | écran ${String(r.ecran_change_ms).padStart(5)} ms, confirmé ${String(confirme).padStart(5)} ms, ${recharge ? 'RECHARGÉE' : 'sur place'}${erreurs.length ? ' ERREURS ' + erreurs.join(' | ') : ''}`);
  await page.close();
}

(async () => {
  if (!fs.existsSync(SESSION)) console.log('connexion :', await connecter());
  const { browser, contexte } = await navigateur();
  // Téléphone qui a déjà ouvert l'application : fichiers gardés, service worker en place.
  const p0 = await contexte.newPage();
  await p0.goto(BASE + '/', { waitUntil: 'load' });
  await p0.waitForTimeout(3000);
  await p0.close();
  for (const profil of profils) {
    for (const nom of Object.keys(GESTES)) {
      for (const quand of ['debut', 'apres10s']) await essai(contexte, profil, nom, quand);
    }
  }
  fs.writeFileSync(path.join(ICI, 'sr1-debut.json'), JSON.stringify(resultats, null, 1));
  await browser.close();
  remettre();
})().catch((e) => { console.error(e); process.exit(1); });
