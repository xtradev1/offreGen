// D4 : le préchargement passe après l'artisan. Un téléphone neuf ouvre la liste (le préchargement part, une page à la
// fois) ; dès sa première page, l'écran fait une requête toutes les 0,7 s pendant 6 s. Attendu : aucune page préchargée
// ne part pendant ce temps (hors celle déjà en vol), puis le préchargement reprend 3 s après la dernière requête.
// Usage : node verifier-prechargement.js. Sortie : preuve-prechargement.json.
'use strict';
const fs = require('fs');
const path = require('path');
const { execSync } = require('child_process');
const { BASE, navigateur, reseau } = require('./commun');

const remettre = () => execSync('wsl -d Ubuntu -u root -e sh /home/xavie/offreGen-fluidite-banc/base.sh remettre', { stdio: 'pipe' });

(async () => {
  remettre();
  const { browser, contexte } = await navigateur();
  const t0 = Date.now();
  const prechargees = [];
  contexte.on('request', (r) => {
    const h = r.headers();
    if (r.serviceWorker() && h['x-version-garde'] !== undefined) prechargees.push({ t: Date.now() - t0, url: r.url().replace(BASE, '') });
  });
  const page = await contexte.newPage();
  await reseau(page, '4g');
  await page.goto(BASE + '/', { waitUntil: 'load' });
  await page.evaluate(() => navigator.serviceWorker.ready.then(() => true));
  if (!(await page.evaluate(() => !!navigator.serviceWorker.controller))) await page.reload({ waitUntil: 'load' });
  // Le préchargement part quand l'écran est calme (au plus une fois par heure) : on attend sa première page.
  const debutAttente = Date.now();
  while (!prechargees.length && Date.now() - debutAttente < 30000) await page.waitForTimeout(100);
  if (!prechargees.length) throw new Error('le préchargement n\'est pas parti en 30 s');
  const activiteDebut = Date.now() - t0;
  let activiteFin = activiteDebut;
  for (let i = 0; i < 9; i++) {
    activiteFin = Date.now() - t0; // la requête part maintenant : le service worker la voit dans la foulée
    await page.evaluate(() => fetch('/sante', { cache: 'no-store' }).then(() => true, () => false));
    await page.waitForTimeout(700);
  }
  await page.waitForTimeout(12000);
  await browser.close();

  // Calme exigé : 3 s après la dernière requête de l'écran (200 ms de marge pour l'écart d'horloge page / service worker).
  const pendant = prechargees.filter((p) => p.t > activiteDebut + 300 && p.t < activiteFin + 2800);
  const reprise = prechargees.find((p) => p.t >= activiteFin + 2800);
  const r = {
    prechargees,
    activite: [activiteDebut, activiteFin],
    partiesPendantLActivite: pendant.length,
    reprise: reprise ? reprise.t - activiteFin : null,
    ok: pendant.length === 0 && !!reprise,
  };
  r.texte = r.ok
    ? `Téléphone neuf, réseau 4G simulé : le préchargement part (${prechargees[0].url} à ${(prechargees[0].t / 1000).toFixed(1).replace('.', ',')} s) ; l'écran fait alors une requête toutes les 0,7 s pendant ${((activiteFin - activiteDebut) / 1000).toFixed(1).replace('.', ',')} s : aucune page préchargée ne part pendant ce temps ; la suivante part ${(r.reprise / 1000).toFixed(1).replace('.', ',')} s après la dernière requête de l'écran. ${prechargees.length} pages préchargées en tout.`
    : `Échec : ${pendant.length} page(s) préchargée(s) pendant l'activité de l'écran ; reprise : ${r.reprise}.`;
  fs.writeFileSync(path.join(__dirname, 'preuve-prechargement.json'), JSON.stringify(r, null, 1));
  console.log(JSON.stringify(r, null, 1));
})().catch((e) => { console.error(e); process.exit(1); });
