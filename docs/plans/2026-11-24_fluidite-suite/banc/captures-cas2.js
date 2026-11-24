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

  await browser.close();
  if (process.env.SEUL_CAS2 !== '1') throw new Error('mode complet retiré');
  // SR-6, cas 2 : le changement en attente est de cette offre-là.
  remettre();
  const b4 = await navigateur();
  const p4 = await b4.contexte.newPage();
  await b4.contexte.addInitScript(() => { navigator.canShare = () => true; navigator.share = async () => {}; });
  for (const a of ['/', '/offres/1', '/offres/1/apercu', '/offres/1']) { await p4.goto(BASE + a, { waitUntil: 'load' }); await p4.waitForTimeout(1200); }
  await b4.contexte.setOffline(true);
  const c4 = p4.locator('#lignes .row input[name=quantite]').first();
  await c4.tap(); await c4.fill('9'); await c4.press('Enter');
  await p4.waitForTimeout(800);
  await p4.locator('a.btn.primary.grand:has-text("Générer l\'offre")').tap();
  await p4.waitForURL(/apercu/, { timeout: 30000 });
  await p4.waitForLoadState('load');
  await p4.waitForTimeout(2500);
  await p4.screenshot({ path: path.join(CAPTURES, 'suite-sr6-diag.png') }); console.log('adresse', p4.url());
  await p4.locator('[data-partager]').first().tap();
  await p4.waitForTimeout(800);
  await p4.screenshot({ path: path.join(CAPTURES, 'suite-sr6-avant-meme-offre.png') });
  await p4.evaluate((nb) => {
    document.querySelectorAll('.app > .snack.bad').forEach((s) => {
      s.innerHTML = '<span>Ce PDF n’a pas encore votre changement (quantité 9, «' + nb + 'Échafaudage, montage et location' + nb + '»). Il se refait au retour du réseau.</span>';
    });
    const r = document.querySelector('.app > .reseau'); if (r) r.textContent = 'Hors réseau' + nb + ': 1 changement attend (offre 1, quantité 9), il partira au retour du réseau.';
  }, NB);
  await p4.waitForTimeout(200);
  await p4.screenshot({ path: path.join(CAPTURES, 'suite-sr6-apres-meme-offre-a.png') });
  await p4.evaluate((nb) => {
    const s = document.querySelector('.app > .snack.bad');
    if (s) {
      const b = document.createElement('button'); b.type = 'button'; b.textContent = 'Envoyer quand même';
      b.style.cssText = 'display:block;margin:6px 0 0;padding:0;font:inherit;font-weight:700;color:inherit;background:none;border:0;text-decoration:underline;min-height:48px';
      s.style.display = 'block'; s.append(b);
    }
  }, NB);
  await p4.waitForTimeout(200);
  await p4.screenshot({ path: path.join(CAPTURES, 'suite-sr6-apres-meme-offre-b.png') });
  await b4.browser.close();
  remettre();
})().catch((e) => { console.error(e); try { remettre(); } catch (x) { /* */ } process.exit(1); });
