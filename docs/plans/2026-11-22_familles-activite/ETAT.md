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

Arrêt demandé par Xavier le 2026-11-24 : ne plus déployer sur le test, ne pas toucher au roadmap. À la reprise : lire le retour de Xavier sur `RECETTE.md` (scénarios 2, 3 et 5 changés par le lot 7). Défauts : phase 5, dans une copie remise sur la tête de `chantier-voix-offre`. Recette validée : clôture (`statut: termine`, `roadmap-sync`, suppression des bases `offregen_familles_test` et `offregen_familles_vue`).

## En attente de toi

- Dérouler `RECETTE.md` sur le site de test, dont cinq dictées de prestations (avec et sans parties annoncées) et cinq de jardinier. Le rangement par catégorie et la consigne hors bâtiment n'ont jamais été essayés sur une vraie dictée : seuls des appels simulés les testent.
- Corriger les prix des catalogues de prestations s'ils sont faux (sources S16 à S18, résumés de recherche, pages non ouvertes).
- Décider du débordement des boutons de suppression (`button.del`) sur l'écran de l'offre à 390 px : antérieur à ce chantier, hors périmètre, non corrigé.
- Fermer les tâches Origin 538 et 539.

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
- 2026-11-22 : accord de Xavier : « oui commite, pousse et déploie sur le test ». Commit par chemins (59 fichiers), rebasé sur deux commits de documentation d'une autre session (aucun fichier en commun, aucune migration nouvelle) : `cedc573`, poussé sur `chantier-voix-offre`.
- 2026-11-22 : déploiement sur le test par `outils/deployer-test.sh` depuis la copie du chantier : 1159 tests verts, migration `Version20261122100001` jouée, sonde verte, `/sante` annonce `cedc573`. Version précédente gardée : `8385e78`. La tête distante n'a pas bougé pendant le déploiement.
- 2026-11-22 : contrôle par l'interface sur le test, une connexion en lecture seule à `demo-paysagiste` : « Votre activité » coche « Extérieur », la consigne de dictée est « Parlez, zone par zone. », capture regardée. La migration a bien passé ce compte en Extérieur.
- 2026-11-23 : maquette interactive `MAQUETTE-activites.html` (trois modes, surlignage des différences) ; réponses de Xavier reportées dans `SPEC.md`. Retour en phase 5 sans repasser les gates : lot 7 ajouté au plan.
- 2026-11-23 : copie remise sur la tête de la branche (`80917d8`, dix commits d'autres sessions : options et variantes, administration). Aucun conflit ; deux migrations venues d'ailleurs jouées sur la base du chantier.
- 2026-11-23 : lot 7 fait (accès replié, catégorie et second appel des Prestations, plus de « Hors catalogue »). Deux assertions d'`EcransTest` attendaient « Hors catalogue » : remplacées, la spec change ce comportement.
- 2026-11-23 : recette visuelle locale du lot 7 (base `offregen_familles_vue`, compte inventé) : en-tête replié puis ouvert, offre avec une ligne libre, document. La sonde de disposition relève trois chevauchements sur l'en-tête replié : le champ « Accès » caché dans le `<details>` fermé garde une boîte (117 px de haut, au rang de « Début »), sans être peint ; replié ouvert, zéro chevauchement, et la capture ne montre rien. Les cinq débordements de l'écran de l'offre sont les boutons de suppression déjà relevés avant ce chantier.

- 2026-11-23 : les deux lignes du jour sur l'accord et le déploiement du lot 7 ont été ajoutées par erreur en fin de fichier, après les preuves ; elles y restent (journal en ajout seul).
- 2026-11-23 : non vérifié après le déploiement du lot 7 : les écrans du site de test. Seul `/sante` prouve le commit en service ; l'accès replié, les catégories et l'absence de « Hors catalogue » sont vérifiés en local seulement.
- 2026-11-23 : la ligne « en cours » du roadmap de `~/offreGen` cite encore `cedc573` ; à mettre à jour à la clôture par `roadmap-sync`. Conversation rattachée à la sous-tâche Origin 543.
- 2026-11-24 : constat, à la question de Xavier (« on dirait que tu travailles sur le même qu'un autre ») : toutes les sessions poussent sur la même branche `chantier-voix-offre`, chacune depuis sa copie (`git worktree list` : 18 copies). Aucune autre session ne travaille dans `~/offreGen-familles`. `f0771cd` est dans la branche ; 15 commits d'autres sessions l'ont suivi (onboarding, options, administration, commerciaux).
- 2026-11-24 : arrêt demandé par Xavier : état écrit ici, commit par chemins, push ; plus de déploiement sur le test, roadmap non touché.

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
| 2026-11-23 | `Activite::rangeParGroupe` retirée | le second appel tourne dans les trois modes : la méthode rendait toujours vrai |
| 2026-11-23 | Le second appel des Prestations dit « partie » au modèle, l'écran dit « catégorie » | « catégorie » désigne déjà la catégorie du catalogue dans la consigne principale : le modèle ne doit pas confondre les deux |
| 2026-11-23 | Le groupe sans titre vient en dernier | au milieu, ses lignes se liraient comme la suite du groupe précédent |
| 2026-11-23 | Récapitulatif nomme ce groupe « Autres postes » | sa table des postes cite chaque groupe par sa lettre ; une lettre sans nom ne se lit pas. Validé par Xavier le 2026-11-23 |
| 2026-11-23 | L'écran de l'offre garde la rangée des en-têtes (Qté, Prix, Total) au-dessus du groupe sans titre | elle le sépare du groupe précédent |

## Artefacts

| Chemin | Phase | État |
|---|---|---|
| `docs/plans/2026-11-22_familles-activite/SPEC.md` | 1 | validée au gate intention le 2026-11-22 |
| `docs/plans/2026-11-22_familles-activite/PLAN.md` | 4 | six lots cochés le 2026-11-22, lot 7 coché le 2026-11-23 |
| code et tests (46 fichiers modifiés, 8 neufs, voir Preuves ; lot 7 : 17 fichiers) | 5 | commits `cedc573` et `f0771cd`, lot 7 en service sur le test le 2026-11-23, recette de Xavier attendue |
| `docs/plans/2026-11-19_metiers-demo/CATALOGUES.md` | 5 | section « Prestations » et sources S16 à S18 ajoutées |
| `docs/plans/2026-11-22_familles-activite/MAQUETTE-activites.html` | 3 | réponses de Xavier reportées dans `SPEC.md` le 2026-11-23 |
| `docs/plans/2026-11-22_familles-activite/REVUE.md` | 7 | écrite, trouvailles retenues corrigées |
| `docs/plans/2026-11-22_familles-activite/RECETTE.md` | 8 | écrite, scénarios 2, 3 et 5 mis à jour au lot 7, en attente de Xavier |
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

### Phase 8

Commit, push et déploiement sur le test, le 2026-11-22, après l'accord de Xavier :

```
$ git push origin HEAD:chantier-voix-offre
   aa5ea49..cedc573  HEAD -> chantier-voix-offre
$ bash outils/deployer-test.sh
commit cedc573cf0691285e1ab95831cce019f1886b10e (Activités du compte : bâtiment, extérieur, prestations)
OK (1159 tests, 10086 assertions)
analyse statique : sans erreur
[notice] Migrating up to OffreGen\Migrations\Version20261122100001
[notice] finished in 24.8ms, used 8M memory, 1 migrations executed, 2 sql queries
en service : releases/cedc573cf0691285e1ab95831cce019f1886b10e ; précédente : releases/8385e781710bd462e1b17b6b24223d8b715c1d7a
/sante annonce cedc573cf0691285e1ab95831cce019f1886b10e après 0 s
LU     /sante : {"commit":"cedc573cf0691285e1ab95831cce019f1886b10e","base":"ok","migrations":"ok"}
OK     migrations à jour
SONDE VERTE
DÉPLOYÉ : cedc573cf0691285e1ab95831cce019f1886b10e sur https://easy-offres.xtradev.ch
$ git rev-parse --short origin/chantier-voix-offre
cedc573
$ node test_site.js
sante : 200 {"commit":"cedc573cf0691285e1ab95831cce019f1886b10e","base":"ok","migrations":"ok"}
connecté : demo-paysagiste@exemple.ch -> /bienvenue/entreprise
activité cochée : exterieur | consigne de dictée : Parlez, zone par zone.
```

### Lot 7 (2026-11-23)

Sur la tête `80917d8` rebasée, base `offregen_familles_test` :

```
$ vendor/bin/phpunit
....................................................          1272 / 1272 (100%)
Time: 02:54.793, Memory: 116.50 MB
OK (1272 tests, 10745 assertions)
$ vendor/bin/phpstan analyse --memory-limit=512M --no-progress --error-format=raw
tests/Offre/GabaritsActiviteTest.php:64:Parameter #4 $lignes of static method OffreGen\Tests\Outils\OffreDEssai::html() expects list<array{...}>, non-empty-list<array{categorie: null}|array{...}> given.
tests/Offre/GabaritsActiviteTest.php:68:Offset 'designation' might not exist on array{categorie: null}|array{...}.
```

Les deux remarques portent sur le test neuf (tableau modifié par indice) : réécrit par `array_map`, puis :

```
$ vendor/bin/phpstan analyse --memory-limit=512M --no-progress --error-format=raw
Note: Using configuration file /home/xavie/offreGen-familles/phpstan.neon.
$ vendor/bin/phpunit tests/Offre/GabaritsActiviteTest.php
OK (62 tests, 177 assertions)
```

Recette visuelle locale du lot 7 :

```
$ node lot7.js
entete fermé | contraste : textes 24 sous 7:1 0 [] | disposition : débordements 0 chevauchements 3 coupés 0 scroll false []
entete ouvert | contraste : textes 24 sous 7:1 0 [] | disposition : débordements 0 chevauchements 0 coupés 0 scroll false []
offre | contraste : textes 78 sous 7:1 0 [] | disposition : débordements 5 (button.del, déjà relevés avant ce chantier)
Hors catalogue sur le document : false
$ node lot7c.js   # boîtes des champs, en-tête replié : [id, haut, hauteur, largeur, dans un details fermé]
[["titre",76,74,358,false],["chantier_adresse",209,74,358,false],["chantier_contact",300,98,358,false],["chantier_acces",464,117,358,true],["debut_travaux",472,74,174,false],["duree_travaux",472,74,174,false],["non_compris",562,160,358,false],["visite_le",739,98,358,false]]
```

- 2026-11-23 : accord de Xavier : « oui commite, pousse et déploie, ok pour autres postes ».
- 2026-11-23 : lot 7 commité (`f0771cd`, 17 fichiers), rebasé sur trois commits d'autres sessions (onboarding, options), suite complète verte avant le push (1312 tests), poussé sur `chantier-voix-offre`, déployé sur le test : 1312 tests verts dans le script, analyse statique sans erreur, sonde verte, `/sante` annonce `f0771cd`. Version précédente gardée : `c8afd28`. Après le déploiement, une autre session a poussé `7b4b4ed` (escompte) : son prochain déploiement emportera ce lot.
