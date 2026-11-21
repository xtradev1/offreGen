// Sort le texte de chaque PDF du corpus avec le module de l'application (public/js/texte-pdf.js) et le PDF.js qu'elle embarque,
// dans Chromium : c'est le texte que le navigateur de l'artisan enverra. Écrit corpus/textes/<id>.txt.
const { chromium } = require(process.env.MK_PLAYWRIGHT);
const fs = require('fs');
const path = require('path');
const DEPOT = '//wsl.localhost/Ubuntu/home/xavie/offreGen/public';
const CORPUS = path.join(__dirname, '..', 'corpus');
const { offres, publiques } = JSON.parse(fs.readFileSync(path.join(__dirname, 'offres.json'), 'utf8'));
const fichiers = offres.map((o) => ({ id: o.id, chemin: path.join(CORPUS, 'inventees', o.id + '.pdf') }))
  .concat(publiques.map((p) => ({ id: p.id, chemin: path.join(CORPUS, p.fichier) })));
const TYPES = { '.js': 'text/javascript', '.pdf': 'application/pdf', '.html': 'text/html' };

(async () => {
  fs.mkdirSync(path.join(CORPUS, 'textes'), { recursive: true });
  const nav = await chromium.launch({ executablePath: process.env.MK_CHROMIUM });
  const page = await nav.newPage();
  await page.route('http://banc.test/**', (route) => {
    const u = new URL(route.request().url());
    if (u.pathname === '/') return route.fulfill({ contentType: 'text/html', body: '<!doctype html><title>banc</title>' });
    const pdf = fichiers.find((f) => '/pdf/' + f.id === u.pathname);
    const chemin = pdf ? pdf.chemin : DEPOT + u.pathname;
    if (!fs.existsSync(chemin)) return route.fulfill({ status: 404, body: 'absent' });
    route.fulfill({ contentType: TYPES[path.extname(chemin)] || 'application/octet-stream', body: fs.readFileSync(chemin) });
  });
  await page.goto('http://banc.test/');
  for (const f of fichiers) {
    const r = await page.evaluate(async (id) => {
      const pdfjs = await import('/js/vendor/pdfjs-6.5.438/pdf.min.js');
      pdfjs.GlobalWorkerOptions.workerSrc = '/js/vendor/pdfjs-6.5.438/pdf.worker.min.js';
      const { lignesDuPdf } = await import('/js/texte-pdf.js');
      const debut = performance.now();
      const t = await lignesDuPdf(pdfjs, { url: '/pdf/' + id });
      return { ...t, ms: Math.round(performance.now() - debut) };
    }, f.id);
    fs.writeFileSync(path.join(CORPUS, 'textes', f.id + '.txt'), r.lignes.join('\n'), 'utf8');
    console.log(`${f.id} : ${r.pages} page(s), ${r.lignes.length} lignes, ${r.caracteres} caractères, ${r.ms} ms`);
  }
  await nav.close();
})();
