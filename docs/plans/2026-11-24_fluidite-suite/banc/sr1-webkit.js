// SR-1 sous WebKit (moteur de Safari, iPhone 13) : « C'est juste », la corbeille et une quantité, faits N ms après
// l'ouverture de l'offre depuis la liste. Banc local, sans bridage (WebKit n'a pas de CDP). Usage : node sr1-webkit.js
'use strict';
const fs = require('fs');
const path = require('path');
const { execSync } = require('child_process');
const { pw, BASE, ICI, SESSION } = require('./commun');

const remettre = () => execSync('wsl -d Ubuntu -u root -e sh /home/xavie/offreGen-fluidite-banc/base.sh remettre', { stdio: 'pipe' });
const DELAIS = (process.argv.slice(2).map(Number).filter((n) => !Number.isNaN(n)));
const delais = DELAIS.length ? DELAIS : [0, 300, 800, 2000, 5000];

async function attendre(page, f, arg, max = 30000) {
  const t0 = Date.now();
  while (Date.now() - t0 < max) {
    try { if (await page.evaluate(f, arg)) return Date.now() - t0; } catch (e) { /* navigation */ }
    await page.waitForTimeout(5);
  }
  return null;
}

const GESTES = {
  juste: {
    prendre: (page) => page.locator('#lignes .row.flag').first().getAttribute('id'),
    faire: (page, id) => page.tap('#' + id + ' form.note.warn button', { noWaitAfter: true }),
    vu: ({ id }) => { if (!document.querySelector('#totaux')) return false; const r = document.getElementById(id); return !!r && !r.querySelector('form.note.warn:not([hidden])'); },
  },
  corbeille: {
    prendre: (page) => page.locator('#lignes .row:has(button.del)').nth(2).getAttribute('id'),
    faire: (page, id) => page.tap('#' + id + ' button.del', { noWaitAfter: true }),
    vu: ({ id }) => { if (!document.querySelector('#totaux')) return false; const r = document.getElementById(id); return !r || r.hidden; },
  },
  quantite: {
    prendre: (page) => page.locator('#lignes .row:has(input[name=quantite])').nth(3).getAttribute('id'),
    faire: async (page, id) => { const c = page.locator('#' + id + ' input[name=quantite]'); await c.tap({ noWaitAfter: true }); await c.fill('7', { noWaitAfter: true }); await c.press('Enter', { noWaitAfter: true }); },
    vu: ({ id, o }) => {
      if (!document.querySelector('#totaux')) return false;
      const r = document.getElementById(id); const tt = r && r.querySelector('.tt');
      if (tt && tt.classList.contains('provisoire')) return true;
      const q = r && r.querySelector('input[name=quantite]');
      return performance.timeOrigin !== o && !!q && q.value.trim() === '7';
    },
  },
};

(async () => {
  const etatSession = JSON.parse(fs.readFileSync(SESSION, 'utf8'));
  etatSession.cookies.forEach((c) => { c.secure = false; });
  const browser = await pw.webkit.launch({ headless: true });
  const res = [];
  for (const nom of Object.keys(GESTES)) {
    for (const delai of delais) {
      remettre();
      const contexte = await browser.newContext({ ...pw.devices['iPhone 13'], locale: 'fr-CH', storageState: etatSession });
      const page = await contexte.newPage();
      const erreurs = [];
      page.on('pageerror', (e) => erreurs.push(String(e.message)));
      await page.goto(BASE + '/', { waitUntil: 'load' });
      await page.waitForTimeout(1500);
      const info = await page.evaluate(() => ({ sw: !!(navigator.serviceWorker && navigator.serviceWorker.controller), idle: typeof window.requestIdleCallback }));
      const t0 = Date.now();
      await page.goto(BASE + '/offres/3', { waitUntil: 'commit' });
      await page.waitForSelector('#lignes .row', { state: 'visible' });
      const visible = Date.now() - t0;
      if (delai) await page.waitForTimeout(delai);
      const etat = await page.evaluate(() => ({ fluide: !!window.fluide, doc: document.readyState, origine: performance.timeOrigin }));
      const g = GESTES[nom];
      const id = await g.prendre(page);
      const tT = Date.now();
      await g.faire(page, id);
      const vu = await attendre(page, g.vu, { id, o: etat.origine });
      const ecran = vu === null ? null : Date.now() - tT;
      const confirme = await attendre(page, () => document.readyState === 'complete' && window.fluide && !window.fluide.occupe());
      const recharge = await page.evaluate((o) => performance.timeOrigin !== o, etat.origine).catch(() => null);
      const r = { moteur: 'webkit', geste: nom, delai, visible, ...info, scripts: etat.fluide, document: etat.doc, ecran_ms: ecran, confirme_ms: ecran !== null && confirme !== null ? Date.now() - tT : null, recharge, erreurs };
      res.push(r);
      console.log(`webkit ${nom.padEnd(10)} geste ${String(delai).padStart(5)} ms après l'affichage (visible ${visible} ms) | sw ${info.sw}, idle ${info.idle}, scripts ${etat.fluide ? 'oui' : 'NON'} ${etat.doc} | écran ${String(ecran).padStart(5)} ms ${recharge ? 'RECHARGÉE' : 'sur place'}${erreurs.length ? ' ERREURS ' + erreurs.join(' | ') : ''}`);
      await contexte.close();
    }
  }
  fs.writeFileSync(path.join(ICI, 'sr1-webkit.json'), JSON.stringify(res, null, 1));
  await browser.close();
  remettre();
})().catch((e) => { console.error(e); process.exit(1); });
