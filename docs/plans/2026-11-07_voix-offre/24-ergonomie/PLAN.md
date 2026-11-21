# Plan du sous-chantier 24 : ergonomie au pouce, version PC et TVA

Source : `MAQUETTE-ergonomie.html`, décisions D1 à D13 de Xavier (2026-11-15, dans `ETAT.md`), règles de taille de
`CODING_RULES.md` (D10). Feu vert de Xavier : « oui tu peux tout faire je te valide les ajouts et modifs ».

## Décisions de mise en œuvre (prises par Claude, signalées à Xavier)

- Offre : les champs quantité et prix restent dans la ligne (un toucher pour corriger), agrandis à 48 px. Les
  lignes « à vérifier » passent par la relecture (D5). La maquette B montrait des lignes sans champs : corriger
  aurait demandé d'ouvrir la ligne, donc plus de touchers.
- PDF.js est livré avec l'application (copie verrouillée dans `public/js/vendor/`), pas chargé d'un CDN : la CSP
  n'autorise que le site lui-même, et une dépendance tierce chargée à chaud échappe au verrou.
- « Envoyer » : partage du téléphone avec le PDF joint (Web Share). Sans partage de fichier (certains navigateurs
  de PC) : téléchargement du PDF. L'offre est notée « envoyée » au moment du partage réussi.

## Lots

Chaque lot : tests écrits avant (vus rouges), code, suite complète, PHPStan, parcours cliqué en 390 et 1280 px,
captures relues, commit, déploiement sur le test, sonde verte.

1. **Navigation (Origin 405)** : barre du bas Offres, Clients, micro au centre, Factures, Plus ; le micro ouvre
   la feuille de dictée sur tous les écrans (import compris dans la feuille) ; page Plus (Catalogue, Réglages,
   Importer, Se déconnecter) ; onglet Plus actif sur Catalogue et Réglages.
2. **Offre (406)** : client et objet en cartes de 60 px, total TTC fixé en bas, action principale selon l'état
   (Relire N lignes, Générer l'offre, Facturer), bouton « + » de 64 px ; corbeille de 48 px écartée du montant,
   et glisser la ligne vers la gauche (D4, A et B) ; « Annuler » comme aujourd'hui.
3. **Relecture (407)** : `/offres/{id}/relire`, une ligne « à vérifier » à la fois, rappel de la dictée,
   quantité et prix en grand, « Corriger » et « C'est juste » de 64 px, la suivante d'elle-même.
4. **Ligne hors catalogue (408)** : sur la ligne, « Créer le produit » (désignation, unité, prix de la ligne) et
   « Rattacher à un produit » (recherche dans le catalogue ; le texte entendu devient un synonyme du produit).
5. **PDF dans l'application (409)** : « Générer l'offre » affiche le PDF (PDF.js), en bas Modifier, Facturer,
   Envoyer. Même écran pour une facture émise.
6. **Envoyer et facturer (410)** : « Envoyer » par le partage du téléphone, offre notée « envoyée » ; « Facturer »
   dans une feuille : genre proposé, numéro, montant ou pourcentage modifiable (D9), « Émettre et envoyer »,
   « Vérifier avant d'émettre » (brouillon, quantités et ligne de remise).
7. **TVA (411)** : taux de l'entreprise (8,1, 2,6, 3,8, 0 %), taux d'exception sur une ligne (et sur un produit,
   repris par ses lignes) ; calculs et document par taux ; migration additive.
8. **Version PC (412)** : à partir de 1024 px, menu à gauche avec « Dicter une offre » (touche D) et « Importer »,
   liste au milieu, offre à droite ; document à côté des lignes pendant la correction ; Tab, Entrée, J.
9. **Revue et recette (413)** : revue du diff, `RECETTE.md`, démo remise à zéro.
