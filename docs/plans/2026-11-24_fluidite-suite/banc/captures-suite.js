// Captures « avant » de la maquette du chantier fluidité, suite (2026-11-24) : « Pour qui est cette offre ? »
// (MP-A19) et la fiche d'une ligne avec son champ « Pièce » (MP-A14). Banc local, téléphone 390 px.
'use strict';
const path = require('path');
const { execSync } = require('child_process');
const { BASE, CAPTURES, navigateur } = require('./commun');

const sql = (q) => execSync('wsl -d Ubuntu -u root -e mariadb offregen_fluidite_vue -e "' + q + '"', { stdio: 'pipe' });
const remettre = () => execSync('wsl -d Ubuntu -u root -e sh /home/xavie/offreGen-fluidite-banc/base.sh remettre', { stdio: 'pipe' });

(async () => {
  remettre();
  // Une offre sans client, avec le nom entendu dans la dictée (mal écrit, comme l'IA le fait).
  sql("update offre set client_id = NULL, client_dicte = 'Madame Gerber-Monin', lieu_dicte = 'Rue du Lac 12, Neuchâtel' where id = 2");
  const { browser, contexte } = await navigateur();
  const page = await contexte.newPage();
  await page.goto(BASE + '/offres/2', { waitUntil: 'load' });
  await page.waitForTimeout(1500);
  await page.locator('a.btn.primary.grand:has-text("Générer l\'offre")').tap();
  await page.waitForTimeout(1500);
  await page.screenshot({ path: path.join(CAPTURES, 'suite-a19-pour-qui.png') });

  await page.goto(BASE + '/offres/3', { waitUntil: 'load' });
  await page.waitForTimeout(1500);
  await page.locator('#lignes .row a.nm').nth(4).tap();
  await page.waitForSelector('.feuille #piece', { state: 'visible', timeout: 15000 });
  await page.waitForTimeout(800);
  await page.locator('.feuille #piece').scrollIntoViewIfNeeded();
  await page.waitForTimeout(300);
  await page.screenshot({ path: path.join(CAPTURES, 'suite-a14-fiche-ligne.png') });
  await page.locator('.feuille #piece').tap();
  await page.waitForTimeout(800);
  await page.screenshot({ path: path.join(CAPTURES, 'suite-a14-piece-touchee.png') });
  const valeur = await page.locator('.feuille #piece').inputValue();
  const options = await page.locator('.feuille #pieces-offre option').evaluateAll((o) => o.map((x) => x.value));
  console.log('pièce de la ligne :', valeur, '| propositions :', options.join(', '));
  // Fermer sans rien enregistrer.
  await browser.close();
  remettre();
})().catch((e) => { console.error(e); remettre(); process.exit(1); });
