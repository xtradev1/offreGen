# Recette : parcours de la dictée et constats des parcours

Tout ce qui suit se teste à la main, en une passe, sur le site de test (`https://easy-offres.xtradev.ch`), avec ton
compte de test, sur ton iPhone. Coche au fur et à mesure.

## Préalables

- [ ] Migrations SQL à jouer : aucune.
- [x] Code commité et poussé sur `chantier-voix-offre`, déployé sur le test le 2026-11-26 avec ton accord (« pas de
      vraies données »). `/sante` dit le commit en service.
- [ ] Données de départ : un catalogue avec au moins un produit à prix, un client déjà en fiche (pour le scénario 1).

## Scénario 1 : dictée propre, client connu, arrivée sur le PDF

| | |
|---|---|
| URL | `/` |
| Compte | ton compte de test |

Gestes :
1. Touche le micro au centre de la barre.
2. Dicte dix secondes : le nom d'un client qui a déjà sa fiche, puis deux produits du catalogue avec leur quantité.
3. Touche « Terminer ».

Attendu : dès le toucher, un écran sombre sur tout l'écran : « Le micro s'ouvre… », puis « Enregistrement », le
minuteur, une onde qui bouge avec la voix, une carte « Conseil 1 sur 3 » qui change toutes les huit secondes. Après
« Terminer », l'écran reste sombre : un anneau qui se remplit, « environ 10 s », trois étapes (Envoi de
l'enregistrement, Transcription de la dictée, Lignes de l'offre). Puis le PDF de l'offre s'ouvre tout seul, avec
« Modifier », « Facturer », « Envoyer ». Aucun toucher entre « Terminer » et le PDF.

Si ça casse : console du navigateur ; `public/js/app.js` (`nouvelleOffre`, `attente`, `ecoute`) ; la réponse de
`/enregistrements/<uuid>/etat` (champ `suite`).

## Scénario 2 : la pause (à prouver sur iPhone)

Gestes :
1. Micro, dicte dix secondes (un produit et sa quantité).
2. Touche « Pause ». Attends dix secondes en parlant d'autre chose.
3. Touche « Reprendre », dicte un second produit, puis « Terminer ».

Attendu : en pause, l'état dit « En pause » en orange, le minuteur ne bouge plus, le bouton dit « Reprendre ».
L'offre contient les deux produits dictés et rien de ce qui a été dit pendant la pause. Dans l'administration des
dictées, la durée de l'audio est d'environ vingt secondes, pas trente.

Si ça casse : dis-moi ce que montre l'écran (figé, erreur, offre incomplète) ; `app.js`, `solderPause` et le bouton
`[data-pause]`. Si Safari refuse la pause, le lot 6 est à refaire en parties séparées (décision fragile 2 du plan).

## Scénario 3 : l'alerte de silence

Gestes :
1. Micro, dis une phrase, puis tais-toi six secondes.
2. Reparle.

Attendu : après cinq secondes sans voix, la carte devient orange : « Le micro n'entend rien. Parlez plus près, ou
vérifiez qu'un casque Bluetooth ne l'a pas pris. » Elle redevient un conseil dès que tu reparles. L'onde est plate
pendant le silence.

Si ça casse : si l'onde ne bouge jamais et qu'aucune alerte ne vient, Safari a laissé le contexte audio suspendu
(décision fragile 3 du plan) ; `app.js`, `ecoute`.

## Scénario 4 : des lignes à relire

Gestes :
1. Dicte une offre avec un travail qui n'est pas dans ton catalogue, sans dire son prix.
2. « Terminer », attends.
3. Sur « Offre prête », touche « Relire les N lignes ».
4. Sur la ligne sans prix, touche le bouton du bas sans rien saisir, deux fois. Puis saisis un prix.
5. Valide chaque ligne.

Attendu : « Offre prête » montre le client, l'objet, « N lignes », « M à relire », sans aucun montant. Sur la ligne sans
prix, le clavier est ouvert dans le prix et le bouton dit « Indiquez le prix » ; le toucher ne montre aucun message
rouge. Le prix saisi, le bouton redevient « C'est juste ». La dernière ligne validée, le PDF s'ouvre tout seul.

Si ça casse : `public/js/fluide.js` (`relecture`), `RelectureControleur::valider`, `src/Offre/Arrivee.php`.

## Scénario 5 : un nouveau client

Gestes :
1. Dicte une offre pour un client qui n'a pas de fiche, avec des produits du catalogue.
2. « Terminer », attends.

Attendu : l'écran « Pour qui est cette offre ? » s'ouvre tout seul, le nom dicté en tête. « Créer la fiche », puis
« Enregistrer et voir le PDF » : le PDF s'ouvre.

## Scénario 6 : le même compte partout

Gestes : ouvre « Offres » avec une offre qui a des lignes à relire, dont une sans prix. Ouvre cette offre.

Attendu : la carte de la liste dit « N à relire » et le bouton de l'offre « Relire N lignes », avec le même N.

## Scénario 7 : la page de l'offre

Gestes :
1. Ouvre une offre sans représentant ni texte avant les lignes.
2. Touche « + Texte avant les lignes », écris une phrase, sors du champ, recharge la page.
3. Touche « + Représentant », choisis une fiche.

Attendu : sous l'objet, une seule ligne de deux liens ; les lignes de l'offre commencent plus haut qu'avant. Le texte
se déplie sur place ; rechargé, il a son champ « Texte avant les lignes » et son lien a disparu. Le représentant choisi,
sa carte est sous celle du client.

## Scénario 8 : ajouter une ligne

Gestes : sur une offre longue, « + Ligne », touche un produit, puis « Terminé ».

Attendu : dans la fenêtre, le champ « Chercher ou taper une ligne » vient avant « Elle va ». Après « Terminé », la page
montre la ligne ajoutée, éclairée un instant, le curseur dans sa quantité. Dis-moi si le clavier de l'iPhone s'ouvre :
je n'ai pas pu le voir.

## Scénario 9 : chercher une offre

Gestes : dans « Offres », tape le nom d'un client dans « Chercher une offre, un client », puis un mot qui n'existe pas.

Attendu : la liste se réduit à la frappe (objet, client ou numéro, sans tenir compte des accents) ; pour un mot
inconnu : « Aucune offre ne correspond. »

## Scénario 10 : la fiche d'une ligne

Gestes : sur une offre, touche la désignation d'une ligne. Puis fais de même pour une ligne offerte ou une option.

Attendu : désignation, quantité, unité et prix, puis « Plus de réglages », replié. Pour la ligne offerte ou l'option,
« Plus de réglages » est déjà ouvert.

## Scénario 11 : après l'envoi, et la déconnexion

Gestes :
1. Sur le PDF d'une offre, « Envoyer », va au bout du partage (envoie-la à toi-même).
2. Ouvre « Plus », puis « Réglages ».

Attendu : après le partage, « Offre N envoyée », et la barre du bas devient « Facturer », « Renvoyer », et en dessous
« Retour aux offres » en bouton principal. Dans « Plus », en bas : « Compte : ton adresse » et un lien « Se
déconnecter » ; plus de grand bouton. Dans les Réglages, au téléphone, plus de « Se déconnecter ».

## Scénario 12 : mettre la préparation en arrière-plan

Gestes :
1. Dans « Offres », dicte une offre, « Terminer ».
2. Dès que l'envoi est fait (première étape cochée), touche « Mettre en arrière-plan ».
3. Reste sur « Offres » sans rien recharger.

Attendu : pendant l'envoi, le bouton n'est pas là. Ensuite il est sous les étapes, avec la phrase « Vous pouvez
travailler sur autre chose ou quitter l'application ». Touché, la fenêtre se ferme et la dictée paraît sous « En
préparation ». Quand l'offre est prête, un bandeau « Offre prête » monte en bas, avec « Ouvrir » (ou « Relire » s'il
reste des lignes), et l'offre entre dans la liste. Si tu changes de page entre-temps, pas de bandeau : l'offre est dans
« Offres », et le courriel « offre prête » part comme avant.

Si ça casse : `public/js/app.js`, `suivreEnFond` ; la console du navigateur.

## Ce qui n'est PAS couvert

- **L'iPhone.** Tout a été joué dans Chromium (téléphone simulé de 390 px, 320 px, couché, PC). La pause, l'onde,
  l'alerte de silence et le clavier ouvert par le curseur restent à prouver sur ton appareil (scénarios 2, 3, 4, 8).
- **La durée de l'attente sur de vraies dictées.** L'anneau compte dix secondes plus 3 % de la durée dictée, d'après
  onze dictées du test, toutes de moins de quatre minutes. Sur une dictée de dix minutes, il peut rester à 95 % avec
  « encore un instant ».
- **Le PC et la tablette.** Aucune maquette ; vérifiés en capture seulement (rien ne déborde), sans parcours joué.
- **Un lecteur d'écran.** Les libellés et les annonces sont posés, aucun essai avec VoiceOver.
- **Les textes des conseils pour les activités « extérieur » et « prestations ».** La maquette n'a validé que ceux du
  bâtiment ; les deux autres jeux sont de moi, à relire (`src/Compte/Activite.php`).
- **La production.** Rien n'y est déployé, je n'y ai pas accès.

## État des vérifications automatiques

Voir la section « Preuves » de `ETAT.md`.
