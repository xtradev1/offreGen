// Poids réel du PDF d'une offre et temps de fabrication au serveur (le banc compte mal les réponses lues par PDF.js).
'use strict';
const { BASE, navigateur } = require('./commun');
(async () => {
  const { browser, contexte } = await navigateur();
  const p = await contexte.newPage();
  for (const id of [2, 3]) {
    const t0 = Date.now();
    const r = await p.request.get(BASE + `/offres/${id}/pdf`, { headers: { 'Accept-Encoding': 'gzip' } });
    const corps = await r.body();
    console.log(`offre ${id} : PDF ${corps.length} octets, ${Date.now() - t0} ms, ${r.headers()['content-type']}, cache : ${r.headers()['cache-control']}, encodage : ${r.headers()['content-encoding'] || 'aucun'}`);
  }
  await browser.close();
})();
