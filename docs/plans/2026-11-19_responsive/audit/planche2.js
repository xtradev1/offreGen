// Tour 2 de la maquette responsive : lignes de l'offre redessinées, micro dans la colonne d'onglets, trois volets.
// Lecture seule : toute requête autre que GET est coupée dans le navigateur (aucune saisie ne part au serveur).
const fs = require('fs');
const path = require('path');
const { chromium } = require(process.env.MK_PLAYWRIGHT);
const CSS = require('./correctifs2.js');
const BASE = 'https://easy-offres.xtradev.ch';
const OUT = path.join(__dirname, 'out4');
fs.mkdirSync(OUT, { recursive: true });
const MDP = fs.readFileSync('//wsl.localhost/Ubuntu/home/xavie/.offregen-audit-compte', 'utf8').split('\n')[1].replace(/^[^:]*:\s*/, '').trim();
if (!Object.values(CSS._verif).every(Boolean)) throw new Error('remplacement du rail raté ' + JSON.stringify(CSS._verif));

const APP = {
  'tel-360': { w: 360, h: 740, mobile: true, dpr: 2 },
  'tel-390': { w: 390, h: 844, mobile: true, dpr: 2 },
  'tel-430': { w: 430, h: 932, mobile: true, dpr: 2 },
  'tel-couche': { w: 844, h: 390, mobile: true, dpr: 2 },
  'tab-1180': { w: 1180, h: 820, mobile: true, dpr: 1 },
  'pc-900': { w: 900, h: 900, mobile: false, dpr: 1 },
  'pc-1920': { w: 1920, h: 1080, mobile: false, dpr: 1 },
};

function mesureLignes() {
  const rows = [...document.querySelectorAll('.rows .row')].filter(r => r.querySelector('.nm'));
  const rect = e => e.getBoundingClientRect();
  const inter = (a, b) => Math.max(0, Math.min(a.right, b.right) - Math.max(a.left, b.left)) * Math.max(0, Math.min(a.bottom, b.bottom) - Math.max(a.top, b.top));
  let recouvre = 0;
  rows.forEach(r => { const nm = rect(r.querySelector('.nm')); r.querySelectorAll('.q > *, .p .saisie, .tt, .del').forEach(e => { if (rect(e).width && inter(nm, rect(e)) > 0) recouvre++; }); });
  const s = [...document.querySelectorAll('.rows .saisie')];
  const qx = new Set(rows.map(r => Math.round(rect(r.querySelector('.p') || r).left)));
  const del = rows.map(r => { const d = r.querySelector('.del'); if (!d) return null; const a = rect(d), b = rect(r); return Math.round((a.top + a.height / 2) - (b.top + b.height / 2)); }).filter(v => v !== null);
  const st = s.length ? getComputedStyle(s[0]) : null;
  return {
    lignes: rows.length, chevauchementsAvecDesignation: recouvre,
    defilementH: document.documentElement.scrollWidth - document.documentElement.clientWidth,
    debordementLigne: Math.max(0, ...rows.map(r => r.scrollWidth - r.clientWidth)),
    hauteurLigneMoy: Math.round(rows.reduce((t, r) => t + rect(r).height, 0) / (rows.length || 1)),
    cibleNomMin: Math.min(...rows.map(r => Math.round(rect(r.querySelector('.nm')).height))),
    champHauteur: s.length ? Math.round(rect(s[0]).height) : null,
    champFond: st && st.backgroundColor, champPolice: st && st.fontSize,
    largeursQte: [...new Set(s.filter(e => e.name === 'quantite').map(e => Math.round(rect(e).width)))].join('/'),
    largeursPrix: [...new Set(s.filter(e => e.name === 'prix').map(e => Math.round(rect(e).width)))].join('/'),
    colonnesPrixAlignees: qx.size === 1,
    poubelleEcartAuCentre: del.length ? Math.max(...del.map(Math.abs)) : null,
    champsCoupes: s.filter(e => e.scrollWidth > e.clientWidth + 1).length,
  };
}
function mesureBas() {
  const bar = document.querySelector('.bar').getBoundingClientRect(), bas = document.querySelector('.bas').getBoundingClientRect();
  const m = document.querySelector('.tabs .micro-central > span').getBoundingClientRect();
  return { hauteurContenu: Math.round(innerHeight - bar.height - bas.height), microCentreY: Math.round(m.top + m.height / 2), vh: innerHeight, microX: Math.round(m.left + m.width / 2) };
}
function mesureLargeur() {
  const a = document.querySelector('.app').getBoundingClientRect();
  const doc = document.querySelector('.colonne-doc'), liste = document.querySelector('.colonne-liste');
  const vis = e => e && e.getBoundingClientRect().width > 0 && getComputedStyle(e).display !== 'none';
  return { app: [Math.round(a.left), Math.round(a.width)], liste: vis(liste), document: vis(doc), grisDroite: Math.round(innerWidth - a.right - (vis(doc) ? doc.getBoundingClientRect().width : 0)), defilementH: document.documentElement.scrollWidth - document.documentElement.clientWidth };
}

const SC = [
  { id: 'l390', app: 'tel-390', url: '/offres/17', v: ['avant', 'lignesA', 'lignesB'], m: mesureLignes, vers: '.rows .row' },
  { id: 'l360', app: 'tel-360', url: '/offres/17', v: ['lignesA'], m: mesureLignes, vers: '.rows .row' },
  { id: 'l430', app: 'tel-430', url: '/offres/17', v: ['lignesA'], m: mesureLignes, vers: '.rows .row' },
  { id: 'lsaisie', app: 'tel-390', url: '/offres/17', v: ['lignesA'], m: mesureLignes, vers: '.rows .row', taper: true },
  { id: 'l1180', app: 'tab-1180', url: '/offres/17', v: ['avant', 'lignesA'], m: mesureLignes },
  { id: 'rail', app: 'tel-couche', url: '/offres/17', v: ['railMilieu', 'railBas'], m: mesureBas },
  { id: 'pc900', app: 'pc-900', url: '/offres/17', v: ['pcMilieu'], m: mesureLignes },
  { id: 'volets', app: 'pc-1920', url: '/offres/17', v: ['avant', 'troisVolets'], m: mesureLargeur, docCote: true },
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
  const res = {};
  let coupes = 0;
  for (const s of SC) {
    if (filtre && !filtre.includes(s.id)) continue;
    const a = APP[s.app];
    res[s.id] = {};
    for (const v of s.v) {
      const ctx = await browser.newContext({ viewport: { width: a.w, height: a.h }, deviceScaleFactor: a.dpr, bypassCSP: true, isMobile: a.mobile, hasTouch: a.mobile, storageState: etat });
      await ctx.route('**/*', r => { if (r.request().method() !== 'GET') { coupes++; return r.abort(); } return r.continue(); });
      const page = await ctx.newPage();
      const err = []; page.on('pageerror', e => err.push(String(e).slice(0, 100)));
      await page.goto(BASE + s.url, { waitUntil: 'networkidle' });
      if (CSS[v]) await page.addStyleTag({ content: CSS[v] });
      if (s.docCote && v !== 'avant') { await page.click('.ohead .doc-cote'); await page.waitForSelector('.colonne-doc:not([hidden])'); await page.waitForTimeout(1200); }
      if (s.vers) await page.evaluate(sel => { const e = document.querySelector(sel); if (e) e.scrollIntoView({ block: 'start' }); window.scrollBy(0, -70); }, s.vers);
      await page.waitForTimeout(500);
      const m = await page.evaluate(s.m);
      if (s.taper) {
        // Un prix à 5 chiffres et une quantité décimale. app.js envoie au « change » par fetch keepalive, que
        // l'interception de Playwright ne voit pas (incident du 2026-11-19 : 125.5 enregistré, puis rétabli). On retire
        // donc l'envoi automatique des champs avant de taper : plus rien ne peut partir.
        await page.evaluate(() => document.querySelectorAll('[data-envoi-change]').forEach(e => e.removeAttribute('data-envoi-change')));
        const prix = page.locator('.rows .row .p .saisie').nth(2), qte = page.locator('.rows .row .q .saisie').nth(1);
        await qte.click(); await qte.fill('125.5');
        await prix.click(); await prix.fill('12500');
        await page.waitForTimeout(300);
        m.apresSaisie = await page.evaluate(s.m);
      }
      if (err.length) m.erreursJS = err;
      res[s.id][v] = m;
      await page.screenshot({ path: path.join(OUT, `${s.id}__${v}.png`) });
      await ctx.close();
    }
    console.log(s.id, JSON.stringify(res[s.id]));
  }
  console.log('requêtes non GET coupées :', coupes);
  fs.writeFileSync(path.join(OUT, 'mesures.json'), JSON.stringify(res, null, 1));
  await browser.close();
})().catch(e => { console.error(e); process.exit(1); });
