// Avant l'envoi d'une offre qui porte encore le nom provisoire de la démo (templates/apercu_pdf.html.twig, chantier
// onboarding, lot 5) : le premier toucher sur « Envoyer » montre l'avertissement ; « Envoyer quand même » rejoue le
// toucher, que apercu-pdf.js reçoit comme d'habitude (partage du téléphone, offre notée envoyée).
(function () {
  'use strict';
  var avis = document.querySelector('[data-avertissement-envoi]');
  if (!avis) { return; }
  var accepte = false, lien = null;
  document.addEventListener('click', function (e) {
    var l = e.target.closest && e.target.closest('[data-partager]');
    if (!l || accepte) { return; }
    e.preventDefault();
    e.stopImmediatePropagation();
    lien = l;
    avis.hidden = false;
  }, true);
  avis.querySelector('[data-envoyer-quand-meme]').addEventListener('click', function () {
    accepte = true;
    avis.hidden = true;
    if (lien) { lien.click(); }
  });
  avis.addEventListener('click', function (e) { if (e.target === avis) { avis.hidden = true; } });
})();
