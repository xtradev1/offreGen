---
chantier: Chronomètre des dictées (sous-chantier 31 de voix-offre)
slug: 2026-11-07_voix-offre/31-chronometre-dictees
phase: 8
statut: gate_ouvert
gate_attendu: prod
profil: .claude/atoz-profile.md
---

## Prochaine action

Lot 8 fait : déployé sur le test (`0ec9fff`), recette par l'interface jouée par Claude, trois défauts corrigés, `RECETTE.md` écrit. Reste : la recette de Xavier et S1. Ensuite clôture (base, copie, `/roadmap-sync`). Sous-tâche Origin : 614.

À savoir à la reprise :
- Copie à soi : `~/offreGen-chrono` (worktree détaché sur `origin/chantier-voix-offre`), base de test `offregen_chrono_test` (droits recopiés de `offregen_test`, `DB_NOM` posé dans son `.env.test.local`).
- Le sous-chantier 30 (`~/offreGen-sc30`, prochain lot 3) et celui de l'abri (27, commit `fb63939` « verrou ») touchent `Traiteur`, `FileTraitement`, `TranscripteurApi` : relire leur tête avant chaque lot, rebase avant chaque push.
- Sur le test, en SSH, `php` est en 8.4 : lancer `php-8.5`, comme le cron.
- À la clôture : supprimer la base `offregen_chrono_test` (root : `DROP DATABASE`, droits de `offregen_app` et `offregen_structure` à retirer) et la copie `~/offreGen-chrono`. `~/offreGen-base-coupee` porte encore le correctif, désormais en double : à supprimer avec l'accord de Xavier (copie d'une autre session).

## En attente de toi

- Jouer `RECETTE.md`, section « À toi » (iPhone, environ 15 minutes).
- Trancher S1 de `REVUE.md` : le texte des dictées dans le rapport de l'administration (garder, journaliser chaque ouverture, ou cacher derrière un clic journalisé).
- Dire si je supprime `~/offreGen-base-coupee` (copie d'une autre session, correctif de connexion en double).
- Remplir `ALERTES_A` sur le test (vide le 2026-11-25 : aucune alerte ne part), et sur la production avant les premiers artisans. Sur le test, `COURRIELS_REDIRIGER_VERS` envoie de toute façon tout à l'adresse de redirection.

## Suivi Origin

- Projet 44 (offreGen), tâche 607 « Chronomètre des dictées (sous-chantier 31) », 17 h, créée le 2026-11-24 (D8).
- Sous-tâches : 608 SPEC, maquette, D1 à D8 ; 609 table et chronomètre dans le traitement ; 610 envoi mesuré par le téléphone ; 611 page, statistiques, rapport, CSV ; 612 verrou de 2 min 30, pouls, message de panne ; 613 rejeu ; 614 revue, déploiement, recette.
- Conversation rattachée à 609, puis à 614 le 2026-11-24.

## Réponses de Xavier à la maquette (2026-11-24, texte collé)

```
D1. Le rapport : frise par tentative, tableau en dessous, étapes de la section « Ce qui est mesuré » : Valider | ce que j'ai surotut besoin c'est des statistiques mais cela me va je veux pouvoir voir les extreme et comprendre pourquoi
D2. La liste : 100 dernières, période réglable, marque à deux fois la médiane, filtre « étape marquée » et « reprise ou erreur » : A | afficher les 100 dernière mais je veux pouvoir avoir accès a toute du moins durant la période de test
et il faut aussi pouvoir récuperer ce qui a été fais afin de pouvoir refaire pour voir ou ce situe le problème
D3. Le format du CSV : A
D4. Le remède au verrou : A
D5. Le message pendant la panne (écran d'attente et fenêtre du bas) : Valider | mais a 2 minutes 30 pour avoir un peu de marge objectif que cela fonctionne et peu de message d'erreur
D6. Le téléphone envoie la durée de son envoi par un second petit message, après l'envoi du fichier : Valider
D7. Où mettre la page : A
D8. Créer la tâche Origin : Créer
```

Questions reposées le même jour (outil de questions) : « refaire » = « Rejouer » (bouton dans le rapport, offre intacte, chronométré à part) ; déploiement du correctif de connexion : « Oui, données inventées ».

## Décisions

| Date | Décision | Raison |
|---|---|---|
| 2026-11-24 | Sous-chantier 31 de voix-offre, pas un chantier à part. | Il instrumente le traitement de voix-offre et partage ses fichiers avec le 30. |
| 2026-11-24 | Phase 0 sautée : le profil existe (`.claude/atoz-profile.md`), `CODING_RULES.md` existe et a été lu. | Rien à redemander. |
| 2026-11-24 | Phases 1 et 3 présentées ensemble, un seul gate (visuel). | Xavier : « Maquette d'abord » ; les décisions ouvertes tiennent dans la maquette (même façon que les sous-chantiers 26 et 30). |
| 2026-11-24 | Phase 2 sautée. | Un seul plan suffit : une table, un service, une page. |
| 2026-11-24 | `enregistrement.duree_ms` (sous-chantier 30, lot 2) reçoit la durée de l'audio ; pas de colonne de plus. | Consigne de Xavier : ne pas la doubler. |
| 2026-11-24 | Une ligne par étape, écrite au début puis complétée à la fin. | Un traitement mort laisse une étape ouverte : c'est ce qui manquait pour l'offre 87. |
| 2026-11-24 | Durée de l'envoi mesurée par le téléphone, heures déduites au serveur. | Le serveur ne voit pas l'envoi ; l'horloge du téléphone n'est pas fiable. |
| 2026-11-24 | D5 « à 2 minutes 30 » lu comme un bail de 150 s ; le message de panne apparaît à son expiration. | Les deux lectures donnent un message à 2 min 30 ; un bail plus long laisse plus de marge au pouls (20 s). |
| 2026-11-24 | D1 : statistiques de la période en tête de la liste (médiane, 90e centile, maximum, durée pour 100 jetons reçus). | « Surtout besoin de statistiques […] voir les extrêmes et comprendre pourquoi ». |
| 2026-11-24 | D2 : pages de 100, toutes les dictées accessibles ; CSV de toute la période. | « Accès à toutes, du moins durant la période de test ». |
| 2026-11-24 | Rejeu : table `dictee_rejeu`, étapes sous `rejeu_id`, après la réponse, sans cron. Un rejeu mort reste visible et se relance après 15 minutes. | Le moins de pièces mobiles ; un rejeu n'a ni artisan ni offre qui l'attendent. |
| 2026-11-24 | Correctif de connexion déployé sans attendre le chronomètre. | Accord de Xavier (« Oui, données inventées ») ; il supprime la cause de la panne du 24.11. |
| 2026-11-24 | Premier octet mesuré par `on_progress` (arrivée des en-têtes), plus par `starttransfer_time`. | Le curl 7.74 du test y met, pour un POST, le début de l'envoi du corps (0 ms) ; la preuve locale de la phase 4 (curl 8.5, 6,005 s) ne le montrait pas. |
| 2026-11-25 | Lot 9 : texte redemandé chaque seconde pendant 30 s, puis toutes les 3 s (au lieu de 3 s d'emblée). Pas 0,5 s. | Le retard dû à la redemande ne s'additionne pas : il vaut en moyenne la moitié de l'intervalle, une seule fois par attente. 1 s au lieu de 3 s : 1 s gagnée en moyenne ; 0,5 s au lieu de 1 s : 0,25 s de plus, pour deux fois plus de requêtes. Infomaniak annonce 60 requêtes par minute et par clé pour son API (non confirmé pour la transcription, aucun en-tête de limite relevé) : la clé est commune à tout le site. |
| 2026-11-25 | Lot 9 : pièces et options lancées ensemble, réponses appliquées dans l'ordre (pièces d'abord). | Demande de Xavier (« on peut fusionner »). Les options ne lisent que la désignation et l'extrait ; une variante prend la pièce de sa ligne de base. |
| 2026-11-25 | Lot 10 : après 30 s, redemande toutes les 1,5 s (au lieu de 3 s). Rythme rapide gardé à 1 s, pas 0,5 s. | Xavier : « fait toutes les secondes […] À la limite, tu peux mettre une seconde et demie ». Rafale du 25.11 : 130 demandes en 58 s sans refus. |
| 2026-11-25 | Lot 10 : l'offre prête s'ouvre 300 ms après la dernière étape cochée (au lieu de 900 ms). | Xavier : « tu peux passer le gain facile à 300 millisecondes ». |
| 2026-11-25 | Lot 11, point de vérification : un refus d'Infomaniak (HTTP 429 ou 503), sur n'importe quel appel, devient une erreur « Infomaniak refuse des appels » ; cron signale chaque minute les dictées qui attendent leur offre depuis plus de 5 minutes (« dictées bloquées »). Les deux passent par le courriel d'alerte existant (Alertes, une fois par heure la même panne). | Xavier : « si demain il y a 10 ou 20 utilisateurs […] et que ça commence à bloquer, il faut vraiment que je sache vite ». Avant ce lot, un refus pendant la redemande du texte et un refus des pièces ou des options n'étaient que des avertissements : la dictée attendait ou l'offre sortait dégradée sans courriel. 5 minutes : une dictée normale est prête en moins d'une minute. Pas d'encart dans la page des dictées : ses durées (médiane, 90e centile, maximum) disent déjà la lenteur, le courriel dit l'urgence. |
| 2026-11-24 | Recette avec une démo à soi (`chrono-claude@exemple.ch`), pas avec la démo d'une autre recette. | `recette-e1` et `fluidite-test` ouvrent l'assistant de bienvenue et servent à d'autres recettes. |

## Journal

- 2026-11-24 : point Origin du jour fait (agent Haiku, recoupé par mon propre relevé du projet 44 : 546 et 568 dus ce jour). Aucune tâche « chronomètre » : conversation rattachée au projet 44 seul.
- 2026-11-24 : copie `~/offreGen-chrono` créée sur `ff1ee21`, base `offregen_chrono_test`. Correctif `wait_timeout` repris de `~/offreGen-base-coupee`, test vu rouge sans le correctif, vert avec, suite complète verte, commité par chemins et poussé (`afd225b`). Non déployé.
- 2026-11-24 : constat relu sur le test (base, `app.log`), section 1 de la SPEC. Les lignes de jetons du journal ne portent aucun identifiant de dictée.
- 2026-11-24 : contrat de `on_progress` vérifié dans le code de Symfony (`vendor/symfony/http-client-contracts/HttpClientInterface.php:63` : « at least 1/s » ; client curl, `CURLOPT_PROGRESSFUNCTION`). Base du remède A, à prouver par un test sur un serveur lent.
- 2026-11-24 : SPEC et maquette écrites. Palette de la frise validée (`validate_palette.js`, clair et sombre ; contraste clair sous 3:1 pour deux teintes, compensé par le nom et la durée écrits sur chaque ligne et le tableau). `check.js` vert (1440 et 390 px), captures de chaque section regardées en clair, sombre et téléphone. Gate visuel ouvert.
- 2026-11-24 : gate visuel franchi, réponses D1 à D8 collées ci-dessus ; « refaire » et le déploiement reposés par l'outil de questions. SPEC corrigée (sections 4, 4 bis, 5, 6). Tâche Origin 607 créée, conversation rattachée à 609.
- 2026-11-24 : correctif de connexion déployé sur le test (`fb63939`, sonde verte), valeur de session relue sur le serveur.
- 2026-11-24 : phase 4, PLAN.md écrit (contrat technique, huit lots). Décisions fragiles 1 à 3 prouvées avant le code (preuves ci-dessous).

- 2026-11-24 : lot 1 (migration `Version20261124230001`, le numéro `20261124200001` ayant été pris par l'abri) : `SchemaTest::testLeChronometreASesTables` rouge puis vert, retour arrière et remigration propres.
- 2026-11-24 : lots 2 à 5 : chronomètre, pouls du verrou (bail de 150 s), message de panne, réception et envoi mesuré. Deux mutations attrapées par les tests (verrou de 900 s ; `on_progress` retiré). Défaut trouvé par la suite complète et corrigé : construire l'extracteur ouvrait la connexion à la base (`ConteneurTest`), chronomètre rendu paresseux. Commit `6c33a7e`, poussé.
- 2026-11-24 : lots 6 et 7 : page des dictées, statistiques, CSV, rapport, rejeu. Recette visuelle locale (base de test semée, `php -S` sur le port 8099, parcours cliqué par Playwright depuis « Plus ») : quatre défauts vus sur les captures et corrigés (tableaux coupés au PC, cartes du téléphone sans titre à cause de la classe `.bas` de l'application, statistiques sans maximum au téléphone, en-tête « 90E »). Commit `2ddda06`, rebasé.
- 2026-11-24 : phase 7, revue lourde lancée (exactitude, sécurité, concurrence du verrou), environ 8 $ estimés, sans phpunit pour les relecteurs.
- 2026-11-24 : revue rendue : aucune faille haute ; T1 (ffmpeg et ffprobe sans pouls) élevée, E1, E2 importantes, T2, S2 moyennes, le reste mineur. Toutes relues dans le code, corrigées sauf S1 (décision de Xavier) et cinq acceptées avec leur raison (`REVUE.md`). Trois mutations vérifiées (T2, E1, E2 rouges sans leur correction). Suite complète verte. Gate prod ouvert.
- 2026-11-24 : accord de Xavier (« oui déploie, pas de vraies données »). Constat : le chronomètre était déjà en service, déployé par la session Prospects (`5d60a46` contient `bee22cc`), migration jouée (49 sur 49), avant cet accord. Défaut de la règle « ne déployer que la tête » quand plusieurs chantiers partagent la branche : à signaler à Xavier.
- 2026-11-24 : recette par l'interface sur le test. Deux échecs de connexion de ma part (intitulé « Mot de passe, affiché une seule fois » puis courriel suivi de « (démo, …) » mal lus), aucun blocage. Démo `chrono-claude@exemple.ch` créée par `bin/creer-compte --demo --metier=peintre`. Trois défauts : premier octet à 0 ms, statuts en code, tableau des rejeux coupé au PC. Corrigés (`0ec9fff`), test du premier octet contre un vrai serveur local, deux mutations rouges, suite complète verte, poussé, déployé, recette rejouée : conforme.
- 2026-11-25 : question de Xavier sur la vitesse de Whisper et Qwen. Durées de transcription relevées sur le test, toutes juste au-dessus d'un multiple de 3 s (6,2 ; 9,2 ; 6,3 ; 21,6 s) : le retard vient de la redemande toutes les 3 s.
- 2026-11-25 : lot 9 codé. `AppelEnCours` (appel lancé, lu plus tard) ; `ExtracteurApi` lance pièces et options ensemble ; `Traiteur::intervalle` (1 s pendant 30 s, puis 3 s), repris par `Rejeu`. Test chronométré sur `php -S` abandonné : un de ses processus accepte parfois les deux connexions et les sert l'une après l'autre (1,20 s ou 0,60 s pour deux requêtes de 600 ms selon le lancement). Remplacé par un test d'ordre (`PiecesOptionsEnsembleTest`) ; la marche en parallèle de curl est mesurée à part (ci-dessous). Défaut trouvé par `testLeVerrouPerduPendantLesPiecesCoupeAussiLesOptions` : `cancel()` relevait le verrou perdu avant que l'étape des options soit fermée ; l'étape se ferme d'abord.
- 2026-11-25 : sortie JSON allégée (sous-chantier 20) : pas en service. Banc du 20.11 (lot `2026-11-20-fiabilite`, appels l'un après l'autre) : extraction en service 18,4 à 26,9 s en moyenne, extraction allégée en JSON 6,9 à 7,6 s, jetons reçus 1 344 à 1 396 contre 768 à 800, fiabilité égale ou meilleure. Travail non commité, dans `~/offreGen` ; gate intention ouvert depuis le 20.11 (tâche Origin 546).
- 2026-11-25 : réponses de Xavier : 1 s gardé, 1,5 s après 30 s, 300 ms avant l'ouverture de l'offre, JSON « un peu plus tard », rafale permise (« il n'y a personne qui va faire ces appels »). Rafale jouée depuis le serveur de test : aucune limite atteinte à 130 par minute. Réserve : des demandes sur un lot inexistant (404) ; une limite comptée seulement sur les lots réels resterait invisible. Lot 10 codé, suite complète verte.
- 2026-11-25 : accord de Xavier pour déployer (« Tu peux déployer ») et demande d'un point de vérification. Premier déploiement de `6f49927` refusé à l'analyse statique : `deployer-test.sh` lance phpstan sur la copie de travail, où j'écrivais déjà les fichiers du lot 11. Rien n'est parti (refus avant la construction). Constat : `ALERTES_A` vide sur le test, aucune alerte n'y part. Lot 11 codé ; deux anciens tests simulaient la panne des pièces et des options par un 503, passés à 500 (503 est désormais un refus).

## Preuves

### Lot 11, point de vérification (2026-11-25)

```
$ php vendor/bin/phpunit tests/Systeme/AlertesTest.php tests/Audio/VeilleDicteesTest.php tests/Audio/TraiteurTest.php tests/Offre/PiecesOptionsEnsembleTest.php
OK (63 tests, 216 assertions)
--- mutation : src/Audio/Traiteur.php (refus pendant la redemande gardé en avertissement)
Tests: 1, Assertions: 3, Failures: 1.
--- mutation : src/Offre/ExtracteurApi.php (refus des pièces gardé en avertissement)
Tests: 1, Assertions: 2, Failures: 1.
--- mutation : src/Donnees/FileTraitement.php (toute dictée en cours comptée en retard)
Tests: 3, Assertions: 4, Failures: 2.
$ php vendor/bin/phpunit
OK (1626 tests, 13372 assertions)
$ php vendor/bin/phpstan analyse
 [OK] No errors
# sur le test : ALERTES_A rempli : 0 ; dictées par statut : pret 39, aucune en cours (pas de fausse alerte au démarrage)
```

### Lot 10, rythme et ouverture (2026-11-25)

```
$ rafale.sh 130 0.45   # depuis current sur le test, GET /1/ai/<produit>/results/lot-inexistant-0000, clé du site
  1 à   0.0 s : code 404
 60 à  26.6 s : code 404
120 à  53.6 s : code 404
fin : 130 demandes en 58.1 s ; codes {"404":130}     # aucun 429, aucun en-tête rate, limit, retry ou remaining
$ php vendor/bin/phpunit tests/Audio
OK (101 tests, 319 assertions)                       # testLaRedemandeRalentitApresTrenteSecondes : 41e appel à 10:00:45 (10:01:00 avec 3 s)
$ php vendor/bin/phpunit
OK (1618 tests, 13353 assertions)
$ php vendor/bin/phpstan analyse
 [OK] No errors
```

### Lot 9, vitesse (2026-11-25)

```
$ php extraire.php http://127.0.0.1:8473   # serveur local, 600 ms par appel, trois processus
0.61 s offreGen, extraction : 10 jetons envoyés, 5 reçus, modèle m.
1.21 s offreGen, pièces : 10 jetons envoyés, 5 reçus, modèle m.
1.21 s offreGen, options : 10 jetons envoyés, 5 reçus, modèle m.
fin à 1.21 s, 2 lignes                      # avant : 1,81 s (test chronométré rouge sur l'ancien code)
--- mutation : pièces lues avant l'envoi des options
Tests: 1, Assertions: 1, Failures: 1.
$ php vendor/bin/phpunit
OK (1612 tests, 13158 assertions)
$ php vendor/bin/phpstan analyse
 [OK] No errors
$ GET /1/ai/<produit>/results/lot-inexistant   # serveur de test, une requête
code 404
en-têtes : date, content-type, vary, cache-control, x-query-time, x-overall-time, x-request-id, strict-transport-security
```

### Recette sur le test (2026-11-24, `0ec9fff`)

```
$ php-8.5 distant.php   # serveur de test, curl 7.74.0
GET : 200, mesuré 42 ms, starttransfer 0.040101
POST petit : 405, mesuré 31 ms, starttransfer 0.000116, pretransfer 0.000114
$ php client.php   # en-têtes vus par on_progress
local : total 1708 ms, en-têtes vus par on_progress à 1207 ms, starttransfer curl 1205 ms
test  : total 80 ms, en-têtes vus par on_progress à 79 ms, starttransfer curl 16 ms
--- extracteur sans progres : premier octet noté par on_progress  Tests: 1, Failures: 1
--- transcripteur sans progres : Failed asserting that null is not null.  Tests: 1, Failures: 1
$ php vendor/bin/phpunit   # tête rebasée sur 6708161
OK (1609 tests, 13147 assertions)
   6708161..0ec9fff  HEAD -> chantier-voix-offre
SONDE VERTE
DÉPLOYÉ : 0ec9fff9eeaa84ed160a199db9923302dcfff950 sur https://easy-offres.xtradev.ch
$ node recette.js
envoi /import 303 après 191 ms
offre ouverte /offres/103 après 17166 ms
Dictées ouverte depuis /admin/plus
CSV dictees-2026-11-24.csv 3851 octets, trois premiers octets efbbbf
  1 Transcription, envoi whisper après réponse 18:17:33,5 18:17:33,6 102 ms ok 97 ms
  1 IA, extraction Qwen/Qwen3.8-27B-FP8 après réponse 18:17:42,9 18:17:47,7 4,8 s ok 1529 348 4,8 s
  1 IA, pièces Qwen/Qwen3.8-27B-FP8 après réponse 18:17:47,7 18:17:49,2 1,5 s ok 737 110 1,5 s
rejeu : Rejeu du 24.11.2026 18:17 fini 6 - - 6,3 s 2,1 s 2,2 s - - 10,6 s après 20 s environ
original : Original prête 6 171 ms 13 ms 9,3 s 4,8 s 1,5 s - 10 ms 15,9 s
débordement horizontal, liste au PC : 0 px
débordement horizontal, rapport au PC : 0 px
réponses : POST /import 303 ; POST /enregistrements/5ff5d244-…/envoi 204 ; POST /admin/dictees/41/rejouer 303
erreurs JS : aucune
```
Captures ouvertes : rapport au PC, liste au téléphone, rapport au téléphone (première passe).

### Revue (2026-11-24)

```
--- T2 sans correction : Failed asserting that two arrays are identical.  Tests: 1, Failures: 1
--- E1 sans correction : Failed asserting that two arrays are identical.  Tests: 1, Failures: 1
--- E2 sans correction : Failed asserting that two arrays are identical.  Tests: 1, Failures: 1
$ php vendor/bin/phpunit tests/Systeme/CommandePoulsTest.php
OK (2 tests, 4 assertions)
$ php vendor/bin/phpstan analyse
 [OK] No errors
$ php vendor/bin/phpunit
OK (1574 tests, 12719 assertions)
```

### Lots 1 à 7 (2026-11-24, base `offregen_chrono_test`)

```
$ php vendor/bin/phpunit --filter testLeChronometreASesTables   # avant la migration
Failed asserting that two arrays are identical.  Tests: 1, Failures: 1
$ migrations:migrate ; phpunit tests/Donnees
OK (40 tests, 154 assertions)
--- mutation 1 : verrou de 900 s
Failed asserting that two strings are identical.   (testUnTraitementMortEstReprisApresLeBailDe150Secondes)
--- mutation 2 : sans on_progress
1) ChronometreTest::testLePoulsProlongeLeVerrouPendantUneReponseLente   Failed
2) ChronometreTest::testLePoulsQuiTrouveLeVerrouReprisCoupeLAppel   Failed
3) RevueOptionsTest::testLesTroisAppelsBattentLePoulsDuVerrou   Failed
$ php vendor/bin/phpunit   # après les lots 1 à 5, rebasé sur 8bb25b1
OK (1530 tests, 12489 assertions)
$ git push origin HEAD:chantier-voix-offre
   8bb25b1..6c33a7e  HEAD -> chantier-voix-offre
$ php vendor/bin/phpstan analyse
 [OK] No errors
$ php vendor/bin/phpunit   # après les lots 6 et 7
OK (1545 tests, 12578 assertions)
```

### Recette visuelle locale des lots 6 et 7 (captures dans le scratchpad, `vue/cap/`)

```
connecté : http://localhost:8099/
rapport : http://localhost:8099/admin/dictees/9864
largeur liste tel : 390
largeur rapport tel : 390
csv : dictees-2026-11-24.csv
erreurs : aucune
```
Captures ouvertes : liste PC, liste téléphone, rapport PC, rapport téléphone, rapport sombre, liste sombre.

### Déploiement du correctif de connexion (2026-11-24)

```
HEAD fb63939dec4367dddb140bbf292212fc086a4abf
origin fb63939dec4367dddb140bbf292212fc086a4abf
LU     /sante : {"commit":"fb63939dec4367dddb140bbf292212fc086a4abf","base":"ok","migrations":"ok"}
SONDE VERTE
DÉPLOYÉ : fb63939dec4367dddb140bbf292212fc086a4abf sur https://easy-offres.xtradev.ch
$ git merge-base --is-ancestor afd225b fb63939 && echo ...
afd225b est dans fb63939
$ ssh ... php-8.5 -r '... (new FabriqueConnexion($env))->pdo() ... SELECT @@SESSION.wait_timeout ...'
release : fb63939dec4367dddb140bbf292212fc086a4abf
wait_timeout de session : 1800 ; global : 60
```

### Décisions fragiles 1 à 3 du PLAN (scripts `scratchpad/preuves/`, serveur `php -S` qui répond après 6 s)

```
Client : Symfony\Component\HttpClient\CurlHttpClient
Réponse en 6.01 s, on_progress appelé 22 fois
Plus grand écart entre deux appels : 1.01 s
info[starttransfer_time] = 6.005028
Exception reçue : Symfony\Component\HttpClient\Exception\TransportException (« verrou repris »), précédente : VerrouPerduEssai, après 2 s
--- ffmpeg : WAV de 30 minutes vers Opus 32k (conversion de PreparationAudio)
taille du WAV : 158760078 octets
conversion : 11.01 s, processeur 101%
conversion sur 1 coeur : 8.19 s
```

Lecture : le pouls tourne pendant l'attente (écart maximal 1,01 s pour un pouls voulu toutes les 20 s) ; l'exception du pouls se reconnaît par `getPrevious()` ; la conversion la plus lourde reste très loin des 150 s du bail (machine locale ; Infomaniak non mesuré, marge de plus de dix fois).

### Correctif wait_timeout

```
$ php vendor/bin/phpunit tests/Donnees/FabriqueConnexionTest.php   # correctif retiré (git stash)
Error: Undefined constant OffreGen\Donnees\FabriqueConnexion::ATTENTE_SANS_REQUETE_SECONDES
Tests: 2, Assertions: 3, Errors: 1.
$ php vendor/bin/phpunit tests/Donnees/FabriqueConnexionTest.php   # correctif remis
OK (2 tests, 5 assertions)
$ php vendor/bin/phpunit                                          # base offregen_chrono_test
Time: 03:17.190, Memory: 130.50 MB
OK (1488 tests, 12331 assertions)
$ git push origin HEAD:chantier-voix-offre
   ff1ee21..afd225b  HEAD -> chantier-voix-offre
```

### Constat sur le test (2026-11-24, lecture seule)

```
offre  enr  recu_le (UTC)        pret_le (UTC)        tentatives  statut  taille  type_mime
70     27   2026-11-21 15:47:49  2026-11-21 16:03:06  1           pret    248314  video/webm
87     34   2026-11-24 11:05:43  2026-11-24 11:21:08  1           pret    672022  video/webm

[2026-11-24T12:07:50.940317+01:00] offregen.INFO: offreGen, extraction : 1759 jetons envoyés, 793 reçus, modèle Qwen/Qwen3.8-27B-FP8.
[2026-11-24T12:07:51.786117+01:00] offregen.INFO: offreGen, pièces : 1188 jetons envoyés, 141 reçus, modèle Qwen/Qwen3.8-27B-FP8.
[2026-11-24T12:07:52.383499+01:00] offregen.INFO: offreGen, options : 1378 jetons envoyés, 49 reçus, modèle Qwen/Qwen3.8-27B-FP8.
[2026-11-24T12:07:52.384843+01:00] offregen.ERROR: offreGen, tâche après réponse : SQLSTATE[HY000]: General error: 2006 MySQL server has gone away
[2026-11-24T12:21:06.316476+01:00] offregen.INFO: offreGen, extraction : 1767 jetons envoyés, 776 reçus, modèle Qwen/Qwen3.8-27B-FP8.
[2026-11-24T12:21:08.189071+01:00] offregen.INFO: offreGen, pièces : 1169 jetons envoyés, 141 reçus, modèle Qwen/Qwen3.8-27B-FP8.
[2026-11-24T12:21:08.697398+01:00] offregen.INFO: offreGen, options : 1359 jetons envoyés, 49 reçus, modèle Qwen/Qwen3.8-27B-FP8.
```

### Maquette

```
$ node check.js MAQUETTE-chronometre.html captures
bureau : largeur 1440/1440, images 0, cassées 0, kit oui
telephone : largeur 390/390, images 0, cassées 0, kit oui
OK.
$ node sections.js (clair, téléphone, sombre)
pc erreurs JS : aucune
tel erreurs JS : aucune
pc-sombre erreurs JS : aucune
```
