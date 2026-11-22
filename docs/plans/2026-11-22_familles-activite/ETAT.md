---
chantier: Activités (bâtiment, extérieur, prestations)
slug: 2026-11-22_familles-activite
phase: 8
statut: gate_ouvert
gate_attendu: prod
profil: .claude/atoz-profile.md
---

## Suivi Origin

- Projet 44 (offreGen), tâche 539 « Activités : ouvrir l'application aux prestations et à l'extérieur » : 540 SPEC et décisions, 541 plan, 542 socle (activité sur le compte, migration, consigne, rangement, unités), 543 mots des écrans et des modèles, réglage, 544 catalogues et création d'une démo, 545 revue, recette, dictées. Décision d'origine : tâche 538, à fermer par Xavier.

## Prochaine action

Lire la réponse de Xavier au gate prod. S'il donne son accord : dans `~/offreGen-familles`, `git fetch`, rebase sur `origin/chantier-voix-offre` si elle a avancé, suite complète, commit par chemins, `git push origin HEAD:chantier-voix-offre`, puis déploiement sur le test selon la mémoire « Production et test » (dossier propre à la session, tête de la branche, `/sante` relu). Ensuite sa recette, puis la clôture (`roadmap-sync`, suppression des bases `offregen_familles_test` et `offregen_familles_vue`).

## En attente de toi

- Dire si Claude commite, pousse sur `chantier-voix-offre` et déploie sur le site de test (le déploiement joue la migration `Version20261122100001`).
- Dérouler `RECETTE.md` sur le site de test, sept scénarios, et dire ce qui casse.
- Relever, sur cinq dictées de prestations et cinq dictées de jardinier, les lignes justes et les lignes à corriger (scénarios 2 et 5).
- Corriger les prix des catalogues Informatique, Conseil, Marketing et « Autre prestation » s'ils sont faux (`docs/plans/2026-11-19_metiers-demo/CATALOGUES.md`).
- Trancher le relevé de la sonde sur l'écran d'une offre à 390 px (boutons de suppression qui dépassent, d'avant ce chantier).

## Journal

Append-only. Une ligne datée par événement. On ajoute, on ne réécrit pas.

- 2026-11-22 : chantier ouvert sur la décision de Xavier du même jour (tâche Origin 538, roadmap de `~/offreGen`, ligne de décision du 22.11). Tâche Origin 539 créée, conversation rattachée à la sous-tâche 540.
- 2026-11-22 : phase 0 sautée, le profil `.claude/atoz-profile.md` existe (daté du 2026-11-07 ; ses commandes « à créer » existent depuis : `vendor/bin/phpunit`, `vendor/bin/phpstan analyse`).
- 2026-11-22 : copie de travail `~/offreGen-familles` (`git worktree add --detach`, tête `8385e78` de `origin/chantier-voix-offre`), `.env` et `.env.test.local` copiés. `~/offreGen` a 40 commits de retard et porte les fichiers d'autres sessions.
- 2026-11-22 : échange de conception mené dans la conversation avant l'ouverture du dossier (quatre réponses de Xavier), sans le skill `brainstorming`. Recherche de l'existant faite par `grep` dans la session, sans agent : sept motifs sur `src`, `templates`, `bin`, `migrations`.
- 2026-11-22 : constats sur la tête, avant la SPEC : l'unité « jour » existe déjà ; `templates/admin/demo_nouvelle.html.twig` est sur la branche ; « famille » désigne déjà les groupes de modèles (`Marque::FAMILLES`) ; aucune infrastructure de textes par clé ; `Extracteur::extraire` ne reçoit ni le compte ni le métier ; 96 fichiers de tests.
- 2026-11-22 : Xavier, pendant le cadrage : « j'ai des clients en test qui détournent déjà le test », « pour faire des prestations et du conseil », « cadre d'abord, je ferai les dictées après ». Porté en R1 et R2 de la SPEC.
- 2026-11-22 : `SPEC.md` écrite, gate intention ouvert.
- 2026-11-22 : gate intention passé. Réponses de Xavier reportées à la fin de `SPEC.md` : pas de vrais clients sur le site de test (accès ouvert), « ok » pour le lieu, les mots, l'absence de maquette, le périmètre et le hors périmètre ; « passons au développement ». Conversation rattachée à la sous-tâche 541.
- 2026-11-22 : phase 2 (décomposition) sautée : six lots, un seul livrable testable de bout en bout, aucune partie bloquée par une décision.
- 2026-11-22 : phase 3 (maquette) sautée, accord de Xavier au gate (R5) : aucun écran ni composant nouveau, captures à la recette.
- 2026-11-22 : phase 4, `PLAN.md` écrit dans la session, sur le format de la référence, sans le skill `writing-plans`. Lecture préalable : extraction, traitement, dépôt des comptes, création des comptes et des démos, catalogues, réglages, douze gabarits, phrases de Lettre, tests existants.
- 2026-11-22 : constats de la phase 4 : `app.js` réécrit « Parlez, pièce par pièce. » au début de chaque enregistrement (ligne 631), la phrase doit donc voyager dans un attribut ; l'outil principal de l'extraction n'a pas de champ « pièce », les pièces ne viennent que du second appel ; Twig est en variables strictes ; les phrases par défaut du modèle Lettre disent « travaux » (`PhrasesLettre::ACTUELLES`) ; trois tests figent les listes de métiers et d'unités.
- 2026-11-22 : base de test du chantier `offregen_familles_test` créée en root, droits recopiés de `offregen_test` ; `DB_NOM` écrit dans le `.env.test.local` de la copie ; `composer install` fait dans la copie.
- 2026-11-22 : phase 5 ouverte. Empreintes des consignes du Bâtiment relevées sur `8385e78` avant tout changement (voir Preuves).
- 2026-11-22 : suite de référence avant tout changement de code existant : 1075 tests, 2 échecs dans `SanteTest` (503). Cause : le fichier de la migration du chantier était déjà posé, pas encore joué. Migration jouée, `SanteTest` vert.
- 2026-11-22 : lot 1 fait. L'énumération `Activite` a été écrite avant son test ; le test d'écran a été vu rouge avant le code (phrase absente de l'attribut).
- 2026-11-22 : lot 2 fait. Deux assertions de mes tests corrigées : une apostrophe attendue échappée à tort, et un contrôle trop large qui refusait le nom du modèle « Chantier » dans une aide.
- 2026-11-22 : lot 3 fait. Tests et code écrits à la suite, sans passe rouge intermédiaire : les tests ne compilaient pas avant le code (méthodes absentes). Trois doublures d'`Extracteur` mises à la nouvelle signature dans `TraiteurTest`.
- 2026-11-22 : lot 4 fait. Découverte en chemin : la phrase de paiement par défaut dit « dès la fin des travaux » (`Conditions`), `app.js` la refait à l'écran, et les libellés d'acompte proposés disent « travaux » et « mi-chantier ». Ajout de `Activite::dire`, qui transpose les phrases fabriquées par l'application, jamais un texte tapé.
- 2026-11-22 : lot 5 fait. Prix des quatre catalogues de prestations tirés d'une recherche (trois requêtes), sources S16 à S18 ajoutées à `CATALOGUES.md` avec leur réserve : résumés de recherche, pages non ouvertes.
- 2026-11-22 : lot 6 fait.
- 2026-11-22 : phase 6. Première passe complète : 1146 tests, 1 échec (un test existant fige la balise `data-phrase`, que j'avais chargée d'attributs : attributs déplacés sur le paragraphe) et 4 remarques de PHPStan (un `array_values` devenu sans effet, trois accès dans un test neuf). Corrigé, seconde passe verte.
- 2026-11-22 : phase 7, revue lourde : le diff touche l'appel au modèle et une écriture sur le compte. Trois relecteurs Opus, environ 4,50 $ estimés. Un défaut haut trouvé par les trois, reproduit puis corrigé ; cinq corrections mineures. Détail : `REVUE.md`.
- 2026-11-22 : pendant la revue, les tests ciblés des relecteurs ont vidé la base `offregen_familles_test`, où vivaient les comptes de la recette visuelle, et percuté une de mes passes (doublons, clés étrangères). Base à part créée pour la recette visuelle : `offregen_familles_vue`.
- 2026-11-22 : recette visuelle locale (skill `recette-visuelle`) : douze captures regardées, à 390 px et à 1280 px, sur quatre comptes inventés. Deux défauts vus et corrigés : l'aide « Par exemple : Peinture de finition » montrée à un prestataire, et l'explication du réglage plus aérée que les autres aides (passée en paragraphe). Captures et sondes dans le scratchpad de la session, non versionnées.
- 2026-11-22 : phase 6 rejouée après la revue : 1159 tests verts, PHPStan sans erreur, migration annulée puis rejouée.
- 2026-11-22 : phase 8, `RECETTE.md` écrite. Gate prod ouvert. Rien n'est commité ni déployé : l'accord permanent de commit et de déploiement vaut pour le chantier de l'administration seulement.

## Décisions

Ce qui a été tranché sans demander, et pourquoi.

| Date | Décision | Raison |
|---|---|---|
| 2026-11-22 | Le concept s'appelle « activité » dans le code (`Activite`, `artisan.activite`) | « famille » y nomme déjà les groupes de modèles de « Ma marque » |
| 2026-11-22 | Trois activités, « Événement » écartée | aucun demandeur ; Xavier a cité l'informatique, le conseil, le marketing et un jardinier |
| 2026-11-22 | Une seule liste d'unités pour tous, « mois » et « passage » ajoutés | une liste par activité ajoute une variation pour un gain nul |
| 2026-11-22 | Prestations : aucun second appel, rangement par catégorie du catalogue | le repli existe déjà ; un rangement par phase serait un comportement d'IA jamais mesuré |
| 2026-11-22 | « Paysagiste » devient « Jardinier, paysagiste », catalogue inchangé | il contient déjà l'entretien (tonte, tailles, heure de jardinier) |
| 2026-11-22 | La consigne du Bâtiment reste identique au caractère près, gardée par un test | la mesure du banc reste valable sans le rejouer |
| 2026-11-22 | Phase 3 (maquette) sautée, confirmée par Xavier au gate (R5) | aucun écran ni composant nouveau |
| 2026-11-22 | Le réglage « Votre activité » va dans « Mon entreprise », sans rubrique nouvelle | zéro route, zéro icône, et l'assistant du premier lancement le montre |
| 2026-11-22 | La correspondance métier vers activité vit dans `Activite::duMetier`, pas dans `CatalogueFictif` | `Compte` connaît déjà les clés des métiers (`Marque::PAR_METIER`) ; l'inverse créerait une dépendance croisée |
| 2026-11-22 | `Extracteur::extraire` reçoit l'activité avec « Bâtiment » par défaut | les tests existants de l'extraction restent tels quels : ils prouvent que le Bâtiment ne bouge pas |
| 2026-11-22 | « Autre prestation » sans texte libre | le texte libre toucherait quatre endroits (contrôle, démo, écran, relevé des saisies) pour un besoin non exprimé |
| 2026-11-22 | Titre par défaut d'une offre en Prestations : « Offre du … » | « Visite du … » ou « Dictée du … » n'a pas sa place sur le document du client |
| 2026-11-22 | En Prestations, les mots de la pièce restent ceux du Bâtiment | seules les offres faites avant le changement d'activité ont des pièces |
| 2026-11-22 | Aides secondaires (facture, TVA, entrée d'une démo) passées aussi par les mots, hors du tableau de la SPEC | trois substitutions d'un mot, pour ne pas laisser « travaux » à un prestataire |
| 2026-11-22 | `Activite::dire` transpose les phrases fabriquées (paiement, phrases par défaut de Lettre, libellés d'acompte) | le critère « aucun modèle ne dit travaux » était intenable sans elle : la phrase de paiement par défaut le dit |
| 2026-11-22 | Trois tests existants modifiés : `SaisieTest` (liste des unités), `CatalogueFictifTest` (treize métiers), `MarqueTest` (modèle par métier) | la SPEC change explicitement ces trois listes ; aucun autre test existant n'est touché, hors les doublures d'`Extracteur` |
| 2026-11-22 | Modèle proposé : Technique pour l'informatique, Lettre pour le conseil, Moderne pour le marketing, Sobre pour « Autre prestation » | choix de Claude, à corriger par Xavier à la recette |
| 2026-11-22 | L'aide de « Ma marque », l'aperçu d'un compte sans offre et `visite.webm` gardent leurs mots du bâtiment | hors périmètre, à traiter ailleurs ; listés dans la recette |
| 2026-11-22 | Pas de commit ni de déploiement sans accord | aucune demande explicite de Xavier pour ce chantier |

## Artefacts

| Chemin | Phase | État |
|---|---|---|
| `docs/plans/2026-11-22_familles-activite/SPEC.md` | 1 | validée au gate intention le 2026-11-22 |
| `docs/plans/2026-11-22_familles-activite/PLAN.md` | 4 | six lots cochés le 2026-11-22 |
| code et tests (46 fichiers modifiés, 8 neufs, voir Preuves) | 5 | livré en code, non commité, non déployé |
| `docs/plans/2026-11-19_metiers-demo/CATALOGUES.md` | 5 | section « Prestations » et sources S16 à S18 ajoutées |
| `docs/plans/2026-11-22_familles-activite/REVUE.md` | 7 | écrite, trouvailles retenues corrigées |
| `docs/plans/2026-11-22_familles-activite/RECETTE.md` | 8 | écrite, en attente de Xavier |
| bases locales `offregen_familles_test` et `offregen_familles_vue` | 5, 6 | à supprimer à la clôture |

## Preuves

Commande exécutée et sortie collée. Pas de résumé.

### Phase 1

Relevé sur la tête, dans `~/offreGen-familles` (extrait) :

```
$ git log --oneline -1
8385e78 Recette sur le test : bandeau de renvoi en direct, message de la finale émise
$ grep -n -E "Tu prépares|titre : court|Tu ranges les lignes|rangerParPiece\(" src/Offre/ExtracteurApi.php
31:        Tu prépares le brouillon d'une offre pour un artisan suisse, à partir de la dictée de sa visite chez le client.
42:        - titre : court, la nature des travaux et le lieu, sans prix.
53:        Tu ranges les lignes d'une offre d'artisan dans les pièces où se font les travaux, d'après la dictée de sa visite.
109:        return $resultat->lignes === [] ? $resultat : $this->rangerParPiece($transcription, $resultat);
$ grep -n "UNITES" src/Offre/Saisie.php
14:    public const UNITES = ['pce', 'm²', 'm', 'ml', 'm³', 'kg', 'h', 'jour', 'forfait'];
```

### Phase 5

Empreintes des consignes du Bâtiment, avant tout changement :

```
$ php -r 'require "vendor/autoload.php"; foreach (["CONSIGNE","CONSIGNE_PIECES"] as $c) { $v = (new ReflectionClassConstant(OffreGen\Offre\ExtracteurApi::class, $c))->getValue(); echo $c, " ", strlen($v), " octets ", hash("sha256", $v), "\n"; }'
CONSIGNE 1688 octets a3cdb155739cd552d9ec4f6469f5fd37c6036dd99f26ce4467b28c6476308b9a
CONSIGNE_PIECES 498 octets f83d5c17b9fd19b7cde96dc168ef42fe1f1191b86d63b0fd720d928ddf53b456
```

### Phase 6

Dernière passe complète, le 2026-11-22, après les corrections de la revue, dans `~/offreGen-familles` (base `offregen_familles_test`) :

```
$ git diff --stat | tail -1
 46 files changed, 550 insertions(+), 113 deletions(-)
$ git status --short | grep '^??'
?? docs/plans/2026-11-22_familles-activite/
?? migrations/Version20261122100001.php
?? src/Compte/Activite.php
?? tests/Compte/ActiviteTest.php
?? tests/Http/ActiviteDemoTest.php
?? tests/Http/ActiviteEcransTest.php
?? tests/Http/ActiviteReglageTest.php
?? tests/Offre/ExtracteurActiviteTest.php
?? tests/Offre/GabaritsActiviteTest.php

$ fichiers touches : tirets longs, BOM, retours chariot (attendu : aucune ligne)

$ migration : annuler, constater, rejouer, constater
 [OK] Successfully migrated to version:
colonne activite apres annulation : 0
 [OK] Successfully migrated to version:
colonne activite apres migration : varchar(20), defaut batiment, null NO
 [OK] Up-to-date! No migrations to execute.

$ vendor/bin/phpstan analyse --memory-limit=512M --no-progress
 [OK] No errors

$ vendor/bin/phpunit
............................................................. 1159 / 1159 (100%)
Time: 02:25.811, Memory: 110.50 MB
OK (1159 tests, 10086 assertions)
```

Référence avant le chantier, même copie, même base (les deux échecs viennent de la migration du chantier posée et pas encore jouée) :

```
$ vendor/bin/phpunit
1) OffreGen\Tests\Http\SanteTest::testToutVaBien
Failed asserting that 503 is identical to 200.
2) OffreGen\Tests\Http\SanteTest::testRoutePubliqueEnGetSeulement
Failed asserting that 503 is identical to 200.
Tests: 1075, Assertions: 9234, Failures: 2.
$ vendor/bin/phpstan analyse --memory-limit=512M --no-progress
 [OK] No errors
```

Défaut de la revue, vu rouge avant sa correction :

```
$ vendor/bin/phpunit tests/Http/ActiviteReglageTest.php
1) OffreGen\Tests\Http\ActiviteReglageTest::testChoisirLesPrestationsChangeLesMotsEtRienDAutre
Failed asserting that two arrays are identical.
2) OffreGen\Tests\Http\ActiviteReglageTest::testLesPhrasesDeLettreSuiventLeChangementDActivite
Tests: 7, Assertions: 23, Failures: 2.
```

Recette visuelle locale (Chromium, serveur `php -S` de la copie sur la base `offregen_familles_vue`, comptes inventés), sondes du skill `recette-visuelle`, seuil de contraste 7:1 :

```
$ node captures.js
01-prestations-assistant-390 | textes 34, contraste sous 7:1 : 0, sur image : 0 | elements 80, debordements 0, textes coupes 0, chevauchements 0, rangees desalignees 0, scroll horizontal false
02-prestations-accueil-390 | textes 25, contraste sous 7:1 : 0, sur image : 0 | elements 73, debordements 0, textes coupes 0, chevauchements 0, rangees desalignees 0, scroll horizontal false
03-prestations-entete-390 | textes 20, contraste sous 7:1 : 0, sur image : 0 | elements 42, debordements 0, textes coupes 0, chevauchements 0, rangees desalignees 0, scroll horizontal false
04-prestations-offre-390 | textes 68, contraste sous 7:1 : 0, sur image : 0 | elements 149, debordements 4 (button.del dans son formulaire, 374 px a droite)
05-prestations-reglage-390 | textes 35, contraste sous 7:1 : 0, sur image : 0 | elements 95, debordements 0, textes coupes 0, chevauchements 0, rangees desalignees 0, scroll horizontal false
06-prestations-conditions-390 | textes 26, contraste sous 7:1 : 0, sur image : 0 | elements 65, debordements 0, textes coupes 0, chevauchements 0, rangees desalignees 0, scroll horizontal false
07-prestations-document-1280 | textes 77, contraste sous 7:1 : 0, sur image : 0 | elements 160, debordements 0, textes coupes 0, chevauchements 0, rangees desalignees 0, scroll horizontal false
08-prestations-reglage-1280 | textes 40, contraste sous 7:1 : 0, sur image : 0 | elements 105, debordements 0, textes coupes 0, chevauchements 0, rangees desalignees 0, scroll horizontal false
09-exterieur-entete-390 | textes 24, contraste sous 7:1 : 0, sur image : 0 | elements 47, debordements 0, textes coupes 0, chevauchements 0, rangees desalignees 0, scroll horizontal false
10-batiment-entete-390 | textes 24, contraste sous 7:1 : 0, sur image : 0 | elements 47, debordements 0, textes coupes 0, chevauchements 0, rangees desalignees 0, scroll horizontal false
11-demo-nouvelle-390 | textes 32, contraste sous 7:1 : 0, sur image : 0 | elements 75, debordements 0, textes coupes 0, chevauchements 0, rangees desalignees 0, scroll horizontal false
12-demo-nouvelle-1280 | textes 32, contraste sous 7:1 : 0, sur image : 0 | elements 75, debordements 0, textes coupes 0, chevauchements 0, rangees desalignees 0, scroll horizontal false
```

La ligne 04 est résumée à la main : sa sortie brute donne le détail des quatre boutons. Non vérifié : le site de test, un iPhone et un Android réels, la justesse de l'extraction hors du bâtiment.
