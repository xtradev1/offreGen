---
chantier: Options et variantes dans l'offre
slug: 2026-11-22_options-variantes
phase: 8
statut: gate_ouvert
gate_attendu: prod
profil: .claude/atoz-profile.md
---

## Suivi Origin

- Projet 44 (offreGen), tâche 499 (Options et variantes dans l'offre). Origin n'ajoute pas de sous-tâches à une tâche existante : tâche 547 « Options et variantes, suite : les lots du chantier » créée le 2026-11-22 à la demande de Xavier, 26 h.
- Sous-tâches : 548 spec et maquette ; 549 banc et corpus écrit ; 550 données, totaux, facturation ; 551 écran ; 552 douze modèles ; 553 extraction ; 554 revue et recette.
- Le banc touche la tâche 447 (sous-chantier 29), sous-tâche 450 (banc sur le corpus).
- Conversation rattachée à 499, puis à 548, puis à 550 (lots 1 et 2) le 2026-11-22.

## Prochaine action

Gate prod ouvert : recette de Xavier faite sur le test (« ça fonctionne ça me convient ») ; italique de Technique ajouté, à déployer sur le test avec son accord. Attendre ses vraies dictées (fiches 21 à 28) pour le banc, sa réponse sur le rabais en francs et son avis sur l'escompte. À la clôture : supprimer les bases `offregen_options_test` et `offregen_options_dev`, la copie `~/offreGen-options`, arrêter le serveur du port 8041, mettre à jour l'ETAT du sous-chantier 29 (le banc existe), `/roadmap-sync`.

## En attente de toi

- Enregistrer les fiches 21 à 28 (`OffreGen/corpus/FICHES.md`) et les déposer dans `corpus/audio/` : je les passe au banc avant la mise en service (scénario 6).
- Trancher : un rabais en francs plafonné au sous-total compte moins sans les options qu'avec (REVUE, C5) ; garder ou changer ? (question reformulée le 23.11)
- Escompte : choisir une formulation parmi celles proposées le 23.11 (offre, facture, carte des conditions). Hors du périmètre de ce chantier (D13, D14 du chantier lignes et conditions).

## Journal

- 2026-11-22 : chantier ouvert à la demande de Xavier (« Options et variantes (tâche Origin 499) »), décisions O1 à O6 et réponses du 22.11 reprises. Copie à part `~/offreGen-options`, détachée sur `origin/chantier-voix-offre` (8385e78), `.env` et `.env.test.local` copiés de `~/offreGen`, `composer install` fait. Base de test à soi : pas encore créée (au premier test).
- 2026-11-22 : phase 0 sautée : profil et `CODING_RULES.md` présents et lus.
- 2026-11-22 : relevé du code (agent Explore, puis contrôlé à la main pour le banc et la consigne) : aucune nature de ligne en base ; `Totaux` compte toutes les lignes ; consigne sans option ; aucune commande vocale ; `outils/banc-corpus.php` absent, `OffreGen/corpus/audio/` vide (vérifié par `ls`). Xavier veut que la dictée passe par le banc du 29 : il est à écrire dans ce chantier.
- 2026-11-22 : `SPEC.md` écrit, gate intention ouvert.
- 2026-11-22 : Xavier : « 1 oui, 2 ok, 3 oui, 4 oui, 5 crée-les ». Gate intention franchi ; le périmètre et le hors périmètre ne sont pas contestés. Tâche 547 et sous-tâches 548 à 554 créées.
- 2026-11-22 : phase 2 (décomposition) sautée : un seul chantier, découpé en lots dans `PLAN.md`.
- 2026-11-22 : phase 3. `MAQUETTE-options-variantes.html`, prototype bâti sur celui des lignes (v3) : téléphone, document Sobre, factures, marche à suivre de dix étapes, cinq choix d'écran (E1 à E5). Défauts vus sur mes captures et corrigés avant de la montrer : la ligne de base remplacée reparaissait sur le document (contraire au point 2) ; la liste « Choix du client » de « Facturer » passait un mot par ligne (une règle `.champ input` étirait les cases) ; l'étape 6 de la marche se défaisait à l'étape 10 ; un raccourci rangeait « porte » d'office dans Chambre (retiré, l'ajout va dans Divers comme dans la v3). Gate visuel ouvert.
- 2026-11-22 : réponses de Xavier : « E1 : A, E2 : A, E3 : Valider, E4 : A, E5 : Valider », sans remarque. Gate visuel franchi. « ensuite tu peux commit et push » : SPEC, ETAT et maquette commités et poussés sur `chantier-voix-offre`.
- 2026-11-22 : commit `06e0690`, rejoué sur `cedc573` (« Activités du compte », autre session) sans conflit. Ce commit change `ExtracteurApi` : la consigne dépend de l'activité du compte, le Bâtiment garde le texte mesuré (empreinte dans `tests/Offre/ExtracteurActiviteTest.php`).
- 2026-11-22 : phase 4, `PLAN.md` écrit (contrat technique, signatures, trois décisions fragiles, huit lots). Constats faits en traçant le flux : `corrections()` d'`app.js` n'envoie pas une case à cocher (il compare `value` à `defaultValue`) et ne remplace que le premier `.totals` ; `lireFacturer` passe par `Totaux::parTaux` et `Totaux::calculer`, la règle dans `Totaux` couvre donc les acomptes.
- 2026-11-22 : base `offregen_options_test` créée en root, droits recopiés de `offregen_test` (relus par `SHOW GRANTS`), `DB_NOM` posé dans le `.env.test.local` de ma copie. Suite de départ : `OK (1159 tests, 10086 assertions)`.
- 2026-11-22 : lot 1 fait. Migration `Version20261122200001` (`nature`, `remplace_id`, `retenue` sur `ligne`), sans clé étrangère de `remplace_id` (raison dans la migration). Montée, descente, remontée sur `offregen_options_test` ; jouée nulle part ailleurs.
- 2026-11-22 : lot 2 fait. `src/Offre/Options.php` ; `Totaux::calculer` et `parTaux` appliquent la règle, `calculer` rend `avec_options`. Tests à part dans `tests/Offre/TotauxOptionsTest.php` (au lieu de `TotauxTest`, prévu au plan : le fichier existant reste lisible) ; `TotauxTest::sansDetail` retire aussi `avec_options` (une ligne). Analyse statique : trois erreurs de typage trouvées et corrigées.
- 2026-11-22 : Xavier : « tu peux enchainer et pousser a chaque fois que tu fini un lot et tu tarrete que quand tas fini ». Lots 1 et 2 poussés (`c57b170`).
- 2026-11-22 : lot 3 fait (lecture et écran). `DepotOffres` lit les trois colonnes, `retenir` (une variante retenue décoche les autres) ; `OffreControleur::grouper` range les variantes sous leur ligne et sort les options (`options()`) ; nouvelle rangée commune `templates/_rangee.html.twig` (ligne, ligne remplacée, variante, option) ; groupe « Options » et « Total avec toutes les options » sous `.totals` ; route `ligne_retenue` et `LigneControleur::retenir` (avancée du lot 4 : sans elle, un lot poussé portait un formulaire mort) ; `corrections()` d'`app.js` envoie une case et remplace `[data-options]`. Écarts au plan : `RelectureControleur::aRelire` passait par `grouper` et aurait perdu les options et variantes à vérifier (corrigé, testé) ; le compteur « Relire N lignes » de l'écran se lit sur `manque + doute` de `vueDocument` (toutes les lignes) au lieu de parcourir les groupes.
- 2026-11-22 : défauts trouvés en route et corrigés : un antislash mangé par le heredoc de l'outil Bash (erreur 500, cause lue dans le journal) ; l'insécable de « au catalogue ? » perdue à la recopie de la rangée (`PrixDicteTest` rouge) ; `IsolationRoutesTest` exigeait un cas pour `ligne_retenue` (ajouté) ; deux constructeurs de lignes d'essai sans les nouveaux champs (phpstan).
- 2026-11-22 : base de développement à soi `offregen_options_dev` (droits recopiés de `offregen_dev`, `DB_NOM` dans le `.env` de ma copie), compte de démo `options-demo@exemple.ch` (fichier `~/.offregen-options-demo-compte`), offre 1 avec une variante et deux options posées en SQL ; serveur local sur le port 8041.
- 2026-11-22 : lot 3 poussé (`bc710c0`). Lot 4 fait (les gestes). `DepotOffres::changerNature` (refuse sans écrire : base hors de l'offre, base qui n'est pas une ligne du total, la ligne elle-même, ligne qui a des variantes ; remet le choix du client à zéro) et `aDesVariantes` ; fenêtre de la ligne : « Cette ligne est » (Dans le total, Option, Variante), « Elle remplace », « + Variante de cette ligne » ; feuille d'ajout : « Elle va » en liens (comme la pièce), « Elle remplace » en petit formulaire, nature et ligne remplacée en champs cachés de chaque produit ; une variante prend la quantité et la pièce de sa ligne ; une variante demandée sans ligne au total devient une option (O3). `app.js` : `optionsEtVariantes()`.
- 2026-11-22 : écart au plan : le choix par défaut de la fenêtre d'une ligne est la dernière ligne du total de l'offre (celle de la pièce dans la feuille d'ajout). Défaut vu sur capture et corrigé : la rangée « Elle remplace » de la feuille d'ajout débordait de 6 px à 390 px. Fausse alerte écartée par mesure : deux puces grises sur une capture, prise pendant le fondu de 0,15 s.
- 2026-11-22 : lot 4 poussé (`395f621`). Lot 5 fait (le document). `OffreControleur::grouper` a un mode document : une option retenue entre dans les lignes de sa pièce (point 2) ; `vueDocument` rend `groupes_document`, lu par `_blocs.html.twig` et Récapitulatif ; une facture n'a que `groupes`. Macros `lignes` et `lignes_marge` : la variante retenue à la place de sa ligne, les autres dessous avec la case et l'écart ; macros `case`, `ecart`, `options`. Les douze modèles appellent `b.options` sous leur total (Récapitulatif : sous le détail des lignes, son total est dans une colonne étroite). Écart au plan : le bloc des options n'est pas dans la macro `totaux`, pour cette raison.
- 2026-11-22 : défaut trouvé au premier rendu : la variante de Technique sortait illisible dans le PDF (IBM Plex n'a pas de face italique ; repli de dompdf). Corrigé : droite dans Technique ; le test refuse toute police de repli dans les douze PDF. Constat à montrer à Xavier : avec six lignes, une variante et deux options, la plupart des modèles laissent les conditions et les signatures seules en page 2 (le bloc de fin ne se coupe pas).
- 2026-11-22 : lot 5 poussé (`6974330`). Lot 6 fait (facturer). La finale copie `Options::lignesDuTotal` ; défaut d'avant prouvé par le test : sans ce lot, la finale copiait aussi la ligne remplacée et les options libres (`Totaux` comptait juste, la copie non). Feuille « Facturer » : « Choix du client » hors du formulaire de facture, une case-bouton par option ou variante qui poste sur `ligne_retenue` avec `retour=facturer` ; dans la feuille, la réponse remplace le contenu et l'aperçu suit (vérifié au clic). Texte de la finale : « Les lignes de l'offre et ce que le client a retenu » quand il y a des options.
- 2026-11-22 : lot 6 poussé (`5eacf1a`). Lot 7 fait (le banc). `outils/banc-corpus.php` (passes, moitié, réglage `service` ou `sans-options`, jetons et coût en centimes, rapport JSON dans `corpus/<dossier>/rapports/`) ; la notation dans `src/Offre/NoteExtraction.php` (écart au plan : `phpstan` n'analyse pas `outils/`, et le test doit charger la classe). Corpus écrit : `OffreGen/corpus/ecrit/`, quinze dictées inventées (six sans option, neuf avec, dont un piège « elle a l'option de… »), catalogues de démonstration peintre, menuisier, plâtrier exportés dans `corpus/catalogues/`. Fiches 21 à 28 ajoutées à `corpus/FICHES.md` pour les vraies dictées de Xavier.
- 2026-11-22 : lot 8 fait (l'extraction). Troisième appel `classer_lignes`, lancé seulement si la dictée contient un mot déclencheur (`MOTS_OPTIONS`, en mode Unicode : sans lui « à la place » ne se reconnaissait pas) ; `validerOptions` traite la sortie comme non fiable ; une variante sans quantité dite prend celle de sa ligne ; `DepotOffres::creer` relie la variante à sa ligne, ou en fait une option à vérifier. Décision fragile n° 2 du plan contredite par le banc : l'appel principal perdait des variantes (la lessivable devenait un second « Peinture des murs », le prix du PVC passait sur la fenêtre bois). Règle ajoutée à sa consigne, puis précisée (une option garde son produit du catalogue) ; consigne du troisième appel durcie contre les fausses options. Empreinte de `ExtracteurActiviteTest` mise à jour avec la mesure qui la justifie.
- 2026-11-22 : reste au banc : la 12 (« ou alors on vitrifie le parquet à la place », parquet absent de l'offre) reste dans le total au lieu d'une option ; la 13 garde une erreur de l'appel principal présente avant le chantier (« murs » rangés en plafond). Le corpus est écrit par Claude et connu pour être optimiste : les vraies dictées de Xavier (fiches 21 à 28) passent au banc avant la mise en service.
- 2026-11-22 : lots 7 et 8 poussés (`5de3b67`). Phase 6 : bout en bout sans l'audio. La dictée 07 du corpus écrit passe par l'extraction réelle puis par `DepotOffres::creerExemple` (même `creer` que le traitement des dictées), compte `options-demo` de la base de développement à soi, offre 3 ; parcours Playwright à 390 px jusqu'à la facture finale, PDF de l'offre et de la facture lus. Constat hors périmètre, d'avant le chantier : sur une facture, une ligne libre est rangée sous « Hors catalogue », que le client lit ; plus visible avec les variantes, souvent libres.
- 2026-11-22 : phase 7, revue lourde (trois relecteurs Opus indépendants : correction, sécurité avec `owasp-security`, régression), parce que le diff touche trois surfaces sensibles du profil : appels au modèle de langue, couche d'accès aux données, droits par route. Estimation annoncée : environ 10 $.
- 2026-11-22 : revue lourde reçue (trois rapports). Trouvailles relues dans le code : 13 retenues (dont 4 majeures : durée des trois appels au-delà du verrou de 900 s, variante d'une ligne supprimée rattachée en silence à une autre, quantité d'une variante au forfait, ligne sortie du total sans « à vérifier »), 2 écartées, 2 hors périmètre ; détail et verdicts dans `REVUE.md`. Huit tests rouges écrits d'abord (`RevueOptionsTest`, `RevueGestesTest`), puis corrections ; deux tests du lot 8 mis à jour (ils figeaient l'ancien comportement). Corrections du script vérifiées au clic (focus, case refusée en 409). Banc complet relancé après coup.
- 2026-11-22 : phase 8. `RECETTE.md` écrit (six scénarios, préalables, ce qui n'est pas couvert). Gate prod ouvert : le déploiement sur le test demande l'accord de Xavier (règle du projet), et la mise en service attend le banc sur ses vraies dictées.
- 2026-11-23 : Xavier : « oui » (déploiement sur le test, données inventées). Contrôle de compatibilité (`docs/DEPLOIEMENT.md`) : la version en service (`cedc573`) passe toute sa suite sur `offregen_options_test`, qui porte la migration. Premier essai faussé par un `vendor` en lien symbolique (les classes venaient de ma copie : 13 échecs sans valeur), refait avec un `composer install` propre. `127854a` déployé par `outils/deployer-test.sh`, sonde verte. Compte de recette `options-recette@exemple.ch` (démo peintre, mot de passe dans `~/.offregen-test-options-recette-compte`) ; contrôle en lecture seule sur le test, sans rien saisir. Copie de contrôle `~/offreGen-compat` retirée.
- 2026-11-23 : Xavier, après sa recette sur le test : « sinon ça fonctionne ça me convient » ; « 4. ajoute l'italique » ; demande ce qu'est le point 2 (vraies dictées) et une meilleure explication du point 3 (rabais en francs) ; « pour l'escompte pourrait-il s'afficher mieux ? ». Italique : faces IBM Plex Sans et Mono italiques (400) ajoutées pour le PDF et l'écran, mêmes sources que les autres faces (relevé `public/fonts/SOURCES.md`), la règle qui redressait la variante de Technique retirée ; le test des gabarits exige les deux faces italiques dans le PDF de Technique avant le choix. Escompte constaté sur l'offre : « Escompte 2 % à 10 jours. » ne dit ni d'où courent les 10 jours, ni ce que le client paie. Un passage de la suite a pris `DB_NOM` de développement (mon `set -a` pour une requête SQL) : le garde-fou `*_test` l'a refusé (827 erreurs), base de développement vérifiée intacte, suite relancée proprement.

## Décisions

| Date | Décision | Raison |
|---|---|---|
| 2026-11-22 | Le banc du sous-chantier 29 s'écrit dans ce chantier, selon la SPEC du 29, avec la nature de la ligne en plus. | Xavier : « La dictée passe par le banc du sous-chantier 29 » ; le banc n'existe pas. |
| 2026-11-22 | La façon d'extraire (champ dans l'appel principal ou second appel) se choisit au banc. | Sous-chantier 28 : un champ de plus dans l'appel principal a fait perdre des prix dictés. |
| 2026-11-22 | Interprétation du 22.11 : le rabais en % s'applique aussi aux options dans « Total avec toutes les options » ; le montant de chaque option se lit avant rabais, comme celui d'une ligne. | « l'option est comprise dans le rabais si c'est pourcentage, c'est sur tout, sinon le rabais est chiffré ». À corriger s'il voulait le montant de l'option déjà rabattu. |
| 2026-11-22 | Corpus : une quinzaine de dictées écrites d'abord (six sans option), puis cinq à huit vraies dictées de Xavier avant la mise en service sur le test. | Point 1 : « oui ». |
| 2026-11-22 | Après le choix : option retenue dans les lignes sans mention, variante retenue à la place de sa ligne de base, le non-retenu dans les blocs, case vide. | Point 2 : « ok ». |
| 2026-11-22 | Plusieurs variantes d'une même ligne, une seule retenue. | Point 3 : « oui ». |
| 2026-11-22 | « Total avec toutes les options » : les options seules, sans les variantes. | Point 4 : « oui ». |
| 2026-11-22 | Le choix du client se coche sur l'offre, et les mêmes cases sont reprises dans « Facturer ». | E1 A. |
| 2026-11-22 | À l'écran, les options forment un groupe « Options » sous le total, comme sur le document. | E2 A. |
| 2026-11-22 | À l'ajout et dans la fenêtre d'une ligne : « Dans le total · Option · Variante » ; pour une variante, la ligne remplacée (dernière du groupe, proposée) ; « + Variante » depuis une ligne. Une ligne qui a des variantes reste dans le total. | E3 Valider. |
| 2026-11-22 | Le montant d'une option est celui de la ligne, avant rabais ; le rabais en % se déduit dans « Total avec toutes les options ». | E4 A (confirme l'interprétation du 22.11). |
| 2026-11-22 | À l'écran, une variante retenue laisse sa ligne de base visible, montant barré ; le document ne montre que la variante. | E5 Valider. |
| 2026-11-22 | Lots 1 et 2 gardés dans la copie, ni commités ni poussés, jusqu'à l'accord de Xavier. | La tête de `chantier-voix-offre` part sur le test par d'autres sessions : la migration y serait jouée sans accord. |
| 2026-11-22 | Chaque lot vérifié est commité et poussé sur `chantier-voix-offre` sans redemander ; le déploiement sur le test reste à demander. | Xavier : « pousse les lots au fur et a mesure ». Portée, lecture de Claude : ce chantier, ses fichiers, jamais `main`. |
| 2026-11-22 | `remplace_id` sans clé étrangère. | Cascade de la suppression d'une offre sur une table qui se référence elle-même ; la cohérence se vérifie dans `DepotOffres::changerNature`. |
| 2026-11-22 | Dans Technique, la variante reste droite (pas d'italique). | IBM Plex n'a pas de face italique dans le document : le PDF retombait sur une police de repli, texte illisible. Ajouter la face italique (fichier, empreinte, `SOURCES.md`) reste possible si Xavier y tient (O2 dit « en italique »). |
| 2026-11-23 | Remplace la décision précédente : la variante de Technique est en italique, faces IBM Plex Sans et Mono italiques ajoutées. | Xavier : « 4. ajoute l'italique ». |
| 2026-11-22 | À l'écran, une option retenue reste dans le groupe « Options » ; sur le document, elle entre dans les lignes de sa pièce. | E2 A (écran comme le prototype) et point 2 (document). |
| 2026-11-22 | La consigne principale reçoit une règle (option ou variante dite : ligne à part ; l'option garde son produit, la variante sa désignation et son prix). | Banc : sans elle, l'appel principal perdait trois variantes sur neuf dictées ; avec elle, moitié de contrôle 94,7 % au lieu de 57 %, dictées sans option inchangées (100 %). |
| 2026-11-22 | Les options et variantes viennent d'un troisième appel, lancé seulement sur un mot déclencheur. | Une offre ordinaire ne coûte que la règle de plus (+0,007 ct) ; une offre à options +0,06 ct (+30 %). |

## Artefacts

| Chemin | Phase | État |
|---|---|---|
| `SPEC.md` | 1 | validé le 2026-11-22 |
| `MAQUETTE-options-variantes.html` | 3 | validée le 2026-11-22 (E1 à E5) |
| `PLAN.md` | 4 | écrit le 2026-11-22 |

## Preuves

### Phase 1

```
$ ls outils/ bin/
bin/: administrateur creer-compte metiers-saisis offres-essai passer-en-essai purger-audio reinitialiser-demo traiter-file verifier-ia
outils/: compte-essai-test.sh deployer-test.sh ia-locale localites.php
$ ls -la ".../OffreGen/corpus/audio"
total 0 (dossier vide)
```

### Phase 3

```
$ python inject_kit.py MAQUETTE-options-variantes.html && node check.js MAQUETTE-options-variantes.html cap
bureau : largeur 1440/1440, images 0, cassées 0, kit oui
telephone : largeur 390/390, images 0, cassées 0, kit oui
OK.
$ node parcours.js 1400   (scratchpad, dix étapes au clic)
apres etape 1 | 1 sur 10 faites
apres etape 2 | 2 sur 10 faites
titre ajout variante : Variante de « Peinture des plafonds » | base proposee : 3
apres etape 3 | 4 sur 10 faites
totaux ecran : Sous-total 2'964.00 Rabais fidélité 5 % -148.20 Total HT 2'815.80 TVA 8,1 % 228.08 Arrondi 0.02 Total TTC CHF 3'043.90
  Total avec toutes les options CHF 3'882.90
apres etape 5 | 5 sur 10 faites
bandeau visible : true
apres etape 6 | 7 sur 10 faites
totaux ecran apres choix : Sous-total 3'636.00 Rabais fidélité 5 % -181.80 Total HT 3'454.20 TVA 8,1 % 279.79 Arrondi 0.01 Total TTC CHF 3'734.00
bandeau apres renvoi : false
acompte : Acompte de 30 %, selon offre n° 42 (CHF 3'734.00) 1'120.20
finale (portes décochées dans Facturer) : Sous-total 3'216.00 Rabais fidélité 5 % -160.80 ... Total TTC CHF 3'302.65 Acompte, facture 2026-031 -1'120.20 À payer CHF 2'182.45
apres etape 10 | 10 sur 10 faites
largeur page 1400/1400
erreurs JS : aucune
$ node parcours.js 390
apres etape 10 | 10 sur 10 faites
largeur page 390/390
erreurs JS : aucune
Recalcul à la main : 1176+768+840+60+120 = 2964 ; options 312+85+420 : 3781 - 5 % = 3591.95, TVA 290.95, 3882.90 ; après le choix 2964-1176+1428+420 = 3636 ; acompte 3734.00 x 30 % = 1120.20 ; finale 3302.65 - 1120.20 = 2182.45.
Tirets longs : 0 ; insécables : 56 ; BOM : non.
Captures regardées : bureau, doc-propose-1400, tel-choix-1400, doc-choix-390, tel-facturer-390 et 1400 (après correction), doc-finale-1400.
```

### Phase 5, lots 1 et 2

```
$ APP_ENV=test vendor/bin/phpunit   (départ, 06e0690, base offregen_options_test)
OK (1159 tests, 10086 assertions)

Lot 1
$ phpunit --filter ColonnesOptionsTest   (avant la migration)
Tests: 2, Assertions: 4, Failures: 1.     (+Array &0 [])
$ doctrine-migrations migrations:migrate          [notice] Migrating up to OffreGen\Migrations\Version20261122200001 ... [OK] Successfully migrated
$ doctrine-migrations migrations:migrate prev     [notice] Migrating down to OffreGen\Migrations\Version20261122100001 ... [OK]
  phpunit --filter ColonnesOptionsTest  ->  Tests: 2, Assertions: 4, Failures: 1.
$ doctrine-migrations migrations:migrate          [notice] Migrating up to OffreGen\Migrations\Version20261122200001 ... [OK]
$ phpunit --filter 'ColonnesOptionsTest|SchemaTest'
OK (6 tests, 18 assertions)

Lot 2
$ phpunit --filter 'OptionsTest|TotauxOptionsTest|TotauxTest'   (avant le code)
ERRORS! Tests: 36, Assertions: 45, Errors: 7, Failures: 5, Warnings: 1.   (Undefined array key "avec_options")
$ phpunit --filter 'OptionsTest|TotauxOptionsTest|TotauxTest'   (après)
OK (36 tests, 67 assertions)
$ APP_ENV=test vendor/bin/phpunit   (après la correction de typage)
OK (1174 tests, 10118 assertions)
$ vendor/bin/phpstan analyse --error-format=raw   (première passe)
Options.php:88 masqueAvecOptions() should return list<bool> ; Options.php:125 lignesDuTotal() should return list<T…> ; TotauxTest.php:197 Cannot unset offset 'avec_options'
$ vendor/bin/phpstan analyse --error-format=raw   (après correction)
(aucune erreur)
BOM et tirets longs dans les fichiers touchés : aucun.
```

### Lot 3

```
$ phpunit --filter OptionsEcranTest   (code du lot mis de côté par git stash)
Tests: 8, Assertions: 22, Failures: 6.
$ phpunit --filter OptionsEcranTest   (code remis)
OK (8 tests, 50 assertions)
$ vendor/bin/phpstan analyse
[OK] No errors
$ APP_ENV=test vendor/bin/phpunit
OK (1182 tests, 10180 assertions)
BOM et tirets longs dans les fichiers touchés : aucun.
$ node ecran3.js   (local, port 8041, offre 1 : échafaudage 2'400, façade 5'400, déplacement 60 ; variante façade minérale 52.00 ; options portes 3 x 140, radiateur 85)
390 total CHF 8'496.65 | avec options CHF 9'042.55
390 cible de la case 161x48 | bouton sans JS caché true
390 après variante CHF 9'404.70 | remplacée 1 | libellé Retenue, à la place
390 après option CHF 9'858.70 | avec options CHF 9'950.60 | bouton sans JS caché après remplacement true
390 rechargements de page pendant les cases 0
390 largeur page 390/390 | erreurs JS aucune ; remis CHF 8'496.65
1280 : mêmes montants, mêmes contrôles, largeur 1280/1280, erreurs JS aucune
Recalcul à la main : 7'860.00 + TVA 636.66 = 8'496.66 → 8'496.65 ; avec portes et radiateur 8'365.00 + 677.57 = 9'042.57 → 9'042.55 ;
  variante 8'700.00 + 704.70 = 9'404.70 ; + portes 9'120.00 + 738.72 = 9'858.72 → 9'858.70.
$ node ecran3b.js : « Total avec toutes les options » et « Conditions » visibles à 390 et 1280, rien posé dessus (elementFromPoint).
Captures regardées : ecran3-apres-390, ecran3-apres-1280, ecran3-options-390.
```

### Lot 4

```
$ phpunit --filter OptionsGestesTest   (code du lot mis de côté par git stash)
Tests: 10, Assertions: 23, Failures: 9.
$ phpunit --filter OptionsGestesTest   (code remis)
OK (10 tests, 52 assertions)
$ phpunit --filter 'OptionsGestesTest|OptionsEcranTest'
OK (18 tests, 102 assertions)
$ vendor/bin/phpstan analyse
[OK] No errors
$ APP_ENV=test vendor/bin/phpunit
OK (1192 tests, 10232 assertions)
$ node --check public/js/app.js  ->  sans erreur
$ node ecran4.js   (local, port 8041)
390 remplace caché au départ true ; visible après Variante true ; choix proposé Peinture de façade, 2 couches ; cible d'une puce 112x48
390 fond de Variante rgb(35, 39, 45), de Dans le total rgb(255, 255, 255)   (après le fondu)
390 feuille d'ajout : nature courante Variante ; liste changée -> /offres/1/ajouter?nature=variante&remplace=3
390 largeur page 396/390 (avant correction) puis 390/390 ; erreurs JS aucune
1280 : mêmes contrôles, largeur 1280/1280, erreurs JS aucune
Captures regardées : ecran4-ligne-390, ecran4-ajout-390 (avant et après correction).
```

### Lot 5

```
$ phpunit --filter GabaritsOptionsTest   (code du lot mis de côté par git stash)
Tests: 37, Assertions: 74, Failures: 36.
$ phpunit --filter GabaritsOptionsTest   (code remis)
OK (37 tests, 242 assertions)
$ phpunit --filter 'GabaritsOptionsTest|ReglesDuDocumentTest|GabaritsPdfTest|GabaritsFactureTest'
OK (168 tests, 1250 assertions)
$ vendor/bin/phpstan analyse
[OK] No errors
$ APP_ENV=test vendor/bin/phpunit   (avant la dernière correction de typage, d'un test seulement)
OK (1229 tests, 10492 assertions)
Texte extrait du PDF de Technique avant la correction : « 1’176.00噡物慮瑥ꀺ⁐敩湴畲攠... » (ligne de variante illisible).
Planches regardées (pdf5.php, PyMuPDF à 70 dpi) : douze modèles avant le choix (22 pages), Sobre et Technique après le choix.
  Avant : variantes sous « Peinture des murs » (+252.00, −84.00), bloc « Options », total avec toutes les options CHF 3'890.50.
  Après : « Peinture lessivable » à la place de sa ligne, minérale −336.00, portail dans les lignes, total CHF 4'071.05
  (3'766.00 + TVA 305.046 → 305.05), radiateur seul dans les options, CHF 4'162.95.
```

### Lot 6

```
$ phpunit --filter FacturerOptionsTest   (code du lot mis de côté par git stash)
Tests: 6, Assertions: 31, Failures: 4.   (cases absentes ; finale avec la ligne remplacée et les options : testSansChoixLaFinaleGardeLaLigne rouge)
$ phpunit --filter FacturerOptionsTest   (code remis, test corrigé pour phpstan)
OK (6 tests, 37 assertions)
$ vendor/bin/phpstan analyse
[OK] No errors
$ APP_ENV=test vendor/bin/phpunit   (avant la correction de typage du test)
OK (1235 tests, 10527 assertions)
$ node ecran6.js   (local, port 8041, feuille ouverte par-dessus l'offre 1)
390 cases 3 | bouton Créer la facture · CHF 8'496.65
390 après la case : cochées 1 | bouton Créer la facture · CHF 9'404.70 | feuille ouverte true
390 remis | largeur 390/390 | erreurs JS aucune
1280 : mêmes valeurs, largeur 1280/1280, erreurs JS aucune
Capture regardée : ecran6-facturer-390.
```

### Lots 7 et 8

```
$ phpunit --filter NoteExtractionTest   (premier passage)
Failed asserting that 1 is identical to 1.0.   (taux entier, corrigé)
OK (5 tests, 14 assertions)
$ phpunit --filter ExtracteurOptionsTest   (code du lot 8 mis de côté par git stash)
Tests: 6, Assertions: 5, Errors: 3, Failures: 2, Warnings: 2.
$ phpunit --filter 'ExtracteurOptionsTest|ExtracteurApiTest|ExtracteurActiviteTest|TraiteurTest'   (code remis)
OK (61 tests, 228 assertions)

Banc, Qwen/Qwen3.8-27B-FP8, trois passes par dictée, corpus écrit (OffreGen/corpus/ecrit), tarif 0.40 / 3.00 CHF par million de jetons :
Référence (code d'avant le lot 8), les quinze dictées :
  Dictées sans option : taux 93.3 % (84 justes sur 90), 0.33 corrections par offre
  Dictées avec options : taux 58.3 % (84 justes sur 144), 2.22 corrections par offre, natures 0/42
  Coût moyen 0.20 ct par offre, durée moyenne 9.0 s (45 passes)
Troisième appel seul (consigne principale inchangée), dictées 07 à 15 :
  Dictées avec options : taux 73.0 % (108 justes sur 148), 1.48 corrections par offre, natures 26/42
Règles ajoutées, moitié de réglage (01, 03, 05, 07, 09, 11, 13, 15), dernière version :
  Dictées sans option : taux 87.5 % (42 justes sur 48)   (référence sur ces trois dictées : 42 sur 48)
  Dictées avec options : taux 88.9 % (72 justes sur 81), 0.60 corrections par offre, natures 24/24
Moitié de contrôle (02, 04, 06, 08, 10, 12, 14), jamais vue pendant le réglage :
  Dictées sans option : taux 100.0 % (42 justes sur 42)   (référence : 42 sur 42)
  Dictées avec options : taux 94.7 % (54 justes sur 57), 0.25 corrections par offre, natures 15/18   (référence : 36 sur 63, 57 %)
  seule faute : 12, « Vitrification du parquet : nature », trois passes sur trois
Coût par offre, moitié de contrôle : sans option 0.200 ct avant, 0.207 ct après (+0.007 ct, +3.5 %, la règle de plus dans la consigne) ;
  avec options 0.198 ct avant, 0.258 ct après (+0.06 ct, +30 %, le troisième appel).
Rapports JSON : corpus/ecrit/rapports/2026-11-22-2020-service-toutes.json (référence), 2026-11-22-2032-service-reglage.json, 2026-11-22-2034-service-controle.json.

$ vendor/bin/phpstan analyse
[OK] No errors
$ APP_ENV=test vendor/bin/phpunit
OK (1246 tests, 10562 assertions)
BOM et tirets longs dans les fichiers touchés : aucun.
```

### Phase 6

```
$ php bout.php corpus/ecrit/07-options-rochat   (extraction réelle, Qwen3.8 27B, base offregen_options_dev)
0. ligne | Peinture des murs, 2 couches | 42.000 m² | 28.00 | remplace NULL | à vérifier non
1. variante | Peinture des murs lessivable, 2 couches | 42.000 m² | 34.00 | remplace 0 | à vérifier oui
2. ligne | Peinture des plafonds, 2 couches | 24.000 m² | 32.00 | remplace NULL | à vérifier non
3. option | Laquage de porte, 2 faces | 3.000 pce | 160.00 | remplace NULL | à vérifier non
4. option | Laquage des plinthes | 26.000 m | 12.00 | remplace NULL | à vérifier oui
5. ligne | Déplacement | 1.000 forfait | 60.00 | remplace NULL | à vérifier non
OFFRE=3
$ node bout.js 3   (390 px)
écran : total CHF 2'166.30 | bouton Relire 2 lignes CHF 2'166.30 | variantes 1 | options 2 | Total avec toutes les options CHF 3'022.50
variante : Variante Peinture des murs lessivable, 2 couches | 1'428.00 +252.00
après le choix : total CHF 2'957.60 | remplacée 1
feuille : cases cochées 2 sur 3
facture 1 PDF 200 | erreurs JS aucune
PDF de l'offre (PyMuPDF) : « Séjour Peinture des murs lessivable, 2 couches 42 m² 34.00 1'428.00 … Laquage de porte, 2 faces 3 pce 160.00 480.00 Sous-total HT 2'736.00 TVA 8,1 % 221.62 Arrondi -0.02 Total TTC CHF 2'957.60 OPTIONS … Laquage des plinthes 26 m 12.00 312.00 Total avec toutes les options TTC CHF 3'294.90 »
PDF de la facture 2026-001 : lessivable, plafonds, portes, déplacement ; ni la peinture des murs standard ni les plinthes ; Total TTC CHF 2'957.60.
Recalcul : 1'176 + 768 + 60 = 2'004.00 + TVA 162.32 = 2'166.32 → 2'166.30 ; + 480 + 312 = 2'796.00 + 226.48 → 3'022.50 ;
  après le choix 1'428 + 768 + 480 + 60 = 2'736.00 + 221.62 → 2'957.60 ; + 312 = 3'048.00 + 246.89 → 3'294.90.
```

### Phase 7

```
$ phpunit --filter 'RevueOptionsTest|RevueGestesTest'   (avant les corrections)
Tests: 8, Assertions: 19, Failures: 8.
$ APP_ENV=test vendor/bin/phpunit   (après les corrections)
OK (1254 tests, 10589 assertions)
$ vendor/bin/phpstan analyse
[OK] No errors
$ node js9.js   (local, port 8041)
C8 focus : INPUT[checkbox]#l11
C9 réponse 409 | case cochée après false | en base 0 | état Non enregistré, réessayez
erreurs JS aucune
$ php outils/banc-corpus.php corpus/ecrit --passes=3   (après les corrections)
Dictées sans option : taux 93.3 % (84 justes sur 90), 0.33 corrections par offre
Dictées avec options : taux 96.3 % (130 justes sur 135), 0.19 corrections par offre, natures 39/42
Coût moyen 0.24 ct par offre, durée moyenne 3.8 s (45 passes)
Rapport : corpus/ecrit/rapports/2026-11-22-2058-service-toutes.json
```

### Phase 8, déploiement sur le test

```
$ curl -s https://easy-offres.xtradev.ch/sante   (avant)
{"commit":"cedc573cf0691285e1ab95831cce019f1886b10e","base":"ok","migrations":"ok"}
$ (copie ~/offreGen-compat sur cedc573, composer install, DB_NOM=offregen_options_test, migration 20261122200001 présente : 1)
APP_ENV=test vendor/bin/phpunit  ->  OK (1159 tests, 10086 assertions)
$ bash outils/deployer-test.sh   (HEAD = origin/chantier-voix-offre = 127854a, aucune autre suite en cours)
LU     /sante : {"commit":"127854a630259b82bae31f134e32c32849d0dc67","base":"ok","migrations":"ok"}
OK     commit déployé : 127854a630259b82bae31f134e32c32849d0dc67
OK     base joignable
OK     migrations à jour
SONDE VERTE
DÉPLOYÉ : 127854a630259b82bae31f134e32c32849d0dc67 sur https://easy-offres.xtradev.ch
$ node test_site.js   (https://easy-offres.xtradev.ch, options-recette, 390 px, une connexion, rien de saisi)
après connexion : /bienvenue/entreprise
offre 77 HTTP 200 | conteneur des options 1 | cases 0
fenêtre de ligne : Cette ligne est | [ 'Dans le total', 'Option', 'Variante' ]
feuille d'ajout : Elle va Dans le total Option Variante
PDF de l'offre 200 application/pdf
largeur 390/390 | erreurs JS aucune
```

### 23.11, italique de Technique

```
$ APP_ENV=test vendor/bin/phpunit tests/Offre/PolicesDocumentTest.php tests/Offre/GabaritsOptionsTest.php
OK (44 tests, 720 assertions)
$ APP_ENV=test vendor/bin/phpunit
OK (1254 tests, 10629 assertions)
$ vendor/bin/phpstan analyse
[OK] No errors
$ polices du PDF de l offre 1 (base de développement, modèle Technique), PyMuPDF
IBMPlexSans, Roboto-Regular, Archivo-ExtraBold, IBMPlexSans-Bold, IBMPlexSans-SmBld, IBMPlexMono-SmBld, IBMPlexMono,
IBMPlexSans-Italic, IBMPlexMono-Italic, IBMPlexMono-Bold
```
Capture regardée : la rangée « Variante : Peinture de façade minérale, 2 couches 120 m² 52.00 +840.00 » en italique, lisible.
