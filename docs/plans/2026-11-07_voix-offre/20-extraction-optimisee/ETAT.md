---
chantier: De la voix à l'offre, 20 extraction optimisée
slug: 2026-11-07_voix-offre/20-extraction-optimisee
phase: 1
statut: en_cours
gate_attendu: aucun
profil: .claude/atoz-profile.md
---

## Prochaine action

Écrire `SPEC.md` d'après le cadrage (`docs/plans/2026-11-05_mesures-ia/cadrage-produit.md`, section 5), après le corpus du sous-chantier 29 : sans lui, la comparaison n'a rien sur quoi se faire.

## En attente de toi

Rien pour l'instant.

## Suivi Origin

- Projet 44 offreGen, tâche 452 « Extraction optimisée à côté de l'actuelle (sous-chantier 20) », 10 h.
- Sous-tâches : 453 spec, 454 seconde extraction, 455 les deux en parallèle sur le test, 456 comparaison sur le corpus, 457 revue et recette.

## Décisions

- 2026-11-12, Xavier : pouvoir tester les deux versions (écart découvert : l'extraction en service envoie tout le catalogue en un appel avec outil, le cadrage veut un catalogue jamais envoyé en entier et deux appels au plus).
- 2026-11-17, Xavier : « on avait une optimisation pour le coût du modèle, j'aimerais que tu regardes pour la mettre en parallèle, comme ça on peut tester les deux ».

## Journal

- 2026-11-17 : ouvert. Principe du cadrage : le modèle relève ce qui est dit (désignation, quantité, unité, pièce, prix dicté) sans catalogue ; le code cherche dans le catalogue ; un second appel, seulement pour les lignes ambiguës, avec 3 à 5 candidats. Il couvre d'office les prix dictés (tâche 438) et la pièce (sous-chantier 28). « En parallèle » : un réglage choisit l'extraction ; sur le site de test, les deux tournent sur chaque dictée et leurs résultats sont gardés côte à côte.

## Preuves

Aucune encore.
