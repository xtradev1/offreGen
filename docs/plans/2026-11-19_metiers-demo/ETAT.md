---
chantier: Métiers de la démo et de l'essai
slug: 2026-11-19_metiers-demo
phase: 5
statut: en_cours
gate_attendu: aucun
profil: .claude/atoz-profile.md
---

## Suivi Origin

- Projet 44 (offreGen), tâche 483 (Métiers de la démo et de l'essai).
- Sous-tâches : 484 proposition, catalogues et SPEC ; 485 plan, catalogues dans le code, tests ; 486 métier sur le compte, commandes, essai aux prix à 0 ; 487 dictées d'essai, revue et recette.
- Conversation rattachée à 484 le 2026-11-19, puis à 485 le même jour (gate visuel franchi), puis à 486 (lots 2 à 6).

## Prochaine action

Lots 0 à 6 de `PLAN.md` faits, testés, commités (555dea4) et déployés sur le site de test, six démos neuves vues par l'interface. Suite : lot 7 (traceur de l'écran « Vos prix »). `public/css/app.css` et `public/js/app.js` portent encore les modifications d'autres sessions : relire `git status` avant d'y écrire, et ne commiter que ses propres morceaux (`git apply --cached`). Puis lots 8 à 10, lot 11 (dictées d'essai sur le site de test), phases 6 à 8.

## En attente de toi

- Faire commiter `public/css/app.css` et `public/js/app.js` par les sessions qui les ont modifiés : l'écran « Vos prix » s'écrit dans ces deux fichiers.
- Dire si les démos déjà posées sur le site de test se remettent à zéro : posées avant la migration, elles n'ont aucune marque, et leur passage à l'essai garderait leurs deux offres d'exemple.
- Regarder les sept prix corrigés après lecture des pages (`CATALOGUES.md`, premier paragraphe) : ils s'écartent de ce que tu avais vu.

## Journal

Append-only. Une ligne datée par événement. On ajoute, on ne réécrit pas.

- 2026-11-19 : chantier ouvert sur la ligne du roadmap « Métiers de la démo et de l'essai » (écrite par la session des comptes). Phase 0 sautée : le profil existe.
- 2026-11-19 : proposition envoyée à Xavier (M1 à M5). Réponse : « Tout me convient », plus une demande de prix de démonstration « les plus réalistes possibles ».
- 2026-11-19 : tâche Origin 483 créée (8 heures, quatre sous-tâches).
- 2026-11-19 : 18 recherches web sur les prix suisses. `CATALOGUES.md` (sept catalogues, 84 prix) et `SPEC.md` écrits. Gate intention ouvert.
- 2026-11-19 : recherche de l'existant faite par grep dans la session, sans agent `Explore` : un seul fichier porte les métiers (`src/Catalogue/CatalogueFictif.php`), trois appelants.

- 2026-11-19 : question de Xavier sur le passage de la démo à l'essai. Constat : aucun code ne sort un compte du mode démo, rien ne marque une donnée d'exemple, la maquette des comptes v2 annonce que tout reste. Proposition ajoutée à `SPEC.md` (marque « exemple », nettoyage, point 9 du périmètre). Gate intention toujours ouvert.

- 2026-11-19 : réponse de Xavier : « va pour A », avec une idée, une petite interface où l'artisan complète les prix des produits non changés. Écran « Vos prix » ajouté à `SPEC.md` (point 10), en complément de A. La phase 3 (maquette) devient nécessaire. Restent sans réponse : les prix de `CATALOGUES.md`, milieu ou bas de la fourchette, M4, même site.

- 2026-11-19 : Xavier doute de l'électricien (« trop compliqué à mettre en place »), Claude recommande de le retirer. Réponse : le retirer du lot 1 et le remplacer, « comme ça on a 8 plus 1 ». Maçon choisi par Claude, catalogue écrit (une recherche web de plus, 19 en tout). Catalogue de l'électricien gardé dans `CATALOGUES.md` pour le lot 2.
- 2026-11-19 : Xavier a collé ici ses réponses à la maquette du parcours v4 (chantier des comptes), puis « oublie la réponse à la maquette ». Rien n'en est appliqué ici. Signalé à Xavier : ce texte porte P2 = B (prix d'exemple gardés), contraire au « va pour A » donné dans ce chantier.
- 2026-11-19 : la description de la tâche Origin 483 cite encore l'électricien ; aucun outil ne permet de la corriger.
- 2026-11-19 : gate intention franchi. Xavier : « ok pour le reste, fais la maquette Vos prix ». Retenu sans réponse détaillée de sa part : prix de `CATALOGUES.md` tels que proposés, milieu des fourchettes, essai aux prix à 0. Hypothèse non confirmée : la démo et l'essai vivent sur le même site.
- 2026-11-19 : phase 2 (décomposition) sautée : un seul lot, quatre sous-tâches Origin déjà posées.
- 2026-11-19 : phase 3, `MAQUETTE-vos-prix.html` dans la charte Dicta : prototype cliquable du parcours, deux variantes de saisie, états retiré, erreur, fin, vide, reprise depuis le catalogue. Corrigé à la capture : message « retiré » qui couvrait les touches du bas (remonté sous le bandeau), écran qui s'allongeait à l'erreur (le message prend la place de la suggestion), « Plus tard » à 44 px (passé à 48) et mal aligné. Gate visuel ouvert.
- 2026-11-19 : limites connues de la maquette : le comportement du clavier de l'iPhone (variante A) vient de la connaissance générale des navigateurs, sans essai sur un appareil ; l'écran du prototype mesure 690 px de haut, un téléphone plus court fera défiler ; « Annuler » disparaît si le produit retiré était le dernier.
- 2026-11-19 : gate visuel franchi. Réponses de Xavier collées dans le chat : V1 Valider (« quand je clique reprendre, ça doit automatiquement faire suivant minimum de clique »), V2 = A (clavier du téléphone), V3 = A (glisser pour passer), V6 = A (proposer le prix de la démo), V4 Valider, V5 Valider. V2 va contre la recommandation de Claude (pavé dans l'écran) : dit une fois, appliqué.
- 2026-11-19 : phase 4, `PLAN.md` écrit : contrat technique, signatures, trois décisions fragiles, onze lots. `SPEC.md` complétée par les réponses. Constat pendant la lecture du code : l'ETAT du chantier des comptes attend une réponse de Xavier sur l'ordre des deux chantiers, qui touchent `Comptes.php` et `DepotArtisans.php`.
- 2026-11-19 : phase 5 ouverte sur les lots 0 et 1, qui ne touchent aucun fichier partagé.
- 2026-11-19 : lot 0 fait. 14 pages ouvertes, 13 confirment leurs chiffres, une (S10) ne porte aucun montant. Sept prix corrigés dans `CATALOGUES.md` ; estimations sans repère : 32 sur 84. Détail sous « Preuves ».
- 2026-11-19 : lot 1 fait, non commité. Test écrit d'abord et vu rouge (« Métier inconnu : sols »), puis neuf catalogues dans `src/Catalogue/CatalogueFictif.php`. PHPStan a refusé un `array_values` devenu inutile dans `produits()` : retiré. `DemoTest` : l'essai du parquet passe du menuisier au poseur de sols ; un essai de plus pose une démo par métier. Conséquence annoncée dans la spec : la prochaine remise à zéro d'une démo « menuisier » change son contenu.
- 2026-11-19 : arrêt avant le lot 2. Raison : `Comptes.php` et `DepotArtisans.php` sont aussi au plan du chantier des comptes, qui attend la réponse de Xavier sur l'ordre ; `app.css` et `app.js` portent encore les modifications d'autres sessions.
- 2026-11-19 : réponse de Xavier : ce chantier passe (« tu peux ajouter celui-ci »), commit, push, puis site de test (« On va tout mettre sur le site de test ») ; « démo et essai, même chose » : même site, l'hypothèse de la spec est confirmée. Constat au `git log` : le chantier des comptes a déjà commité ses lots 1 à 4 (7a7956d), avec l'ordre « Admin, métiers, puis démo » donné par Xavier à sa session.
- 2026-11-19 : question de Xavier sur l'entorse à la règle du pouce (V2 = A). Réponse : aucune erreur de sa part. Clavier ouvert, les boutons sont juste au-dessus de lui, vers le milieu de l'écran, sous le pouce. L'écart est de forme : ces boutons quittent la barre d'action fixe du bas que `CODING_RULES.md` prescrit, parce que le clavier la recouvrirait.
- 2026-11-19 : lots 2 à 6 faits. Tests écrits d'abord et vus rouges (13 erreurs, 4 échecs), puis migration `Version20261119000002` jouée sur les bases locales de test et de développement, dépôts, `Comptes`, quatre commandes. Un rouge venait du test lui-même (les deux fiches d'exemple y étaient gardées) : test corrigé, un essai de plus pour la fiche corrigée. Suite entière et PHPStan verts.
- 2026-11-19 : commit 555dea4 (lots 0 à 6, 25 fichiers, par chemins) poussé sur `chantier-voix-offre`, puis déployé sur le site de test depuis une copie propre. Premier lancement refusé par le script avant tout envoi : ma copie n'avait pas `.env.test.local`, les tests visaient la base de développement. Second lancement : sonde verte, version précédente 3a1715b.
- 2026-11-19 : six démos créées sur le site de test par `bin/creer-compte --demo --metier=…` (`demo-platrier`, `demo-carreleur`, `demo-macon`, `demo-menuisier`, `demo-sols`, `demo-paysagiste`, toutes `@exemple.ch`). Mots de passe dans `~/.offregen-test-demo-<métier>-compte` (WSL, hors dépôt). `demo-peintre` (5 offres) et `audit-responsive` (autre session) laissées telles quelles : posées avant la migration, elles n'ont ni métier ni marque.
- 2026-11-19 : recette par l'interface des six démos, au téléphone (390 px) : connexion, assistant passé par « Plus tard », « Mes offres », catalogue, première offre. Puis les six comptes remis à zéro par `bin/reinitialiser-demo` pour rendre l'assistant au premier visiteur.
- 2026-11-19 : arrêt avant le lot 7. `public/css/app.css` et `public/js/app.js` portent les modifications non commitées d'autres sessions (chantiers responsive et contrastes des boutons) ; un commit par chemins y embarquerait leur travail.

## Décisions

Ce qui a été tranché sans demander, et pourquoi.

| Date | Décision | Raison |
|---|---|---|
| 2026-11-19 | Une démo neuve par nouveau métier sur le site de test, au lieu de remettre à zéro les démos existantes | `demo-peintre` porte 5 offres que je n'ai pas posées ; effacer le travail d'un autre demande l'accord de Xavier |
| 2026-11-19 | Le plafond d'un essai issu d'une démo vaut N plus les imports déjà reçus | Le plafond compte tous les imports du compte (`PlafondEssai`) ; sans cela, les visites dictées pendant la démo seraient décomptées des N offres promises |
| 2026-11-19 | Une fiche client d'exemple modifiée perd sa marque, comme un produit | Même règle que le point 2 de la spec : ce que l'artisan corrige devient le sien et ne part pas au passage à l'essai |
| 2026-11-19 | `bin/creer-compte --demo --metier=…` pose la démo tout de suite | Sinon la commande créerait une démo vide avec un métier écrit dessus |
| 2026-11-19 | Un texte de métier donné avec un métier de la liste est refusé | L'ignorer en silence cacherait une faute de frappe dans la commande |
| 2026-11-19 | Prix d'exemple 0 pour le produit sans prix du peintre | Il reste marqué « exemple » ; l'écran « Vos prix » ne proposera rien pour lui |
| 2026-11-19 | Migration `000002` jouée après `100001` du chantier des comptes | Les deux sont indépendantes ; Doctrine joue toute migration connue et non jouée, vérifié par `migrations:up-to-date` |
| 2026-11-19 | « Annuler » aussi après « Reprendre » | Un seul toucher écrit un prix inventé dans un vrai catalogue ; le mécanisme existe déjà pour « Je ne fais pas ça » |
| 2026-11-19 | Une seule colonne `produit.prix_exemple_centimes` : marque « exemple » et prix proposé | V6 = A demande de garder le prix de la démo ; une colonne de moins à tenir cohérente |
| 2026-11-19 | « Annuler » d'un produit retiré le recrée depuis la session | Aucune lecture existante du catalogue à filtrer ; coût : les lignes dictées en démo perdent leur lien au catalogue |
| 2026-11-19 | Bandeau de l'accueil pour les seuls comptes hors démo qui ont un produit d'exemple sans prix | Un compte réel existant et un visiteur de démo ne doivent rien voir de neuf |
| 2026-11-19 | « Plus tard » retenu dans la session, sans colonne | Le bandeau revient à la connexion suivante : rappel voulu, aucun schéma de plus |
| 2026-11-19 | Migration numérotée `Version20261119000002` | Le chantier des comptes a réservé `000001` dans son plan |
| 2026-11-19 | Lots 2 à 10 retenus | Fichiers partagés : ordre des chantiers à dire par Xavier, `app.css` et `app.js` non commités par d'autres sessions |
| 2026-11-19 | Maquette : pavé de chiffres dans l'écran proposé à côté du clavier du téléphone (V2) | Le clavier de chiffres de l'iPhone n'a pas de touche de validation et recouvre le bas de l'écran, où la règle du pouce place les boutons |
| 2026-11-19 | Maquette : le glisser fait « Passer » et rien d'autre (V3) | Un geste raté ne doit ni enregistrer un prix ni retirer un produit |
| 2026-11-19 | Maquette : prix de la démo proposé, jamais enregistré d'office (V6) | Réunit « va pour A » et la réponse B donnée à la maquette des comptes |
| 2026-11-19 | Maçon à la place de l'électricien | Chiffre à la surface et au volume, peu de pièces, présent sur les chantiers de rénovation, repères suisses trouvés. Rechange possible : nettoyage |
| 2026-11-19 | Aucun prix à 0 dans les nouveaux catalogues de démonstration | Xavier veut des prix partout ; le peintre garde sa ligne à 0, qui montre « à compléter » |
| 2026-11-19 | Tonte au forfait | Le catalogue fictif s'écrit en francs entiers ; 0.35 franc le mètre carré demanderait des centimes |
| 2026-11-19 | Prix repris tels quels pour les lignes de parquet déplacées vers « Poseur de sols » | Calés le 2026-11-06, déjà vus par Xavier |
| 2026-11-19 | Prix au milieu des fourchettes | Aucun repère jurassien trouvé ; question posée au gate |
| 2026-11-19 | Écran de choix du métier laissé au chantier des comptes | Sa maquette porte déjà les tuiles ; un seul chantier par écran |
| 2026-11-19 | Roadmap non modifié | La ligne existe déjà, dans un diff non commité d'une autre session |

## Artefacts

| Chemin | Phase | État |
|---|---|---|
| `SPEC.md` | 1 | validé par Xavier le 2026-11-19 (« ok pour le reste ») |
| `MAQUETTE-vos-prix.html` | 3 | validée par Xavier le 2026-11-19 (V1 à V6) ; variante A retenue pour la saisie |
| `PLAN.md` | 4 | écrit, onze lots, lots 0 à 6 cochés |
| `CATALOGUES.md` | 1 | pages des sources ouvertes le 2026-11-19, sept prix corrigés ; maçon ajouté, électricien gardé pour le lot 2 |
| `src/Catalogue/CatalogueFictif.php`, `tests/Catalogue/CatalogueFictifTest.php` | 5 | lot 1 livré en code, testé |
| `migrations/Version20261119000002.php`, `tests/Donnees/ColonnesMetiersTest.php` | 5 | lot 2 livré en code, testé |
| `src/Compte/Comptes.php`, `src/Donnees/Depot{Artisans,Produits,Offres,Clients,Factures}.php`, `bin/{creer-compte,reinitialiser-demo,passer-en-essai,metiers-saisis}`, `tests/Compte/{DemoTest,CreationCompteTest,PassageEssaiTest,MetiersSaisisTest}.php` | 5 | lots 3 à 6 livrés en code, testés |

## Preuves

Commande exécutée et sortie collée. Pas de résumé.

### Phase 1

Existant (2026-11-19) :

```
$ grep -r -n -i "metier" migrations src/Donnees
(aucune sortie)
$ grep -r -l "CatalogueFictif::" src bin tests | sort
bin/reinitialiser-demo
src/Compte/Comptes.php
tests/Bout/IaLocaleTest.php
tests/Compte/DemoTest.php
```

Contrôle de `CATALOGUES.md` (script du scratchpad, 2026-11-19) :

```
Plâtrier (nouveau)                       produits 12  uniques 12  offres 2  sans repère  3  défauts []
Carreleur (nouveau)                      produits 12  uniques 12  offres 2  sans repère  4  défauts []
Maçon (nouveau, remplace l'électricien d produits 12  uniques 12  offres 2  sans repère  5  défauts []
Poseur de sols (nouveau, reprend les lig produits 12  uniques 12  offres 2  sans repère  1  défauts []
Paysagiste (nouveau)                     produits 12  uniques 12  offres 2  sans repère  4  défauts []
Menuisier (refait)                       produits 12  uniques 12  offres 2  sans repère  4  défauts []
Général (pour « Autre métier »)          produits 12  uniques 12  offres 2  sans repère 12  défauts []
TOTAL produits 84, sans repère suisse 33
```

Sortie rejouée après le remplacement de l'électricien par le maçon. Avant : électricien 7 sans repère, total 35.

Non vérifié : les lignes des offres d'exemple citent les produits en abrégé ; leur présence au catalogue sera prouvée par `DemoTest` quand les catalogues seront dans le code.

### Phase 3

```
$ python inject_kit.py MAQUETTE-vos-prix.html
kit injecté
$ node check.js MAQUETTE-vos-prix.html
bureau : largeur 1440/1440, images 0, cassées 0, kit oui
telephone : largeur 390/390, images 0, cassées 0, kit oui
$ node parcours-vos-prix.js (téléphone clair, bureau, téléphone sombre ; même sortie sur les trois)
erreurs []  petites []  debordement 0
revenuApresAnnuler "Laquage des plinthes"
glisser "Laquage de porte, 2 faces -> Peinture anti-moisissure"
fin "Prix enregistrés | 6 | À compléter plus tard | 2 | Produits retirés | 1"
$ grep -cP des tirets longs sur MAQUETTE-vos-prix.html
0
```

Parcours joué : ouvrir, « Suivant » sans prix (erreur), taper 165, reprendre le prix de la démo, passer, retirer, annuler, retirer, glisser à gauche, finir. « petites » liste les boutons de la carte sous 48 px : aucun. Captures regardées une par une (offres, carte vide, erreur, message « retiré », fin, sections saisie, retirer et fin, thème sombre).

### Lot 0 : pages des repères ouvertes (2026-11-19)

Douze pages lues par cinq agents de lecture (citations rendues dans la langue de la page, tirets des fourchettes remplacés par un tiret court), deux PDF lus par Claude avec `pypdf`. Les citations des agents n'ont pas été relues par un second moyen : aucun outil de lecture directe dans cette session.

| Source | Ouverte | Ce que la page dit (citation) | Effet sur `CATALOGUES.md` |
|---|---|---|---|
| S1 houzy, plâtrier | oui | « Gipser verrechnen 70 bis 120 Franken pro Stunde » ; « Verputzen von Innenwänden oder von Decken mit 40 bis 120 Franken » ; « Gipskarton einziehen kostet ungefähr 40 bis 100 Franken pro Quadratmeter » | lissage des murs 32 → 40, plafonds 38 → 45, crépi 36 → 40 |
| S2 houzy, maçon | oui, page du 12.11.2023 | « 70 bis 90 Franken für einen gelernten Maurer » ; « 25 bis 40 Franken pro Quadratmeter für Spezialputz » ; « 20 bis 25 Franken pro Quadratmeter für eine bis zu 12,5 Zentimeter dicke, nicht tragende Leichtbauwand » (tableau « Materialkosten ») ; « 550 bis 600 Franken pro Kubikmeter » ; « etwa 500 Franken pro Quadratmeter » ; « 200 bis 250 Franken pro Quadratmeter » ; « 7'500 bis 15'000 Franken » pour 5 m² | ouverture de porte 4500 → 5500, passée en estimation |
| S3 houzy, carreleur | oui, prix de 2023 | « Hourly rate tiler 75 CHF 100 CHF » ; « Ceramic tiles cost 70 to 100 Swiss francs per square metre » ; « Laying wall tiles per m2 50 CHF 100 CHF » ; « Waterproofing substrate per m2 40 CHF 60 CHF » ; « Prime substrate per m2 8 CHF 12 CHF » ; « Skirting boards per lm 20 CHF 50 CHF » ; « Fine stone per m2 30 CHF 150 CHF » | aucun |
| S6 houzy, sols (deux pages) | oui | « Vorbereitung des Untergrunds zusätzlich CHF 25 bis 35 pro m² » ; « Parkett abschleifen und versiegeln kostet rund CHF 30 bis 50 pro m² » ; « Moquette : 80 à 160 francs par mètre carré » ; « 25 à 35 francs par mètre carré pour la pose » | moquette 85 → 150 (le prix était sous la somme matériel et pose) |
| S7 houzy, vinyle | oui, prix de 2023 | « Yard goods per m2 60 CHF 80 CHF » ; « High quality vinyl per m2 80 CHF 100 CHF » ; « Laying per m2 30 CHF 40 CHF » ; « Skirting boards per linear meter 10 CHF 20 CHF » | aucun |
| S8 Hornbach, parquet (PDF) | oui, lu par Claude | « Pose de parquet dès 15m2 (à clipser) inclus pose sous couche 33,00 M2 » ; « dès 40m2 … 29,00 M2 » ; « Pose de parquet collé en plein dès 15m2 … 45,00 M2 » ; « Pose de nouvelles plinthes 11,00 M » ; « à partir du 13.10.2022 » | aucun |
| S9 houzy, jardin | oui, article du 16.11.2026 | « Ein Gärtner kostet in der Schweiz CHF 80 bis 110 pro Stunde » ; « Rasen mähen kostet rund CHF 0.25 bis 0.40 pro m² » ; « CHF 20 bis 40 pro m² mit Rollrasen » ; « CHF 5 bis 15 pro m² mit Ansaat » ; « Gartenweg anlegen pro m² CHF 100-250 » ; « Kiesplatz anlegen pro m² CHF 60-120 » ; haie taillée 8 à 22 le mètre ; « Entsorgung von Grüngut (rund CHF 30 bis 100 pro Ladung) » | taille de haie 7 → 15, déchets verts 140 → 80, repère de la tonte repris de S9 |
| S10 magicheidi, jardinier | oui | « Ce guide ne mentionne donc volontairement aucun montant en francs. » | repère retiré : les chiffres lus le matin dans le résumé du moteur de recherche n'existent pas sur la page |
| S11 Beobachter | oui, article du 3.6.2022 | « den Stundenansatz von 80 bis 100 Franken » | aucun |
| S12 houzy, menuisier | oui, page du 23.3.2023 | « 80 bis 140 Franken in der Stunde » ; « ein gelernter Schreiner 101 Franken in der Stunde » ; « eine Stunde Montagearbeit inklusive Handmaschinen 115 Franken » ; « 2'000 bis 2'500 Franken pro Laufmeter » | libellé du repère corrigé |
| S13 canton du Valais | oui | « mit rund CHF 1200.- pro Fenster » ; « CHF 1800.- pro Fenstertür » | aucun |
| S14 Hornbach, portes (PDF) | oui, lu par Claude | « Démontage de portes intérieures. 60,00 ST » ; « Pose de portes intérieures, 1 à 2 pièces 189,00 ST » ; « dès 3 pièces. 149,00 ST » ; « à partir du 13.10.2022 » | aucun |
| S15 IKEA | oui | « Small kitchen installation: from CHF 500.00 » ; « Average prices as listed by home service professionals registered on NeedHelp in 2018 » | aucun |

S4 et S5 (électricien, lot 2) : non ouvertes.

### Lot 1 : neuf catalogues dans le code (2026-11-19)

Avant le code (test seul) :

```
$ vendor/bin/phpunit tests/Catalogue tests/Compte/DemoTest.php
InvalidArgumentException: Métier inconnu : sols. Choix : peintre, menuisier, sanitaire.
Tests: 14, Assertions: 137, Errors: 5, Failures: 2.
```

Après :

```
$ vendor/bin/phpunit --colors=never tests/Catalogue tests/Compte/DemoTest.php
OK (15 tests, 873 assertions)
$ vendor/bin/phpstan analyse --no-progress
 [OK] No errors
$ vendor/bin/phpunit --colors=never
OK (596 tests, 4547 assertions)
$ php bin/reinitialiser-demo
Usage : bin/reinitialiser-demo <courriel> <peintre|platrier|carreleur|macon|sanitaire|menuisier|sols|paysagiste|autre>
$ grep -cP des tirets longs sur les quatre fichiers du lot
0 partout
```

La suite entière (596) a tourné avant l'ajout de `testChaqueMetierDonneUneDemoComplete` ; après l'ajout, seuls `tests/Catalogue`, `DemoTest` et PHPStan ont été rejoués.

Totaux hors taxe des offres d'exemple, calculés depuis le code (francs) :

```
peintre     Rafraîchissement du séjour            2874
peintre     Façade de la maison                   7860
platrier    Cloison et plafond du bureau          4182
platrier    Lissage du séjour                     3454
carreleur   Salle de bains, sol et murs           3078
carreleur   Carrelage de la cuisine               2915
macon       Ouverture entre cuisine et séjour     6829
macon       Mur de jardin et chape du garage      5626
sanitaire   Salle de bains, rénovation            3670
sanitaire   Remplacement du chauffe-eau           2985
menuisier   Fenêtres du séjour et armoire        11220
menuisier   Portes de l'appartement               2930
sols        Parquet de la chambre                 2479
sols        Vinyle du couloir et de la cuisine    3780
paysagiste  Entretien d'automne                   1070
paysagiste  Allée et gazon                        6592
autre       Petits travaux à la cave              1250
autre       Débarras et remise en état            2390
```

Non vérifié : aucune démo posée et regardée dans l'interface pour les six nouveaux métiers (critère de la spec), aucune dictée d'essai (lot 11).

### Lots 2 à 6 : migration, métier sur le compte, essai, passage, métiers saisis (2026-11-19)

Avant le code (tests seuls) :

```
$ vendor/bin/phpunit --colors=never tests/Donnees/ColonnesMetiersTest.php tests/Compte/DemoTest.php tests/Compte/CreationCompteTest.php tests/Compte/PassageEssaiTest.php tests/Compte/MetiersSaisisTest.php tests/Regles/CommandesTest.php tests/Http/EcransTest.php
ERRORS!
Tests: 67, Assertions: 545, Errors: 13, Failures: 4.
```

Après :

```
$ APP_ENV=test vendor/bin/doctrine-migrations migrate --no-interaction
 [OK] Successfully migrated to version:
      OffreGen\Migrations\Version20261119100001
$ vendor/bin/doctrine-migrations migrations:up-to-date (test, puis développement)
 [OK] Up-to-date! No migrations to execute.
 [OK] Up-to-date! No migrations to execute.
$ vendor/bin/phpunit --colors=never tests/Compte/PassageEssaiTest.php
OK (7 tests, 41 assertions)
$ vendor/bin/phpstan analyse --no-progress
 [OK] No errors
$ vendor/bin/phpunit --colors=never
OK (638 tests, 4919 assertions)
$ php bin/metiers-saisis
Aucun métier saisi sous « Autre métier ».
$ php bin/passer-en-essai
Usage : bin/passer-en-essai <courriel> [--offres=N] [--acces]
$ php bin/creer-compte
Usage : bin/creer-compte <courriel> <entreprise> [--demo] [--plafond=N] [--offres=N] [--acces] [--metier=clé] [--metier-saisi=texte]
--demo et --acces ne vont pas ensemble. Métiers : peintre, platrier, carreleur, macon, sanitaire, menuisier, sols, paysagiste, autre.
$ php bin/reinitialiser-demo
Usage : bin/reinitialiser-demo <courriel> <peintre|platrier|carreleur|macon|sanitaire|menuisier|sols|paysagiste|autre> [texte du métier, avec « autre »]
$ grep -cP des tirets longs sur les dix-huit fichiers des lots
0 partout
```

Le message de la migration cite `100001` : c'est la dernière version connue, pas celle qui vient d'être jouée. La preuve que `000002` est passée : `ColonnesMetiersTest` lit les cinq colonnes dans `information_schema` et passe.

La suite entière tourne dans la copie de travail, qui porte les modifications non commitées d'autres sessions (`app.css`, `app.js`, gabarits). Le script de déploiement la rejoue sur le commit seul.

Non vérifié : `bin/passer-en-essai --acces` (envoi du courriel) n'a aucun test et n'a pas été lancé.

### Déploiement sur le site de test et recette des démos par l'interface (2026-11-19)

```
$ git log -1 --format=%h ; git push origin chantier-voix-offre
555dea4
   3a1715b..555dea4  chantier-voix-offre -> chantier-voix-offre
$ bash outils/deployer-test.sh (copie propre du commit, `git worktree`)
== Tests et analyse
OK (638 tests, 4918 assertions)
analyse statique : sans erreur
== Liens, migrations, bascule, cron
[notice] finished in 71.7ms, used 8M memory, 1 migrations executed, 4 sql queries
en service : releases/555dea491caf31dd2610f02e7d4d10bc221ec48b ; précédente : releases/3a1715b6ebcecab2c554ff97d1c15ae808675bff
== Sonde d'exposition
LU     /sante : {"commit":"555dea491caf31dd2610f02e7d4d10bc221ec48b","base":"ok","migrations":"ok"}
SONDE VERTE
DÉPLOYÉ : 555dea491caf31dd2610f02e7d4d10bc221ec48b sur https://easy-offres.xtradev.ch
```

Une assertion de moins que dans la copie de travail (4919) : celle-ci porte les fichiers non commités d'autres sessions.

Comptes du site de test après création des six démos (lecture seule, courriels des comptes hors démo masqués) :

```
demo-peintre@exemple.ch            demo 1  metier -          offres 5   produits 13  marques 0
audit-responsive@exemple.ch        demo 1  metier -          offres 2   produits 12  marques 0
demo-platrier@exemple.ch           demo 1  metier platrier   offres 2   produits 12  marques 12
demo-carreleur@exemple.ch          demo 1  metier carreleur  offres 2   produits 12  marques 12
demo-macon@exemple.ch              demo 1  metier macon      offres 2   produits 12  marques 12
demo-menuisier@exemple.ch          demo 1  metier menuisier  offres 2   produits 12  marques 12
demo-sols@exemple.ch               demo 1  metier sols       offres 2   produits 12  marques 12
demo-paysagiste@exemple.ch         demo 1  metier paysagiste offres 2   produits 12  marques 12
```

Parcours Playwright au téléphone, une connexion par compte (extraits de ce que le script lit à l'écran) :

```
=== macon : après connexion /bienvenue/entreprise
après « Plus tard » : /
accueil "Maçonnerie Démo (compte de démonstration) 2 offres ce mois … Ouverture entre cuisine et séjour Prête Famille Exemple · Offre 2 … CHF 7'382.15 … Mur de jardin et chape du garage Prête Monsieur Modèle · Offre 1 … CHF 6'081.70"
catalogue {"produits":12,"sansPrix":0,"echantillon":["Béton coulé, coffrage compris béton, coffrage, dalle 420.00 / m³","Chape ciment chape 48.00 / m²", …]}
offre "… Ouverture de porte dans un mur porteur, linteau compris pce 5'500.00 … Sous-total HT CHF 6'829.00 TVA 8,1 % CHF 553.15 Total TTC CHF 7'382.15"
erreurs []
```

Même sortie pour les cinq autres : 12 produits, aucun sans prix, 2 offres, aucun débordement, aucune erreur. Sous-totaux lus à l'écran : carreleur 3'078, maçon 6'829, menuisier 11'220, poseur de sols 2'479, paysagiste 1'070, égaux aux totaux calculés depuis le code (lot 1). Captures ouvertes : catalogue du plâtrier, assistant, offre du maçon, « Mes offres » du menuisier.

Non vérifié : peintre, sanitaire et « autre » n'ont pas été regardés dans l'interface (aucune démo neuve pour eux) ; l'essai créé avec un métier et le passage à l'essai n'ont été lancés que par les tests, pas sur le site ; aucune dictée d'essai (lot 11).
