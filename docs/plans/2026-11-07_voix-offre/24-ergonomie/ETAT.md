---
chantier: De la voix à l'offre, 24 ergonomie au pouce
slug: 2026-11-07_voix-offre/24-ergonomie
phase: 4
statut: en_cours
gate_attendu: null
profil: .claude/atoz-profile.md
---

## Prochaine action

Lot 6 du `PLAN.md` : « Envoyer » par le partage du téléphone (offre notée « envoyée ») et « Facturer » dans une feuille (montant modifiable, D9).

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

- 2026-11-15 : « voir en grand » d'un modèle dans la page (grandes images tirées du vrai PDF, flèches, « Choisir ce modèle ») ; règle D10 dans `CODING_RULES.md` ; rappel de la dictée permis en relecture et hors catalogue (D5, D6), écart à la règle du 06.11 signalé à Xavier. Commit 0c1fc43, déployé.
- 2026-11-15 : PLAN.md écrit (9 lots). Lot 1 fait : barre Offres, Clients, micro de 72 px au centre, Factures, Plus ; la feuille de dictée vient avec la barre (une par écran) ; page Plus (Catalogue, Réglages, Importer, Se déconnecter) ; « Terminer et créer l'offre » descendu sous le pouce (72 px) ; micro refusé ou absent : la feuille propose l'import. Tests écrits avant, vus rouges. Trouvé aux captures : « Terminer » au milieu de l'écran avec un grand vide dessous, corrigé.

- 2026-11-15 : lots 2 et 3 faits. Offre : client et objet en cartes de 60 px, total TTC fixé en bas, action principale selon l'état (« Relire N lignes » tant qu'une ligne est à vérifier ou sans prix, puis « Générer l'offre »), « + » de 64 px, champs de 48 px, corbeille de 48 px écartée du montant, glisser une ligne à gauche la retire avec le même « Annuler ». Relecture `/offres/{id}/relire` : une ligne à la fois dans l'ordre de l'écran, rappel de la dictée, quantité et prix modifiables, prix exigé pour une ligne sans prix, « Toutes les lignes sont relues » puis « Générer l'offre ». Décision de mise en œuvre : champs gardés dans la ligne (moins de touchers que la maquette B). Trouvé aux captures et au parcours : règles CSS du 24 écrasées par les anciennes (placées avant elles, puis `.ohead a.who` plus précise), pastille « Prête à relire » figée, montant tapé avec l'apostrophe droite ; un faux échec du glissé venait du test (ligne sous la barre du bas, diagnostiqué par les événements reçus). Tous corrigés.

- 2026-11-15 : lot 4 fait. Ligne hors catalogue d'une offre : « Créer le produit » (désignation, unité, prix de la ligne, catégorie « Divers ») et « Rattacher à un produit » (recherche partie du premier mot de la ligne ; la ligne prend désignation, unité et prix du catalogue, garde sa quantité). Décision de mise en œuvre : le synonyme ajouté est le nom que l'IA a donné à la ligne, pas la phrase entendue entière, qui brouillerait la recherche ; seulement si la ligne était hors catalogue. Rattacher marche aussi pour une ligne déjà au catalogue (corriger un mauvais produit de l'IA), sans synonyme. Trouvé aux captures : message « Rien pour… » resté après une autre recherche, corrigé.

- 2026-11-15 : lot 5 fait. « Générer l'offre » (offre et fin de relecture) mène à `/offres/{id}/apercu` : le vrai PDF dessiné par PDF.js 6.5.438, livré avec l'application (intégrité npm vérifiée, empreintes dans `DEPLOIEMENT.md`), sur fond gris ; en bas Modifier, Facturer, PDF (téléchargement), 64 px. Sans JavaScript ou en cas d'échec : un lien ouvre le PDF. La page HTML `/document` reste pour les liens existants.

## Preuves

```
$ check.js MAQUETTE-ergonomie.html
bureau : largeur 1440/1440, images 21, cassées 0, kit oui
telephone : largeur 390/390, images 21, cassées 0, kit oui
OK.
$ essai-proto.js (prototype joué en 1280 et 390 px)
total après correction CHF 3'197.60 ; PDF chargé 900 px ; « Offre envoyée par WhatsApp » ; « Facture 2026-001 émise et envoyée » ; touchers 13 ; erreurs JS : aucune
```

Lot 1, navigation (2026-11-15) :

```
$ phpunit : OK (424 tests, 2463 assertions) ; phpstan : [OK] No errors
$ node parcours-lot1.js (390 px, faux micro de Chromium)
  accueil : micro 72x72 px, centre à 195 px (écran 390), 18 px du bas ; onglets : Offres / Clients / Dicter une offre / Factures / Plus
  « Terminer et créer l'offre » : 72 px de haut, 84 px du bas ; abandon : feuille fermée
  depuis Clients : enregistrement ouvert ; Plus : Catalogue / Réglages / Importer un enregistrement, onglet actif « Plus »
  Importer : sélecteur de fichier ouvert sans changer de page ; Catalogue : onglet actif « Plus »
  micro refusé : « Le micro est refusé ou absent… » et « Importer un fichier » ; largeur 390/390 ; erreurs : aucune
```

Lots 2 et 3, offre et relecture (2026-11-15) :

```
$ phpunit : OK (430 tests, 2532 assertions) ; phpstan : [OK] No errors
$ node parcours-lot23.js (390 px, dictée de test transcrite et extraite par Infomaniak)
  total en bas : « Total TTC CHF 2’672.25 » ; action : « Relire 3 lignes » 286x64 ; « + » 64x64 ; corbeille 48x48
  glissé « Déplacement » : 5 lignes au lieu de 6 ; bandeau « « Déplacement » supprimée Annuler » ; Annuler : 6 lignes
  boutons : Corriger 175x64 à 10 px du bas, C'est juste 175x64 à 10 px du bas ; montant recalculé à la frappe
  1. Encore 3 à relire · « Peinture des murs, 2 couches » · Entendu : « Les murs du salon, environ 42 mètres carrés… »
  2. Encore 2 à relire · « Peinture des plafonds, 2 couches » ; 3. Encore 1 à relire · « Peinture anti-moisissure » (sans prix → 38)
  fin : « Toutes les lignes sont relues » ; boutons : Revenir à l'offre / Générer l'offre ; retour à l'offre : « Générer l'offre » ; largeur 390/390 ; erreurs JS : aucune
```

Lot 4, ligne hors catalogue (2026-11-15) :

```
$ phpunit : OK (434 tests, 2593 assertions) ; phpstan : [OK] No errors
$ node parcours-lot4.js (390 px)
  ligne libre ajoutée : boutons « Créer le produit 48 px / Rattacher à un produit 48 px »
  page Rattacher : recherche « Crépi » (rien au catalogue : tout le catalogue montré)
  rattachée : message « Ligne rattachée à « … » » ; la ligne prend le nom du produit ; boutons de rangement : 0
  fiche du produit : synonymes « échafaudage, Crépi de soubassement »
  « Créer le produit » : message « « Nettoyage haute pression » ajouté au catalogue » ; boutons restants : 0 ; largeur 390/390 ; erreurs JS : aucune
```

Lot 5, PDF dans l'application (2026-11-15) :

```
$ phpunit : OK (437 tests) ; phpstan : [OK] No errors
$ node parcours-lot5.js
  tel : 1 page(s) dessinée(s) en 539 ms, largeur 358px (écran 390), points sombres échantillonnés 70 ; « Pincer pour agrandir » ; Modifier 64 px / Facturer 64 px / PDF 64 px
  pc : 1 page(s) dessinée(s) en 523 ms, largeur 448px ; erreurs : aucune (au téléphone, seule l'icône du site en 404)
```
