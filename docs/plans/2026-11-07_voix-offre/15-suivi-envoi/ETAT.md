---
chantier: De la voix à l'offre, 15 enregistrement court et suivi en direct
slug: 2026-11-07_voix-offre/15-suivi-envoi
phase: 8
statut: gate_ouvert
gate_attendu: prod
profil: .claude/atoz-profile.md
---

## Prochaine action

Recette de Xavier : une dictée de moins de 5 s, une vraie dictée, une dictée sans rien d'utile.

## En attente de toi

- Enregistrer moins de 5 secondes : message « Enregistrement non pris en compte : 5 secondes au minimum », bouton « Recommencer ».
- Enregistrer une vraie dictée : les étapes avancent une par une, l'offre s'ouvre seule.
- Enregistrer quelques secondes sans rien dire d'utile : une offre vide, plus d'échec.

## Journal

- 2026-11-12 : demande de Xavier : « quand un enregistrement est plus court que 5 secondes [...] enregistrement non pris en compte, minimum 5 secondes » ; « si il dit rien, ben, ça crée une offre vide » ; « ça reste en fait super longtemps à l'envoi. Et une fois que c'est fini, pouf, on arrive sur une page où on a déjà tout qui est vert [...] une petite barre de chargement ou un petit truc qui tourne [...] 4-5 étapes ».
- 2026-11-12 : phase 3 (maquette) sautée : Xavier a décrit l'écran étape par étape ; il recette dans l'application.
- 2026-11-12 : cause de la longue attente trouvée en deux temps. D'abord php -S sans fastcgi_finish_request (réponse complétée par Content-Length et Connection: close). Puis, au parcours rejoué, le vrai verrou : la session PHP reste verrouillée pendant le traitement, toute autre page du même artisan attend (aussi sous PHP-FPM). session_write_close() avant le traitement. Mesuré avant : 17,6 s sur la première étape puis tout vert ; après : envoi 0,3 s, transcription 9,1 s, création 5,1 s.
- 2026-11-12 : défaut trouvé : le WebM du navigateur n'annonce pas sa durée (ffprobe donne N/A), le minimum n'était pas vérifié au serveur. Durée lue sur le dernier paquet ; deux WebM de synthèse ajoutés aux fixtures.
- 2026-11-12 : échec de l'essai en direct de Xavier à 17 h 58 expliqué sans lire la dictée : le modèle a répondu sans appeler l'outil (4 jetons), cinq fois (température 0), puis échec. Désormais : offre vide et avertissement au journal ; une réponse sans message reste une erreur.

## Décisions

| Date | Décision | Raison |
|---|---|---|
| 2026-11-12 | 5 s au minimum : contrôlé dans le navigateur (rien n'est envoyé) et au serveur par ffprobe (DUREE_MIN_SECONDES, 5 par défaut). Durée illisible : accepté, avertissement au journal. | Règle de Xavier ; l'import ne doit pas dépendre d'un outil en panne. |
| 2026-11-12 | Quatre étapes : envoi, transcription, création de l'offre, offre prête. État demandé chaque seconde (/enregistrements/{uuid}/etat, nombres seuls). | « 4-5 étapes », « on voit que ça avance ». |
| 2026-11-12 | Serveur local : PHP_CLI_SERVER_WORKERS=4. | php -S ne sert qu'une requête à la fois ; le suivi interroge l'état pendant le traitement. |
| 2026-11-12 | Le modèle répond sans remplir l'offre : offre vide. | « s'il dit rien, ça crée une offre vide » ; réessayer redonnait la même réponse. |

## Preuves

```
$ vendor/bin/phpunit
OK (214 tests, 866 assertions)
$ vendor/bin/phpstan analyse
 [OK] No errors
Tests vus rouges avant le code : ImportTest (303 au lieu de 422), DureeAudioTest (null au lieu de 2.0),
ExtracteurApiTest (message d'exception ancien).

Parcours sur la vraie IA locale (390 px) :
  2 s : « Enregistrement non pris en compte : 5 secondes au minimum. »
  étapes avant le correctif de session : 0.1 s cttt | 17.6 s dddd
  étapes après : 0.1 s cttt | 0.3 s dctt | 9.4 s ddct | 14.5 s dddd ; offre ouverte à 15.4 s
  import d'un fichier de 1 s : « Enregistrement non pris en compte : 5 secondes au minimum. (code 422). »
  erreurs JS : aucune
```
