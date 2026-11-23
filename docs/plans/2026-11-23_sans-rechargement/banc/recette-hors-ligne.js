// Recette du lot 4 par l'interface : gestes sans réseau, pages gardées, retour du réseau, une seule fois en base,
// session perdue, déconnexion. Usage : node recette-hors-ligne.js. Résultats : recette-hors-ligne.json, captures.
'use strict';
const fs = require('fs');
const path = require('path');
const { execSync } = require('child_process');
const { BASE, CAPTURES, navigateur, reseau, connecter } = require('./commun');

const remettre = () => execSync('wsl -d Ubuntu -u root -e sh /home/xavie/offreGen-fluidite-banc/base.sh remettre', { stdio: 'pipe' });
// Une vraie coupure : le serveur du banc s'arrête (le téléphone se croit connecté, rien ne répond : le pire cas réel).
const couper = () => execSync('wsl -d Ubuntu -e sh -c "kill $(cat /home/xavie/offreGen-fluidite-banc/serveur.pid)"', { stdio: 'pipe' });
const rallumer = () => execSync('wsl -d Ubuntu -e sh /home/xavie/offreGen-fluidite-banc/serveur.sh apres', { stdio: 'pipe' });
const sql = (requete) => execSync('wsl -d Ubuntu -u root -e mariadb -N offregen_fluidite_vue -e "' + requete + '"', { encoding: 'utf8' }).trim();
const constats = [];
const noter = (cle, valeur) => { constats.push({ cle, valeur }); console.log(cle.padEnd(44), typeof valeur === 'string' ? valeur : JSON.stringify(valeur)); };
const photo = (page, nom) => page.screenshot({ path: path.join(CAPTURES, 'hors-ligne-apres-' + nom + '.png') });
const calme = (page) => page.waitForFunction(() => window.fluide && !window.fluide.occupe(), null, { timeout: 60000 });
const bandeau = (page) => page.evaluate(() => { const b = document.querySelector('.reseau'); return b && !b.hidden ? b.textContent.trim() : ''; });

(async () => {
  remettre();
  const { browser, contexte } = await navigateur();
  const erreurs = [];
  contexte.on('page', (p) => p.on('pageerror', (e) => erreurs.push(e.message)));

  // 1. Avec du réseau : la liste, le service worker, ce que le téléphone garde d'avance (D6).
  let page = await contexte.newPage();
  await reseau(page, '4g');
  await page.goto(BASE + '/', { waitUntil: 'load' });
  await page.evaluate(() => navigator.serviceWorker.ready.then(() => true));
  await page.waitForFunction(() => !!navigator.serviceWorker.controller, null, { timeout: 15000 }).catch(() => {});
  if (!(await page.evaluate(() => !!navigator.serviceWorker.controller))) await page.reload({ waitUntil: 'load' });
  noter('service worker aux commandes', await page.evaluate(() => !!navigator.serviceWorker.controller));
  let gardees = [];
  for (let i = 0; i < 90; i++) {
    gardees = await page.evaluate(async () => {
      const noms = (await caches.keys()).filter((n) => n.startsWith('offregen-pages-'));
      if (!noms.length) return [];
      return (await (await caches.open(noms[0])).keys()).map((k) => new URL(k.url).pathname);
    });
    if (gardees.includes('/catalogue') && gardees.filter((c) => /^\/offres\/\d+$/.test(c)).length >= 2) break;
    await page.waitForTimeout(1000);
  }
  noter('pages gardées d\'avance', gardees.sort());

  // 2. L'offre ouverte avec du réseau ; « + Ligne » se prépare.
  await page.goto(BASE + '/offres/3', { waitUntil: 'load' });
  await page.waitForTimeout(5000);
  const avantLignes = Number(sql('SELECT COUNT(*) FROM ligne WHERE offre_id = 3 AND supprimee_le IS NULL'));
  const flag = await page.locator('#lignes .row.flag').first().getAttribute('id');
  const q = await page.locator('#lignes .row:not(.flag):not(.miss)').nth(6).getAttribute('id');

  // 3. Plus de réseau : trois gestes.
  couper();
  await page.click('#' + flag + ' form.note.warn button');
  await page.waitForTimeout(600);
  noter('« C\'est juste » sans réseau : étiquette partie', await page.evaluate((id) => !document.getElementById(id).classList.contains('flag'), flag));
  noter('bandeau après un geste', await bandeau(page));
  await page.fill('#' + q + ' input[name=quantite]', '9');
  await page.locator('#' + q + ' input[name=quantite]').press('Tab');
  await page.waitForTimeout(600);
  noter('montant estimé de la ligne', await page.evaluate((id) => document.querySelector('#' + id + ' .tt').textContent + (document.querySelector('#' + id + ' .tt').classList.contains('provisoire') ? ' (provisoire)' : ''), q));
  noter('total estimé', await page.evaluate(() => document.querySelector('#total-fixe b').textContent + (document.querySelector('#total-fixe b').classList.contains('provisoire') ? ' (provisoire)' : '')));
  await page.click('a.btn.ajout');
  await page.waitForSelector('[data-feuille-page].ouvert #res', { timeout: 10000 });
  const produit = await page.locator('[data-feuille-page] #res form:not([hidden]) button.pick').first().evaluate((b) => b.querySelector('.pn').textContent.trim());
  await page.locator('[data-feuille-page] #res form:not([hidden]) button.pick').first().click();
  await page.waitForTimeout(600);
  noter('« + Ligne » sans réseau : fenêtre ouverte', true);
  noter('ligne provisoire dans l\'offre', await page.evaluate(() => { const r = document.querySelector('#lignes .row.en-attente'); return r ? r.textContent.replace(/\s+/g, ' ').trim() : 'ABSENTE'; }));
  await page.click('[data-feuille-page] .sh-pied a.btn.primary');
  await page.waitForTimeout(500);
  noter('bandeau après trois gestes', await bandeau(page));
  await photo(page, 'trois-gestes');
  noter('en base pendant la coupure : lignes', Number(sql('SELECT COUNT(*) FROM ligne WHERE offre_id = 3 AND supprimee_le IS NULL')) - avantLignes);

  // 4. Toujours sans réseau : la liste et une autre offre, servies par le téléphone.
  await page.goto(BASE + '/', { waitUntil: 'load' }).catch((e) => noter('liste hors réseau : erreur', e.message));
  noter('liste sans réseau : page gardée', await page.evaluate(() => (document.body.dataset.gardeLe ? 'oui, ' : 'non, ') + document.title));
  noter('bandeau de la liste', await bandeau(page));
  await photo(page, 'liste');
  const autre = gardees.find((c) => /^\/offres\/\d+$/.test(c) && c !== '/offres/3') || '/offres/2';
  await page.goto(BASE + autre, { waitUntil: 'load' }).catch(() => {});
  noter('autre offre sans réseau (' + autre + ')', await page.evaluate(() => (document.body.dataset.gardeLe ? 'gardée, ' : 'NON gardée, ') + document.title
    + (window.fluide ? ', scripts là' : ', SANS scripts') + (getComputedStyle(document.body).fontFamily.includes('Inter') ? ', style là' : ', style ' + getComputedStyle(document.body).fontFamily)));
  noter('bandeau de l\'autre offre', await bandeau(page));
  await photo(page, 'autre-offre');
  await page.goto(BASE + '/offres/999999', { waitUntil: 'load' }).catch(() => {});
  noter('page jamais vue sans réseau', await page.evaluate(() => document.title + ' : ' + (document.querySelector('main') ? document.querySelector('main').textContent.trim().slice(0, 80) : '')));
  const t3 = Date.now();
  await page.goto(BASE + '/offres/3', { waitUntil: 'domcontentloaded', timeout: 40000 }).catch((e) => noter('offre 3 rouverte : erreur', e.message.split('\n')[0]));
  noter('offre 3 rouverte : affichée après (ms)', Date.now() - t3);
  await page.waitForTimeout(800);
  noter('offre 3 rouverte : état', await page.evaluate(() => ({ titre: document.title, etat: document.readyState, gardee: !!document.body.dataset.gardeLe, scripts: !!window.fluide, enLigne: navigator.onLine,
    file: window.fluide ? window.fluide.file.length : null })));
  noter('offre 3 rouverte sans réseau : bandeau', await bandeau(page));
  await photo(page, 'offre-rouverte');
  await page.waitForTimeout(26000);
  noter('offre 3, 27 s plus tard : bandeau', await bandeau(page));

  // 5. Le réseau revient : les gestes partent, une seule fois.
  rallumer();
  await page.evaluate(() => window.dispatchEvent(new Event('online')));
  await calme(page);
  await page.waitForTimeout(800);
  const apres = {
    etiquette: sql("SELECT a_verifier FROM ligne WHERE id = " + flag.slice(1)),
    quantite: sql('SELECT quantite_milliemes FROM ligne WHERE id = ' + q.slice(1)),
    lignes_en_plus: Number(sql('SELECT COUNT(*) FROM ligne WHERE offre_id = 3 AND supprimee_le IS NULL')) - avantLignes,
    produit_ajoute: sql("SELECT COUNT(*) FROM ligne WHERE offre_id = 3 AND supprimee_le IS NULL AND designation = '" + produit.replace(/'/g, "''") + "'"),
    cles: sql('SELECT COUNT(*) FROM envoi'),
  };
  noter('en base au retour du réseau', apres);
  await page.reload({ waitUntil: 'load' });
  await calme(page);
  await page.waitForTimeout(1500);
  noter('après rechargement : lignes en plus (1 attendue)', Number(sql('SELECT COUNT(*) FROM ligne WHERE offre_id = 3 AND supprimee_le IS NULL')) - avantLignes);
  noter('file vide dans le téléphone', await page.evaluate(() => new Promise((ok) => { const r = indexedDB.open('offregen-fluide'); r.onsuccess = () => { const t = r.result.transaction('envois').objectStore('envois').count(); t.onsuccess = () => ok(t.result); }; r.onerror = () => ok('base absente'); })));
  await photo(page, 'retour-reseau');

  // 6. Session perdue pendant un geste : le geste attend la reconnexion, rien n'est perdu.
  const session = (await contexte.cookies()).find((c) => c.name === 'offregen');
  await contexte.clearCookies();
  const r2 = await page.locator('#lignes .row:not(.flag):not(.miss)').nth(9).getAttribute('id');
  await page.fill('#' + r2 + ' input[name=quantite]', '4');
  await page.locator('#' + r2 + ' input[name=quantite]').press('Tab');
  await page.waitForTimeout(1500);
  noter('session perdue : bandeau', await bandeau(page));
  noter('session perdue : en base', sql('SELECT quantite_milliemes FROM ligne WHERE id = ' + r2.slice(1)));
  await contexte.addCookies([session]);
  await page.goto(BASE + '/offres/3', { waitUntil: 'load' });
  await calme(page);
  await page.waitForTimeout(800);
  noter('session revenue : en base (4000 attendu)', sql('SELECT quantite_milliemes FROM ligne WHERE id = ' + r2.slice(1)));

  // 7. Déconnexion : les pages gardées et la file partent.
  await page.goto(BASE + '/plus', { waitUntil: 'load' });
  await page.locator('form[action="/deconnexion"] button').first().click();
  await page.waitForURL((u) => u.pathname.startsWith('/connexion'), { timeout: 15000 });
  await page.waitForTimeout(1500);
  noter('après déconnexion : caches de pages', await page.evaluate(async () => (await caches.keys()).filter((n) => n.startsWith('offregen-pages-'))));
  noter('après déconnexion : base du téléphone', await page.evaluate(() => indexedDB.databases ? indexedDB.databases().then((l) => l.map((d) => d.name)) : 'inconnu'));
  noter('erreurs JavaScript', erreurs);
  await browser.close();
  fs.writeFileSync(path.join(__dirname, 'recette-hors-ligne.json'), JSON.stringify(constats, null, 1));
  await connecter();
})().catch((e) => { console.error(e); process.exit(1); });
