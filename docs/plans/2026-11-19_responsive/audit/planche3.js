// Maquette des points à trancher : état actuel, puis proposition appliquée dans la page (CSS ou DOM), rien d'enregistré.
// Seule écriture : une ligne supprimée puis restaurée sur le compte de recette PC, pour faire paraître le bandeau.
const fs = require('fs');
const path = require('path');
const { chromium } = require(process.env.MK_PLAYWRIGHT);
const B = 'http://localhost:8000';
const OUT = path.join(__dirname, 'out7');
fs.mkdirSync(OUT, { recursive: true });
const mdp = f => fs.readFileSync(`//wsl.localhost/Ubuntu/home/xavie/.offregen-${f}-compte`, 'utf8').trim().split('\n')[0].trim();
const T = { tel: [390, 844, true], pc: [1366, 768, false] };
const M = {};

(async () => {
  const b = await chromium.launch({ executablePath: process.env.MK_CHROMIUM });
  const etats = {};
  for (const [id, courriel, f] of [['demo', 'demo-peintre@exemple.ch', 'demo'], ['pc', 'recette-pc@exemple.ch', 'recette-pc'], ['tel', 'recette-tel@exemple.ch', 'recette-tel']]) {
    const c = await b.newContext(); const p = await c.newPage();
    await p.goto(B + '/connexion'); await p.fill('#courriel', courriel); await p.fill('#mot_de_passe', mdp(f));
    await Promise.all([p.waitForNavigation(), p.click('button[type=submit][form=connexion]')]);
    if (p.url().includes('/connexion')) throw new Error('refus ' + id);
    etats[id] = await c.storageState(); await c.close();
  }
  const ouvrir = async (compte, t, ecrire = false) => {
    const [w, h, m] = T[t];
    const ctx = await b.newContext({ viewport: { width: w, height: h }, deviceScaleFactor: m ? 2 : 1, isMobile: m, hasTouch: m, bypassCSP: true, storageState: etats[compte] });
    if (!ecrire) await ctx.route('**/*', x => (x.request().method() === 'GET' ? x.continue() : x.abort()));
    const p = await ctx.newPage();
    return { ctx, p };
  };
  const cap = (p, n, o = {}) => p.screenshot({ path: path.join(OUT, n + '.png'), ...o });
  const css = (p, s) => p.addStyleTag({ content: s });
  const bas = p => p.evaluate(() => Math.round(document.querySelector('.bas').getBoundingClientRect().height));

  // 1. Facture annulée : la page de la facture au téléphone, et le document à l'écran au PC.
  { const { ctx, p } = await ouvrir('tel', 'tel');
    await p.goto(B + '/factures/31/apercu', { waitUntil: 'networkidle' }); await p.waitForTimeout(1500);
    await cap(p, 'p1-page__avant');
    await p.evaluate(() => { const env = [...document.querySelectorAll('.actionbar .btn')].find(x => /Envoyer/.test(x.innerText)); if (env) env.classList.remove('primary'); });
    await cap(p, 'p1-page__A');
    await p.evaluate(() => { const env = [...document.querySelectorAll('.actionbar .btn')].find(x => /Envoyer/.test(x.innerText)); if (env) { env.classList.remove('primary'); env.innerHTML = 'Télécharger le PDF'; } });
    await cap(p, 'p1-page__B');
    await ctx.close(); }
  { const { ctx, p } = await ouvrir('pc', 'pc');
    // Le document d'une facture émise n'existe qu'en PDF : la proposition se montre sur le même document en HTML, celui de Facturer.
    await p.goto(B + '/offres/175/facturer', { waitUntil: 'networkidle' }); await p.waitForTimeout(800);
    await css(p, '.bas { display: none !important; } .facture-mini { height: auto !important; overflow: visible !important; cursor: default; } .facture-mini [data-apercu-mini] > .apercu-facture { width: 100% !important; transform: none !important; } .apercu-facture .filigrane { color: rgba(140, 29, 24, .30) !important; font-size: 80px !important; top: 260px !important; border: 6px solid rgba(140, 29, 24, .30); padding: 4px 24px; } .mq-annule-note { margin: 18px 0 0; padding: 12px 14px; border: 1px solid #8C1D18; color: #8C1D18; border-radius: 6px; font-weight: 600; font-size: 15px; }');
    M.p1Qr = await p.evaluate(() => { const f = document.querySelector('.facture-mini .filigrane'); if (f) f.textContent = 'Annulée'; const q = document.querySelector('.facture-mini .qr-section'); const n = document.createElement('p'); n.className = 'mq-annule-note'; n.textContent = 'Facture annulée le 19.11.2026. Elle ne doit pas être payée.'; if (q) { q.replaceWith(n); return 'section de paiement remplacée'; } const papier = document.querySelector('.facture-mini .paper'); if (papier) papier.append(n); return 'pas de section de paiement dans le document'; });
    await p.waitForTimeout(300);
    await p.locator('.facture-mini').screenshot({ path: path.join(OUT, 'p1-doc__A.png') });
    await ctx.close(); }

  // 2. Couleur de marque (démo, vert 5,13:1) : teinte foncée juste assez pour 7:1 sur le document.
  { const { ctx, p } = await ouvrir('demo', 'pc');
    await p.goto(B + '/offres/161/document', { waitUntil: 'networkidle' });
    M.p2Avant = await p.evaluate(() => { const e = document.querySelector('.c-fond'); return e ? getComputedStyle(e).backgroundColor : 'pas de .c-fond'; });
    await cap(p, 'p2-doc__avant');
    await css(p, '.c-fond { background-color: #1B5E20 !important; } .c-texte { color: #1B5E20 !important; } .c-bord { border-color: #1B5E20 !important; }');
    M.p2Apres = await p.evaluate(() => { const e = document.querySelector('.c-fond'); return e ? getComputedStyle(e).backgroundColor : '-'; });
    await cap(p, 'p2-doc__A');
    await ctx.close(); }

  // 3. Barre du bas de l'offre (démo, 390) : total dans le bouton ; barre repliée pendant le défilement.
  { const { ctx, p } = await ouvrir('demo', 'tel');
    await p.goto(B + '/offres/161', { waitUntil: 'networkidle' });
    await p.evaluate(() => window.scrollTo(0, 420)); await p.waitForTimeout(300);
    M.p3Avant = await bas(p);
    await cap(p, 'p3-bas__avant');
    await css(p, '.totalbar { display: none !important; } .actionbar .btn.grand { flex-direction: column; gap: 0; line-height: 1.15; } .actionbar .btn.grand svg { display: none; } .mq-total { font: 600 14px Inter, sans-serif; opacity: .85; } .totalbar + .actionbar { padding-top: 10px !important; border-top: 1px solid var(--line) !important; }');
    await p.evaluate(() => { const t = document.querySelector('.totalbar b'); const g = document.querySelector('.actionbar .btn.grand'); if (g && t) { const s = document.createElement('span'); s.className = 'mq-total'; s.textContent = 'Total ' + t.innerText.trim(); g.append(s); } });
    await p.waitForTimeout(200);
    M.p3A = await bas(p);
    await cap(p, 'p3-bas__A');
    await p.reload({ waitUntil: 'networkidle' }); await p.evaluate(() => window.scrollTo(0, 420));
    await css(p, '.bas .totalbar, .bas .actionbar { display: none !important; }');
    await p.waitForTimeout(200);
    M.p3B = await bas(p);
    await cap(p, 'p3-bas__B');
    await ctx.close(); }

  // 4. Lignes libres (démo, offre 910, 390) : un lien « Ranger » au lieu de deux boutons ; ou un seul bandeau en tête.
  { const { ctx, p } = await ouvrir('demo', 'tel');
    await p.goto(B + '/offres/160', { waitUntil: 'networkidle' });
    M.p4Boutons = await p.locator('.ranger-ligne .btn').count();
    await p.evaluate(() => { const r = document.querySelector('.ranger-ligne'); r.closest('.row').scrollIntoView({ block: 'start' }); window.scrollBy(0, -120); });
    await p.waitForTimeout(300);
    await cap(p, 'p4-libres__avant');
    await css(p, '.ranger-ligne { display: none !important; } .mq-ranger { display: inline-flex; align-items: center; min-height: 48px; margin: -14px 0 -14px 6px; padding: 0 4px; font-size: 13px; font-weight: 600; color: var(--ink); text-decoration: underline; text-underline-offset: 3px; }');
    await p.evaluate(() => { document.querySelectorAll('.row').forEach(r => { if (!r.querySelector('.ranger-ligne')) return; const tag = r.querySelector('.nm .tag'); if (tag) { const a = document.createElement('a'); a.className = 'mq-ranger'; a.href = '#'; a.textContent = 'Ranger ›'; tag.after(a); } }); });
    await p.waitForTimeout(200);
    await cap(p, 'p4-libres__A');
    await p.reload({ waitUntil: 'networkidle' });
    await css(p, '.ranger-ligne { display: none !important; } .mq-bandeau { display: flex; align-items: center; justify-content: space-between; gap: 10px; min-height: 56px; margin: 12px 0 4px; padding: 6px 6px 6px 14px; border-radius: 12px; background: var(--warn-soft); color: var(--warn); font-size: 14px; font-weight: 600; } .mq-bandeau .btn { min-height: 48px; }');
    await p.evaluate(() => { const n = document.querySelectorAll('.ranger-ligne').length; const d = document.createElement('div'); d.className = 'mq-bandeau'; d.innerHTML = '<span>' + n + ' lignes hors catalogue</span><a class="btn" href="#">Les ranger</a>'; document.querySelector('.rows').before(d); d.scrollIntoView({ block: 'start' }); window.scrollBy(0, -120); });
    await p.waitForTimeout(200);
    await cap(p, 'p4-libres__B');
    await ctx.close(); }

  // 5. Archiver et supprimer à la souris (démo, accueil 1366) : visibles au survol et au clavier.
  { const { ctx, p } = await ouvrir('demo', 'pc');
    await p.goto(B + '/', { waitUntil: 'networkidle' });
    await p.hover('.glisse .ocard'); await p.waitForTimeout(200);
    await cap(p, 'p5-accueil__avant', { clip: { x: 232, y: 0, width: 1040, height: 520 } });
    await css(p, '.glisse { display: flex; gap: 8px; overflow: visible; border-radius: 0; } .glisse > .ocard { flex: 1; order: 0; transform: none !important; } .actions-glisse { position: static; order: 1; opacity: 0; gap: 8px; transition: none; } .glisse:hover .actions-glisse, .actions-glisse:focus-within { opacity: 1; } .actions-glisse button { width: auto; padding: 0 16px; border-radius: 12px; min-height: 48px; }');
    await p.hover('.glisse .ocard'); await p.waitForTimeout(200);
    await cap(p, 'p5-accueil__A', { clip: { x: 232, y: 0, width: 1040, height: 520 } });
    await ctx.close(); }

  // 6. Annuler une facture (recette téléphone, 2026-005) et l'erreur 409 (acompte déduit).
  { const { ctx, p } = await ouvrir('tel', 'tel');
    await p.goto(B + '/factures/34/annuler', { waitUntil: 'networkidle' });
    await cap(p, 'p6-annuler__avant');
    await p.evaluate(() => {
      const pied = document.querySelector('.screen .pied'); if (pied) pied.remove();
      const ab = document.querySelector('.actionbar'); ab.innerHTML = '';
      ab.style.flexDirection = 'column';
      ab.innerHTML = '<button class="btn danger grand" type="button">Annuler et refaire la facture</button><button class="btn danger-texte" type="button">Annuler seulement</button>';
    });
    await css(p, '.actionbar .btn { width: 100%; }');
    await cap(p, 'p6-annuler__A');
    await p.goto(B + '/factures/30/annuler', { waitUntil: 'networkidle' });
    await cap(p, 'p6-409__avant');
    await p.evaluate(() => {
      document.querySelector('.bar h1').textContent = 'Annulation impossible';
      const s = document.querySelector('.screen'); s.innerHTML = '<p class="alerte warn">Cet acompte est déduit par la facture finale de l\'offre. Pour l\'annuler, annulez d\'abord la facture finale.</p>';
      document.querySelector('.actionbar').innerHTML = '<a class="btn" href="#">Retour à l\'acompte</a><a class="btn primary" href="#">Voir la facture finale</a>';
    });
    await cap(p, 'p6-409__A');
    await ctx.close(); }

  // 7. PC : le bandeau « Annuler » sur l'offre avec le document à côté (recette PC, ligne supprimée puis restaurée).
  { const { ctx, p } = await ouvrir('pc', 'pc', true);
    await p.goto(B + '/offres/175', { waitUntil: 'networkidle' });
    if (!(await p.evaluate(() => document.body.classList.contains('doc-a-cote')))) await p.click('.ohead .doc-cote');
    await p.waitForTimeout(600);
    const avant = await p.locator('.rows .row').count();
    await Promise.all([p.waitForNavigation(), p.locator('.rows .row .del').nth(1).click()]);
    await p.waitForTimeout(700);
    await cap(p, 'p7-snack__avant');
    await css(p, '.snack { left: 12px !important; transform: none !important; width: 208px !important; bottom: 16px !important; flex-wrap: wrap; padding: 8px 10px 4px 14px; }');
    await p.waitForTimeout(200);
    await cap(p, 'p7-snack__A');
    const annuler = p.locator('.snack button');
    if (await annuler.count()) await Promise.all([p.waitForNavigation(), annuler.click()]);
    M.p7Restaure = (await p.locator('.rows .row').count()) === avant;
    await ctx.close(); }

  // 8. Facturer au PC (recette PC) : vignette plus grande, filigrane sous l'adresse.
  { const { ctx, p } = await ouvrir('pc', 'pc');
    await p.goto(B + '/offres/175/facturer', { waitUntil: 'networkidle' }); await p.waitForTimeout(800);
    await cap(p, 'p8-facturer__avant');
    await css(p, '.facture-mini { height: 640px !important; } .facture-mini [data-apercu-mini] > .apercu-facture { width: 156% !important; transform: scale(.64) !important; } .apercu-facture .filigrane { top: 330px !important; }');
    await p.waitForTimeout(300);
    await cap(p, 'p8-facturer__A');
    await ctx.close(); }

  fs.writeFileSync(path.join(OUT, 'mesures.json'), JSON.stringify(M, null, 1));
  console.log(JSON.stringify(M));
  await b.close();
})().catch(e => { console.error(e); process.exit(1); });
