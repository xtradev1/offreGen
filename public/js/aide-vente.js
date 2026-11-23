// L'aide à la vente (templates/admin/aide_vente.html.twig, chantier onboarding, lot 6) : une rubrique ouverte à la
// fois, toutes ouvertes à l'impression, et « Imprimer » sur PC.
(function () {
  'use strict';
  var rubriques = Array.prototype.slice.call(document.querySelectorAll('.aide-vente details'));
  var impression = false;
  rubriques.forEach(function (d) {
    d.addEventListener('toggle', function () {
      // Pendant l'impression, toutes restent ouvertes (l'événement « toggle » arrive après coup).
      if (impression || !d.open) { return; }
      rubriques.forEach(function (x) { if (x !== d) { x.open = false; } });
    });
  });
  var ouvertes = [];
  window.addEventListener('beforeprint', function () {
    impression = true;
    ouvertes = rubriques.map(function (d) { return d.open; });
    rubriques.forEach(function (d) { d.open = true; });
  });
  window.addEventListener('afterprint', function () {
    rubriques.forEach(function (d, i) { d.open = ouvertes[i]; });
    // Les « toggle » de la remise en état arrivent après : l'exclusivité revient ensuite.
    window.setTimeout(function () { impression = false; }, 0);
  });
  var imprimer = document.querySelector('[data-imprimer]');
  if (imprimer) { imprimer.addEventListener('click', function () { window.print(); }); }
})();
