// Recette par l'interface de MP-A14 (pièces en puces dans la fiche d'une ligne) et MP-A19 (« Créer la fiche » ouvre la
// fiche remplie d'après la dictée), chantier fluidité suite (2026-11-24). Banc local, téléphone. Usage :
// node recette-suite.js [chromium|webkit]. Résultats : recette-suite-<moteur>.json, captures recette-suite-*.png.
'use strict';
const fs = require('fs');
const path = require('path');
const { execSync } = require('child_process');
const { pw, BASE, ICI, SESSION, CAPTURES, navigateur } = require('./commun');

const moteur = process.argv[2] || 'chromium';
const sql = (q) => String(execSync('wsl -d Ubuntu -u root -e mariadb -N offregen_fluidite_vue -e "' + q + '"', { stdio: 'pipe' })).trim();
const remettre = () => execSync('wsl -d Ubuntu -u root -e sh /home/xavie/offreGen-fluidite-banc/base.sh remettre', { stdio: 'pipe' });
const res = [];
const noter = (cle, valeur) => { res.push({ cle, valeur }); console.log(cle.padEnd(52), typeof valeur === 'string' ? valeur : JSON.stringify(valeur)); };
const cap = (page, nom) => page.screenshot({ path: path.join(CAPTURES, `recette-suite-${moteur}-${nom}.png`) });

(async () => {
  remettre();
  let browser; let contexte;
  if (moteur === 'webkit') {
    const e = JSON.parse(fs.readFileSync(SESSION, 'utf8'));
    e.cookies.forEach((c) => { c.secure = false; });
    browser = await pw.webkit.launch({ headless: true });
    contexte = await browser.newContext({ ...pw.devices['iPhone 13'], locale: 'fr-CH', storageState: e });
  } else {
    ({ browser, contexte } = await navigateur());
  }
  const page = await contexte.newPage();
  const erreurs = [];
  page.on('pageerror', (e) => erreurs.push(String(e.message)));
  const origine = () => page.evaluate(() => performance.timeOrigin);

  // MP-A14 : une ligne du Général passe dans la Chambre par sa puce.
  await page.goto(BASE + '/offres/3', { waitUntil: 'load' });
  await page.waitForTimeout(1500);
  const ligne = await page.locator('#lignes .row:has(a.nm:text-is("Résine, escalier"))').first().getAttribute('id');
  const id = ligne.slice(1);
  noter('A14 pièce avant (base)', sql(`select ifnull(piece,'Général') from ligne where id = ${id}`));
  const o1 = await origine();
  await page.locator('#' + ligne + ' a.nm').tap();
  await page.waitForSelector('.feuille [data-pieces]', { state: 'visible', timeout: 15000 });
  await page.locator('.feuille [data-pieces]').scrollIntoViewIfNeeded();
  noter('A14 puces proposées', await page.locator('.feuille [data-pieces] label.puce').allTextContents());
  noter('A14 puce cochée à l’ouverture', await page.locator('.feuille [data-pieces] label.puce:has(input:checked)').textContent());
  await page.locator('.feuille [data-pieces] label.puce:has-text("Chambre")').tap();
  await page.waitForTimeout(200);
  await cap(page, 'a14-chambre');
  await page.locator('.feuille button[type=submit]:has-text("Enregistrer")').last().tap();
  await page.waitForFunction(() => !document.querySelector('.feuille') || document.querySelector('.feuille').offsetParent === null, null, { timeout: 15000 }).catch(() => {});
  await page.waitForFunction(() => window.fluide && !window.fluide.occupe(), null, { timeout: 15000 });
  await page.waitForTimeout(500);
  noter('A14 pièce après (base)', sql(`select ifnull(piece,'Général') from ligne where id = ${id}`));
  noter('A14 groupe de la ligne à l’écran', await page.evaluate((lid) => { let t = document.getElementById(lid); while (t && !t.classList.contains('cat')) t = t.previousElementSibling; return t ? t.textContent.replace(/\s+/g, ' ').trim() : null; }, ligne));
  noter('A14 page rechargée', (await origine()) !== o1);
  await page.locator('#' + ligne).scrollIntoViewIfNeeded();
  await cap(page, 'a14-offre');

  // « Autre pièce » tapée : les puces se décochent, la ligne part dans une pièce neuve.
  await page.locator('#' + ligne + ' a.nm').tap();
  await page.waitForSelector('.feuille [data-pieces]', { state: 'visible', timeout: 15000 });
  const autre = page.locator('.feuille [data-pieces] input[name="piece_autre"]');
  await autre.scrollIntoViewIfNeeded();
  await autre.tap();
  await autre.fill('bureau');
  noter('A14 puces cochées après « bureau » tapé', await page.locator('.feuille [data-pieces] input[name="piece"]:checked').count());
  await cap(page, 'a14-autre');
  await page.locator('.feuille button[type=submit]:has-text("Enregistrer")').last().tap();
  await page.waitForFunction(() => window.fluide && !window.fluide.occupe(), null, { timeout: 15000 });
  await page.waitForTimeout(800);
  noter('A14 pièce après « bureau » (base)', sql(`select ifnull(piece,'Général') from ligne where id = ${id}`));

  // MP-A19 : offre sans client, nom entendu mal écrit.
  remettre();
  sql("update offre set client_id = NULL, client_dicte = 'Madame Gerber-Monin', lieu_dicte = 'Rue du Lac 12, Neuchâtel' where id = 2");
  await page.goto(BASE + '/offres/2', { waitUntil: 'load' });
  await page.waitForTimeout(1500);
  await page.locator('a.btn.primary.grand:has-text("Générer l\'offre")').tap();
  await page.waitForSelector('.feuille .dicte a.btn.primary', { state: 'visible', timeout: 15000 });
  const o2 = await origine();
  await page.locator('.feuille .dicte a.btn.primary').tap();
  await page.waitForSelector('.feuille form#client #nom', { state: 'visible', timeout: 15000 });
  await page.waitForTimeout(500);
  noter('A19 fiche dans la fenêtre, sans page entière', (await origine()) === o2);
  noter('A19 nom rempli', await page.locator('.feuille #nom').inputValue());
  noter('A19 adresse remplie', await page.evaluate(() => ['rue', 'numero', 'npa', 'localite'].map((k) => (document.querySelector('.feuille [data-a="' + k + '"]') || {}).value).join(' | ')));
  noter('A19 bandeau', ((await page.locator('.feuille .dicte').textContent()) || '').trim());
  noter('A19 bouton', ((await page.locator('.feuille button[type=submit][form="client"]').textContent()) || '').trim());
  noter('A19 fiches en base avant l’enregistrement', sql("select count(*) from client where nom like 'Madame Gerber%'"));
  await cap(page, 'a19-fiche');
  await page.locator('.feuille #nom').fill('Madame Gerber-Monnin');
  await page.locator('.feuille button[type=submit][form="client"]').tap();
  await page.waitForURL(/\/offres\/2\/apercu/, { timeout: 30000 });
  await page.waitForLoadState('load');
  await page.waitForTimeout(2500);
  noter('A19 après « Enregistrer et voir le PDF »', new URL(page.url()).pathname);
  noter('A19 client de l’offre (base)', sql('select c.nom from client c join offre o on o.client_id = c.id where o.id = 2'));
  await cap(page, 'a19-pdf');
  noter('erreurs JavaScript', erreurs);
  fs.writeFileSync(path.join(ICI, `recette-suite-${moteur}.json`), JSON.stringify(res, null, 1));
  await browser.close();
  remettre();
})().catch((e) => { console.error(e); try { remettre(); } catch (x) { /* */ } process.exit(1); });
