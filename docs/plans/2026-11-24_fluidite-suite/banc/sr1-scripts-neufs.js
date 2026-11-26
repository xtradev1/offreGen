// SR-1 : la première offre ouverte après un déploiement qui change les scripts (fluide.js, app.js) et pas la feuille de
// style. Le téléphone garde le style, les deux scripts viennent du réseau. Nouvelle page à chaque mesure (pas de cache
// mémoire). Relève : premier affichage, gestes branchés (window.fluide), fin du chargement.
// Usage : node sr1-scripts-neufs.js <étiquette> [profil...]
'use strict';
const fs = require('fs');
const path = require('path');
const { BASE, ICI, navigateur, reseau } = require('./commun');
const etiquette = process.argv[2] || 'essai';
const profils = process.argv.slice(3).length ? process.argv.slice(3) : ['4g', 'chantier'];
(async () => {
  const res = [];
  for (const profil of profils) {
    for (let passe = 0; passe < 2; passe++) {
      const { browser, contexte } = await navigateur();
      const p0 = await contexte.newPage();
      await p0.goto(BASE + '/', { waitUntil: 'load' });
      // Le service worker range les fichiers de la première page quelques secondes après : on attend qu'il l'ait fait.
      for (let essai = 0; essai < 60; essai++) {
        const ranges = await p0.evaluate(async () => { for (const n of await caches.keys()) { if (!n.startsWith('offregen-fichiers-')) continue; const k = (await (await caches.open(n)).keys()).map((r) => r.url); if (k.some((u) => /fluide\.js/.test(u)) && k.some((u) => /\/app\.js/.test(u)) && k.some((u) => /app\.css/.test(u))) return true; } return false; });
        if (ranges) break;
        await p0.waitForTimeout(300);
      }
      const ote = await p0.evaluate(async () => {
        const otes = [];
        for (const n of await caches.keys()) {
          const c = await caches.open(n);
          for (const r of await c.keys()) if (/\/js\/(fluide|app)\.js/.test(r.url)) { await c.delete(r); otes.push(r.url.replace(location.origin, '')); }
        }
        return otes;
      });
      const c = await contexte.newCDPSession(p0); await c.send('Network.clearBrowserCache'); await c.detach();
      await p0.close();
      const page = await contexte.newPage();
      await reseau(page, profil);
      const t0 = Date.now();
      await page.goto(BASE + '/offres/3', { waitUntil: 'commit', timeout: 90000 });
      await page.waitForSelector('#lignes .row', { state: 'visible', timeout: 90000 });
      const lignes = Date.now() - t0;
      await page.waitForFunction(() => !!window.fluide, null, { timeout: 90000, polling: 10 });
      const gestes = Date.now() - t0;
      await page.waitForLoadState('load');
      const m = await page.evaluate(() => {
        const r = (re) => { const e = performance.getEntriesByType('resource').find((x) => re.test(x.name)); return e ? Math.round(e.responseEnd) : null; };
        return { fcp: Math.round((performance.getEntriesByName('first-contentful-paint')[0] || {}).startTime), css: r(/app\.css/), fluide: r(/fluide\.js/), app: r(/\/app\.js/), dcl: Math.round(performance.getEntriesByType('navigation')[0].domContentLoadedEventStart) };
      });
      const r = { etiquette, profil, passe, otes: ote.length, lignes_visibles_ms: lignes, gestes_ms: gestes, ...m };
      res.push(r);
      console.log(`${etiquette.padEnd(10)} ${profil.padEnd(9)} scripts ôtés ${ote.length} | premier affichage ${String(m.fcp).padStart(5)} ms, lignes ${String(lignes).padStart(5)} ms, gestes ${String(gestes).padStart(5)} ms | css fini ${m.css}, fluide fini ${m.fluide}, app fini ${m.app}, dcl ${m.dcl}`);
      await browser.close();
    }
  }
  fs.writeFileSync(path.join(ICI, 'sr1-scripts-neufs-' + etiquette + '.json'), JSON.stringify(res, null, 1));
})().catch((e) => { console.error(e); process.exit(1); });
