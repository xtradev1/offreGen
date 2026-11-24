---
chantier: Chronomètre des dictées (sous-chantier 31 de voix-offre)
slug: 2026-11-07_voix-offre/31-chronometre-dictees
phase: 3
statut: gate_ouvert
gate_attendu: visuel
profil: .claude/atoz-profile.md
---

## Prochaine action

Attendre les réponses de Xavier à la maquette (D1 à D8). Ensuite : SPEC corrigée, PLAN.md (contrat technique, lots), puis code dans `~/offreGen-chrono`.

À savoir à la reprise :
- Copie à soi : `~/offreGen-chrono` (worktree détaché sur `origin/chantier-voix-offre`), base de test `offregen_chrono_test` (droits recopiés de `offregen_test`, `DB_NOM` posé dans son `.env.test.local`).
- Le sous-chantier 30 (`~/offreGen-sc30`, à l'arrêt, prochain lot 3) touche `Traiteur`, `FileTraitement` et `Statut` : rebase avant chaque push, appels au chronomètre courts dans `Traiteur`.
- Le correctif `SESSION wait_timeout = 1800` est commité et poussé (`afd225b`), **pas déployé** sur le test : déploiement à demander à Xavier.
- À la clôture : supprimer la base `offregen_chrono_test` (root : `DROP DATABASE`, droits de `offregen_app` et `offregen_structure` à retirer) et la copie `~/offreGen-chrono`. `~/offreGen-base-coupee` porte encore le correctif non commité, désormais en double : à supprimer avec l'accord de Xavier (copie d'une autre session).

## En attente de toi

- Ouvrir `MAQUETTE-chronometre.html` (chemin à coller : `\\wsl.localhost\Ubuntu\home\xavie\offreGen-chrono\docs\plans\2026-11-07_voix-offre\31-chronometre-dictees\MAQUETTE-chronometre.html`), répondre à D1 à D8 sous chaque écran, puis « Copier le texte » et le coller dans la conversation.
- Dire si je déploie `afd225b` (correctif de la connexion) sur le test maintenant, et confirmer qu'il n'y a toujours que des données inventées.

## Suivi Origin

- Projet 44 (offreGen). Aucune tâche ne couvre ce chantier : création proposée en D8 (13 h, six sous-tâches). Conversation rattachée au projet seul.

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

## Journal

- 2026-11-24 : point Origin du jour fait (agent Haiku, recoupé par mon propre relevé du projet 44 : 546 et 568 dus ce jour). Aucune tâche « chronomètre » : conversation rattachée au projet 44 seul.
- 2026-11-24 : copie `~/offreGen-chrono` créée sur `ff1ee21`, base `offregen_chrono_test`. Correctif `wait_timeout` repris de `~/offreGen-base-coupee`, test vu rouge sans le correctif, vert avec, suite complète verte, commité par chemins et poussé (`afd225b`). Non déployé.
- 2026-11-24 : constat relu sur le test (base, `app.log`), section 1 de la SPEC. Les lignes de jetons du journal ne portent aucun identifiant de dictée.
- 2026-11-24 : contrat de `on_progress` vérifié dans le code de Symfony (`vendor/symfony/http-client-contracts/HttpClientInterface.php:63` : « at least 1/s » ; client curl, `CURLOPT_PROGRESSFUNCTION`). Base du remède A, à prouver par un test sur un serveur lent.
- 2026-11-24 : SPEC et maquette écrites. Palette de la frise validée (`validate_palette.js`, clair et sombre ; contraste clair sous 3:1 pour deux teintes, compensé par le nom et la durée écrits sur chaque ligne et le tableau). `check.js` vert (1440 et 390 px), captures de chaque section regardées en clair, sombre et téléphone. Gate visuel ouvert.

## Preuves

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
