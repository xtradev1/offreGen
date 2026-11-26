// La fenêtre des deux derniers jours d'une démo (templates/_fenetre_demo.html.twig) : une fois par jour au plus et par
// compte, sur cet appareil. Sans stockage (navigation privée), elle se montre à chaque ouverture des offres.
(function () {
  'use strict';
  var f = document.querySelector('[data-fenetre-demo]');
  if (!f) { return; }
  var cle = 'fenetre-demo-' + f.getAttribute('data-fenetre-demo');
  // Le jour de l'appareil, pas celui d'UTC : « une fois par jour » se compte à l'heure de l'artisan.
  var d = new Date();
  var jour = d.getFullYear() + '-' + (d.getMonth() + 1) + '-' + d.getDate();
  try { if (localStorage.getItem(cle) === jour) { return; } } catch (e) { /* pas de stockage : elle se montre */ }
  f.hidden = false;
  f.querySelector('[data-continuer]').addEventListener('click', function () {
    f.hidden = true;
    try { localStorage.setItem(cle, jour); } catch (e) { /* pas de stockage : rien à retenir */ }
  });
})();
