// Captures avant / après pour la maquette des corrections responsive. Site de test, compte d'audit (données inventées).
// Lecture seule : GET et connexion. Les correctifs sont injectés dans la page, rien n'est envoyé au serveur.
const fs = require('fs');
const path = require('path');
const { chromium } = require(process.env.MK_PLAYWRIGHT);
const CSS = require('./correctifs.js');
const BASE = 'https://easy-offres.xtradev.ch';
const OUT = path.join(__dirname, 'out3');
fs.mkdirSync(OUT, { recursive: true });
const MDP = fs.readFileSync('//wsl.localhost/Ubuntu/home/xavie/.offregen-audit-compte', 'utf8').split('\n')[1].replace(/^[^:]*:\s*/, '').trim();

const APP = {
  'tel-360': { w: 360, h: 740, mobile: true, dpr: 2 },
  'tel-390': { w: 390, h: 844, mobile: true, dpr: 2 },
  'tel-430': { w: 430, h: 932, mobile: true, dpr: 2 },
  'tel-couche': { w: 844, h: 390, mobile: true, dpr: 2 },
  'tab-820': { w: 820, h: 1180, mobile: true, dpr: 1 },
  'tab-1180': { w: 1180, h: 820, mobile: true, dpr: 1 },
  'pc-900': { w: 900, h: 900, mobile: false, dpr: 1 },
  'pc-1024': { w: 1024, h: 768, mobile: false, dpr: 1 },
  'pc-1920': { w: 1920, h: 1080, mobile: false, dpr: 1 },
};

// Mesures, exécutées dans la page.
const MESURES = {
  micro: () => {
    const m = document.querySelector('.tabs .micro-central > span');
    if (!m || !m.getBoundingClientRect().width) return { recouvert: 'pas de micro visible' };
    const r = m.getBoundingClientRect();
    const sous = [...document.querySelectorAll('.bas .btn, .bas button, .bas a')].filter(b => !b.closest('.tabs')).map(b => {
      const q = b.getBoundingClientRect();
      const h = Math.min(q.bottom, r.bottom) - Math.max(q.top, r.top), w = Math.min(q.right, r.right) - Math.max(q.left, r.left);
      return h > 0 && w > 0 ? `${(b.innerText || b.getAttribute('aria-label') || '+').trim().slice(0, 20)} ${Math.round(w)}x${Math.round(h)} px` : null;
    }).filter(Boolean);
    const ab = document.querySelector('.bas .actionbar');
    const ecart = ab ? Math.round(r.top - [...ab.querySelectorAll('.btn')].reduce((a, b) => Math.max(a, b.getBoundingClientRect().bottom), 0)) : null;
    return { recouvert: sous.length ? sous.join(' ; ') : 'aucun', ecartBoutonMicro: ecart, micro: Math.round(r.width) };
  },
  contenu: () => {
    const bar = document.querySelector('.bar').getBoundingClientRect(), bas = document.querySelector('.bas').getBoundingClientRect();
    return { hauteurContenu: Math.round(innerHeight - bar.height - bas.height), vh: innerHeight, en_tete: Math.round(bar.height), bas: Math.round(bas.height) };
  },
  lignes: () => {
    const rows = [...document.querySelectorAll('.rows .row')].filter(r => r.querySelector('.nm'));
    let n = 0, max = 0, deb = 0;
    rows.forEach(row => {
      const a = row.querySelector('.nm').getBoundingClientRect();
      // Le contenu de la cellule quantité (champ, unité) déborde à gauche : on mesure ses enfants, pas la cellule.
      let pire = 0;
      row.querySelectorAll('.q > *, .q .saisie, .q .u').forEach(el => { const b = el.getBoundingClientRect(); if (!b.width) return; const w = Math.min(a.right, b.right) - Math.max(a.left, b.left), h = Math.min(a.bottom, b.bottom) - Math.max(a.top, b.top); if (w > 0 && h > 0) pire = Math.max(pire, Math.round(w)); });
      if (pire) { n++; max = Math.max(max, pire); }
      deb = Math.max(deb, Math.round(row.scrollWidth - row.clientWidth));
    });
    const nm = rows.map(r => Math.round(r.querySelector('.nm').getBoundingClientRect().height));
    const s = [...document.querySelectorAll('.saisie')];
    return { lignes: rows.length, designationsRecouvertes: n, recouvrementMax: max, debordementLigne: deb,
      cibleNomMin: Math.min(...nm), hauteurLigneMoy: Math.round(rows.reduce((t, r) => t + r.getBoundingClientRect().height, 0) / rows.length),
      saisiePolice: s.length ? getComputedStyle(s[0]).fontSize : null, saisiesTropEtroites: s.filter(e => e.scrollWidth > e.clientWidth + 1).map(e => e.value).join(', ') || 'aucune' };
  },
  facturer: () => {
    const e = document.querySelector('#echeance'); const d = e.closest('.duo'); const c = e.closest('details') || d.parentElement;
    const re = e.getBoundingClientRect(), rc = c.getBoundingClientRect(), rd = d.getBoundingClientRect();
    return { defilementHorizontal: document.documentElement.scrollWidth - document.documentElement.clientWidth, echeanceDepasseCarte: Math.max(0, Math.round(re.right - rc.right)), duoDeborde: Math.max(0, Math.round(d.scrollWidth - d.clientWidth)), largeurChamp: Math.round(re.width), colonnes: getComputedStyle(d).gridTemplateColumns };
  },
  formulaire: () => {
    const ch = [...document.querySelectorAll('.champ input, fieldset.adresse input')].filter(i => i.getBoundingClientRect().width);
    const larg = Math.max(...ch.map(i => Math.round(i.getBoundingClientRect().width)));
    const sc = document.querySelector('.screen').getBoundingClientRect();
    return { champLePlusLarge: larg, ecran: Math.round(sc.width) };
  },
  alignement: () => {
    const t = document.querySelector('#telephone').getBoundingClientRect(), i = document.querySelector('#ide').getBoundingClientRect();
    return { decalageTelephoneIDE: Math.round(Math.abs(t.top - i.top)), badge: !!document.querySelector('label[for=ide] .badge') };
  },
  filtres: () => {
    const f = document.querySelector('.fchips'); if (!f) return {};
    return { contenu: f.scrollWidth, visible: f.clientWidth, rangees: Math.round(f.getBoundingClientRect().height), masque: getComputedStyle(f).maskImage || getComputedStyle(f).webkitMaskImage };
  },
  largeur: () => {
    const a = document.querySelector('.app').getBoundingClientRect();
    return { app: [Math.round(a.left), Math.round(a.width)], grisGauche: Math.round(a.left), grisDroite: Math.round(innerWidth - a.right), vw: innerWidth };
  },
};

const SC = [
  { id: 'r1-tel', app: 'tel-390', url: '/offres/17', v: ['avant', 'R1A', 'R1B'], m: ['micro'], clip: 'bas' },
  { id: 'r1-tab', app: 'tab-820', url: '/offres/17', v: ['avant', 'R1A', 'R1B'], m: ['micro'], clip: 'bas' },
  { id: 'r1-cat', app: 'tel-390', url: '/catalogue', v: ['avant', 'R1A', 'R1B'], m: ['micro'], clip: 'bas' },
  { id: 'r2-offre', app: 'tel-couche', url: '/offres/17', v: ['avant', 'R2A', 'R2B'], m: ['contenu'] },
  { id: 'r2-cat', app: 'tel-couche', url: '/catalogue', v: ['avant', 'R2A'], m: ['contenu'] },
  { id: 'r3-430', app: 'tel-430', url: '/offres/17', v: ['avant', 'R3'], m: ['lignes'], vers: '.rows .row' },
  { id: 'r3-pc900', app: 'pc-900', url: '/offres/17', v: ['avant', 'R3'], m: ['lignes'], vers: '.rows .row' },
  { id: 'r4-360', app: 'tel-360', url: '/offres/17/facturer', v: ['avant', 'R4'], m: ['facturer'], ouvrir: true, vers: '#echeance' },
  { id: 'r4-pc1024', app: 'pc-1024', url: '/offres/17/facturer', v: ['avant', 'R4'], m: ['facturer'], ouvrir: true, vers: '#echeance' },
  { id: 'r5-390', app: 'tel-390', url: '/offres/17', v: ['avant', 'R5'], m: ['lignes'], vers: '.rows .row' },
  { id: 'r6-1180', app: 'tab-1180', url: '/clients/nouveau', v: ['avant', 'R6'], m: ['formulaire'] },
  { id: 'r6-ent', app: 'tab-820', url: '/reglages/entreprise', v: ['avant', 'R6'], m: ['formulaire'] },
  { id: 'r7-390', app: 'tel-390', url: '/reglages/entreprise', v: ['avant', 'R7'], m: ['alignement'], vers: '#telephone' },
  { id: 'r8-390', app: 'tel-390', url: '/catalogue', v: ['avant', 'R8'], m: ['filtres'], clip: 'haut' },
  { id: 'r8-1180', app: 'tab-1180', url: '/catalogue', v: ['avant', 'R8'], m: ['filtres'], clip: 'haut' },
  { id: 'r9-390', app: 'tel-390', url: '/offres/17', v: ['avant', 'R9'], m: ['lignes'], vers: '.rows .row', montrerCible: true },
  { id: 'r10a-900', app: 'pc-900', url: '/offres/17', v: ['avant', 'R10aB'], m: ['largeur', 'micro'] },
  { id: 'r10b-accueil', app: 'pc-1920', url: '/', v: ['avant', 'R10bB'], m: ['largeur'] },
  { id: 'r10b-offre', app: 'pc-1920', url: '/offres/17', v: ['avant', 'R10bB'], m: ['largeur'] },
];

(async () => {
  const filtre = process.argv[2] ? process.argv[2].split(',') : null;
  const browser = await chromium.launch({ executablePath: process.env.MK_CHROMIUM });
  const c0 = await browser.newContext();
  const p0 = await c0.newPage();
  await p0.goto(BASE + '/connexion');
  await p0.fill('#courriel', 'audit-responsive@exemple.ch');
  await p0.fill('#mot_de_passe', MDP);
  await Promise.all([p0.waitForNavigation(), p0.click('button[type=submit][form=connexion]')]);
  if (p0.url().includes('/connexion')) throw new Error('connexion refusée');
  const etat = await c0.storageState(); await c0.close();
  const res = fs.existsSync(path.join(OUT, 'mesures.json')) ? JSON.parse(fs.readFileSync(path.join(OUT, 'mesures.json'), 'utf8')) : {};
  const ctxs = {};
  for (const s of SC) {
    if (filtre && !filtre.includes(s.id)) continue;
    const a = APP[s.app];
    if (!ctxs[s.app]) ctxs[s.app] = await browser.newContext({ viewport: { width: a.w, height: a.h }, deviceScaleFactor: a.dpr, bypassCSP: true, isMobile: a.mobile, hasTouch: a.mobile, storageState: etat });
    res[s.id] = { appareil: s.app, url: s.url };
    for (const v of s.v) {
      const page = await ctxs[s.app].newPage();
      const err = []; page.on('pageerror', e => err.push(String(e).slice(0, 100)));
      await page.goto(BASE + s.url, { waitUntil: 'networkidle' });
      if (v !== 'avant') await page.addStyleTag({ content: CSS[v] });
      if (s.ouvrir) await page.evaluate(() => document.querySelectorAll('details').forEach(d => { d.open = true; }));
      if (s.vers) await page.evaluate(sel => { const e = document.querySelector(sel); if (e) e.scrollIntoView({ block: 'center' }); }, s.vers);
      if (s.montrerCible) await page.addStyleTag({ content: '.rows .row > a.nm { outline: 2px dashed #C4552D; outline-offset: -2px; }' });
      await page.waitForTimeout(500);
      const m = {};
      for (const k of s.m) Object.assign(m, await page.evaluate(MESURES[k]));
      if (err.length) m.erreursJS = err;
      res[s.id][v] = m;
      const opts = { path: path.join(OUT, `${s.id}__${v}.png`) };
      if (s.clip === 'bas') opts.clip = { x: 0, y: a.h - 300, width: a.w, height: 300 };
      if (s.clip === 'haut') opts.clip = { x: 0, y: 0, width: a.w, height: Math.min(a.h, 420) };
      await page.screenshot(opts);
      await page.close();
    }
    console.log(s.id, JSON.stringify(res[s.id]));
  }
  fs.writeFileSync(path.join(OUT, 'mesures.json'), JSON.stringify(res, null, 1));
  await browser.close();
})().catch(e => { console.error(e); process.exit(1); });
