# REVUE : fluidité, suite de la recette du 24.11

Un relecteur indépendant (Opus, lecture seule, diff de 706 lignes, sans navigateur ni phpunit) le 2026-11-24. Aucune trouvaille bloquante, aucune faille d'isolation ni d'injection. Chaque trouvaille relue dans le code par la session principale avant correction.

| N° | Trouvaille | Verdict | Suite |
|---|---|---|---|
| I1 | Touchers retenus avalés pour de bon si `app.js` plante, a une erreur de syntaxe ou ne vient pas | juste | `app.js` : `try/finally` autour du démarrage, `appPrete()` toujours appelé ; `fluide.js` : filet de 5 s après le chargement, toucher marqué `aria-busy`. Preuve : `banc/sans-app.js` |
| I2 | Un geste tapé pendant le chargement part avant les gestes rangés plus anciens, et l'écran reprend l'ancienne valeur | juste (fenêtre élargie par SR-1) | la file attend la relecture des gestes rangés (3 s au plus), qui passent devant ; une valeur plus récente n'est pas écrasée ; un geste sort de la file par son identité. Preuve : `banc/ordre-gestes.js` |
| I3 | Gestes de l'offre partis avant tout « Envoyer » : l'aperçu garde l'ancien PDF et le partage | juste (préexistant) | l'aperçu se refait dès que plus rien de l'offre n'attend, « Envoyer » touché ou non. Preuve : `I3=1 RETOUR=1 banc/sr5-sr6.js chromium aucun 1 1` |
| I4 | Un refus déclenche le rechargement de l'aperçu, qui efface le message de refus | juste | `geste-parti` porte `refus` ; l'aperçu ne se recharge pas sur un refus |
| I5 | MP-A19 : le lieu dicté qui ne se découpe pas est perdu à l'enregistrement ; un 422 perd le bandeau et le lieu | juste | champs cachés `dictee` et `adresse_ancienne`, gardés en texte si l'adresse reste vide, réaffichés sur un refus. Test `ClientsEcranTest` étendu |
| I6 | SR-6 : une fiche client modifiée hors de toute offre ne retient plus l'envoi | juste | `retientLePdf` compte aussi les gestes sur `/clients` sans offre |
| M1 | L'envoi noté en attente (ou archiver) bloque un second « Envoyer » avec un message faux | juste | exclus : `envoyee`, `archiver`, `restaurer`, `supprimer` d'une offre |
| M2 | « vos 2 changements (et 1 autre) » | juste | premier geste nommé, sinon rien entre parenthèses |
| M3 | Nom d'offre lu dans le titre d'une sous-page (« Ligne ») | juste | lu seulement sur l'écran de l'offre |
| M4 | Retenue : deux fenêtres de suite, Ctrl ou Cmd + clic perdu, fenêtre « PC seulement » retardée au téléphone | juste | seul le dernier toucher ; touches de modification et `data-en-feuille-pc` au téléphone passent |
| M5 | « Autre pièce » vidée : aucune puce cochée ; commentaire « mêmes puces que + Ligne » inexact | juste | la puce de départ revient ; aucune puce cochée au rendu si « Autre pièce » est remplie ; commentaire corrigé |
| M6 | Code mort : `file-vide`, branche `creer=1` | juste | `file-vide` retiré ; `creer=1` gardé, commenté : des pages « Pour qui » gardées dans les téléphones (14 jours) le postent encore, à retirer après le 2026-12-09 |
| M7 | Pages gardées avant un déploiement, hors réseau : leurs anciens scripts sont effacés du téléphone, la page s'ouvre sans JavaScript | juste, préexistant (`sw.js` efface l'ancienne version d'un fichier à la lecture de la nouvelle) | non corrigé : point ouvert pour Xavier |
| M8 | Morceaux reçus avant la fin de l'analyse de la page, jetés | non confirmé par le relecteur ; la file attend maintenant la fin du chargement (I2), ce qui l'écarte en pratique | aucune |
| M9 | `fluide.js` bloque l'affichage tant qu'il n'est pas arrivé | compromis voulu, mesuré (`ETAT.md`, SR-1) | aucune |
| M10 | « Envoyer » touché avant la relecture des gestes rangés | juste, préexistant, fenêtre de quelques dizaines de millisecondes | non corrigé |

Tests de I5 : écrits avec la correction, pas rejoués contre l'ancien code (le comportement testé n'existait pas : bandeau sur un 422, lieu gardé). Preuve de I2 : non rejouée contre l'ancien code.
