# Chantier : administration des comptes et des démos

- statut : en_cours (gate visuel franchi le 2026-11-19 pour les lots A et B)
- phase : lot A en 5 (lots 0 à 5 faits, lots 6 à 9 à faire) ; lot B en 2 (découpage écrit)
- Demande de Xavier, 2026-11-19 : « fais la page d'administration, maquette d'abord » ; « est-ce que maintenant on peut déjà créer un compte facile pour les démos ».

## Suivi Origin

- Projet 44 (offreGen), tâche 458 (Passage à l'échelle), sous-tâche 460 (compte créé en deux clics par l'exploitant). Conversation rattachée le 2026-11-19.

## En attente de toi

- Dire si le lot 0 et les documents du chantier se commitent, et qui passe en premier entre ce chantier et celui des métiers (questions du 19.11, sans réponse).

## Prochaine action

Lot A, suite : lot 5 (démo par QR et par message), après le chantier des métiers (ordre choisi par Xavier le 2026-11-19 : « Admin, métiers, puis démo »). Les lots 5 et 6 touchent `public/js/app.js` et `public/css/app.css` : attendre que les autres sessions les aient commités. Puis lots 6 à 9.

Lot B : découpé dans `DECOUPAGE-lot-B.md`, après le lot A. Préalables à fournir par Xavier : créancier des QR-factures, TVA, conditions générales, compte Stripe, site de production.

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

Lots 1 à 4 du lot A (2026-11-19), accord de Xavier pour coder (« Admin, métiers, puis démo ») :

- Lot 1 : migration `Version20261119100001` (numéro en 1000xx pour ne pas croiser une migration du chantier des métiers), jouée sur les bases de test et de développement.
- Lot 2 : droits `Route::EQUIPE` et `Route::MODERATION`, rôle relu en base à chaque requête, `/admin` et ses pages par rôle, entrée dans « Plus » et dans le menu PC.
- Lot 3 : équipe (créer, donner un rôle à un compte existant, changer de rôle, retirer), journal des gestes.
- Lot 4 : liste et fiche des artisans sous `PorteeEquipe`, « offrir des offres » dans la limite du rôle, fil sans contenu d'offre.

Écarts avec `PLAN-lot-A.md`, décidés en codant :
- Les styles de l'administration sont dans `public/css/admin.css`, chargé après `app.css` : une autre session modifie `app.css` sans l'avoir commité.
- Le menu du lot A ne propose que des pages qui existent : « Équipe » (administrateur) et « Suivi » (modérateur) tiennent la place d'« Offres », qui arrive avec le sous-chantier 26. Le rond « Nouvelle démo » arrive avec le lot 5.
- Filtres de la liste : Tous, Démos, Comptes (« Essais » n'existe plus depuis T1).
- Retirer un membre lui laisse un compte d'artisan ordinaire : fermer un compte attend la tâche 463 (conservation des données). La maquette disait « son compte se ferme ».
- Le lien d'accès d'un nouveau membre s'affiche à l'écran quand le site n'envoie pas de courriels (D6). Le chemin « courriel réel » n'a pas de test : non vérifié.
- Motifs des jetons de démo, pour le lot 5 : `demo_qr` et `demo_msg` (la colonne `jeton_acces.motif` fait 10 caractères).
- Tests : `tests/Http/ArtisansEquipeTest.php` regroupe l'isolation et les limites d'offres prévues en deux fichiers.

```
$ env APP_ENV=test vendor/bin/phpunit --filter testLEquipeASesColonnesEtSesTables   (avant la migration)
FAILURES! Tests: 1, Assertions: 2, Failures: 1.
$ doctrine-migrations migrate (bases de test et de développement)
[OK] Successfully migrated to version: OffreGen\Migrations\Version20261119100001
$ phpunit --filter DroitsEquipe (avant le code du lot 2)
Error: Undefined constant OffreGen\Http\Route::EQUIPE ; Tests: 9, Errors: 1, Failures: 5
$ phpunit --filter EquipeTest (avant le branchement du lot 3)
ERRORS! Tests: 16, Errors: 7
$ phpunit --filter ArtisansEquipe (avant le branchement du lot 4)
FAILURES! Tests: 7, Failures: 7
$ env APP_ENV=test vendor/bin/phpunit   (après le lot 4)
OK (621 tests, 4841 assertions)
$ vendor/bin/phpstan analyse --no-progress
 [OK] No errors
$ node recette-lot2.js (trois rôles, téléphone et PC, clair et sombre, 24 écrans)   : aucun défaut aux sondes
$ node recette-lot3.js (créer, refus, changer de rôle, retirer ; téléphone clair, PC sombre, 14 écrans) : aucun défaut aux sondes
$ node recette-lot4.js (liste, filtres, recherche vide, fiche, offrir ; administrateur et commercial, 10 écrans) : aucun défaut aux sondes
  administrateur : boutons +2 +5 +10 avant et après ; commercial : +2 +5 +10 puis aucun, « c'est la limite de votre rôle »
```

Défauts vus sur les images et corrigés, que les sondes n'avaient pas relevés : logo du menu PC invisible (version pour fond sombre) et en double avec l'en-tête ; étiquette de rôle étirée sur toute la largeur ; bouton « Retirer de l'équipe » rouge plein, passé en contour rouge ; rôles affichés sous leur nom interne dans le fil.

Commit `7a7956d` (lots 1 à 4), poussé sur `chantier-voix-offre` le 2026-11-19, accord de Xavier. Vérifié seul dans une copie propre (`git worktree`, `composer install`) :

```
sources chargées depuis : /home/xavie/offreGen-verif/src
OK (613 tests, 4033 assertions)
 [OK] No errors
```

Un premier contrôle en copie propre était faux et a été refait : la copie empruntait `vendor/` par un lien, et le chargeur de classes de Composer pointait vers les sources de la copie de travail (avec les fichiers non commités des autres sessions). Les 621 tests de la copie de travail comprennent des tests non commités d'autres sessions.

Déploiement sur le test et premier retour de Xavier (2026-11-19) :

- Xavier : « non mais sur le serveur de test et on fait les tests là-dessus ». Commit `3a1715b` déployé sur le test depuis une copie propre (`git worktree`, `composer install`, `bash outils/deployer-test.sh`), la copie de travail portant les fichiers non commités d'autres sessions. Sortie : « OK (613 tests, 4033 assertions) », migration `Version20261119100001` jouée, « SONDE VERTE », `/sante` annonce `3a1715b`. Non vérifié : aucun parcours connecté sur le test par Claude (pas de mot de passe du compte de Xavier).
- Retour de Xavier après avoir ouvert `/admin` sur le test : « il manque encore certains éléments », « l'interface pour l'équipe est un peu pourrie », « on ne peut pas créer d'administrateur. Ça pourrait quand même être bien. Mais garde ça pour après ».
- Constat : la maquette v3 ne dessinait que « Nouveau membre » (E1). La liste de l'équipe et la fiche d'un membre ont été codées sans maquette validée. À refaire par une maquette.
- À faire plus tard, à la demande de Xavier : créer un administrateur depuis l'interface. Cela remplace la règle A1 du 2026-11-18 (le droit administrateur ne se donne que par commande) : à lier au second facteur de l'équipe, parce qu'une session d'administrateur volée pourrait alors créer un autre administrateur.
- Écart entre deux chantiers, à trancher par Xavier : ici P2 = B (prix d'exemple gardés, « à vérifier ») ; dans `2026-11-19_metiers-demo`, « va pour A » (essai aux prix à 0) avec l'écran « Vos prix » qui propose le prix de la démo (V6 = A), et Xavier y a dit « oublie la réponse à la maquette ». Hypothèse de Claude : la version du chantier des métiers fait foi, elle est plus récente et plus détaillée. L'électricien y est déjà retiré (remplacé par le maçon).

- Maquette `MAQUETTE-equipe-v7.html` (2026-11-19) : liste rangée par rôle, écran « seul » qui explique les rôles, fiche d'un membre, tableau et fiche sur ordinateur. Questions L1 à L4, en attente de Xavier. Contrôle du kit : bureau 1440/1440, téléphone 390/390, 3 images, 0 cassée ; captures regardées (logo de la bande agrandi par une règle trop large, tableau coupé sous la fiche et deux boutons pleins sur ordinateur : corrigés).

Écran Équipe refait (2026-11-19), réponses de Xavier à `MAQUETTE-equipe-v7.html`, collées telles quelles :

- L1 : Valider
- L2 : A, la garder
- L3 : Valider · il faut juste regarder qui a amener les artisans car en fonction il y'a une comission.
- L4 : Valider · la taille doit être la même tout a droite car la j'ai l'impression que les bouton sont taille smarthphone

Puis : « tu peux commit et push et je regarde en live dès que c'est fais ».

Décisions prises en codant :
- Commission (L3) : colonne `apporte_par`, posée une fois et jamais changée. `cree_par` reste « qui suit l'artisan » et passe à l'administrateur au départ d'un membre. Avant, retirer un commercial effaçait la trace de celui qui avait amené ses artisans. Le lot 5 (`Demos::creer`) devra poser les deux colonnes. La fiche d'un membre ouvre « Ses artisans » (`/admin/artisans?par=<id>`), la liste et la fiche d'un artisan disent « amené par » et, si c'est quelqu'un d'autre, « suivi par ».
- Accès pas encore ouvert (L2 = A) : colonne `acces_en_attente`, mise à 1 à l'invitation d'un compte neuf, remise à 0 par `DepotArtisans::remplacerMotDePasse`. Les jetons d'accès ne suffisaient pas : `annulerTout` les marque tous utilisés.
- L4 : sur PC, tous les boutons de la fiche font 48 px de haut et la largeur du panneau.
- Écart avec la maquette : le libellé du compteur est « Offres » et non « Offres par mois ». Le plafond d'un compte est un total ; le quota par mois arrive avec le lot B1.
- Écart avec la maquette : « Renvoyer un lien d'accès » est un bouton au téléphone aussi (c'est un envoi, pas une page).
- `public/js/admin.js`, fichier neuf : les boutons − et + du compteur. Sans script, ils restent cachés et le champ se remplit au clavier.
- Sur PC, l'administration prend toute la largeur (l'application s'arrête à 1040 px) : au-delà de 1272 px, l'en-tête s'arrêtait au milieu de l'écran. Défaut vu à la capture de 1440 px, présent depuis le lot 2.
- Migration `Version20261119100002`, additive.

```
$ phpunit --filter 'EquipeTest|ArtisansEquipe|testLEquipeASesColonnes'   (tests écrits, avant le code)
FAILURES! Tests: 29, Assertions: 239, Failures: 7.
$ doctrine-migrations migrate (bases de test et de développement)
[OK] Successfully migrated to version: OffreGen\Migrations\Version20261119100002
$ env APP_ENV=test vendor/bin/phpunit   (après le code)
OK (657 tests, 5142 assertions)
$ vendor/bin/phpstan analyse --no-progress
 [OK] No errors
$ node recette-v7.js (téléphone clair, PC sombre 1280, PC clair 1440 ; 27 écrans : liste, accès en attente, fiche, compteur, enregistrer, lien, ses artisans, retrait, seul)
aucun défaut aux sondes ; compteur 10 -> 11 ; après Enregistrer : « Enregistré. », offres 11, rôle moderateur
```

Une première suite entière a rendu 198 erreurs (« Duplicate entry 'a@exemple.ch' ») : deux suites tournaient en même temps sur la base de test (une autre session). Relancée seule, elle est verte.

Non vérifié : l'envoi du lien par une vraie messagerie (le chemin « courriel réel » n'a pas de test) ; le parcours connecté sur le site de test (pas de mot de passe du compte de Xavier).

Commit `56fbddd` (écran Équipe refait), poussé puis déployé sur le test le 2026-11-19, accord de Xavier (« tu peux commit et push et je regarde en live ») :

```
== Tests et analyse (copie propre du commit)
OK (657 tests, 5141 assertions)
analyse statique : sans erreur
[OK] Successfully migrated to version: OffreGen\Migrations\Version20261119100002
SONDE VERTE
DÉPLOYÉ : 56fbddd07fd8cd9bfb23a61dddf43483eea87a6f sur https://easy-offres.xtradev.ch
```

Deux essais de déploiement ont été refusés avant celui-ci, tests rouges (379 erreurs, puis 16) : une autre session déployait `687606b` au même moment, sur la même base de test et depuis un dossier du même nom (`~/offreGen-deploiement`). Ce chantier déploie désormais depuis `~/offreGen-deploiement-admin`. Erreur de Claude : en nettoyant ce qu'il prenait pour un reste de son essai, il a retiré le dossier `var/` de la copie de l'autre session pendant qu'elle tournait, puis l'a recréé. Le déploiement de l'autre session n'a pas abouti ; cause non établie. À la fin, `/sante` annonce `56fbddd`.

Non vérifié sur le test : aucun parcours connecté (pas de mot de passe du compte de Xavier). Constaté : `/js/admin.js` 200, `/css/admin.css` porte les styles neufs, `/admin/equipe` sans session 404.

Retour de Xavier sur le test (2026-11-19), après l'écran Équipe refait :

- « Pour la partie équipe, c'est vraiment bien. »
- Nouveau membre : « j'ai un énorme quantité de vide », le bouton « est hyper loin, à l'autre bout de l'univers », « Fais-moi une interface plus petite et plus propre. » Fait : sur PC, une carte de 480 px, le bouton sous les champs ; mêmes pièces que la fiche (rôle avec sa conséquence, compteur). La page du lien d'accès suit la même carte. Codé sans maquette : correction de disposition d'un écran déjà validé (E1).
- Artisans : « Il faudra rajouter aussi les essais dedans. » Fait : filtre « Essais » et étiquette « Essai ». Un essai est un compte avec un plafond d'offres (`PlafondEssai`, 2026-11-17) ; un compte n'en a pas. Avant, la liste étiquetait les essais « Compte » : l'écart « Essais n'existe plus depuis T1 » noté plus haut était une erreur de Claude, T1 décrit la formule à venir (lot B), pas les comptes d'essai qui existent.
- Tableau de bord vide : normal, lot 7.
- Accord permanent de Xavier (question posée le 2026-11-19, réponse « Oui, et à chaque fois ») : commiter, pousser et déployer sur le test après chaque correction de l'administration, sans redemander. Jamais sur `main`, jamais en production.

```
$ phpunit --filter 'testLesFiltresEtLaRecherche|testUneSaisieFausseEstRefusee'   (tests écrits, avant le code)
FAILURES! Tests: 2, Assertions: 5, Failures: 2.
$ env APP_ENV=test vendor/bin/phpunit   (après le code)
OK (657 tests, 5154 assertions)
$ vendor/bin/phpstan analyse --no-progress
 [OK] No errors
$ node recette-v8.js (téléphone, PC sombre 1280, PC clair 1680 ; 15 écrans : formulaire, refus, rempli, lien, essais)
aucun défaut aux sondes ; sur PC, bouton à 88 px sous le compteur, 438 px de large ; filtre Essais : étiquettes « Essai » seulement
```

Lot 5, démo par QR et par message (2026-11-20). Demande de Xavier transmise par le chat des métiers : « quand on crée une démo, la personne elle a juste à écrire son mail et la démo est lancée avec les données de démo. […] en 30 secondes que le gars puisse commencer à parler. Il met son mail, touf, il a l'app, elle s'ouvre, il la teste, il parle. » « c'est le commercial qui lui crée une démo direct pour son métier ». Ses décisions : le commercial crée la démo et choisit le métier ; le courriel est tapé par le prospect à l'ouverture, ou par le commercial à la création ; il se confirme au passage à l'essai, pas avant ; ce chat construit.

Construit :
- `/admin/demos/nouvelle` : neuf métiers (`CatalogueFictif::libelles()`), entreprise et courriel du prospect facultatifs. La démo est posée tout de suite par `Comptes::creer` (catalogue, deux offres d'exemple), 10 offres, 7 jours, `cree_par` et `apporte_par` remplis.
- `/admin/artisans/{id}/qr` : code QR écrit en SVG dans la page (`QrLien`, sans la croix suisse de la QR-facture), lien d'une heure émis à chaque affichage. `/admin/artisans/{id}/message` : message prêt, lien de 24 heures, envoi par WhatsApp ou par courriel (simples liens), « Copier le message ».
- `/d/{jeton}`, page publique : un champ (courriel), un bouton (« Commencer »). Ouvrir la page ne consomme pas le lien ; l'envoyer, oui, une seule fois.
- Entrée dans la liste des artisans (« Créer une démo ») et dans la fiche d'une démo (« Afficher le code QR », « Envoyer par message »).

Décisions prises en codant :
- Le courriel tapé va dans `courriel_saisi` (migration `Version20261120140001`). Le compte garde son adresse `demo-<hex>@demo.invalid` jusqu'au passage à l'essai : un courriel non confirmé peut être celui d'un autre, il ne donne aucun droit et ne sert pas à se connecter.
- Aucun courriel n'est envoyé. La règle du 18.11 (« une démo ne reçoit jamais de courriel ») reste en place tant que Xavier ne l'a pas changée : sans courriel de retour, le prospect revient par sa session ou par un nouveau lien du commercial.
- Un lien de démo n'ouvre qu'une démo : `LiensAcces::artisanDuLien` et `utiliser` le refusent, sinon il aurait permis de choisir le mot de passe du compte de démo. Et un lien d'accès n'ouvre pas de démo.
- Écart avec le plan : la page `/d/{jeton}` porte un formulaire, elle ne s'envoie plus seule. La décision fragile 1 (aperçus de lien des messageries) tombe : rien n'est consommé sans le toucher sur « Commencer ». Aucun changement dans `public/js/app.js`.
- L'assistant de bienvenue est noté comme passé à la création d'une démo de l'équipe : sans cela, le prospect tombait sur « Votre entreprise », étape 1 sur 4, au lieu de ses offres (vu au parcours cliqué). Les réglages restent ouverts dans « Plus ».
- Nom provisoire quand le commercial ne tape pas d'entreprise : « Démo » suivi du métier.
- Limite d'un commercial : 10 démos par 24 heures glissantes, comptées dans `journal_admin` (une démo supprimée compte encore). Administrateur et modérateur sans limite.
- `Demos` est construit dans `AdminControleur`, pas dans `Conteneur.php`, qu'une autre session modifiait sans l'avoir commité. Pour la même raison, `config/routes.php` est commité par l'index : la version commitée plus les six routes du lot.
- Le rond « Nouvelle démo » du menu (R3) n'est pas fait : le bouton est dans la liste des artisans.

Textes vus par le prospect (page d'entrée, lien expiré, message) relus par `relecteur-xtradev` : « À corriger » au tour 1 (la phrase sous le champ ne disait pas qui lit le courriel), « Prêt » au tour 2. Au choix de Xavier : « dictez votre première visite » ou « votre première offre » (deux endroits).

```
$ DB_NOM=offregen_admin_test phpunit --filter DemosTest   (tests écrits, avant le code)
ERRORS! Tests: 9, Assertions: 4, Errors: 7, Failures: 1.
$ doctrine-migrations migrate (bases de test et de développement, base du chantier)
[OK] Successfully migrated to version: OffreGen\Migrations\Version20261120140001
$ DB_NOM=offregen_admin_test phpunit   (après le code, copie de travail)
Tests: 888, Assertions: 7894, Failures: 1.
$ vendor/bin/phpstan analyse --no-progress
 [OK] No errors
$ node recette-lot5.js (commercial au téléphone et sur PC sombre, prospect sur un téléphone ; 20 écrans)
aucun défaut aux sondes ; code QR 320 x 320 px ; après « Commencer » : / , titre « Mes offres · Dicta » ; le lien une seconde fois : 410
fiche : Démo · Carreleur · info@carrelage-buchs.ch · amené par Peinture Démo Sàrl
```

La suite tourne sur une base à part, `offregen_admin_test` (mémoire « Base de test partagée ») : son seul échec, `ConteneurTest::testLeMotDePasseDeStructureNEstLuQueParLesMigrations`, vient de la variable `DB_NOM`. À supprimer à la clôture du chantier.

Non vérifié : un vrai téléphone qui scanne le code ; WhatsApp et la messagerie ouverts par les deux liens ; iPhone.

Questions ouvertes pour Xavier :
1. Courriel de retour : garder la règle du 18.11 (aucun courriel à une démo), ou envoyer un seul courriel « Revenir dans votre démo » à l'adresse tapée.
2. Page publique où un prospect lance sa démo sans commercial : non construite. Elle demanderait un plafond par adresse IP et le choix du métier par le prospect.
3. Nom provisoire « Démo <métier> » et assistant de bienvenue sauté : à confirmer ; l'assistant pourrait revenir au passage à l'essai.
4. Passage à l'essai (lot 6 et lot B) : `Comptes::passerEnEssai` remplace le mot de passe et ferme la session ; une offre d'exemple envoyée ou facturée pendant la démo part au passage (D4 de la revue des métiers).

Copie de travail du chantier, depuis le 2026-11-20 : `~/offreGen-admin` (`git worktree`, tête détachée, poussée par `git push origin HEAD:chantier-voix-offre`). Raison : la copie principale `~/offreGen` avait douze commits de retard sur la branche et portait le travail non commité d'une autre session sur `config/routes.php`, `Comptes.php` et `Conteneur.php` ; le push du lot 5 y a été refusé. Le commit du lot 5 a été rejoué ici sur la tête de la branche (un conflit, `config/routes.php`, résolu en ajoutant les six routes à la version de la branche), puis retiré de la copie principale, remise dans l'état où elle était avant le lot.

```
$ DB_NOM=offregen_admin_test phpunit   (copie du chantier, lot 5 rejoué sur c035e8b)
Tests: 693, Assertions: 5493, Failures: 1.   (le seul échec : ConteneurTest, à cause de DB_NOM)
$ vendor/bin/phpstan analyse --no-progress
 [OK] No errors
```

Ruban de démo et passage à l'essai (2026-11-20, début du lot 6). Retour de Xavier après avoir essayé une démo : « la démo, je vois qu'on ne peut pas passer à aucun moment, le gars il peut passer dans un essai. […] Il faut que ce soit visible en haut quand on crée une offre. » « quand je suis dans mes offres démo plâtrier compte de démonstration il faut un petit bouton passer à l'essai pouf et là ça passe. »

Construit :
- Ruban en haut de chaque écran d'une démo créée par l'équipe (`templates/_ruban_demo.html.twig`, inclus dans `base.html.twig`, fonction Twig `demo()`) : « Démo · 7 jours restants » et le bouton « Passer à l'essai ». Styles dans `public/css/demo.css`, chargés dans une démo seulement.
- `/passer-a-l-essai` : un écran (ce qui reste, ce qui est effacé, le courriel pré-rempli, un bouton). Le passage appelle `Comptes::passerEnEssai` (chantier des métiers), sur le même compte, avec 10 offres.
- La session du prospect est rouverte aussitôt : `passerEnEssai` remplace le mot de passe et avance le numéro de session.
- Le courriel se confirme par le lien du mot de passe : envoyé si le site envoie des courriels ; sur un site sans courriel (le test), le prospect est conduit tout de suite sur la page du mot de passe, sinon son compte serait perdu à la fin de la session.
- Le passage est noté au journal (`passage_essai`) et se lit dans la fiche de l'artisan, toujours « amené par » son commercial.

Décisions prises en codant :
- Deux touchers et non un : le passage efface les exemples, il se confirme sur un écran qui le dit avant. À revoir si Xavier veut un seul toucher.
- Une démonstration partagée (sans date de fin, mot de passe connu de plusieurs personnes) n'a ni ruban ni passage : 404.
- L'adresse est `/passer-a-l-essai` : `/essai` est déjà le formulaire public de demande d'essai de la page de présentation (vu aux tests : le POST rendait 303).
- Sous le ruban, la ligne du plafond dit « Démo : 10 offres restantes » et non « Compte d'essai » (une ligne changée dans `templates/accueil.html.twig`).
- L'essai ouvert ici est celui qui existe : 10 offres, sans durée, sans formule ni paiement. Le mois gratuit d'une formule (T1) reste au lot B.

Textes vus par le prospect relus par `relecteur-xtradev`, trois tours : « À corriger » aux tours 1 et 2 (« Vous gardez » promettait de garder une offre d'exemple modifiée, alors qu'elle est effacée ; les deux encadrés se contredisaient sur les clients d'exemple), une dernière faute au tour 3, corrigée. Au choix de Xavier : le ruban dit « dernier jour » sans dire ce qui arrive après ; rien ne dit ce qui se passe après les 10 offres de l'essai.

```
$ DB_NOM=offregen_admin_test phpunit --filter PassageDemoTest   (tests écrits, avant le code)
FAILURES! Tests: 2, Assertions: 10, Failures: 2.
$ DB_NOM=offregen_admin_test phpunit   (après le code, copie du chantier)
Tests: 914, Assertions: 8143, Failures: 1.
$ vendor/bin/phpstan analyse --no-progress
 [OK] No errors
$ node recette-lot6.js (prospect au téléphone clair et sur PC sombre ; 12 écrans)
ruban : « Démo · 7 jours restants  Passer à l'essai », 61 px de haut ; après le passage : /mot-de-passe/<jeton>, « Vous êtes à l'essai. Choisissez votre mot de passe pour retrouver votre compte. » ; après le mot de passe : / , plus de ruban
un défaut relevé par la sonde, hors de ce chantier : le texte indicatif de la recherche du catalogue (3,7:1 en clair, 3,2:1 en sombre)
```

Non vérifié : le chemin « courriel réel » (aucun test) ; iPhone.

Demandes de Xavier du même message, hors de ce chantier, notées dans Origin : application sans rechargement de page (tâche 504) ; code QR de démonstration sur la facture tant qu'il n'y a pas d'IBAN (tâche 509, pour le chantier des modèles d'offre).
