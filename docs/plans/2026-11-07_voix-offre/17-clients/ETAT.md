---
chantier: De la voix à l'offre, 17 clients
slug: 2026-11-07_voix-offre/17-clients
phase: 3
statut: gate_ouvert
gate_attendu: visuel
profil: .claude/atoz-profile.md
---

## Prochaine action

Xavier répond dans `MAQUETTE-clients.html` et colle « Copier le texte ». Ensuite : remettre la migration 08 dans le dépôt, `DepotClients`, création ou reprise du client par la dictée, onglet, fiche, choix du client dans l'offre, tests d'isolation.

## En attente de toi

- Onglet « Clients » en deuxième position : valider ou non.
- Liste (localité, nombre d'offres, date de la dernière) et fiche qui monte : valider ou non.
- Choisir le client dans l'offre : A (champ qui propose) ou B (fenêtre de choix).
- Les quatre points réglés : d'accord, ou à revoir avec un commentaire.

## Décisions

- 2026-11-12 : périmètre accepté par Xavier (« c'est bon ») : client créé à partir du nom dicté, corrigeable, rattachable à un client existant, liste de clients.
- 2026-11-12 : une table `client`, l'offre la désigne (`offre.client_id`) ; les colonnes `client_nom` et `client_adresse` partent après reprise des offres existantes (migration Version20261112000008, en attente dans `~/offregen-attente/`).
- 2026-11-12 : le nom dicté retrouve une fiche seulement s'il est écrit pareil, à la casse et aux accents près (collation `utf8mb4_unicode_ci`). Pas de rapprochement plus large : rattacher une offre au mauvais client coûte plus cher qu'un doublon qu'on corrige en deux touchers.
- 2026-11-12 : une fiche créée par la dictée et quittée par sa seule offre est supprimée d'elle-même.
- 2026-11-12 : on ne supprime que la fiche d'un client sans offre.
- 2026-11-12 : une offre suit la fiche (adresse corrigée, offres corrigées) ; la facture (18) figera nom et adresse du jour.
- 2026-11-12 : tri de la liste sans les civilités (Madame, Mme, Monsieur, M., Mlle, Famille).

## Journal

- 2026-11-12 : demande de Xavier (dictée du 2026-11-12) : « La gestion des clients. Aujourd'hui, il n'y a pas de clients. Alors, ça rentre déjà dans l'offre. Mais ça pourrait être un peu plus propre. »
- 2026-11-12 : sous-chantier ouvert, migration écrite, puis mis en pause pour le sous-chantier 22.
- 2026-11-12 : reprise après le 22 (Xavier : « oui go »). Aucun écran client n'avait été maquetté : maquette écrite avant le code (liste et fiche, deux façons de choisir le client dans l'offre, points réglés). Contrôlée par `check.js` et au clic : fiche, recherche, variantes A et B, aucune erreur JavaScript. Trois défauts de la maquette corrigés avant de la montrer (« 0 offre », compte d'offres après rattachement, tri qui retirait « Commune de »).
- 2026-11-12 : suivi Origin : aucune sous-tâche ne couvre ce sous-chantier, question posée à Xavier.
