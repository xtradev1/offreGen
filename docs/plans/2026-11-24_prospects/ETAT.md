---
chantier: Prospects, le catalogue des commerciaux
slug: 2026-11-24_prospects
phase: 7
statut: en_cours
gate_attendu: null
profil: .claude/atoz-profile.md
---

## Suivi Origin

- Projet 44 (offreGen). Conversation rattachée au projet le 2026-11-24, sans tâche.
- Aucune tâche créée : question posée à Xavier deux fois le 2026-11-24, sans réponse.

## Prochaine action

Lire les trois rapports de revue, vérifier chaque trouvaille dans le code, écrire `REVUE.md`, corriger les retenues (test rouge d'abord), relancer la suite et PHPStan, puis commit, push sur `chantier-voix-offre`, déploiement sur le test et `RECETTE.md`.

## En attente de toi

Rien.

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
| 2026-11-24 | Plafond de 30 fiches suivies à la fois par personne (états à contacter, contacté, rendez-vous, plus tard) ; démos, mois gratuits et clients ne comptent pas ; « Je prends » et la création d'une fiche à soi sont refusés au-delà, avec la phrase qui le dit | question de Xavier sous P2 ; avec P3 = B, il ferme la voie « prendre tous les libres ». Chiffre de Claude, à contester |
| 2026-11-24 | « Libres » = fiches que personne ne suit : saisies sans être prises, lâchées, ou libérées par la règle P3 | confirmation de Xavier sous P2 |
| 2026-11-24 | La libération se calcule à la lecture (échéance = prochain pas + 7 jours, à défaut dernière action + 7 jours) ; aucun cron, aucune écriture en GET | pas de tâche de nuit à surveiller, « Je prends » constate la liberté dans son UPDATE |
| 2026-11-24 | « Pas intéressé » classe la fiche : plus personne ne la tient, elle reste dans « Tous », hors de « Libres », et peut être reprise | garde la trace pour les collègues sans occuper le plafond |
| 2026-11-24 | Un prochain pas se date à 30 jours au plus pour tous les états, « Plus tard » compris | P3 = B de Xavier ; au-delà il faut redater ou lâcher |
| 2026-11-24 | Les comptes d'un collègue n'apparaissent que par la recherche d'un nom et dans l'avertissement de doublon (nom, sorte, qui l'a amené) ; « Tous » ne liste que des fiches | exception minimale à R1 du 19.11 (« le commercial ne voit que ses artisans »), à faire confirmer par Xavier |
| 2026-11-24 | Une démo créée hors d'une fiche ne crée pas de fiche (point 12 de la SPEC, seconde phrase, retiré) | le compte se retrouve par la recherche ; une fiche « Démo Peintre » créée d'office polluerait le catalogue |
| 2026-11-24 | Numéro IDE contrôlé par sa forme seule | clé de contrôle non prouvée ici |
| 2026-11-24 | La fiche d'un collègue ne montre pas son état saisi, seulement l'état de son compte (démo, essai, client) | P2 = A de Xavier : « nom, localité, métier, qui le suit et depuis quand » |
| 2026-11-24 | L'avertissement de doublon est une page, ses boutons dans la barre du bas, au lieu de la feuille par-dessus le formulaire de la maquette | même contenu, même place, sans script |
| 2026-11-24 | Sur PC, la fiche s'ouvre à côté de la liste « Tous » pour qui lit tout, « Les miens » pour un commercial | P8 de Xavier |

Lectures de Claude à confirmer par Xavier : « au bout d'une semaine » lu comme 7 jours sans action notée (P3 = A), avec une variante B recommandée ; « une liste globale où les prospects qui existent, on peut directement les prendre » lu comme l'onglet « Libres » et « Je prends ».

## Artefacts

| Chemin | Phase | État |
|---|---|---|
| `SPEC.md` | 1 | écrite, à relire par Xavier |
| `MAQUETTE-prospects.html` | 3 | v1, contrôlée, en attente de réponses |
| `PLAN.md` | 4 | écrit |
| `migrations/Version20261124210001.php`, `src/Donnees/DepotProspects.php`, `src/Compte/Prospects.php`, `src/Compte/DoublonProspect.php`, `tests/Compte/ProspectsTest.php` | 5 | lots 1 et 2 verts |
| `src/Http/Controleur/ProspectControleur.php`, `config/routes.php`, `templates/admin/prospect*.html.twig`, `_prospect*.html.twig`, `_prospects_liste.html.twig`, `_menu.html.twig`, `public/css/prospects.css`, `tests/Http/ProspectsEcransTest.php` | 5 | lots 3 à 6 verts |

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
