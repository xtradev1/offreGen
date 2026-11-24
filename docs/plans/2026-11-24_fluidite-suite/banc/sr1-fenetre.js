// SR-1 : combien de temps l'offre est-elle à l'écran sans que ses gestes soient branchés ? Du premier affichage des
// lignes à l'exécution de fluide.js et d'app.js, et poids de la page. Usage : node sr1-fenetre.js [profil...]
'use strict';
const fs = require('fs');
const path = require('path');
const { BASE, ICI, navigateur, reseau } = require('./commun');

const NEUF = process.env.SR1_NEUF === '1'; // scripts absents du téléphone : la première offre ouverte après un déploiement
const profils = process.argv.slice(2).length ? process.argv.slice(2) : ['fibre', '4g', 'chantier'];
const offres = ['/offres/3', '/offres/1', '/offres/2'];

(async () => {
  const { browser, contexte } = await navigateur();
  const p0 = await contexte.newPage();
  await p0.goto(BASE + '/', { waitUntil: 'load' });
  await p0.waitForTimeout(3000);
  await p0.close();
  const res = [];
  for (const profil of profils) {
    for (const adresse of offres) {
      const page = await contexte.newPage();
      if (NEUF) {
        await page.goto(BASE + '/connexion', { waitUntil: 'load' });
        await page.evaluate(async () => { for (const n of await caches.keys()) if (n.startsWith('offregen-fichiers-')) await caches.delete(n); });
        const c = await contexte.newCDPSession(page); await c.send('Network.clearBrowserCache'); await c.detach();
      }
      const cdp = await reseau(page, profil);
      let octets = 0;
      page.on('response', async (r) => { if (r.request().resourceType() === 'document') { try { octets = (await r.body()).length; } catch (e) { /* */ } } });
      const t0 = Date.now();
      await page.goto(BASE + adresse, { waitUntil: 'commit', timeout: 90000 });
      await page.waitForSelector('#lignes .row', { state: 'visible', timeout: 90000 });
      const lignes = Date.now() - t0;
      await page.waitForFunction(() => !!window.fluide, null, { timeout: 90000, polling: 10 });
      const scripts = Date.now() - t0;
      await page.waitForLoadState('load');
      const m = await page.evaluate(() => {
        const n = performance.getEntriesByType('navigation')[0];
        return { html_transfere: n.transferSize, html_decode: n.decodedBodySize, reponse_debut: Math.round(n.responseStart), reponse_fin: Math.round(n.responseEnd), dcl: Math.round(n.domContentLoadedEventStart), nb_lignes: document.querySelectorAll('#lignes .row').length, sw: !!navigator.serviceWorker.controller };
      });
      const r = { profil, adresse, lignes_visibles_ms: lignes, scripts_prets_ms: scripts, fenetre_sans_gestes_ms: scripts - lignes, octets_html: octets, ...m };
      res.push(r);
      console.log(`${profil.padEnd(9)} ${adresse.padEnd(10)} ${String(m.nb_lignes).padStart(3)} lignes | lignes visibles ${String(lignes).padStart(5)} ms, gestes branchés ${String(scripts).padStart(5)} ms : ${String(scripts - lignes).padStart(5)} ms sans gestes | HTML ${Math.round(m.html_decode / 1024)} Ko (${Math.round(m.html_transfere / 1024)} Ko transférés), réponse ${m.reponse_debut} à ${m.reponse_fin} ms, sw ${m.sw}`);
      await page.close();
    }
  }
  fs.writeFileSync(path.join(ICI, NEUF ? 'sr1-fenetre-neuf.json' : 'sr1-fenetre.json'), JSON.stringify(res, null, 1));
  await browser.close();
})().catch((e) => { console.error(e); process.exit(1); });
