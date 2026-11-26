// Captures « proposé » de la maquette du chantier fluidité, suite (2026-11-24) : l'application réelle du banc, l'écran
// retouché dans la page (rien n'est enregistré) pour montrer la proposition. Téléphone 390 px.
'use strict';
const path = require('path');
const { execSync } = require('child_process');
const { BASE, CAPTURES, SESSION, navigateur: nav0 } = require('./commun');
const navigateur = async () => { const { browser, contexte } = await nav0(); await contexte.close(); const c = await browser.newContext({ viewport: { width: 390, height: 844 }, deviceScaleFactor: 2, isMobile: true, hasTouch: true, locale: 'fr-CH', storageState: SESSION, bypassCSP: true }); return { browser, contexte: c }; };

const sql = (q) => execSync('wsl -d Ubuntu -u root -e mariadb offregen_fluidite_vue -e "' + q + '"', { stdio: 'pipe' });
const remettre = () => execSync('wsl -d Ubuntu -u root -e sh /home/xavie/offreGen-fluidite-banc/base.sh remettre', { stdio: 'pipe' });
const NB = ' ';
const cap = (page, nom) => page.screenshot({ path: path.join(CAPTURES, nom + '.png') });

(async () => {
  remettre();
  sql("update offre set client_id = NULL, client_dicte = 'Madame Gerber-Monin', lieu_dicte = 'Rue du Lac 12, Neuchâtel' where id = 2");
  const { browser, contexte } = await navigateur();
  await contexte.addInitScript(() => { navigator.canShare = () => true; navigator.share = async () => {}; });
  const page = await contexte.newPage();
  page.on('dialog', (d) => d.dismiss());

  // MP-A19, B : le nom entendu devient un champ, dans le cadre jaune.
  await page.goto(BASE + '/offres/2', { waitUntil: 'load' });
  await page.waitForTimeout(1500);
  await page.locator('a.btn.primary.grand:has-text("Générer l\'offre")').tap();
  await page.waitForSelector('.feuille .dicte', { state: 'visible' });
  await page.waitForTimeout(800);
  await page.evaluate((nb) => {
    const d = document.querySelector('.feuille .dicte');
    const p = d.querySelectorAll('p')[1];
    const champ = document.createElement('div');
    champ.className = 'champ';
    champ.innerHTML = '<label for="nom-dicte">Nom</label><input id="nom-dicte" value="Madame Gerber-Monin" autocomplete="off">'
      + '<p class="muted petit" style="margin-top:6px">Rue du Lac 12, 2000 Neuchâtel</p>';
    p.replaceWith(champ);
    d.querySelector('button.btn.primary').textContent = 'Créer la fiche et voir le PDF';
    d.querySelector('p.muted').textContent = 'Entendu dans la dictée' + nb + ': corrigez le nom s’il est mal écrit';
  }, NB);
  await cap(page, 'suite-a19-b');
  await page.evaluate(() => { const c = document.querySelector('#nom-dicte'); c.focus(); c.value = 'Madame Gerber-Monnin'; c.setSelectionRange(c.value.length, c.value.length); });
  await page.waitForTimeout(200);
  await cap(page, 'suite-a19-b-corrige');

  // MP-A19, A : « Créer la fiche » ouvre la fiche du client, remplie d'après la dictée.
  await page.goto(BASE + '/clients/nouveau?offre=2&suite=apercu', { waitUntil: 'load' });
  await page.waitForTimeout(1000);
  await page.evaluate((nb) => {
    const v = (sel, val) => { const c = document.querySelector(sel); if (c) c.value = val; };
    v('#nom', 'Madame Gerber-Monin'); v('[data-a=rue]', 'Rue du Lac'); v('[data-a=numero]', '12'); v('[data-a=npa]', '2000'); v('[data-a=localite]', 'Neuchâtel');
    const h = document.querySelector('header.bar h1'); if (h) h.textContent = 'Fiche du client';
    const form = document.querySelector('form#client');
    const aide = document.createElement('p');
    aide.className = 'alerte-maquette';
    aide.style.cssText = 'background:#FFF0B3;border:1px dashed #5A4300;border-radius:12px;padding:10px 12px;margin:0 0 12px';
    aide.textContent = 'Remplie d’après la dictée' + nb + ': corrigez le nom s’il est mal écrit, puis enregistrez.';
    form.prepend(aide);
    document.querySelectorAll('button[form="client"]').forEach((b) => { b.textContent = 'Enregistrer et voir le PDF'; });
    const nom = document.querySelector('#nom'); nom.focus(); nom.setSelectionRange(nom.value.length, nom.value.length);
  }, NB);
  await page.waitForTimeout(200);
  await cap(page, 'suite-a19-a');
  remettre();

  // MP-A14 : la fiche d'une ligne, ses pièces en puces.
  await page.goto(BASE + '/offres/3', { waitUntil: 'load' });
  await page.waitForTimeout(1500);
  await page.locator('#lignes .row a.nm').nth(4).tap();
  await page.waitForSelector('.feuille #piece', { state: 'visible', timeout: 15000 });
  await page.waitForTimeout(800);
  const pieces = await page.locator('.feuille #pieces-offre option').evaluateAll((o) => o.map((x) => x.value));
  await page.evaluate((liste) => {
    const champ = document.querySelector('.feuille #piece').closest('.champ');
    const f = document.createElement('fieldset');
    f.className = 'groupe choix';
    f.innerHTML = '<legend>Pièce</legend><div class="puces">' + [''].concat(liste).map((p, i) => '<label class="puce"><input type="radio" name="piece-m" value="' + p + '"' + (i === 0 ? ' checked' : '') + '>' + (p || 'Général') + '</label>').join('')
      + '</div><div class="champ" style="margin-top:10px"><input placeholder="Autre pièce" aria-label="Autre pièce" maxlength="80" autocomplete="off"></div>';
    champ.replaceWith(f);
    f.scrollIntoView({ block: 'center' });
  }, pieces);
  await page.waitForTimeout(300);
  await cap(page, 'suite-a14-a');
  await page.evaluate(() => {
    const r = [...document.querySelectorAll('.feuille input[name="piece-m"]')].find((x) => x.value === 'Chambre');
    if (r) r.checked = true;
  });
  await page.waitForTimeout(200);
  await cap(page, 'suite-a14-a-chambre');
  await browser.close();

  // MP-A14, B : le champ, et la liste des pièces ouverte au toucher.
  const b2 = await navigateur();
  const p2 = await b2.contexte.newPage();
  await p2.goto(BASE + '/offres/3', { waitUntil: 'load' });
  await p2.waitForTimeout(1500);
  await p2.locator('#lignes .row a.nm').nth(4).tap();
  await p2.waitForSelector('.feuille #piece', { state: 'visible', timeout: 15000 });
  await p2.waitForTimeout(800);
  await p2.evaluate((liste) => {
    const c = document.querySelector('.feuille #piece');
    c.removeAttribute('list');
    c.closest('.champ').scrollIntoView({ block: 'start' });
    const ul = document.createElement('ul');
    ul.style.cssText = 'list-style:none;margin:4px 0 0;padding:0;border:1px solid #999;border-radius:12px;background:#fff;box-shadow:0 6px 18px rgba(0,0,0,.15)';
    ul.innerHTML = ['Général'].concat(liste).map((p) => '<li style="padding:14px 16px;border-bottom:1px solid #eee;font-size:17px;list-style:none">' + p + '</li>').join('');
    c.after(ul);
  }, pieces);
  await p2.waitForTimeout(300);
  await p2.screenshot({ path: path.join(CAPTURES, 'suite-a14-b.png') });
  await b2.browser.close();

  remettre();
})().catch((e) => { console.error(e); try { remettre(); } catch (x) { /* */ } process.exit(1); });
