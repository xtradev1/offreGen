---
chantier: De la voix à l'offre, 17 clients
slug: 2026-11-07_voix-offre/17-clients
phase: 8
statut: livre_en_code
gate_attendu: prod
profil: .claude/atoz-profile.md
---

## Prochaine action

Xavier essaie sur `http://localhost:8000` (compte de démonstration remis à zéro). Ensuite : sous-chantier 18 (facture).

## Plan

1. Rapprochement : nom normalisé (minuscules, sans accents, sans civilité), ressemblance de 0 à 1, rattachement au-dessus de 0,80 seulement s'il n'y a qu'un meilleur candidat.
2. Migration 08 revue : table `client`, `offre.client_id` ; `client_nom` et `client_adresse` deviennent `client_dicte` et `lieu_dicte` (la proposition de la dictée, sans fiche tant qu'elle n'est pas confirmée). Aucune fiche créée d'office.
3. `DepotClients` (liste avec nombre d'offres et date de la dernière, fiche, création, modification, suppression sans offre) ; `DepotOffres` lit le client par jointure.
4. Traitement : la dictée rattache au client reconnu, sinon garde la proposition.
5. Écrans : onglet Clients, liste, fiche qui monte (enregistrement automatique), fenêtre « Client de l'offre » (variante B), en-tête de l'offre sans les champs client.
6. Document, PDF, liste des offres, démonstration (fiches pour les offres d'exemple), isolation, recette au clic.

## En attente de toi

- Ouvrir l'onglet Clients : deux fiches d'exemple, une fiche qui monte au toucher, « Nouveau client » en bas.
- Toucher le client en haut d'une offre : la fenêtre « Client de l'offre » doit permettre de choisir une fiche ou d'en créer une.
- Après une vraie dictée (IA relancée) : vérifier qu'un client connu se rattache seul et qu'un inconnu reste « à confirmer ».
- Suivi Origin : faut-il créer des sous-tâches « Clients » et « Facture » sous la tâche 315 ?

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
- 2026-11-12 : réponses de Xavier à la maquette : onglet en deuxième position, liste et fiche validés ; variante B (fenêtre de choix) ; points réglés « À revoir » : « il faut tenter de trouver une approximation et à plus de 80 % on valide sinon on crée un nouveau et c'est uniquement lorsque le client valide que le client est bien créé ».
- 2026-11-12 : lecture retenue : au-dessus de 80 %, l'offre est rattachée d'office à la fiche ; sinon le nom dicté reste une proposition sur l'offre, et la fiche naît quand l'artisan la confirme dans « Client de l'offre ». Seuil strict (« Rossi » et « Rossé » font 80 % : pas de rattachement) ; égalité entre deux fiches : pas de rattachement, l'artisan choisit. Remplace les décisions « écrit pareil » et « fiche orpheline supprimée ».
- 2026-11-12 : lot 1, `Rapprochement` (ressemblance de 0 à 1, sans casse, accents ni civilité ; contenance d'un nom dans l'autre à 0,9 ; civilités contraires plafonnées à 0,5 ; seuil strict 0,8 ; égalité : rien). 15 tests, vus rouges (classe absente) puis verts.
- 2026-11-12 : lot 2, migration 08 revue (aucune fiche créée d'office, `client_dicte` et `lieu_dicte`), jouée sur dev et test, descente vers 7 puis remontée vérifiées. `~/offregen-attente/` vidé et supprimé.
- 2026-11-12 : lots 3 à 6 : `SaisieClient`, `DepotClients`, jointure dans `DepotOffres` (client_nom et client_adresse donnent la fiche, sinon le nom dicté), `Traiteur` rattache au-dessus de 80 %, `ClientControleur` (liste, fiche, création, modification en JSON, suppression sans offre, «Client de l'offre»), onglet Clients, en-tête de l'offre en deux liens (client, objet), démo avec fiches. La fenêtre qui monte (app.js) sert désormais au catalogue, aux clients et à l'offre ; recherche et enregistrement automatique se branchent sur une fenêtre chargée après coup.
- 2026-11-12 : écart de méthode : `SaisieClient`, `ClientsEcranTest` et les deux tests du traitement ont été écrits avec ou après le code, sans les voir rouges. Compensé par des mutations : suppression sans contrôle des offres, seuil des fiches proches porté à 2, rapprochement coupé dans le traitement ; chaque mutation fait échouer son test.
- 2026-11-12 : recette au clic (390 px) sur le compte de démonstration. Le cas «nom dicté à confirmer» a été préparé par la base (offre «Façade de la maison» : `client_dicte` = «M. Modele») : produire ce cas par une vraie dictée demande l'IA, coupée à la demande de Xavier. Un premier essai visait la mauvaise offre (la plus ancienne porte le n° 1) : corrigé, rejoué.
- 2026-11-12 : à noter pour Xavier : dans «Client de l'offre», le bouton «Créer la fiche» reste le plus visible même si une fiche ressemble ; en usage réel, au-dessus de 80 % le traitement a déjà rattaché l'offre.
- 2026-11-14 : essai réel chez Infomaniak : « Madame Exemple, à Bulle » rattachée d'office à « Famille Exemple, 1700 Fribourg ». Xavier : « si c'est pas la même localité ça ne doit pas marcher ». `Rapprochement::memeLocalite` : le lieu dicté doit contenir le NPA ou la localité de la fiche (mots entiers, sans accents, article de tête ignoré, « St » pour « Saint ») ; sans lieu dicté ou sans localité sur la fiche, le nom seul décide. Deux homonymes de localités différentes sont départagés par le lieu. Conséquence : un chantier dicté ailleurs que chez le client n'est plus rattaché d'office. Tests écrits avant, vus rouges ; mutation « lieu non transmis par le traitement » attrapée ; parcours réel rejoué : « nouveau client, à confirmer ».

## Preuves

```
$ vendor/bin/phpunit
OK (261 tests, 1352 assertions)
$ vendor/bin/phpstan analyse --no-progress
 [OK] No errors
```

Parcours cliqué (`scratchpad/parcours-17.js`) :

```
  liste : 2 clients | EX Famille Exemple Fribourg · 1 offre, la dernière le 10.11.2026 › / MO Monsieur Modèle Neuchâtel · aucune offre ›
  fiche : titre « Famille Exemple » ; bouton Enregistrer visible false
  saisie : « Enregistré »
  après fermeture : EX Famille Exemple Delémont · 1 offre, la dernière le 10.11.2026 › / MO Monsieur Modèle Neuchâtel · aucune offre ›
  nouveau : Famille Exemple / Monsieur Modèle / Mme Rossi | message « Fiche « Mme Rossi » créée »
  en-tête : M. Modele, Neuchâtel nouveau client, à confirmer
  fenêtre : Client de l'offre | Entendu dans la dictée M. Modele, Neuchâtel Créer la fiche « M. Modele » | proches : Monsieur Modèle / Famille Exemple
  recherche dans la fenêtre « ross » : Mme Rossi
  choisi : Monsieur Modèle, Route du Test 18, 2000 Neuchâtel | message « Client de l'offre : Monsieur Modèle »
  nouveau depuis l'offre : Commune de Courroux
  document : porte le client
  fiche Courroux : N° 1 · Façade de la maison CHF 8’496.65 Faite le 06.11.2026 | bouton supprimer absent
erreurs : aucune
```

Non-régression (`scratchpad/non-regression-17.js`) : fiche produit du catalogue enregistrée, fenêtre fermée, ligne à jour ; réglages enregistrés automatiquement ; aucune erreur.

Captures regardées : liste, fiche, offre à confirmer, «Client de l'offre», offre rattachée.

Non vérifié : une vraie dictée de bout en bout (IA coupée) ; sans JavaScript dans un navigateur (couvert par les tests HTTP seulement).
