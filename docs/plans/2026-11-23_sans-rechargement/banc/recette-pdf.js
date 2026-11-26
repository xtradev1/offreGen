// Recette du lot 5 par l'interface : aperçu du PDF (première et deuxième ouverture), « Envoyer » avec un partage simulé
// (le partage du téléphone n'existe pas dans Chromium sans tête) : aucun PDF redemandé, envoi noté sans rechargement.
// Usage : node recette-pdf.js [profil]
'use strict';
const path = require('path');
const { execSync } = require('child_process');
const { BASE, CAPTURES, navigateur, reseau, compteur } = require('./commun');

const profil = process.argv[2] || 'chantier';
const remettre = () => execSync('wsl -d Ubuntu -u root -e sh /home/xavie/offreGen-fluidite-banc/base.sh remettre', { stdio: 'pipe' });
const sql = (r) => execSync('wsl -d Ubuntu -u root -e mariadb -N offregen_fluidite_vue -e "' + r + '"', { encoding: 'utf8' }).trim();

(async () => {
  remettre();
  const { browser, contexte } = await navigateur();
  await contexte.addInitScript(() => {
    navigator.canShare = () => true;
    navigator.share = async () => { window.__partage = (window.__partage || 0) + 1; };
  });
  const erreurs = [];
  const p0 = await contexte.newPage();
  await p0.goto(BASE + '/', { waitUntil: 'load' });
  await p0.close();
  for (const passe of ['premiere', 'deuxieme']) {
    const page = await contexte.newPage();
    page.on('pageerror', (e) => erreurs.push(e.message));
    await reseau(page, profil);
    const c = compteur(page);
    c.debut();
    const t0 = Date.now();
    await page.goto(BASE + '/offres/2/apercu', { waitUntil: 'commit' });
    await page.waitForSelector('[data-pdf] canvas', { timeout: 90000 });
    const vu = Date.now() - t0;
    const m = await c.fin();
    const pdf = m.detail.filter((d) => /\/pdf$/.test(d.url));
    console.log(`aperçu ${passe.padEnd(9)} PDF dessiné après ${String(vu).padStart(6)} ms  ${m.requetes} req ${Math.round(m.octets / 1024)} Ko  PDF : ${pdf.map((d) => d.octets + ' octets').join(', ')}`);
    if (passe === 'deuxieme') {
      const origine = await page.evaluate(() => performance.timeOrigin);
      const c2 = compteur(page);
      c2.debut();
      await page.click('[data-partager]');
      await page.waitForFunction(() => window.__partage === 1, null, { timeout: 10000 });
      await page.waitForFunction(() => !window.fluide || !window.fluide.occupe(), null, { timeout: 30000 });
      await page.waitForTimeout(500);
      const m2 = await c2.fin();
      const recharge = await page.evaluate((o) => performance.timeOrigin !== o, origine);
      console.log(`« Envoyer »        ${m2.requetes} req (${m2.detail.map((d) => d.methode + ' ' + d.url).join(' ; ')}) ${recharge ? 'RECHARGÉE' : 'sans rechargement'}`);
      console.log('  message à l\'écran :', await page.evaluate(() => [...document.querySelectorAll('.app > .snack')].map((s) => s.textContent.trim()).join(' | ')));
      console.log('  en base, envoyée le :', sql('SELECT envoyee_le FROM offre WHERE id = 2'));
      await page.screenshot({ path: path.join(CAPTURES, 'recette-pdf-envoyer.png') });
    }
    await page.close();
  }
  console.log(erreurs.length ? 'ERREURS JS : ' + erreurs.join(' | ') : 'aucune erreur JavaScript');
  await browser.close();
})().catch((e) => { console.error(e); process.exit(1); });
