# SPEC : chronomètre des dictées (sous-chantier 31)

Demande de Xavier du 2026-11-24 : chronométrer chaque dictée en base, pour comparer une trentaine de rapports et trouver ce qui ralentit ; proposer un remède au verrou qui reste pris 15 minutes quand un traitement meurt.

## 1. Constat

Relu sur le test le 2026-11-24 (base et `partage/var/log/app.log`, heures de Zurich) :

| Offre | Reçue | Prête | Ce qu'on sait |
|---|---|---|---|
| 70 | 21.11, 16:47:49 | 17:03:06 | 15 min 17 s, aucune ligne du journal ne la nomme |
| 87 | 24.11, 12:05:43 | 12:21:08 | extraction finie à 12:07:50 (1759 jetons envoyés, 793 reçus), pièces 12:07:51, options 12:07:52, puis « MySQL server has gone away » ; reprise par le cron vers 12:21:00, extraction finie à 12:21:06 (1767 et 776 jetons), offre prête à 12:21:08 |

Ce qui manque pour comprendre :
- les lignes de jetons ne portent ni la dictée ni l'offre : on les rattache à l'offre 87 par l'heure, à la main ;
- le début de chaque appel n'est noté nulle part : « l'extraction a pris 58 s ou plus » (Xavier) se déduit, il ne se lit pas ; à 12:21, la même extraction a pris 6 s au plus ;
- ni l'envoi depuis le téléphone, ni l'attente avant traitement, ni la transcription ne sont mesurés.

Le verrou : `Traiteur::DUREE_VERROU_SECONDES` vaut 900. Le 24.11, l'écriture de l'offre 87 a perdu la connexion ; la note d'échec et le retour du verrou ont perdu la même connexion. Le verrou est resté pris jusqu'à son expiration, l'artisan a attendu 15 minutes devant l'écran d'attente, sans message, et l'extraction a été payée deux fois. Le correctif `SESSION wait_timeout = 1800` (commit `afd225b`, poussé, non déployé) supprime cette cause-là ; un processus tué (limite de PHP-FPM, redémarrage, mémoire) laisse encore le verrou 15 minutes.

## 2. Ce que la base retient

Une ligne par étape et par passage, dans une nouvelle table `dictee_etape` :

| Étape | Début | Fin | Processus |
|---|---|---|---|
| `envoi` | déduit : réception moins la durée mesurée par le téléphone | début de la réception | `telephone` |
| `reception` | arrivée de la requête au serveur | réponse rendue au téléphone | `web` |
| `attente` | réception, ou fin de l'étape en erreur d'avant | prise du verrou | celui qui prend le verrou |
| `reprise` | début de l'étape laissée ouverte par un traitement mort | prise du verrou | idem |
| `mesure` | ffprobe, durée de l'audio | | `apres_reponse` ou `cron` |
| `conversion` | ffmpeg, préparation de l'audio pour Whisper | | idem |
| `transcription_envoi` | envoi de l'audio chez Infomaniak | numéro de lot reçu | idem |
| `transcription` | numéro de lot reçu | texte reçu | celui qui reçoit le texte |
| `ia_extraction`, `ia_pieces`, `ia_options` | requête au modèle | réponse lue | idem |
| `ecriture` | rapprochement du client, puis transaction de l'offre | offre validée | idem |

Chaque ligne porte : début et fin à la milliseconde, durée en ms, tentative (`tentatives + 1` au moment du passage), processus, issue (`en_cours`, `ok`, `erreur`, `interrompue`), le type et les 190 premiers caractères de l'erreur (jamais le texte de la dictée). Pour la transcription et l'IA : le modèle ; pour l'IA, les jetons envoyés et reçus déclarés par Infomaniak, et le temps jusqu'au premier octet de la réponse (sépare l'attente chez Infomaniak du transfert).

La ligne s'écrit **au début** de l'étape, puis se complète à la fin : un traitement mort laisse une étape `en_cours`, que le passage suivant marque `interrompue`. C'est ce qui aurait montré, pour l'offre 87, où le premier passage s'est arrêté.

Sur la dictée (`enregistrement`) : la durée de l'audio va dans `duree_ms`, colonne posée par le sous-chantier 30 (lot 2) et encore jamais remplie : mesurée au serveur quand ffprobe tourne, sinon celle du téléphone. Taille (`taille`) et format (`type_mime`) existent. À ajouter : `navigateur` (en-tête User-Agent, 255 caractères) et `origine` (`micro` ou `fichier`).

Pas chronométré : la lecture du catalogue (une requête) et le courriel « offre prête », envoyé après que l'offre est prête. La page montre la part « hors étapes » (total moins la somme des étapes) pour que rien ne se cache.

Un échec d'écriture du chronomètre n'arrête jamais une dictée : il est noté au journal.

## 3. L'envoi depuis le téléphone

Le serveur ne peut pas mesurer l'envoi : la requête ne commence pour PHP qu'une fois le fichier arrivé (ou pendant, selon Apache : non vérifié). Le téléphone mesure lui-même la durée de l'envoi du fichier (`xhr.upload`), puis l'envoie par un petit message après coup (`POST /enregistrements/{uuid}/envoi`, une fois, artisan propriétaire, CSRF). Les heures sont déduites de la durée : l'horloge du téléphone n'est jamais crue. Message perdu : l'étape manque, la page le dit. Un envoi raté puis renvoyé depuis l'abri (sous-chantier 27) ne compte que l'envoi réussi.

Avec le fichier : `origine` et la durée de l'audio vue par le téléphone (fin moins début de l'enregistrement, ou durée lue du fichier importé).

## 4. Page d'administration

`/admin/dictees`, droit administrateur (`Route::ADMIN`), entrée « Dictées » dans « Plus » et lien depuis le Tableau (D7). Les dictées par pages de 100, plus récentes d'abord, période réglable ; toutes restent accessibles page après page (D2 : « accès à toutes, du moins durant la période de test »). Filtres : toutes, avec une étape marquée, avec une reprise ou une erreur.

En tête de page, les statistiques de la période (D1 : « surtout besoin de statistiques, voir les extrêmes et comprendre pourquoi ») : pour chaque étape, nombre, médiane, 90e centile et maximum, le maximum menant au rapport de sa dictée. Pour les appels à l'IA, en plus, la durée pour 100 jetons reçus (médiane et maximum) : une extraction longue avec beaucoup de jetons n'a pas la même cause qu'une extraction longue avec peu.

Par dictée : date, offre, compte (numéro), durée de l'audio, taille, format, navigateur (lu en clair : « Safari, iPhone »), origine, puis la durée de chaque étape (tentatives additionnées), le total de l'envoi à l'offre prête, la part hors étapes, le nombre de tentatives. Une durée plus de deux fois la médiane de son étape sur la liste est marquée. Un clic ouvre le rapport de la dictée : chaque étape sur une frise, tentative par tentative, avec processus, modèle et jetons.

Export CSV (`/admin/dictees.csv`, même droit) : toutes les dictées de la période et du filtre, sans limite de 100, une ligne par dictée (D3), séparateur point-virgule, UTF-8 avec BOM pour Excel, durées en ms, jetons de chaque appel.

## 4 bis. Rejouer une dictée (D2, réponse du 2026-11-24 : « Rejouer »)

Dans le rapport d'une dictée, un bouton « Rejouer » (administrateur, CSRF) relance sur l'audio gardé la conversion, la transcription et les appels à l'IA, avec le catalogue du compte au moment du rejeu. L'offre n'est jamais touchée. Le rejeu est chronométré comme une dictée, sous son propre numéro de passage (processus `rejeu`), et s'affiche à côté de l'original dans le rapport, avec le texte transcrit et le nombre de lignes trouvées des deux côtés. Il tourne après la réponse, comme un traitement ordinaire ; le rapport se recharge pour le suivre.

Limites : possible tant que l'audio existe (purge à 30 jours) ; un rejeu à la fois par dictée ; environ 0,5 centime par rejeu (transcription et trois appels). Jamais de courriel, jamais de compteur de l'artisan touché (quota de démo, offres d'essai).

## 5. Remède au verrou (D4 : A, D5 : 2 min 30)

Retenu : un bail court et un pouls.
- Le verrou se prend pour 2 min 30 au lieu de 15 minutes (Xavier, D5 : « à 2 minutes 30 pour avoir un peu de marge »).
- Le traitement le prolonge à chaque étape, et pendant les longues attentes : `on_progress` du client HTTP de Symfony est appelé au moins une fois par seconde (contrat lu dans `HttpClientInterface.php`) ; le pouls y prolonge le verrou toutes les 20 secondes. Le même pouls garde la connexion à la base en vie.
- Un pouls qui trouve le verrou repris par un autre arrête l'appel en cours : on ne paie pas deux fois.
- Un traitement mort ne prolonge plus rien : le verrou expire en 2 min 30, le cron (chaque minute) reprend. Attente maximale : environ 3 min 30 au lieu de 15 minutes.

À vérifier pendant le code : que ffmpeg et ffprobe, qui ne battent pas le pouls, restent sous 2 min 30 sur une dictée de 30 minutes (le chronomètre le mesure, étape `conversion`).

Autres voies : (B) garder 15 minutes, mais rendre le verrou à toute erreur par une connexion neuve et à l'arrêt de PHP (`register_shutdown_function`) : couvre l'offre 87, rien pour un processus tué ; (C) rien de plus que le correctif de `wait_timeout`.

## 6. Message à l'artisan (D5 : validé)

Quand le verrou a expiré sans que la dictée soit prête, l'écran d'attente et la fenêtre du bas le disent (« Le traitement s'est arrêté en route. Il reprend tout seul d'ici une minute. »). `/etat` ajoute `interrompu`. Le message n'apparaît qu'à l'expiration du verrou, 2 min 30 après le dernier pouls : un appel lent ne le déclenche pas (Xavier : « peu de message d'erreur »). Il disparaît dès qu'un traitement reprend la dictée.

## 7. Hors périmètre

- Les causes de lenteur chez Infomaniak : le chronomètre les montre, il ne les corrige pas.
- Le coût de l'IA en francs (sous-chantier 26) : les jetons gardés ici lui serviront, sans écran de coût dans ce sous-chantier.
- Les envois ratés avant l'envoi réussi.

## 8. Recoupement avec le sous-chantier 30

Son lot 3 touche `Traiteur`, `FileTraitement` et `Statut`, comme ce chantier. Mes ajouts dans `Traiteur` restent des appels courts au chronomètre, pour que ses rebases restent simples. `duree_ms` n'est pas doublée ; son lot 4 la recalculera par `MesureVoix` sans conflit de sens.
