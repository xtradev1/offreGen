// « Parler » de bout en bout : le micro de la barre, 28 s de dictée de peintre (faux micro de Chromium), « Terminer »,
// envoi, suivi, offre ouverte. Usage : node dicter.js <profil>. Vraie transcription et vraie extraction (Infomaniak).
'use strict';
const fs = require('fs');
const path = require('path');
const { BASE, ICI, CAPTURES, navigateur, reseau, compteur } = require('./commun');

const profil = process.argv[2] || 'fibre';
const WAV = path.join(ICI, 'dictee-peintre.wav').replace(/\\/g, '/');

(async () => {
  const { browser, contexte } = await navigateur({ args: ['--use-fake-ui-for-media-stream', '--use-fake-device-for-media-stream', `--use-file-for-fake-audio-capture=${WAV}%noloop`] });
  await contexte.grantPermissions(['microphone'], { origin: BASE });
  const page = await contexte.newPage();
  page.on('dialog', (d) => d.accept());
  await reseau(page, profil);
  await page.goto(BASE + '/', { waitUntil: 'load' });
  await page.waitForTimeout(500);
  const c = compteur(page);
  const marques = {};
  const t0 = Date.now();
  await page.locator('[data-enregistrer]:visible').first().click();
  await page.waitForSelector('[data-terminer]:visible', { timeout: 15000 });
  await page.waitForTimeout(29000);
  await page.screenshot({ path: path.join(CAPTURES, `dictee-${profil}-enregistrement.png`) });
  c.debut();
  const tTerminer = Date.now();
  const envoi = page.waitForResponse((r) => r.url().endsWith('/import') && r.request().method() === 'POST', { timeout: 120000 });
  await page.click('[data-terminer]');
  const rEnvoi = await envoi;
  marques.envoi = Date.now() - tTerminer;
  await page.screenshot({ path: path.join(CAPTURES, `dictee-${profil}-suivi.png`) });
  await page.waitForURL(/\/offres\/\d+/, { timeout: 300000 });
  marques.offrePrete = Date.now() - tTerminer;
  await page.waitForLoadState('load');
  marques.offreAffichee = Date.now() - tTerminer;
  const m = await c.fin();
  await page.screenshot({ path: path.join(CAPTURES, `dictee-${profil}-offre.png`) });
  const etats = m.detail.filter((d) => d.url.includes('/etat'));
  const lignes = await page.evaluate(() => document.querySelectorAll('.rows .row').length);
  const r = { profil, statutEnvoi: rEnvoi.status(), ...marques, lignes, requetes: m.requetes, octets: m.octets,
    envoiOctets: m.detail.filter((d) => d.url === '/import').reduce((s, d) => s + d.octets, 0),
    suivis: etats.length, suiviOctets: etats.reduce((s, d) => s + d.octets, 0), offre: page.url().replace(BASE, ''),
    detail: m.detail.filter((d) => !d.url.includes('/etat')) };
  fs.appendFileSync(path.join(ICI, 'dictees.jsonl'), JSON.stringify(r) + '\n');
  console.log(JSON.stringify({ ...r, detail: r.detail.map((d) => `${d.methode} ${d.url} ${d.octets} o ${d.attente} ms`) }, null, 1));
  await browser.close();
})().catch((e) => { console.error(e); process.exit(1); });
