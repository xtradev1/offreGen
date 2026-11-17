---
chantier: De la voix à l'offre, 26 coût de l'IA
slug: 2026-11-07_voix-offre/26-couts-ia
phase: 4
statut: en_cours
gate_attendu: aucun
profil: .claude/atoz-profile.md
---

## Prochaine action

Écrire `PLAN.md` (lots, tests), y compris le lot « offre extrême » (délai de 300 s, limite de jetons de sortie), puis ajuster les sous-tâches Origin et implémenter.

## En attente de toi

- Créer dans le Manager Infomaniak un produit IA pour la production, et me donner son jeton le moment venu (pas dans la conversation : je te dirai où le poser).

## Suivi Origin

- Projet 44 offreGen, tâche 422 « Coût de l'IA : statistiques d'exploitation (sous-chantier 26) ».
- Sous-tâches : 423 spec et maquette, 424 jetons et coût de chaque extraction, 425 relevé quotidien Infomaniak, 426 écran de l'exploitant, 427 revue et recette.
- Conversation rattachée à 423 le 2026-11-17.

## Décisions

- 2026-11-17, Xavier : « c'est important pour les statistiques que j'aie le coût global des requêtes API » ; « juste pour moi, comme statistiques d'exploitation » (aucun écran pour l'artisan).
- 2026-11-17, Xavier : Infomaniak facture Whisper au temps de calcul du serveur, pas à la durée de l'audio.
- 2026-11-17, Xavier (F1) : « accessible avec des accès admin, pour que si un jour j'ai des collaborateurs ils y aient aussi accès, enfin si je le décide ». Droit administrateur sur le compte, donné et retiré par commande.
- 2026-11-17, Claude : F3 relevé chaque nuit, 7 jours réécrits ; F4 tarifs dans le code, datés. Restent pour Xavier : F2 et l'écran.
- 2026-11-17 : phase 1 (spec) et phase 3 (maquette) présentées ensemble, une seule attente : le chantier est petit et les décisions ouvertes tiennent dans la maquette.
- 2026-11-17, Xavier, sur la maquette du coût : « je préférerais franchement un écran admin vraiment séparé », « une partie dédiée » avec statistiques et autres éléments ; choisir le mois, l'année, ou une date de début et de fin ; objectif 1000 utilisateurs : pas tous les artisans, les 10 ou 20 plus gros, triés par nombre d'offres et par offres les plus chères ; analyser de façon anonyme pourquoi une offre coûte plus (produits, lignes, prix par ligne) ; écran validé, téléphone compris ; F2 = A, prorata (« on sait que c'est une estimation, il faut qu'on affine avec les données ») ; un produit Infomaniak séparé, autre jeton, possible ; plus tard, dans l'administration : la rentabilité (abonnements, frais, coût de l'IA, résultat), le mois passé, en direct et à l'avance.
- 2026-11-17 : la spec devient « partie administration, coût de l'IA et rentabilité ». Sous-tâches Origin à ajuster quand le plan sera écrit (estimation de 9 h trop basse pour cinq sections).
- 2026-11-17, Xavier, maquette d'administration : G1 à G5 « Valider » (partie séparée et son menu ; période mois, année ou deux dates avec raccourcis ; artisans top 10 ou 20, tris, recherche, signal ; offres en analyse anonyme ; rentabilité plus tard, en direct et en projection). Gate visuel franchi.

## Journal

- 2026-11-17 : relevé `GET /1/ai/357/consumptions` lu, coût de Qwen vérifié égal à jetons × tarif ; projection donnée à Xavier (pire offre observée CHF 0.0020 avec le 27B, 50 offres par mois au prix × 10 : CHF 1.00). Il n'existe que deux droits d'accès (public, artisan) : la manière de réserver l'écran à l'exploitant est la décision F1.
- 2026-11-17, question de Xavier sur « 19 643 / 4 175 » : la colonne « Consommation » mêlait jetons envoyés, jetons reçus et temps de calcul, illisible. Maquette corrigée : trois colonnes nommées, une ligne d'explication d'un jeton, totaux (27 379 et 5 645 jetons, 19 s). L'écran réel suivra ces colonnes.
- 2026-11-17 : recherche demandée par Xavier, un coût par requête chez Infomaniak ? Spécification officielle `https://developer.infomaniak.com/openapi.json` (4,9 Mo, téléchargée) : points d'entrée de l'IA relevés ; aucun ne rend de coût par requête. Discussion : `usage` (jetons). Transcription : asynchrone, résultat sans durée ni coût. Consommation : pagination et tri seulement, schéma des éléments non décrit. Pages publiques : la grille parle d'un « temps de génération » selon la taille du fichier ; les conditions d'utilisation, de « durée du fichier » ; l'observé (19,2 s d'audio, 1 s facturée) suit le temps de calcul.
- 2026-11-17, simulation demandée par Xavier, offre extrême (1 h de dictée, catalogue de 500 produits), sans appel réel : environ CHF 0.07 avec le 27B (Whisper 1,8 ct au rapport 1:20, Qwen 5,2 ct dont 3,6 ct de sortie). Constats dans le code : aucune limite d'une heure trouvée (seul plafond : 200 Mo dans `ValidateurAudio::TAILLE_MAX`) ; l'extraction n'a pas de `max_tokens` et un délai de 300 s : environ 12 000 jetons de sortie risquent de dépasser ce délai ou la limite par défaut du modèle. À essayer sur le site de test avec une longue dictée inventée.
- 2026-11-17, Xavier : « il faut rajouter la limite à 1 heure » (30 minutes envisagées, 1 heure retenue selon le coût). Fait hors plan, petit et urgent : `DUREE_MAX_SECONDES` (3600 par défaut), import refusé au-delà (tolérance 5 s), enregistrement prévenu 5 min avant puis arrêté et envoyé seul.
- 2026-11-17, limites mesurées chez Infomaniak (`GET /1/ai/models`, puis une demande à `max_tokens` démesuré) : Qwen3.8-27B, fenêtre totale de 100 032 jetons (envoyé plus reçu), statut bêta ; 397B, 204 800. Sans `max_tokens`, vLLM laisse au modèle toute la place restante : pas de coupure silencieuse tant que la fenêtre suffit. Vitesse du 27B : 185 et 301 jetons/s (deux essais de 1500 jetons) ; 12 000 jetons prennent environ une minute, sous le délai de 300 s. Limite réelle : la fenêtre ; catalogue plus dictée d'une heure plus réponse doivent tenir dans 100 000 jetons, soit environ 1 400 produits au plus.
- 2026-11-17, robustesse vérifiée dans le code : l'audio est rangé sur le serveur et inscrit en base avant tout traitement ; cron (`bin/traiter-file`, chaque minute, constaté dans la crontab du site de test) reprend à l'étape enregistrée, 5 tentatives, transcription gardée en base avant l'extraction. Point faible : avant l'envoi, l'enregistrement n'existe que dans la mémoire du navigateur.

## Preuves

```
$ php conso.php : GET /1/ai/357/consumptions HTTP 200 ; 16.11 Qwen3.8-27B 1208 / 192 jetons, cost 0.0010592 ; whisper 00:00:01, 0.0001
$ calcul : 1208 × 0.40 / 1e6 + 192 × 3.00 / 1e6 = 0.0010592
$ php sortie-max.php : Qwen3.8-27B « max_tokens=1000000 cannot be greater than max_model_len=100032 » ; 397B « max_model_len=max_total_tokens=204800 »
$ php vitesse.php : essai 1, 1500 jetons en 8.1 s (185 jetons/s) ; essai 2, 1500 jetons en 5.0 s (301 jetons/s)
$ phpunit (limite d'une heure) : OK (475 tests, 2936 assertions) ; phpstan : No errors
$ node limite-duree.js (serveur local, limite à 12 s, micro simulé) : à 3 s « Parlez, pièce par pièce. » ; à 10,5 s « Encore 1 min, puis l'enregistrement s'arrête et part. » ; à 16,5 s état « envoi », POST /import 303 ; erreurs JS : aucune
```
