# Plan du sous-chantier 11 : les écrans de la maquette, en local

Demande de Xavier, 2026-11-12 : « Où est-ce qu'il est le bouton pour que je puisse directement parler
et puis l'interface, il manque pas mal d'éléments. [...] Quel catalogue, quel élément ». Choix fait
par lui le même jour : bouton pour parler, onglet Catalogue (consulter et modifier), corrections de
l'offre, onglet Réglages. L'aspect est celui de la maquette v4 validée
(`docs/plans/2026-11-06_maquette-ux/MAQUETTE-offre-vocale.html`).

Ce sous-chantier reprend le 05 (corrections à l'écran) et la partie locale du 08 (enregistrement
dans le navigateur : `localhost` est un contexte sécurisé, le micro y est permis ; depuis le
téléphone, il faudra le site de test en HTTPS).

## Hors de ce sous-chantier

- Commandes vocales (sous-chantier 06) : pas de bouton micro sur l'offre tant qu'il ne fait rien.
- Majoration pour travail difficile, remise, ligne libre « achat + marge », fournisseur d'une ligne
  libre, aperçu, envoi, acompte : aucun code ne les porte encore ; un réglage sans effet serait un
  mensonge.
- Modifier le titre de l'offre, réordonner les lignes.

## Contrat technique

| Écran | Routes | Écrit |
|---|---|---|
| Barre d'onglets Offres, Catalogue, Réglages | sur `/`, `/offres/{id}`, `/catalogue`, `/reglages` | rien |
| Nouvelle offre : Enregistrer ou Importer | `GET /import`, `POST /import` (inchangé) | l'enregistrement du navigateur part par le même `POST /import`, champ `audio`, format webm/Opus (Chrome) ou mp4 (Safari) |
| Catalogue | `GET /catalogue?q=&cat=`, `GET /catalogue/nouveau`, `POST /catalogue`, `GET|POST /catalogue/{id}`, `GET|POST /catalogue/{id}/supprimer` | `produit` |
| Réglages | `GET|POST /reglages` | `artisan.entreprise`, `artisan.tva_pour_dix_mille`, `artisan.arrondi_cinq_centimes` |
| Offre modifiable | `POST /offres/{id}/lignes/{ligne}` (quantité, unité, prix, désignation), `.../juste`, `.../supprimer`, `.../restaurer`, `GET /offres/{id}/lignes/{ligne}`, `GET /offres/{id}/ajouter`, `POST /offres/{id}/lignes` | `ligne` (et `produit` si « garder au catalogue ») |

Règles :

- Chaque écriture passe par un formulaire HTML avec jeton CSRF, puis une redirection 303. Tout marche
  sans JavaScript ; `app.js` ne fait qu'envoyer un champ modifié, filtrer une liste et enregistrer.
- Les montants restent calculés par `Totaux`, côté serveur seulement, avec la TVA et l'arrondi du compte.
- Une ligne corrigée (quantité, prix, désignation) ou confirmée (« C'est juste ») perd « À vérifier ».
- Supprimer une ligne : un clic, puis « Annuler » dans le bandeau du bas (CODING_RULES). La ligne est
  marquée `supprimee_le`, jamais effacée : « Annuler » la remet.
- Saisie des nombres : `12.5`, `12,5`, `1'234.50`, `28.-` ; quantité > 0 et au plus 1 000 000 ;
  prix de 0 (« Prix ? ») à 1 000 000 CHF. Toute autre saisie est refusée avec un message.
- Isolation : chaque nouvelle route à paramètre a son cas dans `IsolationRoutesTest`, en lecture
  ET en écriture (l'artisan A ne modifie rien chez B, réponse 404).
- La ligne n'affiche plus « Entendu : … » : contraire à la règle « sans rappel de la dictée »
  (CODING_RULES, Ergonomie ; Xavier, 2026-11-06), ajouté par erreur au sous-chantier 10.
  La colonne `entendu` reste en base pour le banc.

## Lots

1. Migration (`artisan.tva_pour_dix_mille`, `artisan.arrondi_cinq_centimes`, `ligne.supprimee_le`),
   `Totaux` avec l'arrondi en option, `DepotReglages`, écran Réglages, barre d'onglets.
2. Catalogue : `DepotProduits` (lire, créer, modifier, supprimer), `SaisieProduit`, écrans.
3. Offre modifiable : `Offre\Saisie`, `DepotOffres` (modifier, confirmer, supprimer, restaurer,
   ajouter), écrans offre, ligne, ajout ; lignes groupées par catégorie du catalogue.
4. Enregistrement dans le navigateur : `ValidateurAudio` accepte webm, écran Nouvelle offre,
   `app.js` (MediaRecorder, minuteur, envoi avec progression, échec sans perte : Réessayer ou garder
   le fichier).
5. Preuves : PHPUnit (isolation, saisies, écrans), PHPStan, parcours cliqué Playwright avec un faux
   micro qui lit la dictée fictive, jusqu'à l'offre corrigée.
