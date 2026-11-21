// Essai PDF : sort de generateur.html un fichier HTML autonome par feuille, prêt pour dompdf.
// Variables CSS remplacées par leurs valeurs (dompdf ne les lit pas), logos rendus en JPEG (comme le logo d'un artisan),
// polices déclarées en TTF locales. Les cas « long » testent une offre de quarante lignes sur plusieurs pages.
const path = require('path'), fs = require('fs');
const { chromium } = require(process.env.MK_PLAYWRIGHT);
const dir = __dirname, out = path.join(dir, 'essai-pdf', 'html');
fs.mkdirSync(out, { recursive: true });
const POLICES = '/home/xavie/offreGen/docs/plans/2026-11-19_modeles-offre/essai-pdf/polices';
// [graisse, style, graisse du fichier] : une graisse sans fichier fait retomber dompdf sur DejaVu (vu au premier essai),
// on la déclare donc avec le fichier de la graisse voisine.
const FACES = [
  ['Roboto', 'roboto', [[400, 'normal'], [500, 'normal'], [700, 'normal'], [400, 'italic'], [600, 'normal', 700]]],
  ['Archivo', 'archivo', [[600, 'normal'], [700, 'normal'], [800, 'normal']]],
  ['Barlow Condensed', 'barlow-condensed', [[500, 'normal'], [600, 'normal'], [700, 'normal']]],
  ['IBM Plex Sans', 'ibm-plex-sans', [[400, 'normal'], [500, 'normal'], [600, 'normal'], [700, 'normal']]],
  ['IBM Plex Mono', 'ibm-plex-mono', [[400, 'normal'], [500, 'normal'], [600, 'normal'], [700, 'normal', 600]]],
  ['Source Serif 4', 'source-serif-4', [[400, 'normal'], [600, 'normal'], [700, 'normal'], [400, 'italic'], [600, 'italic']]],
];
const NL = String.fromCharCode(10);
const faces = FACES.map(([fam, id, vs]) => vs.map(([w, st, f]) => `@font-face { font-family: '${fam}'; font-style: ${st}; font-weight: ${w}; src: url('file://${POLICES}/${id}-${f || w}-${st}.ttf') format('truetype'); }`).join(NL)).join(NL);
fs.writeFileSync(path.join(dir, 'essai-pdf', 'polices.txt'), FACES.map(([, id, vs]) => vs.filter(v => !v[2]).map(([w, st]) => `${id} ${w} ${st}`).join(NL)).join(NL) + NL);

// Écarts de dompdf relevés au premier essai : interligne plus haut que dans le navigateur, box-sizing ignoré.
const PDF = `body { margin: 0; background: #fff; }
.a4 { line-height: 1.22; }
.a4 .coord, .a4 .adr, .m-lateral .cote .bloc, .m-lateral .cote .fond, .m-chantier .ref { line-height: 1.28; }
.m-lettre { line-height: 1.3; }
.m-technique { line-height: 1.1; }
.m-bandeau .carte { height: 86px; }`;
const UNE = `@page { size: A4; margin: 0; }
.a4 { height: 1122px; }`;
// Offre longue : marges de page. dompdf ne répète un élément fixe sur chaque page que s'il est enfant direct de body
// (essai du 2026-11-20 : imbriqué, il ne sort que sur une page). Pied, bande du bas et colonne sortent donc de la feuille.
const LONG = couleur => `@page { size: A4; margin: 46px 0 70px 0; }
body { font: 12px/1.22 Roboto, Arial, sans-serif; color: #1b1f24; }
.a4 { height: auto; overflow: visible; background: transparent; }
.a4 .in { padding-top: 0; }
.a4 .fin { page-break-inside: avoid; }
body > .pied { position: fixed; left: 64px; bottom: -44px; width: 666px; border-collapse: collapse; font-size: 9.5px; color: #5a616b; border-top: 1px solid #d9dde2; }
body > .pied td { padding-top: 7px; }
body.m-lateral > .pied { left: 244px; width: 494px; }
.m-bandeau .bande { margin-top: -46px; }
.a4 .totaux { page-break-inside: avoid; }
body.long > .bas { position: fixed; left: 0; bottom: -70px; width: 794px; height: 34px; border-collapse: collapse; font-size: 9.5px; background: ${couleur}; }
body.long > .cote { position: fixed; left: 0; top: -46px; width: 208px; height: 1122px; background: ${couleur}; color: #fff; }
body > .cote .lab { font-size: 8.5px; text-transform: uppercase; letter-spacing: .12em; }
body > .cote .gros { font: 800 26px/1.05 Archivo, Arial, sans-serif; }`;

const CAS = ['sobre', 'bandeau', 'atelier', 'technique', 'moderne', 'epure', 'chantier', 'recap', 'lateral', 'enseigne', 'marge', 'lettre'].map(m => [m, m, null, false])
  .concat([['sobre-options', 'sobre', 'morel-options', false], ['sobre-long', 'sobre', 'long', true], ['bandeau-long', 'bandeau', 'long', true], ['lateral-long', 'lateral', 'long', true], ['marge-long', 'marge', 'long', true]]);

(async () => {
  const b = await chromium.launch({ executablePath: process.env.MK_CHROMIUM });
  const pg = await b.newPage({ viewport: { width: 794, height: 1123 }, deviceScaleFactor: 3 });
  await pg.goto(require('url').pathToFileURL(path.join(dir, 'generateur.html')).href + '#m=sobre');
  await pg.evaluate(() => document.fonts.ready);
  const css = await pg.evaluate(() => document.querySelector('style').textContent);
  // Offre longue : six pièces, quarante lignes, sur les données de Peinture Morel.
  await pg.evaluate(() => {
    const pieces = ['Séjour', 'Cuisine', 'Chambre parentale', 'Chambre des enfants', 'Couloir et entrée', 'Salle de bains'];
    const postes = [['Protection des sols et des meubles', 1, 'forfait', 180], ['Rebouchage et ponçage des murs', 42, 'm²', 9], ['Peinture des murs, 2 couches', 42, 'm²', 28], ['Peinture des plafonds, 2 couches', 24, 'm²', 32], ['Laquage des plinthes', 26, 'm', 12], ['Laquage des portes et des cadres', 2, 'pce', 140], ['Peinture des radiateurs', 2, 'pce', 95]];
    const o = JSON.parse(JSON.stringify(JEUX.morel.o));
    o.titre = "Peinture complète de l'appartement"; o.num = '2026-051';
    o.g = pieces.map((nom, i) => ({ nom, l: postes.slice(0, i === 5 ? 5 : 7).map(([d, q, u, p]) => ({ d, q: u === 'forfait' ? 1 : q + i * 3, u, p })) }));
    JEUX.long = { e: JEUX.morel.e, o };
  });
  for (const [nom, m, jeu, long] of CAS) {
    await pg.evaluate(([m, jeu]) => { document.getElementById('pile').innerHTML = feuille(m, jeu); }, [m, jeu]);
    // Logo : capture JPEG du dessin, comme le fichier que l'application garde pour un artisan.
    const svgs = await pg.$$('.a4 .logo svg');
    const logos = [];
    for (const s of svgs) { const box = await s.boundingBox(); logos.push({ w: Math.round(box.width), h: Math.round(box.height), data: (await s.screenshot({ type: 'jpeg', quality: 92 })).toString('base64') }); }
    const r = await pg.evaluate(([logos, long]) => {
      const a = document.querySelector('.a4');
      const v = n => a.style.getPropertyValue(n).trim();
      const couleurs = { p: v('--p'), t: v('--t'), t2: v('--t2') };
      a.querySelectorAll('.logo').forEach((el, i) => { el.innerHTML = `<img src="data:image/jpeg;base64,${logos[i].data}" width="${logos[i].w}" height="${logos[i].h}" alt="">`; });
      a.removeAttribute('style');
      // Conditions et signatures d'un seul tenant, comme .doc-fin dans l'application.
      const cond = a.querySelector('.cond, .condp'), sign = a.querySelector('.sign');
      if (cond && sign && cond.parentNode === sign.parentNode) { const d = document.createElement('div'); d.className = 'fin'; cond.parentNode.insertBefore(d, cond); let n = cond; while (n) { const s = n.nextSibling; d.appendChild(n); if (n === sign) break; n = s; } }
      let tete = '';
      if (long) {
        a.querySelectorAll(':scope > .cote, :scope > .pied, :scope > .bas').forEach(el => { tete += el.outerHTML; el.remove(); });
        tete = tete.split('Page 1 / 1').join('');
      }
      return { html: tete + a.outerHTML, couleurs };
    }, [logos, long]);
    const feuilleCss = css.replace(/var\(--t2\)/g, r.couleurs.t2).replace(/var\(--t\)/g, r.couleurs.t).replace(/var\(--p\)/g, r.couleurs.p);
    if (/var\(/.test(feuilleCss)) throw new Error('variable CSS restante dans ' + nom);
    const html = ['<!doctype html>', `<html lang="fr-CH"><head><meta charset="utf-8"><title>${nom}</title>`, '<style>', faces, feuilleCss, PDF, long ? LONG(r.couleurs.p) : UNE, '</style></head>', `<body class="pdf${long ? ' long m-' + m : ''}">`, r.html, '</body></html>', ''].join(NL);
    fs.writeFileSync(path.join(out, nom + '.html'), html);
    console.log(nom.padEnd(16), (html.length / 1024).toFixed(0) + ' Ko', 'logos', logos.length, 'couleur', r.couleurs.p);
  }
  await b.close();
})();
