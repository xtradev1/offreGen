---
chantier: Chronomètre des dictées (sous-chantier 31 de voix-offre)
slug: 2026-11-07_voix-offre/31-chronometre-dictees
phase: 5
statut: en_cours
gate_attendu: aucun
profil: .claude/atoz-profile.md
---

## Prochaine action

Lot 1 du PLAN (migration), puis lots 2 à 7 dans l'ordre. Sous-tâche Origin en cours : 609.

À savoir à la reprise :
- Copie à soi : `~/offreGen-chrono` (worktree détaché sur `origin/chantier-voix-offre`), base de test `offregen_chrono_test` (droits recopiés de `offregen_test`, `DB_NOM` posé dans son `.env.test.local`).
- Le sous-chantier 30 (`~/offreGen-sc30`, prochain lot 3) et celui de l'abri (27, commit `fb63939` « verrou ») touchent `Traiteur`, `FileTraitement`, `TranscripteurApi` : relire leur tête avant chaque lot, rebase avant chaque push.
- Sur le test, en SSH, `php` est en 8.4 : lancer `php-8.5`, comme le cron.
- À la clôture : supprimer la base `offregen_chrono_test` (root : `DROP DATABASE`, droits de `offregen_app` et `offregen_structure` à retirer) et la copie `~/offreGen-chrono`. `~/offreGen-base-coupee` porte encore le correctif, désormais en double : à supprimer avec l'accord de Xavier (copie d'une autre session).

## En attente de toi

- Rien pour l'instant. Le déploiement du chronomètre sur le test sera redemandé au lot 8.

## Suivi Origin

- Projet 44 (offreGen), tâche 607 « Chronomètre des dictées (sous-chantier 31) », 17 h, créée le 2026-11-24 (D8).
- Sous-tâches : 608 SPEC, maquette, D1 à D8 ; 609 table et chronomètre dans le traitement ; 610 envoi mesuré par le téléphone ; 611 page, statistiques, rapport, CSV ; 612 verrou de 2 min 30, pouls, message de panne ; 613 rejeu ; 614 revue, déploiement, recette.
- Conversation rattachée à 609 le 2026-11-24.

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

## Journal

- 2026-11-24 : point Origin du jour fait (agent Haiku, recoupé par mon propre relevé du projet 44 : 546 et 568 dus ce jour). Aucune tâche « chronomètre » : conversation rattachée au projet 44 seul.
- 2026-11-24 : copie `~/offreGen-chrono` créée sur `ff1ee21`, base `offregen_chrono_test`. Correctif `wait_timeout` repris de `~/offreGen-base-coupee`, test vu rouge sans le correctif, vert avec, suite complète verte, commité par chemins et poussé (`afd225b`). Non déployé.
- 2026-11-24 : constat relu sur le test (base, `app.log`), section 1 de la SPEC. Les lignes de jetons du journal ne portent aucun identifiant de dictée.
- 2026-11-24 : contrat de `on_progress` vérifié dans le code de Symfony (`vendor/symfony/http-client-contracts/HttpClientInterface.php:63` : « at least 1/s » ; client curl, `CURLOPT_PROGRESSFUNCTION`). Base du remède A, à prouver par un test sur un serveur lent.
- 2026-11-24 : SPEC et maquette écrites. Palette de la frise validée (`validate_palette.js`, clair et sombre ; contraste clair sous 3:1 pour deux teintes, compensé par le nom et la durée écrits sur chaque ligne et le tableau). `check.js` vert (1440 et 390 px), captures de chaque section regardées en clair, sombre et téléphone. Gate visuel ouvert.
- 2026-11-24 : gate visuel franchi, réponses D1 à D8 collées ci-dessus ; « refaire » et le déploiement reposés par l'outil de questions. SPEC corrigée (sections 4, 4 bis, 5, 6). Tâche Origin 607 créée, conversation rattachée à 609.
- 2026-11-24 : correctif de connexion déployé sur le test (`fb63939`, sonde verte), valeur de session relue sur le serveur.
- 2026-11-24 : phase 4, PLAN.md écrit (contrat technique, huit lots). Décisions fragiles 1 à 3 prouvées avant le code (preuves ci-dessous).

## Preuves

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
