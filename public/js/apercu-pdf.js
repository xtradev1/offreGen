// Aperçu d'un PDF dans l'application (décision D7 A de Xavier, 2026-11-15) : PDF.js, livré avec l'application
// (public/js/vendor, version verrouillée), dessine chaque page dans un canevas à la largeur de l'écran.
// La CSP interdit eval : isEvalSupported est coupé. En cas d'échec, un lien ouvre le PDF.
import * as pdfjs from '/js/vendor/pdfjs-6.5.438/pdf.min.js';

const zone = document.querySelector('[data-pdf]');
if (zone) {
  const etat = zone.querySelector('[data-pdf-etat]');
  pdfjs.GlobalWorkerOptions.workerSrc = zone.dataset.worker;
  const dessiner = async () => {
    const doc = await pdfjs.getDocument({ url: zone.dataset.pdf, isEvalSupported: false }).promise;
    const largeur = zone.clientWidth;
    const densite = Math.min(window.devicePixelRatio || 1, 3);
    for (let n = 1; n <= doc.numPages; n++) {
      const page = await doc.getPage(n);
      const echelle = largeur / page.getViewport({ scale: 1 }).width;
      const vue = page.getViewport({ scale: echelle * densite });
      const toile = document.createElement('canvas');
      toile.width = Math.floor(vue.width);
      toile.height = Math.floor(vue.height);
      toile.style.width = Math.floor(vue.width / densite) + 'px';
      toile.setAttribute('role', 'img');
      toile.setAttribute('aria-label', 'Page ' + n + ' sur ' + doc.numPages);
      zone.appendChild(toile);
      await page.render({ canvas: toile, canvasContext: toile.getContext('2d'), viewport: vue }).promise;
    }
    etat.textContent = doc.numPages > 1 ? doc.numPages + ' pages · pincer pour agrandir' : 'Pincer pour agrandir';
    zone.appendChild(etat);
  };
  dessiner().catch(() => {
    etat.innerHTML = '';
    const lien = document.createElement('a');
    lien.href = zone.dataset.pdf;
    lien.textContent = 'Le PDF ne s’affiche pas ici : l’ouvrir';
    etat.appendChild(lien);
  });
}
