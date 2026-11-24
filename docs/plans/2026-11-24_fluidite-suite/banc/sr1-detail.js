// SR-1 : détail des requêtes de l'offre quand les scripts manquent (juste après un déploiement), réseau donné.
'use strict';
const { BASE, navigateur, reseau } = require('./commun');
const profil = process.argv[2] || 'chantier';
(async () => {
  const { browser, contexte } = await navigateur();
  const p0 = await contexte.newPage();
  await p0.goto(BASE + '/', { waitUntil: 'load' }); await p0.waitForTimeout(3000);
  await p0.goto(BASE + '/connexion', { waitUntil: 'load' });
  await p0.evaluate(async () => { for (const n of await caches.keys()) if (n.startsWith('offregen-fichiers-')) await caches.delete(n); });
  const c = await contexte.newCDPSession(p0); await c.send('Network.clearBrowserCache'); await c.detach();
  await p0.close();
  const page = await contexte.newPage();
  await reseau(page, profil);
  await page.goto(BASE + '/offres/3', { waitUntil: 'load', timeout: 90000 });
  const r = await page.evaluate(() => {
    const fcp = (performance.getEntriesByName('first-contentful-paint')[0] || {}).startTime;
    const n = performance.getEntriesByType('navigation')[0];
    return { fcp: Math.round(fcp), html: [Math.round(n.responseStart), Math.round(n.responseEnd)], dcl: Math.round(n.domContentLoadedEventStart),
      res: performance.getEntriesByType('resource').map((e) => [e.name.replace(location.origin, '').slice(0, 45), Math.round(e.startTime), Math.round(e.responseEnd), e.transferSize, e.encodedBodySize, e.renderBlockingStatus || ''].join(' ')) };
  });
  console.log(profil, 'fcp', r.fcp, 'html', r.html.join('..'), 'dcl', r.dcl);
  r.res.forEach((l) => console.log('  ' + l));
  await browser.close();
})().catch((e) => { console.error(e); process.exit(1); });
