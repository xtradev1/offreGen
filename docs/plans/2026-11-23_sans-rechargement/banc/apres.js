// Écrans « après » de la maquette : la vraie page de l'application (vraie feuille de style), modifiée dans le navigateur
// pour montrer le comportement proposé. Rien n'est envoyé au serveur après le chargement (route bloquée).
// Usage : node apres.js
'use strict';
const path = require('path');
const { execSync } = require('child_process');
const { BASE, CAPTURES, navigateur } = require('./commun');

const remettre = () => execSync('wsl -d Ubuntu -u root -e sh /home/xavie/offreGen-fluidite-banc/base.sh remettre', { stdio: 'pipe' });

(async () => {
  remettre();
  const { browser, contexte } = await navigateur();
  const page = await contexte.newPage();
  const capture = async (nom) => { await page.waitForTimeout(250); await page.screenshot({ path: path.join(CAPTURES, nom + '.png') }); };
  // Les champs de l'offre partent seuls (keepalive) : aucun envoi ne doit partir de ces écrans de démonstration.
  const bloquer = async () => { await page.route('**/*', (r) => (r.request().method() === 'GET' ? r.continue() : r.abort())); };

  // 1. « C'est juste » sans rechargement : la ligne change sur place, un message discret le confirme.
  await page.goto(BASE + '/offres/3', { waitUntil: 'load' });
  await bloquer();
  await page.evaluate(() => {
    document.querySelectorAll('input[data-envoi-change]').forEach((c) => c.removeAttribute('data-envoi-change'));
    const r = document.querySelector('#l14');
    r.classList.remove('flag');
    r.querySelector('form.note.warn').remove();
    r.style.background = 'var(--ok-soft)';
    r.scrollIntoView({ block: 'center' });
    const etat = document.querySelector('[data-sauvegarde]');
    if (etat) etat.textContent = 'Enregistrée à l’instant';
    const bouton = document.querySelector('.actionbar a.primary span span');
    if (bouton) bouton.textContent = 'Relire 5 lignes';
    const s = document.createElement('div');
    s.className = 'snack';
    s.setAttribute('role', 'status');
    s.innerHTML = '<span>« Peinture des murs, 2 couches » vérifiée</span><button type="button">Annuler</button>';
    document.querySelector('.app').append(s);
  });
  await capture('apres-juste');

  // 1 bis. Corbeille sans rechargement : la ligne part sur place, l'écran ne bouge pas, « Annuler » reste.
  await page.goto(BASE + '/offres/3', { waitUntil: 'load' });
  await page.evaluate(() => {
    document.querySelectorAll('input[data-envoi-change]').forEach((c) => c.removeAttribute('data-envoi-change'));
    const r = document.querySelector('#l37');
    r.scrollIntoView({ block: 'center' });
    const y = window.scrollY;
    r.remove();
    window.scrollTo(0, y);
    // Les totaux renvoyés par le serveur, les mêmes que ceux de la page rechargée d'aujourd'hui (demo.js).
    const petit = document.querySelector('[data-total-bouton]');
    if (petit) petit.textContent = 'CHF 26’561.80';
    const fixe = document.querySelector('[data-total-fixe] b');
    if (fixe) fixe.textContent = 'CHF 26’561.80';
    const s = document.createElement('div');
    s.className = 'snack';
    s.setAttribute('role', 'status');
    s.innerHTML = '<span>« Heure de régie » supprimée</span><button type="button">Annuler</button>';
    document.querySelector('.app').append(s);
  });
  await capture('apres-corbeille');

  // 2. Hors réseau : bandeau, lignes en attente, total provisoire.
  await page.goto(BASE + '/offres/3', { waitUntil: 'load' });
  await page.evaluate(() => {
    document.querySelectorAll('input[data-envoi-change]').forEach((c) => c.removeAttribute('data-envoi-change'));
    // Le bandeau reste visible sous l'en-tête, où que l'artisan soit dans l'offre.
    const bandeau = document.createElement('p');
    bandeau.className = 'alerte warn';
    bandeau.setAttribute('role', 'status');
    bandeau.style.cssText = 'position:fixed;top:8px;left:12px;right:12px;z-index:40;margin:0;box-shadow:0 2px 8px rgba(0,0,0,.18)';
    bandeau.innerHTML = '<b>Pas de réseau.</b> Vos changements restent dans ce téléphone et partiront seuls dès que le réseau revient.';
    document.querySelector('.app').append(bandeau);
    const attente = (id) => {
      const r = document.querySelector('#l' + id);
      const nm = r.querySelector('a.nm');
      const t = document.createElement('small');
      t.innerHTML = '<span class="tag" style="background:var(--warn-soft);color:var(--warn)">en attente d’envoi</span>';
      nm.append(t);
      return r;
    };
    // 24 m² passés à 27 m² à 220.50 : la ligne et le total provisoire suivent (calcul du téléphone, 661.50 de plus).
    const q = document.querySelector('#l12 input[name=quantite]');
    q.value = '27';
    document.querySelector('#l12 .tt').textContent = '5’953.50';
    // Le sous-total de la pièce suit aussi (9 083.00 + 661.50).
    document.querySelectorAll('.cat h5').forEach((h) => { h.innerHTML = h.innerHTML.replace(/9[’']083\.00/, '9’744.50'); });
    attente(12);
    const r14 = attente(14);
    r14.classList.remove('flag');
    r14.querySelector('form.note.warn').remove();
    const relire = document.querySelector('.actionbar a.primary span span');
    if (relire) relire.textContent = 'Relire 5 lignes';
    const etat = document.querySelector('[data-sauvegarde]');
    if (etat) etat.textContent = '2 changements en attente d’envoi';
    const total = document.querySelector('[data-total-fixe] span');
    if (total) total.textContent = 'Total provisoire';
    // Total provisoire : l'ancien total, plus 661.50 et la TVA de l'offre (taux lu dans ses totaux).
    const nombre = (s) => parseFloat(String(s).replace(/[^\d.,-]/g, '').replace(/[’']/g, ''));
    const ligneTva = [...document.querySelectorAll('.totals .r span')].find((s) => /TVA/.test(s.textContent));
    const taux = ligneTva ? parseFloat(ligneTva.textContent.replace(',', '.').match(/(\d+(?:\.\d+)?)\s*%/)[1]) / 100 : 0;
    const petit = document.querySelector('[data-total-bouton]');
    const ancien = nombre((petit || {}).textContent || '0');
    const neuf = (Math.round((ancien + 661.5 * (1 + taux)) * 20) / 20).toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, '’');
    if (petit) petit.textContent = 'CHF ' + neuf + ' (provisoire)';
    const fixe = document.querySelector('[data-total-fixe] b');
    if (fixe) fixe.textContent = 'CHF ' + neuf;
    window.__totalProvisoire = { ancien, taux, neuf };
    document.querySelector('#l12').scrollIntoView({ block: 'center' });
  });
  await capture('apres-hors-ligne');

  // 3. Le réseau revient : les changements partent, un message le dit.
  await page.evaluate(() => {
    document.querySelector('.app > .alerte.warn').remove();
    document.querySelectorAll('.tag').forEach((t) => { if (/attente/.test(t.textContent)) t.closest('small').remove(); });
    const etat = document.querySelector('[data-sauvegarde]');
    if (etat) etat.textContent = 'Enregistrée à l’instant';
    const total = document.querySelector('[data-total-fixe] span');
    if (total) total.textContent = 'Total TTC';
    const petit = document.querySelector('[data-total-bouton]');
    if (petit) petit.textContent = petit.textContent.replace(' (provisoire)', '');
    const s = document.createElement('div');
    s.className = 'snack';
    s.style.background = 'var(--ok)';
    s.style.color = 'var(--on-ok)';
    s.setAttribute('role', 'status');
    s.innerHTML = '<span>Réseau revenu : 2 changements envoyés</span>';
    document.querySelector('.app').append(s);
  });
  await capture('apres-retour-reseau');

  // 4. « + Ligne » : le catalogue est déjà dans le téléphone, la fenêtre s'ouvre tout de suite ; l'ajout ne recharge rien.
  await page.goto(BASE + '/offres/3', { waitUntil: 'load' });
  await page.click('a.btn.ajout');
  await page.waitForFunction(() => { const c = document.querySelector('[data-feuille-corps]'); return c && c.children.length > 0; });
  await page.waitForTimeout(500);
  await page.evaluate(() => {
    const corps = document.querySelector('[data-feuille-corps]');
    const note = document.createElement('p');
    note.className = 'muted petit';
    note.style.margin = '0 0 8px';
    note.textContent = 'Catalogue gardé dans ce téléphone : 274 produits, à jour.';
    const res = corps.querySelector('#res');
    res.before(note);
    const ok = document.createElement('p');
    ok.className = 'ajoutee';
    ok.setAttribute('role', 'status');
    ok.textContent = '« Lessivage des volets » ajoutée, l’offre est à jour derrière.';
    corps.prepend(ok);
  });
  await capture('apres-ajout');

  // 5. La même fenêtre sans réseau : l'ajout est gardé et partira seul.
  await page.evaluate(() => {
    const corps = document.querySelector('[data-feuille-corps]');
    corps.querySelector('.ajoutee').textContent = '« Lessivage des volets » ajoutée. Pas de réseau : elle partira seule.';
    corps.querySelector('.ajoutee').style.background = 'var(--warn-soft)';
    corps.querySelector('.ajoutee').style.color = 'var(--warn)';
  });
  await capture('apres-ajout-hors-ligne');

  // 6. La liste des offres hors réseau : les offres déjà ouvertes restent lisibles.
  await page.goto(BASE + '/', { waitUntil: 'load' });
  await page.evaluate(() => {
    const bandeau = document.createElement('p');
    bandeau.className = 'alerte warn';
    bandeau.innerHTML = '<b>Pas de réseau.</b> Les offres ouvertes ces 30 derniers jours restent lisibles et modifiables.';
    document.querySelector('main.screen').prepend(bandeau);
    document.querySelectorAll('a.ocard').forEach((a, i) => {
      if (i > 1) { a.style.opacity = '.5'; }
    });
  });
  await capture('apres-liste-hors-ligne');

  await browser.close();
})().catch((e) => { console.error(e); process.exit(1); });
