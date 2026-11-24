---
chantier: Prospects, le catalogue des commerciaux
slug: 2026-11-24_prospects
phase: 8
statut: gate_ouvert
gate_attendu: prod
profil: .claude/atoz-profile.md
---

## Suivi Origin

- Projet 44 (offreGen), tâche 616 « Prospects : catalogue partagé des commerciaux », créée le 2026-11-24 à la demande de Xavier.
- Sous-tâches : 617 proposition et maquette, 618 code, revue et recette sur le test, 619 fiche gardée jusqu'à ce qu'on la lâche (conversation rattachée ici), 620 recette de Xavier, 621 mise en production.

## Prochaine action

Attendre la recette de Xavier sur le site de test (`RECETTE.md`, revue pour la nouvelle règle). S'il remonte des défauts : phase 5, test rouge d'abord, puis commit, push et déploiement sur le test. À la clôture : supprimer les bases locales `offregen_prospects_test` et `offregen_prospects_vue`, la copie `~/offreGen-prospects` et le fichier `~/.offregen-prospects-vue-comptes`, puis `/roadmap-sync`.

## En attente de toi

- Dérouler `RECETTE.md` sur `https://easy-offres.xtradev.ch` (sept scénarios, comptes `prospects-nina@exemple.ch` et `prospects-leo@exemple.ch`, mots de passe dans `~/.offregen-test-prospects-nina-compte` et `-leo-compte` de WSL) et dire ce qui casse.
- Confirmer ou refuser l'exception à la règle du 19.11 : en cherchant un nom, un commercial apprend qu'un compte d'un collègue existe (nom, sorte, qui l'a amené), et la fiche d'un collègue dit si l'artisan a une démo, un essai ou un abonnement.
- Dire si l'administrateur doit pouvoir libérer la fiche d'un commercial : sans plafond ni libération à date, rien d'autre n'arrête celui qui garde 100 fiches.
- Dire quel rôle a la responsable commerciale dans l'application : aujourd'hui administrateur et modérateur lisent toutes les fiches.
- Créer le site de production chez Infomaniak : sans lui, aucun vrai prospect ne peut être saisi.

## Journal

- 2026-11-24 : demande de Xavier, relayée de sa responsable commerciale. Proposition faite dans la conversation (identité d'un prospect, doublons, registre).
- 2026-11-24 : réponses de Xavier : catalogue sans ordres, chacun prend lui-même, « déjà suivi » affiché, la liste ne réserve rien, libération pour éviter le blocage, responsable qui voit tout pour commencer, rien en attendant, maquette puis production.
- 2026-11-24 : chantier ouvert dans la copie `~/offreGen-prospects` (détachée de `origin/chantier-voix-offre`, `a8fde70`). Phase 0 : profil présent, rien à valider.
- 2026-11-24 : phase 1, `SPEC.md` écrite depuis les réponses de Xavier. Gate intention tenu pour franchi par ces réponses ; la relecture de la SPEC est jointe au gate visuel.
- 2026-11-24 : phase 2 sautée : un seul lot fonctionnel, pas de sous-chantier.
- 2026-11-24 : phase 3, `MAQUETTE-prospects.html` v1, dix écrans de téléphone et une vue ordinateur, neuf questions. Contrôle et captures faits. Gate visuel ouvert.
- 2026-11-24 : gate visuel franchi. Réponses de Xavier collées dans la conversation : P1 valider (« j'aime juste pas la bordure orange a gauche soit entièrement soit rien ») ; P2 = A, avec une question (« comment faire pour éviter qu'une personne pique tous les prospects ? et donc l'idée c'est que les prospects libre sont ceux dont ont c'est pas occupé ») ; P3 = B ; P4, P5, P8 valider ; P6 = A ; P7 = A ; P9 = A (coder et déployer sur le test avec des artisans inventés).
- 2026-11-24 : sans réponse de Xavier : la tâche Origin du chantier (demandée trois fois), le rôle de la responsable commerciale. Le chantier avance sans tâche Origin ; administrateur et modérateur lisent tout.
- 2026-11-24 : phase 4 : `PLAN.md` écrit (contrat technique, signatures, trois décisions fragiles, sept lots).
- 2026-11-24 : phase 5, lots 1 et 2 : migration `Version20261124210001` jouée sur `offregen_prospects_test` (base du chantier, droits copiés de `offregen_test`), `DepotProspects`, `Prospects`, `DoublonProspect`, accesseurs du conteneur. Test rouge avant le service (13 erreurs « undefined method »), vert après : 13 tests, 78 assertions.
- 2026-11-24 : phase 5, lots 3 à 6 : `ProspectControleur`, 16 routes, entrée « Prospects » au menu des trois rôles, neuf gabarits, `prospects.css`, `tests/Http/ProspectsEcransTest.php`. Tests des prospects : 21 tests, 276 assertions verts ; règles, droits de l'équipe, schéma, suppression des démos : 66 tests verts ; PHPStan niveau 8 sans erreur.
- 2026-11-24 : phase 6, recette cliquée locale (Playwright, base `offregen_prospects_vue`, serveur `php -S localhost:8097`, quatre comptes inventés) : création par la recherche, formulaire, note, états, démo depuis la fiche, doublon par le téléphone, « Je prends », fiche réduite sans fuite, tableau et fiche à côté sur PC, thème sombre. Captures regardées par Claude : 12 au téléphone, 5 sur PC. Aucun débordement à 390 px, aucune erreur de console. Trois retouches après lecture des captures : feuille `suivi.css` manquante sur la recherche, lien souligné des cartes, prochain pas visible pour qui lit tout.
- 2026-11-24 : phase 7 : revue lourde (le diff touche les droits par route et le filtre par rôle), trois relecteurs `route-heavy` indépendants (correction, sécurité, régression), estimation 4,5 $.
- 2026-11-24 : phase 7 : trois rapports reçus, chaque trouvaille relue dans le code. Onze retenues (neuf reproduites par des tests rouges dans `tests/Compte/ProspectsRevueTest.php`, puis corrigées), sept écartées avec leur raison : `REVUE.md`. Suite complète après corrections : 1541 tests, 12 754 assertions ; PHPStan sans erreur.
- 2026-11-24 : commit `aa4d39f` (rebasé sur neuf commits d'autres sessions, un conflit résolu dans `tests/Outils/BaseDeTest.php`), poussé sur `chantier-voix-offre`, déployé sur le test : sonde verte, migrations à jour.
- 2026-11-24 : phase 8, recette cliquée par Claude sur le site de test (Playwright, Chromium, 390 px et 1440 px) : deux comptes inventés créés par `outils/compte-essai-test.sh` puis entrés dans l'équipe par l'écran « Ajouter un membre » (aucun courriel : comptes existants). Parcours complet sans erreur de console, douze captures regardées. Un échec de connexion de mon fait sur `recette-e1@exemple.ch` (mauvaise ligne lue dans le fichier du compte), sans blocage.
- 2026-11-24 : défaut trouvé par cette recette : « mercredi 26.11 » pour un jeudi. Le serveur de test est à l'heure de Zurich, le poste en UTC. Test rouge dans trois fuseaux, correction, commit `5d60a46` poussé et déployé (sonde verte, 1608 tests verts dans la suite du déploiement). Relu sur le test : « jeudi 26.11 ».
- 2026-11-24 : `RECETTE.md` écrite, gate prod ouvert. La démo de la recette part d'une fiche sans courriel : aucun rappel de démo ne part vers la boîte de Xavier.
- 2026-11-24 : réponse de Xavier : « oui crée la tâche Origin, pas de plafond, une fiche pour l'instant est gardé tant quon la libère pas puis commit et push et /roadmap-sync ». Tâche Origin 616 créée (sous-tâches 617 à 621), conversation rattachée à 619.
- 2026-11-24 : phase 5 : copie rebasée sur `origin/chantier-voix-offre` (`81a6856`, avance rapide). Trois tests rouges d'abord, chacun pour sa raison : pas au 15.03.2027 refusé (borne de 30 jours), `OverflowException` du plafond, « redevient libre » sur la fiche. Puis retrait de l'échéance (`DepotProspects::ECHEANCE`, `JOURS_LIBRE`), du plafond (`Prospects::PLAFOND`, `refuserAuPlafond`), de l'encart « redevient libre » et du badge « Libre dans N jours ». Un prochain pas se date dans l'année.
- 2026-11-24 : vérification : tests des prospects 30 tests, 324 assertions ; suite complète 1608 tests, 13 144 assertions ; PHPStan sans erreur.
- 2026-11-24 : commit `ec8bab0` (rebasé sur `4eb9e76`), poussé sur `chantier-voix-offre`, déployé sur le test : sonde verte, 1614 tests verts dans la suite du déploiement. Relu en cliquant, compte de Léo, une connexion : ni sa fiche ni celle de Nina n'annoncent de date de libération, aucun bouton sur celle de Nina, aucune erreur de console ; deux captures regardées.

## Décisions

Ce qui a été tranché sans demander, et pourquoi.

| Date | Décision | Raison |
|---|---|---|
| 2026-11-24 | Gate intention et gate visuel réunis en un seul arrêt | Xavier a répondu aux questions de cadre et demandé la maquette dans le même message |
| 2026-11-24 | Maquette écrite avec les jetons de la charte, comme `MAQUETTE-equipe-v7.html` | même convention que les maquettes validées de l'administration |
| 2026-11-24 | Un prospect est une entreprise, les personnes sont ses contacts | une seule fiche par artisan, clé de dédoublonnage stable |
| 2026-11-24 | Registre du commerce et registre IDE hors de la version 1 | accès non confirmé, registre IDE annoncé comme remplacé |
| 2026-11-24 | Menu à cinq entrées montré dans les écrans (P7 = A) | aucune entrée existante retirée sans accord |
| 2026-11-24 | La carte du prochain pas perd son filet terracotta à gauche (aucune bordure de couleur) | réponse P1 de Xavier ; la charte réserve le terracotta au logo, au micro et au contour actif |
| 2026-11-24 | **Remplacée le 2026-11-24, voir plus bas.** Plafond de 30 fiches suivies à la fois par personne (états à contacter, contacté, rendez-vous, plus tard) ; démos, mois gratuits et clients ne comptent pas ; « Je prends » et la création d'une fiche à soi sont refusés au-delà, avec la phrase qui le dit | question de Xavier sous P2 ; avec P3 = B, il ferme la voie « prendre tous les libres ». Chiffre de Claude, à contester |
| 2026-11-24 | **Remplacée le 2026-11-24, voir plus bas.** « Libres » = fiches que personne ne suit : saisies sans être prises, lâchées, ou libérées par la règle P3 | confirmation de Xavier sous P2 |
| 2026-11-24 | **Remplacée le 2026-11-24, voir plus bas.** La libération se calcule à la lecture (échéance = prochain pas + 7 jours, à défaut dernière action + 7 jours) ; aucun cron, aucune écriture en GET | pas de tâche de nuit à surveiller, « Je prends » constate la liberté dans son UPDATE |
| 2026-11-24 | « Pas intéressé » classe la fiche : plus personne ne la tient, elle reste dans « Tous », hors de « Libres », et peut être reprise | garde la trace pour les collègues sans occuper le plafond |
| 2026-11-24 | **Remplacée le 2026-11-24, voir plus bas.** Un prochain pas se date à 30 jours au plus pour tous les états, « Plus tard » compris | P3 = B de Xavier ; au-delà il faut redater ou lâcher |
| 2026-11-24 | Les comptes d'un collègue n'apparaissent que par la recherche d'un nom et dans l'avertissement de doublon (nom, sorte, qui l'a amené) ; « Tous » ne liste que des fiches | exception minimale à R1 du 19.11 (« le commercial ne voit que ses artisans »), à faire confirmer par Xavier |
| 2026-11-24 | Une démo créée hors d'une fiche ne crée pas de fiche (point 12 de la SPEC, seconde phrase, retiré) | le compte se retrouve par la recherche ; une fiche « Démo Peintre » créée d'office polluerait le catalogue |
| 2026-11-24 | Numéro IDE contrôlé par sa forme seule | clé de contrôle non prouvée ici |
| 2026-11-24 | La fiche d'un collègue ne montre pas son état saisi, seulement l'état de son compte (démo, essai, client) | P2 = A de Xavier : « nom, localité, métier, qui le suit et depuis quand » |
| 2026-11-24 | L'avertissement de doublon est une page, ses boutons dans la barre du bas, au lieu de la feuille par-dessus le formulaire de la maquette | même contenu, même place, sans script |
| 2026-11-24 | Sur PC, la fiche s'ouvre à côté de la liste « Tous » pour qui lit tout, « Les miens » pour un commercial | P8 de Xavier |
| 2026-11-24 | Deux fiches suivies par deux personnes ne se fusionnent pas ; une fiche libre qui absorbe une fiche tenue passe à celui qui tenait l'autre | revue C1 et S5 ; la maquette dit que l'administrateur ne retire un prospect à personne |
| 2026-11-24 | Les fiches d'un membre retiré de l'équipe sont libres aussitôt | revue R1 ; sinon elles restaient gelées |
| 2026-11-24 | Les comptes ne se rapprochent d'une saisie que par le nom, plus par le courriel | revue S3 ; un courriel deviné désignait le compte d'un collègue |
| 2026-11-24 | La recherche exige un mot de trois lettres au moins | revue S2 ; des lettres isolées listaient les comptes des collègues |
| 2026-11-24 | Perdre la course à « Je prends » rouvre la fiche avec un message au lieu d'une page introuvable | revue C3 |
| 2026-11-24 | Une fiche reste suivie jusqu'à ce que celui qui la suit la lâche, ou quitte l'équipe ; aucun plafond | Xavier, 2026-11-24, « pour l'instant » ; remplace P3 = B, le plafond de 30 et le calcul d'échéance |
| 2026-11-24 | Un prochain pas se date d'aujourd'hui à dans un an | la borne de 30 jours ne servait qu'à P3 = B ; un an arrête une faute de frappe |
| 2026-11-24 | `ProspectsRevueTest::testUneActionApresMinuitCompteSurLeJourDeZurich` supprimé | C4 portait sur l'échéance, qui n'existe plus |

Lectures de Claude à confirmer par Xavier : « au bout d'une semaine » lu comme 7 jours sans action notée (P3 = A), avec une variante B recommandée ; « une liste globale où les prospects qui existent, on peut directement les prendre » lu comme l'onglet « Libres » et « Je prends ».

## Artefacts

| Chemin | Phase | État |
|---|---|---|
| `SPEC.md` | 1 | écrite, à relire par Xavier |
| `MAQUETTE-prospects.html` | 3 | v1, contrôlée, en attente de réponses |
| `PLAN.md` | 4 | écrit |
| `migrations/Version20261124210001.php`, `src/Donnees/DepotProspects.php`, `src/Compte/Prospects.php`, `src/Compte/DoublonProspect.php`, `tests/Compte/ProspectsTest.php` | 5 | lots 1 et 2 verts |
| `src/Http/Controleur/ProspectControleur.php`, `config/routes.php`, `templates/admin/prospect*.html.twig`, `_prospect*.html.twig`, `_prospects_liste.html.twig`, `_menu.html.twig`, `public/css/prospects.css`, `tests/Http/ProspectsEcransTest.php` | 5 | lots 3 à 6 verts |
| `REVUE.md` | 7 | écrite, onze retenues corrigées |
| `RECETTE.md` | 8 | écrite, à dérouler par Xavier |
| `tests/Compte/ProspectsRevueTest.php` | 7 | neuf tests, rouges avant correction |

Rien n'est commité.

## Preuves

### Phase 3

```
$ python ~/.claude/skills/maquette/scripts/inject_kit.py MAQUETTE-prospects.html
kit injecté
$ node ~/.claude/skills/maquette/scripts/check.js MAQUETTE-prospects.html <captures>
bureau : largeur 1440/1440, images 0, cassées 0, kit oui
telephone : largeur 390/390, images 0, cassées 0, kit oui
OK.
$ node capturer.js <captures>   (script du scratchpad, hors dépôt)
light polices Archivo/Inter : true/true | logos (largeur naturelle) : 300,300,300,300,300,0
light écrans de téléphone : 10
après le clic sur Libres, vue visible : libres
après « Je prends » : Sols Berbier Vous › Moutier · Poseur de sols · noté par Maud Perret le 18.11
texte à copier : "Réponses, maquette Prospects v1 (24.11) :\n- P3 : B, gardé tant qu'un prochain pas est daté à 30 jours au plus, libre 7 jours après un pas manqué · essai"
identifiants en double : aucun
dark polices Archivo/Inter : true/true | logos (largeur naturelle) : 300,300,300,300,300,0
390 px : largeur du document 390
erreurs : aucune
$ node pc.js <captures>   (après correction du tableau qui débordait sous la fiche)
{"milieu":646,"table":606,"tableDroite":963,"milieuDroite":983,"defile":"1148/1148"}
```

Le sixième logo, de largeur 0, est l'image vide de la visionneuse du kit. « images 0 » de `check.js` : les logos portent `data-no-zoom`.

Captures regardées par Claude : les dix écrans en clair, trois en sombre, la vue ordinateur, l'écran après « Je prends ». Non vérifié : un vrai téléphone, Safari, Firefox.

### Phases 5 et 6

```
$ php vendor/bin/phpunit tests/Compte/ProspectsTest.php   (avant le service)
Tests: 13, Assertions: 0, Errors: 13.   (Call to undefined method OffreGen\Http\Conteneur::prospects())
$ php vendor/bin/phpunit tests/Compte/ProspectsTest.php   (après)
OK (13 tests, 78 assertions)
$ php vendor/bin/phpunit   (suite complète, avant la revue)
OK (1532 tests, 12721 assertions)
$ php vendor/bin/phpstan analyse --no-progress
 [OK] No errors
$ node parcours.js   (recette cliquée locale, base offregen_prospects_vue)
- doublon : Ce prospect est peut-être déjà noté | Peinture Roux Sàrl Même téléphone · Delémont · Peintre · suivi par Nina Rey depuis le 24.11.2026
- après « Je prends » : Ce prospect est à vous. | 7 suivis par vous
- menu du bas, premier onglet : 78 × 56 px
erreurs : aucune
```

### Phase 7

```
$ php vendor/bin/phpunit tests/Compte/ProspectsRevueTest.php   (avant les corrections)
neuf tests, neuf rouges : 2 erreurs, 7 échecs
$ php vendor/bin/phpunit tests/Compte/ProspectsRevueTest.php tests/Compte/ProspectsTest.php tests/Http/ProspectsEcransTest.php   (après)
OK (30 tests, 309 assertions)
$ php vendor/bin/phpunit   (suite complète, après les corrections)
OK (1541 tests, 12754 assertions)
$ php vendor/bin/phpstan analyse --no-progress
 [OK] No errors
```

### Phase 8

```
$ sh deployer.sh   (push puis outils/deployer-test.sh, premier déploiement)
   3b2e510..aa4d39f  HEAD -> chantier-voix-offre
LU     /sante : {"commit":"aa4d39f70bd04d14cae8e2d61cafe2e4c0b79544","base":"ok","migrations":"ok"}
SONDE VERTE
DÉPLOYÉ : aa4d39f70bd04d14cae8e2d61cafe2e4c0b79544 sur https://easy-offres.xtradev.ch
$ node parcours-test.js   (recette cliquée sur le site de test)
- équipe, prospects-nina@exemple.ch : Nina Rey Commercial · prospects-nina@exemple.ch Ce compte existait déjà : il a maintenant le rôle Commercial.
- fiche créée : /admin/prospects/1
- doublon : Ce prospect est peut-être déjà noté | Peinture Roux Sàrl Même téléphone · Delémont · Peintre · suivi par Nina Rey depuis le 24.11.2026
- fiche de Nina vue par Léo : Peinture Roux Sàrl Delémont · Peintre Suivi par Nina Rey Depuis le 24.11.2026. Dernière action notée le 24.11.2026. Sans nouvelle action de Nina Rey, ce prospect redevient libre le 02.12.2026.
- gestes offerts à Léo sur la fiche de Nina : 0 formulaire(s)
- après « Je prends » : Ce prospect est à vous. | 2 suivis par vous
- fiche de Léo lue par l'administrateur : téléphone et note visibles, gestes : absents
erreurs : aucune
$ php vendor/bin/phpunit tests/Http/ProspectsEcransTest.php --filter FuseauDuServeur   (avant la correction)
Failed asserting that '<!doctype html>…' contains "Rappeler · mardi 10.11".
$ php vendor/bin/phpunit tests/Http/ProspectsEcransTest.php tests/Compte/ProspectsTest.php tests/Compte/ProspectsRevueTest.php   (après)
OK (31 tests, 327 assertions)
$ sh commit2.sh   (commit, push, second déploiement ; la suite complète est celle du script de déploiement)
   aa4d39f..5d60a46  HEAD -> chantier-voix-offre
OK (1608 tests, 13135 assertions)
OK     commit déployé : 5d60a465334ccfb77670e90b165bc56e4a9afa81
SONDE VERTE
$ node verif-jour.js   (lecture de l'écran sur le site de test)
lignes : Demander son avis sur la démo · jeudi 26.11
```

Captures regardées par Claude : douze du site de test (téléphone et ordinateur). Non vérifié : un vrai téléphone, Safari, Firefox ; la libération après sept jours et le plafond de 30, prouvés par test seulement ; deux gestes au même instant.

### Fiche gardée jusqu'à ce qu'on la lâche, sans plafond (2026-11-24)

```
$ php vendor/bin/phpunit tests/Compte/ProspectsTest.php tests/Compte/ProspectsRevueTest.php tests/Http/ProspectsEcransTest.php   (avant)
1) ProspectsTest::testUneFicheResteSuivieTantQuOnNeLaLachePas
InvalidArgumentException: Choisissez une date entre aujourd'hui et le 24.12.2026.
2) ProspectsTest::testAucunPlafondDeFichesSuivies
OverflowException: Vous suivez déjà 30 prospects : c'est la limite. Lâchez-en un, ou créez la démo de l'un d'eux.
1) ProspectsEcransTest::testUnCollegueVoitLeNomEtQuiSuitRienDAutre   (« redevient libre » sur la fiche)
$ (après)
OK (30 tests, 324 assertions)
$ php vendor/bin/phpunit   (suite complète)
OK (1608 tests, 13144 assertions)
$ php vendor/bin/phpstan analyse --no-progress
 [OK] No errors
$ sh commit4.sh   (commit, push, déploiement)
   4eb9e76..ec8bab0  HEAD -> chantier-voix-offre
OK (1614 tests, 13204 assertions)
LU     /sante : {"commit":"ec8bab0398abfa6e984732dfcc2637df23d0bf1f","base":"ok","migrations":"ok"}
SONDE VERTE
$ node verif-garde.js   (site de test, compte de Léo)
en-tête de la liste : 2 suivis par vous
ma fiche, « redevient libre » : false ; « Libre dans » : false
fiche de Nina, « redevient libre » : false ; formulaires : 0
erreurs de console : aucune
```

