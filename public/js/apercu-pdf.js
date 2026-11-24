// Aperçu d'un PDF dans l'application (décision D7 A de Xavier, 2026-11-15) : PDF.js, livré avec l'application
// (public/js/vendor, version verrouillée), dessine chaque page dans un canevas à la largeur de l'écran.
// La CSP interdit eval : isEvalSupported est coupé. En cas d'échec, un lien ouvre le PDF.
// Chantier sans rechargement (lot 5, 2026-11-23) : les octets du PDF sont lus une fois ; ils servent au dessin et au
// partage, sans nouvelle requête ; l'envoi est noté sans recharger la page.
import * as pdfjs from '/js/vendor/pdfjs-6.5.438/pdf.min.js';

const zone = document.querySelector('[data-pdf]');
if (zone) {
  const etat = zone.querySelector('[data-pdf-etat]');
  pdfjs.GlobalWorkerOptions.workerSrc = zone.dataset.worker;
  // Le PDF lu une fois : le même fichier part au partage (le geste de l'artisan reste valable pour le téléphone, aucune
  // attente du réseau entre le toucher et le partage).
  let octets = null;
  const lire = fetch(zone.dataset.pdf, { credentials: 'same-origin' })
    .then((r) => (r.ok ? r.arrayBuffer() : Promise.reject(new Error('PDF ' + r.status))))
    .then((b) => { octets = b; return b; });
  const dessiner = async () => {
    // PDF.js garde le tampon qu'on lui donne : il reçoit une copie, l'original reste pour le partage.
    const doc = await pdfjs.getDocument({ data: new Uint8Array((await lire).slice(0)), isEvalSupported: false }).promise;
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
    // Au PC, on n'agrandit pas en pinçant (7b, 2026-11-19).
    const pc = window.matchMedia('(min-width: 1024px) and (hover: hover) and (pointer: fine)').matches;
    const geste = pc ? 'Ctrl + molette pour agrandir' : 'Pincer pour agrandir';
    etat.textContent = doc.numPages > 1 ? doc.numPages + ' pages · ' + geste.charAt(0).toLowerCase() + geste.slice(1) : geste;
    zone.appendChild(etat);
  };

  // L'envoi noté : par la file de fluide.js (sans rechargement ; sans réseau, il attend), sinon le formulaire. Le morceau
  // etat-envoi fait revenir le message « envoyée ».
  const noterEnvoi = () => {
    const marque = document.querySelector('form[data-apres-partage]');
    if (!marque) return;
    if (window.fluide) {
      // Sans réseau, le partage a eu lieu : l'envoi noté attend, l'écran le dit.
      window.fluide.envoyer(marque, {
        sansResume: true, page: window.location.pathname, morceaux: ['etat-envoi'],
        attente: () => window.fluide.messages.dire('PDF partagé. L’envoi sera noté au retour du réseau.'),
      });
      return;
    }
    marque.submit();
  };
  // Des changements de CETTE offre attendent le réseau : ce PDF ne les contient pas. « Envoyer » le dit, nomme le
  // changement et attend ; ses gestes partis, l'aperçu se refait avec le PDF à jour (revue du 2026-11-23, B-I6). Un
  // changement d'une autre offre ne retient rien (recette du 2026-11-24, SR-6 : il bloquait l'envoi d'un PDF juste).
  const base = window.location.pathname.replace(/\/apercu$/, '');
  const enAttente = () => (window.fluide && window.fluide.enAttentePour ? window.fluide.enAttentePour(base) : []);
  const NB = String.fromCharCode(0xA0);
  // Le premier changement nommé (une quantité, un prix) dit lequel ; sans nom, le message reste général (revue, M2).
  const nommer = (gestes) => {
    const nomme = gestes.find((g) => g.libelle);
    const quoi = nomme ? [nomme.libelle, nomme.ligneNom ? '«' + NB + nomme.ligneNom + NB + '»' : ''].filter(Boolean).join(', ') : '';
    const autres = quoi && gestes.length > 1 ? ', et ' + (gestes.length - 1) + (gestes.length > 2 ? ' autres' : ' autre') : '';
    const qui = gestes.length > 1 ? 'vos ' + gestes.length + ' changements' : 'votre changement';
    return 'Ce PDF n’a pas encore ' + qui + (quoi ? ' (' + quoi + autres + ')' : '') + '. Il se refera dès qu’' + (gestes.length > 1 ? 'ils seront partis.' : 'il sera parti.');
  };
  // Un changement de cette offre est parti (rangé sans réseau, renvoyé depuis cette page) : le PDF affiché, et ses octets
  // gardés pour le partage, sont d'avant ; l'aperçu se refait dès que plus rien de l'offre n'attend, « Envoyer » touché
  // ou non (revue du 2026-11-24, I3). Un refus ne change rien au serveur : pas de rechargement, son message reste (I4).
  document.addEventListener('geste-parti', (e) => {
    const d = e.detail || {};
    if (d.refus || !window.fluide.retientLePdf({ action: d.action, offre: d.offre }, base)) return;
    if (!enAttente().length) window.location.reload();
  });

  // « Envoyer » (décision D8) : le partage du téléphone, le PDF joint. Partage réussi : l'offre est notée envoyée
  // (formulaire data-apres-partage). Partage annulé : rien. Pas de partage de fichier (certains navigateurs de PC) : le
  // lien garde son effet, le PDF se télécharge, rien n'est noté.
  document.querySelectorAll('[data-partager]').forEach((lien) => {
    lien.addEventListener('click', async (e) => {
      const attendent = enAttente();
      if (attendent.length) {
        e.preventDefault();
        window.fluide.messages.dire(nommer(attendent), true);
        return;
      }
      if (!navigator.canShare) return;
      e.preventDefault();
      const nom = lien.getAttribute('download') || 'document.pdf';
      let fichier;
      try {
        fichier = new File([octets || await lire], nom, { type: 'application/pdf' });
      } catch (erreur) {
        window.location.assign(lien.href);
        return;
      }
      if (!navigator.canShare({ files: [fichier] })) {
        const url = URL.createObjectURL(fichier);
        const a = document.createElement('a');
        a.href = url;
        a.download = fichier.name;
        document.body.appendChild(a);
        a.click();
        a.remove();
        setTimeout(() => URL.revokeObjectURL(url), 10000);
        return;
      }
      try {
        await navigator.share({ files: [fichier], title: lien.dataset.titre || fichier.name });
      } catch (erreur) {
        return; // partage annulé : rien n'est envoyé
      }
      noterEnvoi();
    });
  });

  dessiner().catch(() => {
    etat.innerHTML = '';
    const lien = document.createElement('a');
    lien.href = zone.dataset.pdf;
    lien.textContent = 'Le PDF ne s’affiche pas ici' + String.fromCharCode(0xA0) + ': l’ouvrir';
    etat.appendChild(lien);
  });
}
