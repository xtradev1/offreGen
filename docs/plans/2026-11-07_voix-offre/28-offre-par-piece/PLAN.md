# Plan : l'offre rangée par pièce

Décisions de Xavier du 2026-11-17 (maquette 28) : P1 A, P2 A, P3, P4, P5 validés. Voir `ETAT.md`.

## Contrat

- `ligne.piece` VARCHAR(80) NULL (migration additive). NULL : la ligne vaut pour tout le chantier, groupe « Général ».
- Extraction : champ `piece` dans l'outil, une règle dans la consigne. « général », « tout le chantier » ou vide donnent NULL. Première lettre en capitale, 80 caractères au plus.
- Regroupement (`OffreControleur::grouper`) : si au moins une ligne a une pièce, groupes par pièce (même nom sans tenir compte de la casse), « Général » en tête, puis les pièces dans l'ordre de leur première ligne ; sinon par catégorie, comme avant (P1 A). Les lignes de facture n'ont pas de pièce : rien ne change pour elles.
- Écran de l'offre : le titre d'une pièce porte son sous-total et « Renommer » (P5). PDF : le gabarit fait déjà titre et « Total … » dès deux lignes (P3).
- Ajout à la main (P4) : sur une offre rangée par pièce, la feuille d'ajout propose les pièces de l'offre, « Général » et « Autre pièce » ; la pièce de la dernière ligne est choisie d'avance. L'ajout rapide au clavier (PC) prend cette même pièce.
- Renommer (P5) : `GET|POST /offres/{id}/pieces?nom=…`, change la pièce de toutes les lignes qui la portent. Déplacer : champ « Pièce » dans la fiche d'une ligne d'offre.

## Lots

1. Données et extraction : migration, `LigneExtraite::piece`, validation, outil et consigne, `DepotOffres` (création, lecture, correction). Tests. Banc : dictée de Luigi, banc inventé 10 et 30 min, banc `--implicites` (pièce juste), contre la consigne en service.
2. Regroupement et écran : `grouper`, sous-total au titre, PDF. Tests.
3. Ajout à la main : pièces proposées, pièce par défaut. Tests.
4. Renommer et déplacer : routes, formulaires, isolation. Tests.
5. Revue, captures (téléphone et PC), déploiement sur le test.
