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
## Preuves

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
