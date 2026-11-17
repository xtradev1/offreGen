---
chantier: De la voix à l'offre, 29 qualité de l'extraction
slug: 2026-11-07_voix-offre/29-qualite-extraction
phase: 1
statut: en_cours
gate_attendu: aucun
profil: .claude/atoz-profile.md
---

## Prochaine action

Quand les premières dictées sont dans `OffreGen/corpus/audio/` : transcrire, écrire les offres attendues, les faire valider par Xavier, puis écrire `outils/banc-corpus.php` (SPEC, « Ce qui reste dans l'application »).

## En attente de toi

- Déposer des dictées dans `OffreGen/corpus/audio/` sur le Synology (`NN-court-titre.m4a`, une vingtaine pour commencer, liste de ce qu'il faut couvrir dans `SPEC.md`).

## Suivi Origin

- Projet 44 offreGen, tâche 447 « Qualité de l'extraction : corpus réel, objectif 95 % (sous-chantier 29) », 8 h.
- Sous-tâches : 448 corpus, 449 offres attendues, 450 banc, 451 réglages.

## Décisions

- 2026-11-17, Xavier : « si le mec il fait 50 offres, il doit avoir max une ligne ou deux par offre. On doit vraiment être de l'ordre de 95 % de taux de réussite » ; « comment on améliore, quel modèle on utilise, est-ce qu'on veut que le modèle il tourne un peu plus longtemps ».

## Journal

- 2026-11-17 : le banc synthétique (`scratchpad/banc-longues.php`, dictées inventées) donne 94 à 98 % jusqu'à 30 min ; la seule dictée réelle relue (Luigi, enregistrement 6 du test, 7 postes avec prix) donne 5 ou 6 lignes justes sur 7 avec le réglage en service. Le banc synthétique est donc optimiste : il faut un corpus réel.
- 2026-11-17, rejeu de la dictée 6 sur 8 réglages, deux passes chacun (`scratchpad/luigi-configs.php`, catalogue de démonstration du test, offre attendue écrite à la main) :
  - 27B sans raisonnement, consigne en service : 5 puis 6 sur 7, 2 lignes en trop (« Bloc », « Remise en état du plafond ») : ce qu'a vu Luigi.
  - 27B avec raisonnement, consigne en service : 5 sur 7, 1 en trop ; une passe a produit 22 000 jetons de réflexion (74 s, 6,7 ct).
  - 397B, avec ou sans raisonnement, consigne en service : 2 sur 7 ; il fait des lignes libres au lieu de prendre les produits du catalogue.
  - Consigne corrigée (prix et unité dictés pour toute ligne, prix « en bloc » rattaché au poste d'avant, une précision n'est pas un poste) : 27B sans raisonnement 5 puis 6 sur 7 ; **27B avec raisonnement 7 sur 7 deux fois, 0 en trop, 31 à 37 s, 1,4 à 2,0 ct** ; 397B 4 ou 5 sur 7.
  - Réserves : une seule dictée de 7 postes ; la consigne corrigée a été écrite en voyant ses erreurs (risque de sur-mesure, d'où la moitié du corpus tenue à part) ; durée avec raisonnement sur une dictée de 30 min non mesurée (délai de 300 s).
- 2026-11-17 : erreur de Claude corrigée : la ligne « Démontage des lames » de l'offre 10 était le produit 26 du catalogue (créé par Luigi, forfait, 0 franc), pas une erreur du modèle. Vu en vérifiant le barème, avant de conclure.

- 2026-11-17, consigne mise en service avec la tâche 438 (prix dicté) : règles « prix dit pour toute ligne », « en bloc », « une précision n'est pas un poste ». Une quatrième règle mesurée le matin (« relève l'unité dite, même pour un produit du catalogue ») faisait reculer le banc inventé à 10 min : 26 et 27 sur 30 au lieu de 29 et 29 avec l'ancienne consigne (variantes voisines confondues) ; retirée, elle ne servait à rien (le code garde l'unité du catalogue). Consigne livrée, 27B sans raisonnement : dictée de Luigi 7, 7 et 6 sur 7, 0 ligne en trop (ancienne : 5 et 6, 2 en trop) ; banc inventé 10 min 29 et 28 sur 30, 30 min 88 sur 90 (ancienne : 29, 29 ; 88). Le modèle varie d'une passe à l'autre malgré la température 0 : une seule passe ne prouve rien.

## Preuves

```
$ php luigi-configs.php 2 (extrait)
=== 27B, sans raisonnement, consigne actuelle (passe 1) : 5 justes sur 7, 2 en trop ; 1392 envoyés, 613 reçus, 3 s, CHF 0.0024
=== 27B, avec raisonnement, consigne actuelle (passe 1) : 5 justes sur 7, 1 en trop ; 1428 envoyés, 22073 reçus, 74 s, CHF 0.0668
=== 397B, sans raisonnement, consigne actuelle (passe 1) : 2 justes sur 7, 6 en trop ; 1392 envoyés, 732 reçus, 11 s, CHF 0.0037
=== 27B, avec raisonnement, consigne corrigée (passe 1) : 7 justes sur 7, 0 en trop ; 1639 envoyés, 6303 reçus, 37 s, CHF 0.0196
=== 27B, avec raisonnement, consigne corrigée (passe 2) : 7 justes sur 7, 0 en trop ; 1639 envoyés, 4588 reçus, 31 s, CHF 0.0144
=== 397B, avec raisonnement, consigne corrigée (passe 1) : 5 justes sur 7, 2 en trop ; 1601 envoyés, 3239 reçus, 49 s, CHF 0.0129
```
