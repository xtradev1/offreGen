---
chantier: De la voix à l'offre, 21 liste des offres
slug: 2026-11-07_voix-offre/21-liste-offres
phase: 8
statut: livre_en_code
gate_attendu: prod
profil: .claude/atoz-profile.md
---

## Prochaine action

Recette de Xavier sur le site de test, dans la recette regroupée de la mise en production (`docs/plans/2026-11-17_mise-en-production/RECETTE.md`). Les gestes de ce sous-chantier y sont repris ; l'ancienne `RECETTE.md` locale, s'il y en a une, ne fait plus foi.

## En attente de toi

- Jouer la recette regroupée (`docs/plans/2026-11-17_mise-en-production/RECETTE.md`).
- Confirmer que « la barre horizontale » à enlever était bien la jauge du compteur.

## Journal

- 2026-11-12 : demande de Xavier : « pouvoir supprimer une offre [...] Elle ne devra pas s'enlever du quota [...] Elle peut être archivée » ; « une offre qui a été mise en échec, elle ne compte pas » ; « petit système de tri, puis ensuite les archives. Pour que ça ne devienne pas trop gros ». Maquette demandée.
- 2026-11-12 : maquette écrite, démo cliquable sur écran de téléphone : en préparation et échecs en haut, tri en quatre choix, menu « ⋯ » et balayage, archives repliées avec « Restaurer », compteur du mois. Contrôlée au clic (tri, archivage annulé, suppression, restauration, réponses gardées), aucune erreur.

- 2026-11-12 : réponses de Xavier : ordre validé ; échec en rouge, Réessayer ou Retirer, hors quota validé ; tri validé ; « B, archiver et supprimer » ; geste « B, balayer » ; compteur du mois validé. Et : « Il faut enlever la barre là qui est moche, qui barre horizontale. »
- 2026-11-12 : interprété : la barre est la jauge du compteur ; retirée, le texte reste. Le plafond « sur 30 » attend l'abonnement : non affiché.
- 2026-11-12 : codé. Défauts trouvés au parcours et corrigés : le clic envoyé à la fin d'un balayage refermait l'offre qu'on venait d'ouvrir ; le rouge de « Supprimer » débordait aux coins arrondis des cartes fermées ; « Aucune offre pour l'instant » s'affichait alors que des offres étaient archivées.
- 2026-11-12 : pour le parcours, deux échecs posés directement en base sur le compte de démo (texte de test, sans audio) : un échec ne se provoque pas depuis l'écran. Compte remis à zéro ensuite.
- 2026-11-18 : remis à jour au chantier de mise en production. Branche `chantier-voix-offre` poussée et PR #3 ouverte depuis le 2026-11-12 (les accords de commit et de PR demandés ici sont caducs) ; la recette passe dans la recette regroupée ; la revue indépendante du diff est l'étape 2 du `PLAN.md` de la mise en production.

## Décisions

| Date | Décision | Raison |
|---|---|---|
| 2026-11-12 | Une offre archivée ou supprimée compte toujours dans le quota ; un échec ne compte pas. | Règles de Xavier. Le quota se comptera sur les offres créées, pas sur celles visibles. |
