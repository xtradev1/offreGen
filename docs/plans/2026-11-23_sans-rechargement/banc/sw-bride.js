// Essai : le bridage du réseau (Network.emulateNetworkConditions) appliqué aussi au service worker. Sans lui, un GET que
// le service worker relaie au serveur passe à la vitesse locale, quel que soit le profil de la page.
'use strict';
const { BASE, PROFILS, navigateur, reseau } = require('./commun');

async function briderSw(browser, profil) {
  const b = await browser.newBrowserCDPSession();
  const { targetInfos } = await b.send('Target.getTargets');
  const sws = targetInfos.filter((t) => t.type === 'service_worker');
  let n = 0;
  for (const t of sws) {
    const { sessionId } = await b.send('Target.attachToTarget', { targetId: t.targetId, flatten: false });
    const p = PROFILS[profil];
    const msg = (id, method, params) => b.send('Target.sendMessageToTarget', { sessionId, message: JSON.stringify({ id, method, params }) });
    await msg(1, 'Network.enable', {});
    await msg(2, 'Network.emulateNetworkConditions', p
      ? { offline: false, latency: p.latency, downloadThroughput: p.downloadThroughput, uploadThroughput: p.uploadThroughput }
      : { offline: false, latency: 0, downloadThroughput: -1, uploadThroughput: -1 });
    n++;
  }
  return { b, n, types: targetInfos.map((t) => t.type) };
}

(async () => {
  const { browser, contexte } = await navigateur();
  const p0 = await contexte.newPage();
  await p0.goto(BASE + '/', { waitUntil: 'load' });
  await p0.evaluate(() => navigator.serviceWorker.ready);
  await p0.goto(BASE + '/clients', { waitUntil: 'load' });
  await p0.close();
  const mesure = async (libelle) => {
    const page = await contexte.newPage();
    await reseau(page, 'chantier');
    await page.goto(BASE + '/', { waitUntil: 'load' });
    const t0 = Date.now();
    await page.goto(BASE + '/clients', { waitUntil: 'load' });
    const t1 = Date.now() - t0;
    const t2 = await page.evaluate(async () => { const t = performance.now(); await fetch('/catalogue', { headers: { Accept: 'text/html' } }); return Math.round(performance.now() - t); });
    console.log(libelle, ': /clients ouverte en', t1, 'ms ; fetch /catalogue', t2, 'ms');
    await page.close();
  };
  await mesure('page bridée, service worker libre');
  const r = await briderSw(browser, 'chantier');
  console.log('cibles :', r.types.join(', '), '; service workers bridés :', r.n);
  await mesure('page et service worker bridés');
  await browser.close();
})().catch((e) => { console.error(e); process.exit(1); });
