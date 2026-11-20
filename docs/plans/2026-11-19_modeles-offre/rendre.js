// Rend chaque feuille en JPEG de 900 px de large et contrôle : polices chargées, débordement, chasse des chiffres.
const path = require('path'), fs = require('fs');
const { chromium } = require(process.env.MK_PLAYWRIGHT);
const dir = __dirname, out = path.join(dir, 'MAQUETTE-modeles-v2-img');
fs.mkdirSync(out, { recursive: true });
const DOUZE = ['sobre', 'bandeau', 'atelier', 'technique', 'moderne', 'epure', 'chantier', 'recap', 'lateral', 'enseigne', 'marge', 'lettre'];
// Feuilles en plus : les deux totaux retouchés sur les lignes de Peinture Morel, puis les options et les variantes.
const PLUS = [['moderne', 'morel', 'moderne-morel.jpg'], ['epure', 'morel', 'epure-morel.jpg'], ['sobre', 'morel-options', 'sobre-options.jpg'], ['chantier', 'platrier-options', 'chantier-options.jpg']];
(async () => {
  const b = await chromium.launch({ executablePath: process.env.MK_CHROMIUM });
  const pg = await b.newPage({ viewport: { width: 794, height: 1123 }, deviceScaleFactor: 900 / 794 });
  const url = require('url').pathToFileURL(path.join(dir, 'generateur.html')).href;
  const jobs = DOUZE.map(m => [m, null, m + '.jpg']).concat(PLUS);
  let premier = true;
  for (const [m, d, f] of jobs) {
    await pg.goto(url + '#m=' + m + (d ? '&d=' + d : ''));
    await pg.reload();
    await pg.evaluate(() => document.fonts.ready);
    if (premier) {
      premier = false;
      await pg.evaluate(() => Promise.all(['Roboto', 'Archivo', 'Barlow Condensed', 'IBM Plex Sans', 'IBM Plex Mono', 'Source Serif 4'].map(f => document.fonts.load('20px "' + f + '"'))));
      const polices = await pg.evaluate(() => ['Roboto', 'Archivo', 'Barlow Condensed', 'IBM Plex Sans', 'IBM Plex Mono', 'Source Serif 4'].map(f => {
        const s = document.createElement('span'); s.style.cssText = 'position:absolute;visibility:hidden;font:20px "' + f + '"'; document.body.appendChild(s);
        const w = t => { s.textContent = t; return s.getBoundingClientRect().width; };
        const r = f + ' : chargée ' + document.fonts.check('12px "' + f + '"') + ', 1111=' + w('1111').toFixed(1) + ' 0000=' + w('0000').toFixed(1) + ' 8888=' + w('8888').toFixed(1);
        s.remove(); return r; }));
      console.log(polices.join('\n'));
    }
    const r = await pg.evaluate(() => {
      const a = document.querySelector('.a4'), A = a.getBoundingClientRect();
      const fin = [...a.querySelectorAll('.in > *')].pop().getBoundingClientRect().bottom - A.top;
      const p = a.querySelector('.pied, .bas').getBoundingClientRect().top - A.top;
      const large = [...a.querySelectorAll('.in table')].filter(t => t.scrollWidth > t.clientWidth + 1 || t.getBoundingClientRect().right > A.right - 30).length;
      return { fin: Math.round(fin), pied: Math.round(p), large };
    });
    await (await pg.$('.a4')).screenshot({ path: path.join(out, f), type: 'jpeg', quality: 82 });
    console.log(f.padEnd(22), 'fin', r.fin, 'pied', r.pied, r.fin <= r.pied - 8 ? 'TIENT' : 'DEBORDE', 'tables trop larges', r.large);
  }
  await b.close();
})();
