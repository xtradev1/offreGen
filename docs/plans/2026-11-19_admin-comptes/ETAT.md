# Chantier : administration des comptes et des démos

- statut : en_cours (gate visuel franchi le 2026-11-19 pour les lots A et B)
- phase : lot A en 5 (lot 0 fait) ; lot B en 2 (découpage écrit, plans par sous-lot à venir)
- Demande de Xavier, 2026-11-19 : « fais la page d'administration, maquette d'abord » ; « est-ce que maintenant on peut déjà créer un compte facile pour les démos ».

## Suivi Origin

- Projet 44 (offreGen), tâche 458 (Passage à l'échelle), sous-tâche 460 (compte créé en deux clics par l'exploitant). Conversation rattachée le 2026-11-19.

## En attente de toi

- Dire si le lot 0 et les documents du chantier se commitent, et qui passe en premier entre ce chantier et celui des métiers (questions du 19.11, sans réponse).

## Prochaine action

Lot A : `PLAN-lot-A.md` écrit le 2026-11-19. Lot 0 fait, non commité. Lot 1 (migration) puis lot 2 (traceur `/admin`) dès que les autres sessions ont commité `public/css/app.css`, `public/js/app.js` et `CODING_RULES.md` (modifiés et non commités le 19.11 à 12:25), et sur accord de Xavier pour l'ordre entre ce chantier et celui des métiers, qui touchent les mêmes fichiers (`src/Compte/Comptes.php`, table `artisan`).

Lot B : découpé en cinq sous-lots dans `DECOUPAGE-lot-B.md`, après le lot A. Préalables à fournir par Xavier : créancier des QR-factures, TVA, conditions générales, compte Stripe, site de production.

## Décisions

Réglées avant ce chantier :
- G1 (sous-chantier 26, 2026-11-17) : partie `/admin` séparée, sa mise en page, son menu (à gauche sur PC).
- A1 (2026-11-18) : liste des comptes validée ; droit administrateur, 404 pour tout autre compte (livré le 18.11).
- A2 (2026-11-18) : formulaire et fiche refusés, « il faut plus travailler l'ui ça doit avoir une super expérience ».
- A3 = A (2026-11-18) : accès d'un artisan par courriel, mot de passe choisi par lui (lien de 7 jours).

Réponses de Xavier du 2026-11-19 (maquette v1) :
- D1 = A (QR, connexion directe) ; D2 = A (un compte neuf par démo) ; D4 valider (parcours de démo) ; D8 valider (vue PC).
- D6 : « je comprends pas ce que c'est » (reformulée en v2). D7 : pas valider, « faut rendre ça mieux d'un point de vue ux/ui ». D3 et D5 sans réponse.
- Couleurs : « pourquoi il n'y a pas d'orange », « il manque toute la charte graphique ». Constat de Claude : logo et polices absents, boutons secondaires gris, étiquettes de type aux couleurs d'état ; la charte limite le terracotta au logo, au micro et au contour actif. V2 : charte appliquée, rond central terracotta = « Nouvelle démo », filet terracotta sous le bandeau, rôle cerclé de terracotta.
- Pas de titre dans le bandeau, « Admin » y est permis ; menu en bas (artisans, coût de l'IA, offres, rentabilité), coût de l'IA peut-être hors accès rapide.
- Démo : 10 offres, augmentables ; durée 7 ou 14 jours (à trancher). Essai : 10 offres par défaut, 20 au besoin. Une démo peut devenir un essai.
- Métiers : en ajouter, et pouvoir écrire un métier (noté au roadmap).
- Rôles : administrateur (tout), commercial (crée des démos, ne voit que ses artisans, pas le coût de l'IA, un tableau de bord à lui), modérateur (tous les artisans, offrir des offres, suivi des offres) ; comptable plus tard.

Réponses de Xavier du 2026-11-19 (maquette v2) :
- R1 valider. R2 = A, « c'est le client qui gère cet accès, ça rend le truc fiable ». R3 valider, « rentabilité cachée dans plus et tableau de bord à la place, dans l'ordre tableau, artisans, offre ». R4 = B, « artisans avec x offres par mois, pas possible de s'augmenter son propre compte en commercial, il faut juste me montrer comment tu passes de l'app au back ». D5 valider « mais avec les icônes ». D7 valider.
- Départ d'un commercial : validé. Démo : active 7 jours, proposition de l'essai deux jours avant la fin, gardée jusqu'au 30e jour puis supprimée. Durée de l'essai venu d'une démo : « 3 semaines ou 1 mois, ou simple » (P1). Question sur le catalogue d'une démo transformée (P2). « Comment on améliore » le passage au paiement (P3). Lien de démo à envoyer par WhatsApp ou courriel en plus du QR (E2).
- Boutons : Xavier croyait avoir validé des boutons orange. Vérifié : O2 du 18.11 = terracotta en accent, boutons anthracite ; blanc sur terracotta 4,48:1, noir 4,68:1, sous 7:1 (C1).

Réponses de Xavier du 2026-11-19 (maquette v3) :
- C1 = A (boutons anthracite). E1 valider. E2 valider. P2 = A (catalogue gardé, prix d'exemple à vérifier). P3 valider.
- P1 : « premier mois gratuit, 2 sur abonnement pris pour 1 an, 2eme mois gratuit offert que la première fois puis 1 mois par an ». Lecture de Claude, à confirmer (P1 de la v4) : essai d'un mois gratuit ; annuel = 2 mois offerts la première année, 1 mois les suivantes.
- Demandes : un message toujours présent pour passer à l'essai, possible dès le premier jour ; une fenêtre à l'ouverture deux jours avant la fin (« passez à l'essai ou continuez ») ; « Vous gardez tout » ; à la fin de la démo, « un truc du style j'aimerais être contacté », message au commercial ou à Xavier ; formules décrites simplement, en pleine page, avec « recommandé » ; « à rappeler » dans le suivi du commercial et un courriel au commercial ; « pas de dark pattern », rappeler sans forcer.

Contradiction à trancher par Xavier (P2 de la v4) : P2 = A ici (prix d'exemple gardés, marqués à vérifier) contre le chantier des métiers, où Xavier a répondu « Tout me convient » à M4 (l'essai garde les désignations, prix à 0). Claude recommande les prix à 0.

Réponses de Xavier du 2026-11-19 (maquette v4) :
- N1 valider. K1 valider. F1 valider. S1 valider. P1 : « Oui, c'est ça ».
- T1 : « l'essaie gratuit c'est choisir un abonnement, puis il débute l'abbonnement avec l'essaie gratuit. » L'essai séparé disparaît : le premier mois de la formule choisie est gratuit.
- P2 = B (prix d'exemple gardés, à vérifier), confirmé contre la proposition du chantier des métiers (prix à 0). À répercuter dans `docs/plans/2026-11-19_metiers-demo/SPEC.md` par sa session.
- F2 = B, « peut etre pas solo puis pro, mais d'autre termes car une boite de 5 gars ou seul le patron fais les offres prendra équivalent solo ». F3 : « pour l'instant enlève » (prix de lancement retiré).
- P4 : « plafond de l'offre choisi. mon seul point c'est pour la grande offre ça peut me couter cher comment je gère cela. » Réponse chiffrée dans la v5 (pire mois gratuit : CHF 31.20), question G2.
- R5 valider, « 7 jours avant la fin, 2 jours avant et 12h avant si toujours pas ».
- M1 valider, « enlève éléctricien ça me parait un métier trop complexe a gérer trop de pièce ». À dire aussi à la session des métiers.

Réponses de Xavier du 2026-11-19 (maquette v5) :
- V1 valider. G1 = A, « on pourra a la limite le faire quand y'a carte de crédit et stripe car la c'est automatique mais je cours pas après les gens ». G3 = A (Essentiel, Volume, Équipe). G2 = B, « c'est a surveiller et je mettrais les plus grande offre dispo seulement si le besoin ce fais sentir sinon elle seront dispo plus tard quand j'ai deja 15-20 clients » : au lancement, Essentiel seule.
- Dit de vive voix : Volume à 2 comptes ; plus d'infos sur la carte de formule (offres, factures, clients compris) ; prix annuel barré avec un pourcentage ; une promo pour les premiers, à réfléchir ; le mois gratuit commence au bouton, sans la mention du mot de passe ; proposer l'année et un mois offert au moment de la facture ; rappel plus propre quand la QR-facture n'est pas encore payée ; Stripe à étudier pour automatiser.
- G4, sens donné par Xavier : deux chemins. L'artisan commence seul son mois gratuit par le site, ou un commercial lance une démo par QR, qui propose le mois gratuit. Le formulaire « Nouvel essai » (D5) est retiré.

Réponses de Xavier du 2026-11-19 (maquette v6) :
- H1 valider. H5 valider. H7 valider.
- H3 : « il faut le prix par mois, offre 2 mois la première + le mois gratuit d'essai ». Appliqué : à l'année, CHF 37.42 par mois affiché, CHF 449.00 pour 12 mois en plus du mois gratuit (17 % de moins), puis CHF 493.90 par an.
- H4 : « a voir j'ai pas encore décidé comment je fais ». Aucune promo construite.
- H2 = A, « dans un premier temps regarde et tant que j'ai environ 10 abonnée pas besoin de faire par carte, mais je veux déjé l'intégrer ». Lecture de Claude : la carte est intégrée et livrée éteinte ; au lancement tout se paie par QR-facture, au mois comme à l'année.

Découpage décidé par Claude le 2026-11-19 :
- Lot A (tout est validé) : champ d'adresse des pages de mot de passe ; partie `/admin`, rôles administrateur, modérateur, commercial ; équipe ; liste et fiche des artisans ; démo (métier, QR, lien par message, ruban, fenêtre, fin, « Demander un appel ») ; suivi du commercial et son courriel.
- Lot B (attend V1, G1 à G4, et les tâches Origin 461 et 462) : formules, mois gratuit, première facture, rappels à 7 jours, 2 jours et 12 heures.

Prises par Claude, à contester par Xavier dans la maquette :
- La gestion des comptes vit dans la rubrique « Artisans » du menu de G1 ; les classements du sous-chantier 26 s'y ajouteront.
- Le droit administrateur reste en commande (`bin/administrateur`) : un bouton ferait d'une session volée une porte d'entrée.
- Une démo ne reçoit jamais de courriel (règle du 18.11).
- « Demander un appel » au lieu de « J'aimerais être contacté » : infinitif de la charte, sans accord au masculin (K1).
- Formules : deux cartes (Solo au prix de lancement, Équipe) au lieu des trois de la v3. « Fondateur » est un tarif (tâche 461), pas une formule ; Solo et Fondateur côte à côte font un leurre (avis du relecteur aussi). Un seul bouton en bas, aucune carte choisie d'avance (F1, F2).
- Fenêtre de rappel : une fois par jour au plus, jamais pendant une dictée ni un envoi (R5).
- D6 tranchée par Claude après deux tours sans réponse : lien d'accès à l'écran seulement quand le site n'envoie pas de courriels (le test), jamais en production.
- Le QR de connexion se fait avec `endroid/qr-code`, déjà installé par `sprain/swiss-qr-bill` (à vérifier dans `composer.lock` au plan).

## Journal

- 2026-11-19 : compte de Xavier créé sur le test (`xavier.frelechoz@xtradev.ch`, entreprise XtraDev, administrateur), accès par le lien du courriel écrit dans `partage/var/courriels` (le test n'envoie pas de courriels).
- 2026-11-19 : phase 1 sans `SPEC.md` séparée : l'intention tient dans la demande de Xavier et dans G1, A1, A2, A3 déjà tranchés. Phase 2 sautée : un seul lot.
- 2026-11-19 : phase 3, `MAQUETTE-comptes.html` dans la charte Dicta : parcours de démo en trois touchers (QR de connexion directe), essai refait, deux fiches, vue PC. Gate visuel ouvert.
- 2026-11-19, défaut à corriger (livré le 18.11, signalé par Xavier : Bitwarden a mal enregistré son mot de passe) : `mot_de_passe_nouveau.html.twig` et `reglages_mot_de_passe.html.twig` n'ont aucun champ `autocomplete="username"`, le gestionnaire ne sait pas à quel compte rattacher le nouveau mot de passe. Correction : l'adresse du compte en champ lisible, en lecture seule, `autocomplete="username"`, dans les deux formulaires, plus un test. En attente du commit de la session « responsive », qui modifie ces deux fichiers. Nouveau lien d'oubli généré pour Xavier par l'interface du test.

## Preuves

```
$ node check.js MAQUETTE-comptes.html
bureau : largeur 1440/1440, images 0, cassées 0, kit oui
telephone : largeur 390/390, images 0, cassées 0, kit oui
$ node captures-comptes.js (bureau clair, téléphone, bureau sombre ; écrans regardés un par un)
bureau défauts [] largeur 1440 erreurs []
tel défauts [] largeur 390 erreurs []
sombre défauts [] largeur 1440 erreurs []
$ grep des tirets longs (cadratin, demi-cadratin, entité HTML) sur MAQUETTE-comptes.html
0
```

Maquette v2 (2026-11-19) : check.js et captures (bureau clair, téléphone, bureau sombre), six blocs regardés un par un. Corrigés à la capture : étiquette « Essai » verte (couleur de « Prête ») passée en neutre cerclée, coche collée au bandeau, étiquette invisible dans la ligne choisie sur PC. Défauts mesurés : aucun, largeur 1440 et 390, aucune erreur JS, aucun tiret long.

Maquette v3 (2026-11-19) : message de démo relu par `relecteur-xtradev`, « À corriger » au premier tour (délai « quelques minutes » non mesuré, « compte 10 offres » ambigu, issue manquante si le lien a servi, espace insécable), « Prêt » au second. check.js vert, captures bureau, téléphone et sombre regardées ; corrigés à la capture : libellés de la barre du commercial sur deux lignes, jauge coupée, chiffres de la démo contradictoires (offre d'exemple comptée). Contrastes du bouton terracotta mesurés par la formule WCAG.

Erreur de Claude, 2026-11-19 : la v3 affirmait que le site de test tournait sur la version de la veille. Faux à l'heure de l'écriture : `current/REVISION` du test = `dabc3d9`, version envoyée le 19.11 à 10:20 (lu par SSH). Phrase corrigée dans la v3, dite à Xavier dans la v4.

Maquette v4 (2026-11-19) : textes vus par l'artisan et courriel au commercial relus par `relecteur-xtradev`, « À corriger » aux tours 1 et 2, « Prêt » au tour 3. Après la relecture, un libellé a changé sans nouveau tour : « Choisir Solo » et « Choisir Équipe » remplacés par un bouton unique « Choisir une formule » puis « Continuer avec Solo » (un seul bouton principal par écran, règle de la charte).

```
$ node check.js MAQUETTE-parcours-v4.html
bureau : largeur 1440/1440, images 0, cassées 0, kit oui
telephone : largeur 390/390, images 0, cassées 0, kit oui
$ node captures-v4.js (sept blocs, bureau clair, téléphone, bureau sombre, regardés)
bureau défauts [] largeur 1440 erreurs []
tel défauts [] largeur 390 erreurs []
sombre défauts [] largeur 1440 erreurs []
$ node bascule-v4.js (parcours cliqué)
au mois   : ["CHF 39.00par mois","CHF 179.00par mois"]
à l'année : ["CHF 390.00la première année","CHF 1’790.00la première année"]
bouton    : Choisir une formule | btn inactif
après Solo: Continuer avec Solo | btn principal | cochées 1
clavier   : ["CHF 39.00par mois","CHF 179.00par mois"]
choix     : N1 T1 P2 K1 F1 F2 F3 P1 P4 S1 R5 M1
erreurs JS: []
```

Maquette v5 (2026-11-19) : textes relus par `relecteur-xtradev`, « À corriger » au tour 1 (fenêtre qui promettait « sans coupure », issue du mois gratuit absente de la page des formules), une seule remarque au tour 2 (majuscule après un deux-points, sans objet : libellé et valeur sont deux cellules). Coûts du tableau calculés sur `PROFILS-USAGE.md` (2,3, 3,8 et 10,4 centimes par offre).

```
$ node check.js MAQUETTE-mois-gratuit-v5.html
bureau : largeur 1440/1440, images 0, cassées 0, kit oui
telephone : largeur 390/390, images 0, cassées 0, kit oui
$ node captures-v5.js ; node bascule-v5.js
bureau défauts [] ; tel défauts [] ; sombre défauts []
au mois   : ["CHF 44.90par mois","CHF 89.00par mois","CHF 179.00par mois"]
à l'année : ["CHF 449.00la première année","CHF 890.00la première année","CHF 1’790.00la première année"]
bouton    : Choisir une formule | btn inactif
après un toucher sur Volume : Continuer avec Volume | btn principal | cochées 1
choix     : V1 G1 G3 G4 G2
erreurs JS: []
```

Plan du lot A (2026-11-19) : format de la phase 4 du skill `chantier` (contrat technique, signatures, décisions fragiles, lots) ; la skill `writing-plans` n'a pas été chargée. Faits vérifiés dans le code avant d'écrire : droits du noyau (`src/Http/Noyau.php`), `artisan.courriel NOT NULL UNIQUE` (`migrations/Version20261107000001.php`), `endroid/qr-code` 6.2.0 et son `SvgWriter` (`composer.lock`, `vendor/`), plafond total (`src/Http/Controleur/ImportControleur.php:50`).

Lot 0, champ d'adresse des pages de mot de passe (2026-11-19), non commité :

```
$ env APP_ENV=test vendor/bin/phpunit --filter testLesPagesDeMotDePassePortentLAdresseDuCompte   (avant le code)
FAILURES! Tests: 1, Assertions: 1, Failures: 1.
$ (après le code)
OK (1 test, 7 assertions)
$ env APP_ENV=test vendor/bin/phpunit
OK (589 tests, 3794 assertions)
$ vendor/bin/phpstan analyse --no-progress
 [OK] No errors
$ node recette-lot0.js (Chromium 390 px, serveur local, clair et sombre, captures regardées)
nouveau-light  champ {"valeur":"dev@exemple.ch","lectureSeule":true,"auto":"username","haut":48} contraste 0 défaut sur 7, disposition 0 défaut sur 21
reglages-light champ idem, contraste 0 défaut sur 10, disposition 0 défaut sur 27
nouveau-dark et reglages-dark : mêmes résultats ; erreurs JS : aucune
```

Reste du lot 0 : le champ « Compte » a le même aspect qu'un champ modifiable. Le distinguer demande une règle dans `public/css/app.css`, en cours de modification par une autre session : à faire avec le lot 6. Non vérifié : l'enregistrement réel par Bitwarden (à essayer par Xavier).

Maquette v6 (2026-11-19) : Stripe vérifié dans sa documentation (essai sans moyen de paiement, `trial_settings.end_behavior.missing_payment_method` à `pause` ou `cancel` ; rappel de fin d'essai). Frais repris de `PRIX-ABONNEMENT.md` (3,6 % + CHF 0.30). Textes relus par `relecteur-xtradev` : « À corriger » au tour 1 (carte exigée au mois alors que l'écran d'entrée disait « sans carte bancaire », deux délais non mesurés), une redite au tour 2, corrigée. Écart assumé avec le relecteur : l'étiquette reste « −9 % », Xavier ayant demandé un pourcentage. Remise calculée : au mois, la première année coûte 11 mois (CHF 493.90) ; l'annuel à CHF 449.00 fait 9 % de moins, 8 % les années suivantes.

```
$ node check.js MAQUETTE-formule-paiement-v6.html
bureau : largeur 1440/1440, images 0, cassées 0, kit oui
telephone : largeur 390/390, images 0, cassées 0, kit oui
$ node captures-v6.js ; node clics-v6.js (captures regardées)
défauts [] au bureau, au téléphone et en sombre
au mois   : "CHF 44.90par mois", "Au mois : paiement par carte, dès le deuxième mois."
à l'année : "CHF 493.90 CHF 449.00la première année−9 %", "Ensuite CHF 493.90 par an. Paiement par carte ou par QR-facture."
payer     : Payer CHF 44.90 par carte ; après un toucher sur « À l'année » : Payer CHF 449.00, 1 carte cochée
choix     : H1 H3 H4 H2 H5 H7
erreurs JS: []
```

Mise à jour de la v6 après les réponses (2026-11-19) : prix annuel par mois, « −17 % », tout en QR-facture ; textes relus, « Prêt » (montants recalculés par le relecteur : 538.80, 449.00, 37.42, 493.90). Captures regardées, clics rejoués : bouton « Recevoir ma QR-facture de CHF 449.00 » après un toucher sur « À l'année », aucune erreur JS. `DECOUPAGE-lot-B.md` écrit.
