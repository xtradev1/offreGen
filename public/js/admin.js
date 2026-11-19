// Partie administration. Fichier à part de app.js : la CSP n'accepte que les scripts du site, aucun script en ligne.
(function () {
  'use strict';
  // Compteur : les boutons − et + changent le nombre du champ voisin, dans ses bornes. Sans ce script, les boutons
  // restent cachés et le champ se remplit au clavier.
  document.querySelectorAll('[data-pas]').forEach(function (bouton) {
    var champ = bouton.parentNode.querySelector('input[type="number"]');
    if (!champ) { return; }
    bouton.hidden = false;
    bouton.addEventListener('click', function () {
      var valeur = (parseInt(champ.value, 10) || 0) + parseInt(bouton.getAttribute('data-pas'), 10);
      var mini = champ.min === '' ? valeur : Number(champ.min);
      var maxi = champ.max === '' ? valeur : Number(champ.max);
      champ.value = String(Math.min(maxi, Math.max(mini, valeur)));
    });
  });
})();
