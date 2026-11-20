// Recette par l'interface des corrections R1 à R10 et B2 à B4, sur le serveur local (base offregen_dev, compte de
// démonstration). Rien n'est enregistré : l'envoi automatique des champs est retiré avant de taper, et les envois de
// formulaire retenus pour voir le rond qui tourne sont annulés avant d'atteindre le serveur.
const fs = require('fs');
const path = require('path');
const { chromium } = require(process.env.MK_PLAYWRIGHT);
const BASE = 'http://localhost:8000';
const OUT = path.join(__dirname, 'out5');
fs.mkdirSync(OUT, { recursive: true });
const brut = fs.readFileSync('//wsl.localhost/Ubuntu/home/xavie/.offregen-demo-compte', 'utf8').trim().split('\n')[0];
const MDP = brut.includes(': ') ? brut.slice(brut.lastIndexOf(': ') + 2).trim() : brut.trim();
const SONDE_C = fs.readFileSync('C:/Users/xavie/.claude/skills/recette-visuelle/references/sonde-contraste.js', 'utf8').replace(/export\s+/g, '');
const SONDE_D = fs.readFileSync('C:/Users/xavie/.claude/skills/recette-visuelle/references/sonde-disposition.js', 'utf8').replace(/export\s+/g, '');

const APP = {
  'tel-360': { w: 360, h: 740, m: true }, 'tel-390': { w: 390, h: 844, m: true }, 'tel-430': { w: 430, h: 932, m: true },
  'tel-couche': { w: 844, h: 390, m: true }, 'tab-820': { w: 820, h: 1180, m: true }, 'tab-1180': { w: 1180, h: 820, m: true },
  'pc-900': { w: 900, h: 900, m: false }, 'pc-1366': { w: 1366, h: 768, m: false }, 'pc-1920': { w: 1920, h: 1080, m: false },
};

// --- Mesures dans la page ---
function mesures() {
  const rect = e => e.getBoundingClientRect();
  const vis = e => { const b = rect(e), s = getComputedStyle(e); return b.width > 0 && b.height > 0 && s.visibility !== 'hidden' && s.display !== 'none'; };
  const r = { defilementH: document.documentElement.scrollWidth - document.documentElement.clientWidth };
  // Micro sur un bouton
  const mi = document.querySelector('.tabs .micro-central > span');
  if (mi && vis(mi)) {
    const m = rect(mi);
    r.microSurBouton = [...document.querySelectorAll('.bas .btn')].filter(vis).map(b => { const q = rect(b); const w = Math.min(q.right, m.right) - Math.max(q.left, m.left), h = Math.min(q.bottom, m.bottom) - Math.max(q.top, m.top); return w > 0 && h > 0 ? `${b.innerText.trim().slice(0, 18) || '+'} ${Math.round(w)}x${Math.round(h)}` : null; }).filter(Boolean).join(' ; ') || 'aucun';
    r.microCentre = [Math.round(m.left + m.width / 2), Math.round(m.top + m.height / 2)];
  }
  const bar = document.querySelector('.bar'), bas = document.querySelector('.bas');
  if (bar && bas) r.hauteurContenu = Math.round(innerHeight - rect(bar).height - rect(bas).height);
  // Lignes de l'offre
  const rows = [...document.querySelectorAll('.rows .row')].filter(x => x.querySelector('.nm'));
  if (rows.length) {
    let rec = 0;
    rows.forEach(x => { const nm = rect(x.querySelector('.nm')); x.querySelectorAll('.q > *, .p .saisie, .tt').forEach(e => { const b = rect(e); if (b.width && Math.min(nm.right, b.right) > Math.max(nm.left, b.left) && Math.min(nm.bottom, b.bottom) > Math.max(nm.top, b.top)) rec++; }); });
    const s = [...document.querySelectorAll('.rows .saisie')];
    r.lignes = { n: rows.length, chevauchements: rec, hauteurMoy: Math.round(rows.reduce((t, x) => t + rect(x).height, 0) / rows.length),
      nomMin: Math.min(...rows.map(x => Math.round(rect(x.querySelector('.nm')).height))),
      champ: s.length ? `${Math.round(rect(s[0]).height)} px, ${getComputedStyle(s[0]).fontSize}, fond ${getComputedStyle(s[0]).backgroundColor}` : null,
      prixAlignes: new Set(rows.map(x => Math.round(rect(x.querySelector('.p') || x).left))).size === 1,
      corbeille: rows[0].querySelector('.del') ? Math.round(rect(rows[0].querySelector('.del')).top - rect(rows[0].querySelector('.nm')).top) + ' px sous le haut du nom' : null,
      uneRangee: rows.every(x => Math.abs(rect(x.querySelector('.nm')).top - rect(x.querySelector('.q') || x).top) < 20) };
  }
  // Cibles sous 48 px
  r.ciblesSous48 = [...document.querySelectorAll('a[href], button, input:not([type=hidden]), select, textarea, summary, [role=button]')].filter(vis)
    .filter(e => !(e.type === 'radio' || e.type === 'checkbox') && !e.closest('.cache, .sr') && (rect(e).height < 47.5 || rect(e).width < 47.5))
    .map(e => `${e.tagName.toLowerCase()}.${(e.className || '').toString().split(' ')[0]} « ${(e.innerText || e.value || e.getAttribute('aria-label') || '').trim().slice(0, 22)} » ${Math.round(rect(e).width)}x${Math.round(rect(e).height)}`);
  return r;
}

// Contraste des formes : bord des boutons secondaires, cadre des champs de saisie, contre le fond peint derrière.
function formes() {
  const lum = c => { const [r, g, b] = c.map(v => { v /= 255; return v <= 0.03928 ? v / 12.92 : ((v + 0.055) / 1.055) ** 2.4; }); return 0.2126 * r + 0.7152 * g + 0.0722 * b; };
  const lire = s => (s.match(/[\d.]+/g) || []).map(Number);
  const fond = el => { for (let n = el.parentElement; n; n = n.parentElement) { const c = lire(getComputedStyle(n).backgroundColor); if (c.length === 3 || c[3] === 1) return c.slice(0, 3); } return lire(getComputedStyle(document.body).backgroundColor).slice(0, 3); };
  const ratio = (a, b) => { const x = lum(a), y = lum(b); return Math.round(((Math.max(x, y) + 0.05) / (Math.min(x, y) + 0.05)) * 100) / 100; };
  const out = [];
  document.querySelectorAll('.btn:not(.primary):not(.danger):not(.ok), .saisie').forEach(el => {
    const b = el.getBoundingClientRect(); if (!b.width || getComputedStyle(el).display === 'none') return;
    const st = getComputedStyle(el);
    const bord = el.classList.contains('saisie') ? lire(st.outlineColor).slice(0, 3) : lire(st.borderTopColor).slice(0, 3);
    out.push(`${el.classList.contains('saisie') ? 'cadre champ' : 'bord bouton'} « ${(el.innerText || el.value || '').trim().slice(0, 16)} » ${ratio(bord, fond(el))}:1`);
  });
  return [...new Set(out)];
}

async function sondes(page) {
  const c = await page.evaluate(src => { (0, eval)(src + '; window.__sc = sondeContraste;'); const r = window.__sc(7); return { examines: r.textesExamines, defauts: r.defauts, transparents: r.surImageOuTransparent, detail: r.detail.slice(0, 8).map(d => `${d.gravite} ${d.contraste}:1 « ${(d.texte || '').slice(0, 30)} »`) }; }, SONDE_C);
  const d = await page.evaluate(src => { (0, eval)(src + '; window.__sd = sondeDisposition;'); return JSON.stringify(window.__sd('body', '.champ, .btn, .row, .krow, .crow')).slice(0, 600); }, SONDE_D);
  return { contraste: c, formes: await page.evaluate(formes), disposition: d };
}

(async () => {
  const filtre = process.argv[2] ? process.argv[2].split(',') : null;
  const veut = id => !filtre || filtre.includes(id);
  const browser = await chromium.launch({ executablePath: process.env.MK_CHROMIUM });
  const c0 = await browser.newContext();
  const p0 = await c0.newPage();
  await p0.goto(BASE + '/connexion');
  await p0.fill('#courriel', 'demo-peintre@exemple.ch');
  await p0.fill('#mot_de_passe', MDP);
  await Promise.all([p0.waitForNavigation(), p0.click('button[type=submit][form=connexion]')]);
  if (p0.url().includes('/connexion')) throw new Error('connexion refusée, arrêt (pas de second essai)');
  if (p0.url().includes('/bienvenue')) await Promise.all([p0.waitForNavigation(), p0.click('form[action="/bienvenue/passer"] button')]);
  const etat = await c0.storageState();
  await p0.goto(BASE + '/');
  const offres = [...new Set(await p0.$$eval('a[href^="/offres/"]', as => as.map(a => a.getAttribute('href')).filter(h => /^\/offres\/\d+$/.test(h))))];
  let OFFRE = null;
  for (const o of offres) { await p0.goto(BASE + o); if (await p0.locator('.rows .row .saisie').count() >= 3) { OFFRE = o; break; } }
  const valeursAvant = { q: await p0.$$eval('.rows input[name=quantite]', a => a.map(e => e.value).join(', ')), p: await p0.$$eval('.rows input[name=prix]', a => a.map(e => e.value).join(', ')) };
  await p0.goto(BASE + '/clients');
  const CLIENT = (await p0.$$eval('a[href^="/clients/"]', as => as.map(a => a.getAttribute('href')).filter(h => /^\/clients\/\d+$/.test(h))))[0];
  await p0.goto(BASE + '/factures');
  const nbFactures = await p0.locator('.frow').count();
  await c0.close();
  console.log('offre', OFFRE, 'client', CLIENT, 'factures avant', nbFactures);
  const res = { offre: OFFRE, client: CLIENT };

  const ouvrir = async (app, theme = 'light', extra = {}) => {
    const a = APP[app];
    const ctx = await browser.newContext({ viewport: { width: a.w, height: a.h }, deviceScaleFactor: a.m ? 2 : 1, isMobile: a.m, hasTouch: a.m, colorScheme: theme, storageState: etat, ...extra });
    const page = await ctx.newPage();
    page.__err = []; page.on('pageerror', e => page.__err.push(String(e).slice(0, 120)));
    return { ctx, page };
  };
  const cap = (page, nom, opts = {}) => page.screenshot({ path: path.join(OUT, nom + '.png'), ...opts });
  const sansEnvoi = page => page.evaluate(() => document.querySelectorAll('[data-envoi-change]').forEach(e => e.removeAttribute('data-envoi-change')));

  // 1. Les trois écrans de la décision B, à 390 px, en clair et en sombre.
  for (const theme of ['light', 'dark']) {
    if (!veut('b-' + theme)) continue;
    const { ctx, page } = await ouvrir('tel-390', theme);
    const t = res['b-' + theme] = {};
    await page.goto(BASE + OFFRE, { waitUntil: 'networkidle' });
    await cap(page, `b-${theme}__offre-haut`);
    await page.evaluate(() => { const r = document.querySelector('.rows .row'); r.scrollIntoView({ block: 'start' }); window.scrollBy(0, -60); });
    await page.waitForTimeout(300);
    t.offre = { url: OFFRE, ...(await page.evaluate(mesures)), ...(await sondes(page)) };
    await cap(page, `b-${theme}__offre`);
    // La feuille d'ajout : le « + » de la barre du bas, touché comme un artisan.
    await page.click('.actionbar .btn.carre');
    await page.waitForTimeout(900);
    t.ajout = { url: page.url().replace(BASE, ''), ...(await page.evaluate(mesures)), ...(await sondes(page)) };
    await cap(page, `b-${theme}__ajout`);
    // La fiche client, depuis la liste des clients.
    await page.goto(BASE + '/clients', { waitUntil: 'networkidle' });
    await page.click('a.krow');
    await page.waitForSelector('.feuille-fond.ouvert .champ');
    await page.waitForTimeout(700);
    t.client = { url: 'fiche en fenêtre', ...(await page.evaluate(mesures)), ...(await sondes(page)) };
    await cap(page, `b-${theme}__client`);
    await cap(page, `b-${theme}__client-plein`, { fullPage: true });
    t.erreursJS = page.__err;
    await ctx.close();
    console.log('fait b-' + theme);
  }

  // 2. B3 : le bouton pendant un envoi. « Créer la facture » (envoi de formulaire) et une fiche en fenêtre du catalogue
  //    (envoi par fetch). L'envoi est retenu 3 s puis annulé : rien n'atteint le serveur.
  if (veut('b3')) {
    for (const theme of ['light', 'dark']) {
      const { ctx, page } = await ouvrir('tel-390', theme);
      let annules = 0;
      await ctx.route('**/*', async r => { if (r.request().method() === 'POST') { await new Promise(f => setTimeout(f, 3000)); annules++; return r.abort(); } return r.continue(); });
      await page.goto(BASE + OFFRE + '/facturer', { waitUntil: 'networkidle' });
      const lu = [];
      page.on('console', m => { if (m.text().startsWith('B3 ')) lu.push(JSON.parse(m.text().slice(3))); });
      // Le formulaire ne part pas : app.js a déjà posé l'état d'attente (écouteur du formulaire), celui-ci annule ensuite.
      await page.evaluate(() => document.addEventListener('submit', (e) => e.preventDefault()));
      await page.evaluate(() => document.addEventListener('submit', () => setTimeout(() => { const b = document.querySelector('[data-bouton-creer]'); console.log('B3 ' + JSON.stringify({ texte: b.innerText, busy: b.getAttribute('aria-busy'), disabled: b.disabled, opacite: getComputedStyle(b).opacity, fond: getComputedStyle(b).backgroundColor, rond: getComputedStyle(b, '::before').content !== 'none' })); }, 150), true));
      await page.locator('[data-bouton-creer]').scrollIntoViewIfNeeded();
      await page.click('[data-bouton-creer]', { noWaitAfter: true });
      await page.waitForTimeout(500);
      await page.locator('[data-bouton-creer]').screenshot({ path: path.join(OUT, `b3-${theme}__facturer.png`) }).catch(e => console.log('capture bouton', e.message.slice(0, 80)));
      await cap(page, `b3-${theme}__facturer-ecran`);
      res['b3-' + theme] = { facturer: lu[0] || 'non lu' };
      await page.waitForTimeout(3000);
      await page.goto(BASE + '/catalogue', { waitUntil: 'networkidle' });
      await page.click('.crow');
      await page.waitForSelector('.feuille-fond.ouvert button[type=submit]');
      await page.waitForTimeout(500);
      const bouton = page.locator('.feuille-fond.ouvert button[type=submit]').last();
      await bouton.click({ noWaitAfter: true });
      await page.waitForTimeout(700);
      res['b3-' + theme].fiche = await bouton.evaluate(b => ({ texte: b.innerText, busy: b.getAttribute('aria-busy'), disabled: b.disabled, opacite: getComputedStyle(b).opacity, rond: getComputedStyle(b, '::before').content !== 'none' }));
      await cap(page, `b3-${theme}__fiche`);
      await page.waitForTimeout(3300);
      res['b3-' + theme].ficheApres = await bouton.evaluate(b => ({ texte: b.innerText, busy: b.getAttribute('aria-busy'), disabled: b.disabled }));
      res['b3-' + theme].postsAnnules = annules;
      res['b3-' + theme].erreursJS = page.__err;
      await ctx.close();
    }
    console.log('fait b3');
  }

  // 3. R1 à R10, aux tailles de l'audit.
  const R = [
    ['r1-offre', 'tel-390', OFFRE], ['r1-catalogue', 'tel-390', '/catalogue'], ['r1-clients', 'tel-390', '/clients'], ['r1-tab', 'tab-820', OFFRE],
    ['r2-offre', 'tel-couche', OFFRE], ['r2-catalogue', 'tel-couche', '/catalogue'],
    ['r3-430', 'tel-430', OFFRE], ['r3-360', 'tel-360', OFFRE],
    ['r4-360', 'tel-360', OFFRE + '/facturer', 'details'], ['r6-1180', 'tab-1180', '/clients/nouveau'], ['r6-820', 'tab-820', '/reglages/entreprise'],
    ['r7-390', 'tel-390', '/reglages/entreprise'], ['r8-390', 'tel-390', '/catalogue'], ['r8-1180', 'tab-1180', '/catalogue'],
    ['r10a-900', 'pc-900', OFFRE], ['r10a-900-client', 'pc-900', '/clients/nouveau'], ['r10b-1366', 'pc-1366', OFFRE], ['tab-1180-offre', 'tab-1180', OFFRE],
  ];
  for (const [id, app, url, quoi] of R) {
    if (!veut(id) && !veut('r')) continue;
    const { ctx, page } = await ouvrir(app);
    await page.goto(BASE + url, { waitUntil: 'networkidle' });
    if (quoi === 'details') await page.evaluate(() => document.querySelectorAll('details').forEach(d => { d.open = true; }));
    if (url === OFFRE && app !== 'tel-couche') await page.evaluate(() => { const r = document.querySelector('.rows .row'); if (r) { r.scrollIntoView({ block: 'start' }); window.scrollBy(0, -60); } });
    if (id === 'r7-390') await page.evaluate(() => document.querySelector('#telephone').scrollIntoView({ block: 'center' }));
    await page.waitForTimeout(400);
    const m = await page.evaluate(mesures);
    delete m.ciblesSous48;
    if (quoi === 'details') m.echeance = await page.evaluate(() => { const e = document.querySelector('#echeance'), c = e.closest('details'); return Math.max(0, Math.round(e.getBoundingClientRect().right - c.getBoundingClientRect().right)); });
    if (id.startsWith('r6') || id === 'r10a-900-client') m.champLePlusLarge = await page.evaluate(() => Math.max(...[...document.querySelectorAll('.champ input, fieldset.adresse input')].map(i => Math.round(i.getBoundingClientRect().width))));
    if (id === 'r7-390') m.decalage = await page.evaluate(() => Math.round(Math.abs(document.querySelector('#telephone').getBoundingClientRect().top - document.querySelector('#ide').getBoundingClientRect().top)));
    if (id.startsWith('r8')) m.filtres = await page.evaluate(() => { const f = document.querySelector('.fchips'); return f ? { contenu: f.scrollWidth, visible: f.clientWidth, hauteur: Math.round(f.getBoundingClientRect().height), masque: getComputedStyle(f).maskImage !== 'none' } : null; });
    m.app = await page.evaluate(() => { const a = document.querySelector('.app').getBoundingClientRect(); return [Math.round(a.left), Math.round(a.width)]; });
    m.erreursJS = page.__err;
    res[id] = m;
    await cap(page, id);
    await ctx.close();
  }
  console.log('fait R');

  // 4. R10 b C : trois volets à 1920 px, ouverts d'office ; « Fermer le document » ; fermé, il le reste.
  if (veut('r10b')) {
    const { ctx, page } = await ouvrir('pc-1920');
    await page.goto(BASE + OFFRE, { waitUntil: 'networkidle' });
    await page.waitForSelector('.colonne-doc:not([hidden]) .paper, .colonne-doc:not([hidden]) article', { timeout: 8000 }).catch(() => {});
    await page.waitForTimeout(800);
    const etatVolets = () => page.evaluate(() => { const v = s => { const e = document.querySelector(s); return !!e && !e.hidden && getComputedStyle(e).display !== 'none' && e.getBoundingClientRect().width > 0; }; const a = document.querySelector('.app').getBoundingClientRect(); return { liste: v('.colonne-liste'), document: v('.colonne-doc'), app: [Math.round(a.left), Math.round(a.width)], bouton: document.querySelector('.ohead .doc-cote') && document.querySelector('.ohead .doc-cote').innerText, garde: localStorage.getItem('offregen-doc-a-cote'), defilementH: document.documentElement.scrollWidth - document.documentElement.clientWidth }; });
    res.r10b = { ouverture: await etatVolets() };
    await cap(page, 'r10b-1920');
    await page.click('.ohead .doc-cote');
    await page.waitForTimeout(400);
    res.r10b.apresFermer = await etatVolets();
    await page.reload({ waitUntil: 'networkidle' });
    await page.waitForTimeout(600);
    res.r10b.apresRechargement = await etatVolets();
    res.r10b.erreursJS = page.__err;
    await ctx.close();
    // 1366 px, sans choix gardé : le document ne s'ouvre pas tout seul.
    const p2 = await ouvrir('pc-1366');
    await p2.page.goto(BASE + OFFRE, { waitUntil: 'networkidle' });
    await p2.page.waitForTimeout(600);
    res.r10b.a1366 = await p2.page.evaluate(() => ({ docOuvert: document.body.classList.contains('doc-a-cote'), garde: localStorage.getItem('offregen-doc-a-cote') }));
    await p2.ctx.close();
    console.log('fait r10b');
  }

  // 5. Largeur des champs à la frappe : avec field-sizing (Chromium), puis sans (comme Safari), par l'attribut size.
  if (veut('saisie')) {
    for (const sans of [false, true]) {
      const { ctx, page } = await ouvrir('tel-390', 'light', sans ? { bypassCSP: true } : {});
      if (sans) await ctx.addInitScript(() => { const o = CSS.supports.bind(CSS); CSS.supports = (a, b) => (String(a).includes('field-sizing') ? false : o(a, b)); });
      await page.goto(BASE + OFFRE, { waitUntil: 'networkidle' });
      if (sans) await page.addStyleTag({ content: '.saisie { field-sizing: fixed !important; }' });
      await sansEnvoi(page);
      await page.evaluate(() => { const r = document.querySelector('.rows .row'); r.scrollIntoView({ block: 'start' }); window.scrollBy(0, -60); });
      const largeurs = () => page.evaluate(() => ({ q: [...document.querySelectorAll('.rows input[name=quantite]')].map(e => Math.round(e.getBoundingClientRect().width)), p: [...document.querySelectorAll('.rows input[name=prix]')].map(e => Math.round(e.getBoundingClientRect().width)), size: document.querySelector('.rows input[name=prix]').getAttribute('size'), prixX: [...new Set([...document.querySelectorAll('.rows .p')].map(e => Math.round(e.getBoundingClientRect().left)))] }));
      const avant = await largeurs();
      const q = page.locator('.rows input[name=quantite]').nth(1), p = page.locator('.rows input[name=prix]').nth(1);
      await q.click(); await q.fill('125.5'); await p.click(); await p.fill('12500');
      await page.waitForTimeout(300);
      res['saisie-' + (sans ? 'safari' : 'chromium')] = { avant, apres: await largeurs(), erreursJS: page.__err };
      await cap(page, 'saisie-' + (sans ? 'safari' : 'chromium'));
      await ctx.close();
    }
    console.log('fait saisie');
  }

  // Rien n'a été créé.
  const c9 = await browser.newContext({ storageState: etat });
  const p9 = await c9.newPage();
  await p9.goto(BASE + '/factures');
  res.facturesApres = await p9.locator('.frow').count();
  res.facturesAvant = nbFactures;
  await p9.goto(BASE + OFFRE);
  res.valeursOffre = { q: await p9.$$eval('.rows input[name=quantite]', a => a.map(e => e.value).join(', ')), p: await p9.$$eval('.rows input[name=prix]', a => a.map(e => e.value).join(', ')) };
  await c9.close();
  fs.writeFileSync(path.join(OUT, 'recette' + (filtre ? '-' + filtre.join('_') : '') + '.json'), JSON.stringify(res, null, 1));
  res.valeursAvant = valeursAvant;
  console.log('factures avant/après', nbFactures, res.facturesApres, '| valeurs avant', JSON.stringify(valeursAvant), '| après', JSON.stringify(res.valeursOffre));
  await browser.close();
})().catch(e => { console.error(e); process.exit(1); });
