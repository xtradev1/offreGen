// Recette par l'interface des défauts trouvés à la revue du 2026-11-23 (chantier sans rechargement), une fois corrigés.
'use strict';
const path = require('path');
const { execSync } = require('child_process');
const { BASE, CAPTURES, navigateur, reseau } = require('./commun');

const remettre = () => execSync('wsl -d Ubuntu -u root -e sh /home/xavie/offreGen-fluidite-banc/base.sh remettre', { stdio: 'pipe' });
const sql = (r) => execSync('wsl -d Ubuntu -u root -e mariadb -N offregen_fluidite_vue -e "' + r + '"', { encoding: 'utf8' }).trim();
const couper = () => execSync('wsl -d Ubuntu -e sh -c "kill $(cat /home/xavie/offreGen-fluidite-banc/serveur.pid)"', { stdio: 'pipe' });
const rallumer = () => execSync('wsl -d Ubuntu -e sh /home/xavie/offreGen-fluidite-banc/serveur.sh apres', { stdio: 'pipe' });
const calme = (page) => page.waitForFunction(() => window.fluide && !window.fluide.occupe(), null, { timeout: 60000 });
const noter = (cle, v) => console.log(cle.padEnd(58), typeof v === 'string' ? v : JSON.stringify(v));

(async () => {
  const { browser, contexte } = await navigateur();
  const erreurs = [];
  contexte.on('page', (p) => p.on('pageerror', (e) => erreurs.push(e.message)));
  let page;

  // 1. La corbeille d'une ligne qui a une variante, puis « Annuler » : jamais deux fois la variante.
  remettre();
  page = await contexte.newPage();
  await reseau(page, '4g');
  await page.goto(BASE + '/offres/3', { waitUntil: 'load' });
  await calme(page);
  const variante = await page.locator('#lignes .row.var').first().getAttribute('id');
  const base = await page.evaluate((v) => { let s = document.getElementById(v).previousElementSibling; while (s && s.classList.contains('var')) s = s.previousElementSibling; return s.id; }, variante);
  await page.click('#' + base + ' button.del');
  await calme(page);
  await page.waitForTimeout(500);
  noter('corbeille de la ligne à variante : exemplaires de la variante', await page.evaluate((v) => document.querySelectorAll('[id="' + v + '"]').length, variante));
  noter('  la variante est passée dans les options', await page.evaluate((v) => !!document.querySelector('#options [id="' + v + '"]'), variante));
  noter('  la ligne retirée est cachée', await page.evaluate((b) => !document.getElementById(b) || document.getElementById(b).hidden, base));
  await page.click('.app > form.snack[data-disparait] button');
  await calme(page);
  await page.waitForTimeout(500);
  noter('« Annuler » : exemplaires de la variante', await page.evaluate((v) => document.querySelectorAll('[id="' + v + '"]').length, variante));
  noter('  la ligne et sa variante sont revenues dans les lignes', await page.evaluate(([b, v]) => !!document.querySelector('#lignes [id="' + b + '"]:not([hidden])') && !!document.querySelector('#lignes [id="' + v + '"]'), [base, variante]));
  await page.screenshot({ path: path.join(CAPTURES, 'revue-variante.png') });
  await page.close();

  // 2. Une quantité, puis la corbeille de la même ligne : la ligne ne revit pas par la réponse de la quantité.
  remettre();
  page = await contexte.newPage();
  await reseau(page, 'chantier');
  await page.goto(BASE + '/offres/3', { waitUntil: 'load' });
  await calme(page);
  const l = await page.locator('#lignes .row:not(.flag):not(.miss):not(.var)').nth(7).getAttribute('id');
  await page.fill('#' + l + ' input[name=quantite]', '6');
  await page.click('#' + l + ' button.del');
  await calme(page);
  await page.waitForTimeout(500);
  noter('quantité puis corbeille : ligne cachée après les deux réponses', await page.evaluate((id) => { const r = document.getElementById(id); return !r || r.hidden; }, l));
  noter('  en base : ligne supprimée', sql('SELECT supprimee_le IS NOT NULL FROM ligne WHERE id = ' + l.slice(1)));
  await page.close();

  // 3. Relecture : deux touchers à 80 ms ne valident que la ligne vue.
  remettre();
  page = await contexte.newPage();
  await reseau(page, '4g');
  await page.goto(BASE + '/offres/3/relire', { waitUntil: 'load' });
  const [r1, r2] = await page.locator('section[data-relu]').evaluateAll((s) => s.slice(0, 2).map((x) => x.dataset.relu));
  await page.fill('section[data-relu="' + r1 + '"] input[name=prix]', '48');
  await page.click('[data-relu-gestes] button[type=submit]');
  await page.waitForTimeout(80);
  await page.click('[data-relu-gestes] button[type=submit]', { force: true }).catch(() => {});
  await calme(page);
  noter('relecture, double toucher : la deuxième ligne est encore à relire', sql('SELECT a_verifier FROM ligne WHERE id = ' + r2) === '1' || sql('SELECT prix_centimes FROM ligne WHERE id = ' + r2) === '0');
  noter('  la deuxième ligne est à l\'écran', await page.evaluate((id) => !document.querySelector('section[data-relu="' + id + '"]').hidden, r2));
  await page.close();

  // 4. « Facturer » : une case du choix du client recharge la fenêtre, l'artisan reste sur l'offre.
  remettre();
  // L'offre 3 rendue facturable : envoyée, rien à relire (sinon son bouton principal est « Relire »).
  sql("UPDATE offre SET envoyee_le = NOW() WHERE id = 3; UPDATE ligne SET a_verifier = 0 WHERE offre_id = 3; UPDATE ligne SET prix_centimes = 1000 WHERE offre_id = 3 AND prix_centimes = 0");
  page = await contexte.newPage();
  await reseau(page, '4g');
  await page.goto(BASE + '/offres/3', { waitUntil: 'load' });
  await calme(page);
  await page.click('a[href="/offres/3/facturer"]');
  await page.waitForSelector('[data-feuille-page].ouvert .choix-client button.case-client', { timeout: 30000 });
  const avant = await page.locator('[data-feuille-page] .choix-client button.case-client').first().getAttribute('aria-pressed');
  await page.locator('[data-feuille-page] .choix-client button.case-client').first().click();
  await calme(page);
  await page.waitForFunction((a) => { const b = document.querySelector('[data-feuille-page] .choix-client button.case-client'); return b && b.getAttribute('aria-pressed') !== a; }, avant, { timeout: 30000 }).catch(() => {});
  noter('« Facturer », case du client : on reste sur l\'offre', await page.evaluate(() => location.pathname));
  noter('  la fenêtre est ouverte, la case a changé', await page.evaluate((a) => !!document.querySelector('[data-feuille-page].ouvert') && document.querySelector('[data-feuille-page] .choix-client button.case-client').getAttribute('aria-pressed') !== a, avant));
  await page.screenshot({ path: path.join(CAPTURES, 'revue-facturer-case.png') });

  // 5. « Créer la facture » sans réseau : rien ne part en file, la fenêtre le dit et reste ouverte.
  couper();
  await page.click('[data-feuille-page] button[form="facturer"][value="creer"]').catch((e) => noter('clic Créer : erreur', e.message.split('\n')[0]));
  await page.waitForTimeout(2500);
  noter('« Créer la facture » sans réseau : gestes en file (0 attendu)', await page.evaluate(() => window.fluide.file.length));
  noter('  message dans la fenêtre', await page.evaluate(() => { const a = document.querySelector('[data-feuille-page] .alerte.bad'); return a ? a.textContent.trim() : 'AUCUN'; }));
  noter('  fenêtre ouverte', await page.evaluate(() => !!document.querySelector('[data-feuille-page].ouvert')));
  await page.screenshot({ path: path.join(CAPTURES, 'revue-facturer-hors-reseau.png') });
  rallumer();
  noter('  en base : factures de l\'offre 3', sql('SELECT COUNT(*) FROM facture WHERE offre_id = 3'));
  await page.close();

  noter('erreurs JavaScript', erreurs);
  await browser.close();
  remettre();
})().catch((e) => { console.error(e); try { rallumer(); } catch (x) { /* déjà rallumé */ } process.exit(1); });
