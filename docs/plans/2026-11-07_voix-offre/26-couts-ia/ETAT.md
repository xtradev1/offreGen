---
chantier: De la voix à l'offre, 26 coût de l'IA
slug: 2026-11-07_voix-offre/26-couts-ia
phase: 3
statut: gate_ouvert
gate_attendu: visuel
profil: .claude/atoz-profile.md
---

## Prochaine action

Attendre les réponses de Xavier à `MAQUETTE-admin.html` (G1 à G5), puis `PLAN.md` et implémentation.

## En attente de toi

- Ouvrir `MAQUETTE-admin.html`, essayer la partie administration (menu, période, classements, offres, rentabilité), répondre sous G1 à G5, puis « Copier le texte » et le coller dans la conversation.
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

## Journal

- 2026-11-17 : relevé `GET /1/ai/357/consumptions` lu, coût de Qwen vérifié égal à jetons × tarif ; projection donnée à Xavier (pire offre observée CHF 0.0020 avec le 27B, 50 offres par mois au prix × 10 : CHF 1.00). Il n'existe que deux droits d'accès (public, artisan) : la manière de réserver l'écran à l'exploitant est la décision F1.
- 2026-11-17, question de Xavier sur « 19 643 / 4 175 » : la colonne « Consommation » mêlait jetons envoyés, jetons reçus et temps de calcul, illisible. Maquette corrigée : trois colonnes nommées, une ligne d'explication d'un jeton, totaux (27 379 et 5 645 jetons, 19 s). L'écran réel suivra ces colonnes.
- 2026-11-17 : recherche demandée par Xavier, un coût par requête chez Infomaniak ? Spécification officielle `https://developer.infomaniak.com/openapi.json` (4,9 Mo, téléchargée) : points d'entrée de l'IA relevés ; aucun ne rend de coût par requête. Discussion : `usage` (jetons). Transcription : asynchrone, résultat sans durée ni coût. Consommation : pagination et tri seulement, schéma des éléments non décrit. Pages publiques : la grille parle d'un « temps de génération » selon la taille du fichier ; les conditions d'utilisation, de « durée du fichier » ; l'observé (19,2 s d'audio, 1 s facturée) suit le temps de calcul.

## Preuves

```
$ php conso.php : GET /1/ai/357/consumptions HTTP 200 ; 16.11 Qwen3.8-27B 1208 / 192 jetons, cost 0.0010592 ; whisper 00:00:01, 0.0001
$ calcul : 1208 × 0.40 / 1e6 + 192 × 3.00 / 1e6 = 0.0010592
```
