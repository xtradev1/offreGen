---
chantier: De la voix à l'offre, 24 ergonomie au pouce
slug: 2026-11-07_voix-offre/24-ergonomie
phase: 4
statut: en_cours
gate_attendu: null
profil: .claude/atoz-profile.md
---

## Prochaine action

SPEC et PLAN à partir des décisions ci-dessous ; premier lot : navigation (micro au centre, Plus) et offre (total fixé, action principale).

## En attente de toi

- D10 sans réponse : tailles 64 / 56 / 48 px écrites dans `CODING_RULES.md`, ou garder la règle actuelle de 44 px ?
- D9 : pour baisser une facture (moins de travail que prévu), confirmer la proposition : montant ou pourcentage modifiable dans la feuille, et « Vérifier avant d'émettre » pour changer les quantités ou ajouter une ligne de remise.

## Suivi Origin

- Projet 44 offreGen, tâche 403 « Ergonomie au pouce, version PC et TVA (sous-chantier 24) ».
- Sous-tâches : 404 maquette et décisions, 405 navigation, 406 offre, 407 relecture, 408 ligne hors catalogue, 409 PDF.js, 410 envoyer et facturer, 411 TVA, 412 version PC, 413 revue et recette.
- Conversation rattachée à 404 le 2026-11-15.

## Décisions

- 2026-11-15, réponses de Xavier à la maquette (copiées telles quelles) : D1 micro au centre de la barre, sur tous les écrans : Valider (remplace la variante B du 2026-11-12) ; D2 Catalogue et Réglages rangés dans « Plus » : Valider ; D3 total fixé en bas et action principale qui change selon l'état : Valider ; D4 retirer une ligne : « A · corbeille gardée, 48 px, écartée du montant · fais A et B » (corbeille de 48 px et glisser vers la gauche) ; D5 relecture ligne par ligne : Valider ; D6 « Créer le produit » et « Rattacher », mot entendu ajouté aux synonymes : Valider ; D7 PDF dans l'application avec PDF.js : A ; D8 envoyer par le partage du téléphone : Valider, « ça veut dire quoi envoyer avec le téléphone ? ça envoie comment ? » ; D9 facturer dans une seule feuille : Valider, « si on veut changer la valeur de l'acompte ou de la facture finale, par exemple une réduction car il y a eu moins à faire que prévu ? » ; D10 : pas de réponse ; D11 écrans de la TVA : Valider ; D12 PC en trois colonnes : Valider ; D13 document à côté des lignes sur PC : Valider, « où est le micro sur PC ? car cela doit aussi fonctionner sur PC ! ».
- 2026-11-15 : micro sur PC : « Dicter une offre » en premier bouton du menu de gauche, « Importer un fichier » dessous, touche D ; l'enregistrement dans le navigateur marche déjà sur PC (sous-chantier 11), avec le micro du portable ou d'un casque. Écran PC de la maquette mis à jour.
- 2026-11-15 : TVA (Xavier) : un taux pour l'entreprise (8,1, 2,6, 3,8 ou 0 %), une ligne peut en avoir un autre ; le document détaille la TVA par taux dès qu'il y en a plusieurs.

## Demandes de Xavier (2026-11-15)

- Version mobile pour artisan, facile, « less click is better », boutons plus grands, atteignables d'une main ; version PC aussi.
- Ligne proposée par l'IA hors catalogue : la transformer en produit en un clic, ou la rattacher à un produit ; en rattachant, le mot entendu devient un synonyme.
- « Générer l'offre » affiche directement le PDF (rendu fidèle), retour au menu de modification pour corriger.
- TVA : un taux pour l'entreprise (8,1, 2,6, 3,8 ou 0 %), une ligne peut en avoir un autre, détail de la TVA par taux sur le document.
- « Voir plus grand » sur un modèle d'offre : agrandir l'image dans la page, sans en ouvrir une nouvelle (hors maquette, à faire sans décision).

## Journal

- 2026-11-15 : mesure de l'existant (`audit-ux.js`, démo locale, 15 écrans en 390 et 1280 px) : 184 cibles, 8 sous 44 px, toutes dans l'offre (liens client et titre, 27 et 29 px ; six corbeilles de 40 px) ; actions dans le tiers haut sur 14 écrans ; total de l'offre sous le pli ; facturer un acompte : 7 touchers plus l'envoi hors de l'application ; pas de version PC (colonne de téléphone centrée). Premier relevé faux (étiquettes de champs comptées comme cibles) : corrigé avant d'écrire la maquette.
- 2026-11-15 : maquette écrite (14 écrans proposés, prototype cliquable avec compteur). Trouvé au contrôle : page qui défilait de côté en 390 px (prototype et tableau), total faux sur un écran PC (3'197.55 au lieu de 3'197.60, recalculé : 2'958.00 HT, TVA 239.60, arrondi aux 5 centimes). Corrigés.

## Preuves

```
$ check.js MAQUETTE-ergonomie.html
bureau : largeur 1440/1440, images 21, cassées 0, kit oui
telephone : largeur 390/390, images 21, cassées 0, kit oui
OK.
$ essai-proto.js (prototype joué en 1280 et 390 px)
total après correction CHF 3'197.60 ; PDF chargé 900 px ; « Offre envoyée par WhatsApp » ; « Facture 2026-001 émise et envoyée » ; touchers 13 ; erreurs JS : aucune
```
