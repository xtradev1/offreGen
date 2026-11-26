# SPEC : documents et réglages

Trois retouches demandées par Xavier le 2026-11-24 après la recette générale (pas LC-3 et OV-3 de `MAQUETTE-recette-generale.html`). Constats détaillés et preuves : `ETAT.md`. Choix ouverts : `MAQUETTE-documents-reglages.html` (L0 à L2, O1, O2, V1 à V3).

## Problème

- LC-3 : « la liste des règles est trop longue ». Lecture retenue sous réserve de L0 : Ma marque, section 4 (formule d'appel, début et fin, 14 phrases), 53 % d'un écran de 4'165 px au téléphone.
- OV-3 : le filigrane « Aperçu » gêne la lecture de la facture dans « Facturer ». Il ne marque pas les démos ; aucun PDF ne le porte.
- Variantes : la quantité reprise ne se voit pas avant l'ajout ; une ligne libre en variante prend « forfait » ; un produit d'une autre unité prend 1 pièce.

## Utilisateurs

L'artisan (compte, essai ou démo) ; l'assistant du premier lancement pour L2.

## Périmètre (selon les choix de Xavier)

- L1 A (proposé) : rubrique « Phrases du document » (`/reglages/phrases`), contenu de la section 4 déplacé sans changement ; Ma marque garde logo, couleur, modèle ; ligne au sommaire des réglages.
- L2 (proposé non) : l'étape « marque » de l'assistant perd la section 4.
- O1 A (proposé) : filigrane retiré de la vignette de « Facturer » et du brouillon ; la légende « Aperçu : rien n'est encore créé » reste.
- V1 A (proposé) : variante = quantité et unité de la ligne remplacée, prix du produit ; unité différente du produit : ligne à relire.
- V2 A (proposé) : champ « Quantité » sous « Elle remplace », prérempli, envoyé avec chaque ajout.
- V3 : ligne libre en variante : quantité et unité de la ligne remplacée préremplies.

## Hors périmètre

- Marque « Démonstration » des PDF (Origin 557) et QR de démonstration (Origin 509), sauf O2 « Ici ».
- Le texte des phrases et des tournures.
- L'extraction des variantes dictées.

## Critères de succès

- Ma marque au téléphone sous 2'000 px (L1 A), mesurée comme au relevé.
- Aucune vue ne porte plus « Aperçu » en filigrane (O1 A) ; tests existants qui le cherchent (`tests/Http/EnvoyerFacturerTest.php:87`, légende) mis à jour.
- Les quatre cas du tableau des variantes donnent le résultat de la colonne « Avec ta règle », vérifiés par l'interface.
- Suite phpunit et phpstan verts sur `offregen_documents_test`.

## Contraintes

- `CODING_RULES.md`, `docs/SECURITE.md` (formulaires : jeton CSRF, rien de neuf côté droits).
- Enregistrement automatique de Ma marque (`data-auto`) : la nouvelle rubrique garde le même mécanisme ; un formulaire sans la section 4 ne doit pas effacer les phrases (champs absents = inchangés, à vérifier dans `ReglagesControleur` avant le code).
- Ajout sans réseau (chantier sans rechargement) : la ligne provisoire lit `data-unite` ; la quantité reprise doit la suivre.

## Risques

- L0 : si Xavier visait le bloc Conditions du document, L1 et L2 tombent et une autre maquette suit.
- V1 A : 42 m² au prix de la pièce donne un total faux si la ligne n'est pas relue ; le passage dans « Relire » d'une ligne ajoutée à la main n'est pas vérifié (colonne `a_verifier` de `DepotOffres`, lue par `RelectureControleur`) : à lire avant le plan.
- Champs absents du formulaire de Ma marque : risque d'effacer les phrases à l'enregistrement si le contrôleur lit tout le formulaire.
