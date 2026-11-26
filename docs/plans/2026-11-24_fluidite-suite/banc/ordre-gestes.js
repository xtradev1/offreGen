// I2 (revue du 2026-11-24) : quantité 5 rangée sans réseau ; réseau revenu, l'offre rouverte sur un réseau de chantier,
// 7 tapé sur la même ligne pendant son chargement. Attendu : 5 part d'abord, puis 7 ; la base et l'écran finissent à 7.
'use strict';
const { execSync } = require('child_process');
const { BASE, navigateur, reseau } = require('./commun');
const sql = (q) => String(execSync('wsl -d Ubuntu -u root -e mariadb -N offregen_fluidite_vue -e "' + q + '"', { stdio: 'pipe' })).trim();
const remettre = () => execSync('wsl -d Ubuntu -u root -e sh /home/xavie/offreGen-fluidite-banc/base.sh remettre', { stdio: 'pipe' });
(async () => {
  remettre();
  const { browser, contexte } = await navigateur();
  const page = await contexte.newPage();
  const posts = [];
  page.on('request', (r) => { if (r.method() === 'POST') posts.push(r.url().replace(BASE, '') + ' ' + (r.postData() || '').replace(/_csrf=[^&]*&?/, '')); });
  for (const a of ['/', '/plus', '/offres/1']) { await page.goto(BASE + a, { waitUntil: 'load' }); await page.waitForTimeout(1500); }
  const id = (await page.locator('#lignes .row:has(input[name=quantite])').first().getAttribute('id')).slice(1);
  await contexte.setOffline(true);
  const c = page.locator('#l' + id + ' input[name=quantite]');
  await c.tap(); await c.fill('5'); await c.press('Enter');
  await page.waitForTimeout(600);
  await page.goto(BASE + '/plus', { waitUntil: 'load' }).catch(() => {});
  await page.waitForTimeout(800);
  await page.evaluate(() => { window.stop(); });
  // Réseau revenu, mais la page « Plus » ne pompe pas (navigation aussitôt) : l'offre rouverte sur le chantier.
  await contexte.setOffline(false);
  await reseau(page, 'chantier');
  await page.goto(BASE + '/offres/1', { waitUntil: 'commit' });
  const champ = page.locator('#l' + id + ' input[name=quantite]');
  await champ.waitFor({ state: 'visible' });
  const pendant = await page.evaluate(() => document.readyState);
  await champ.tap(); await champ.fill('7'); await champ.press('Enter');
  await page.waitForFunction(() => window.fluide && !window.fluide.occupe() && document.readyState === 'complete', null, { timeout: 60000 });
  await page.waitForTimeout(1500);
  console.log('7 tapé pendant :', pendant, '| écran :', await champ.inputValue(), '| base (millièmes) :', sql(`select quantite_milliemes from ligne where id = ${id}`));
  console.log('POST dans l’ordre :', posts.filter((p) => p.includes('/lignes/')).join(' ; '));
  await browser.close();
  remettre();
})().catch((e) => { console.error(e); try { remettre(); } catch (x) { /* */ } process.exit(1); });
