---
chantier: De la voix à l'offre, 25 facturer en trois touchers
slug: 2026-11-07_voix-offre/25-facturer
phase: 5
statut: en_cours
gate_attendu: aucun
profil: .claude/atoz-profile.md
---

## Prochaine action

Lot 2 du `PLAN.md` : numéro de facture donné à la création (migration `numero NULL`, `DepotFactures`).

## En attente de toi

- Rien pour l'instant.

## Suivi Origin

- Projet 44 offreGen, tâche 414 « Facturer en trois touchers et écrans secondaires (sous-chantier 25) ».
- Sous-tâches : 415 maquette et décisions, 416 tablette en vue téléphone, 417 feuille Facturer, 418 facture toujours en PDF, 419 client confirmé avant l'offre, 420 Ajouter et petits écrans sur PC, 421 revue et recette.
- Conversation rattachée à 415 le 2026-11-15.

## Décisions

- 2026-11-15, Xavier (après essai au téléphone) : une tablette reçoit la vue du téléphone ; la vue PC « OK, rien d'exceptionnel », à rendre plus belle plus tard, le produit reste pensé d'abord pour le téléphone ; facturer depuis une offre doit être fluide et rapide, une facture envoyable en 3 touchers ; une facture se voit toujours en PDF ; refaire la page d'acompte et les petits écrans pas encore travaillés ; « Ajouter à l'offre » pensé pour le PC ; facture sans offre abandonnée (« on passe par une offre pour créer une facture »).
- 2026-11-15, tablette : la version PC demandera `(hover: hover) and (pointer: fine)` en plus de la largeur ; décision de Xavier, pas de question dans la maquette.
- 2026-11-15 : le lot 9 du sous-chantier 24 (revue et recette) passe après le 25, sa recette porterait sur des écrans qui changent.
- 2026-11-15, réponses de Xavier à la maquette (gate visuel franchi) :
  - E1 Valider : Facturer dans une feuille avec l'aperçu, « Vérifier avant d'émettre » disparaît, rien n'est créé avant « Créer la facture ».
  - E2 A : le bouton qui rend la facture définitive s'appelle « Créer la facture ».
  - E3 A : pas de page de confirmation, 3 touchers ; une erreur se répare en annulant la facture.
  - E4 Valider : acompte à 20 / 30 / 40 / 50 % ou un montant en CHF, TVA comprise.
  - E5 Valider : une facture s'ouvre toujours en PDF ; brouillon seulement pour corriger des lignes, numéroté à la création.
  - E6 Valider : client à confirmer en orange, confirmé au moment de « Générer l'offre ».
  - E7 Valider, avec la remarque « à améliorer pour que ce soit plus simple et sobre ».
  - E8 Valider : sur PC, menu partout, Facturer en deux volets, facture en PDF avec son panneau, petites fenêtres au milieu.
- 2026-11-15, E7 « plus simple et sobre » : je le traduis à l'implémentation sans nouvelle maquette : un seul champ de recherche, la liste des résultats sans cadre ni badge, pas de bandeau de confirmation (la ligne ajoutée apparaît dans le tableau, c'est la preuve), pas de bouton « Ligne libre » séparé (la dernière suggestion est « Ajouter « texte tapé » »). Les captures de la recette le montreront ; si Xavier trouve encore lourd, on reprend.

## Journal

- 2026-11-15 : parcours de Xavier refait sur la démo locale remise à zéro, à 390 et 1440 px (`parcours-facturer.js`, 24 écrans chacun). Constats : « Vérifier avant d'émettre » crée un brouillon numéroté ; depuis le brouillon, « Voir la facture » (HTML), « Émettre et PDF » (page de confirmation), « Émettre et PDF » (fige, retour au HTML), puis « PDF » dans un autre onglet sans « Envoyer » ; sur PC, huit écrans sans menu dans la colonne du téléphone ; la recherche de client existe sur l'offre (carte du client) mais rien ne la signale et l'offre se génère avec un client non confirmé.
- 2026-11-15 : maquette `MAQUETTE-facturer.html` : constat avec captures, règles proposées, prototypes cliquables téléphone et PC (trois cas : acompte, client à confirmer, finale avec travaux en plus), décisions E1 à E8. Défauts trouvés à l'image et corrigés : conditions du papier masquées par la classe `.pc` du prototype, liste des suggestions coupée par le tableau, bandeau sur la recherche et sur le bouton de création, quantités du brouillon mal alignées, identifiant `tel` en double.

- 2026-11-15 : `PLAN.md` écrit (contrat technique, signatures, trois décisions fragiles, 8 lots). Phase 4 terminée.
- 2026-11-15, lot 1 (Origin 416) : la version PC demande `(hover: hover) and (pointer: fine)` en plus de la largeur, dans app.css (bloc PC, bloc téléphone en `not all and …`) et dans les deux `matchMedia` de app.js. Test `VersionPcTest::testLaVersionPcDemandeUnPointeurFin` vu rouge puis vert. Un premier passage de la suite complète a donné 1 échec, non reproduit en quatre passages suivants : test instable à surveiller, cause non trouvée.

## Preuves

```
$ env APP_ENV=test vendor/bin/phpunit --filter testLaVersionPcDemandeUnPointeurFin   (avant le code)
  bloc PC de app.css / Failed asserting that 0 is identical to 1.
$ env APP_ENV=test vendor/bin/phpunit --no-progress   (après, trois fois)
  OK (454 tests, 2739 assertions)
$ vendor/bin/phpstan analyse --no-progress
  [OK] No errors
$ node lot1-tablette.js lot1   (démo locale)
  tablette-1180 {"pointeurFin":false,"menuGauche":"static","colonne":"none","plus":"flex"} erreurs : aucune
  pc-1440 {"pointeurFin":true,"menuGauche":"fixed","colonne":"block","plus":"none"} erreurs : aucune
```

```
$ node joue-mk25.js MAQUETTE-facturer.html
  tel CHF 1000 bouton : Créer la facture · CHF 1'000.00
  tel cas 1 direct, touchers : 3 · Facture 2026-001 Envoyée
  tel cas 2, touchers : 3
  tel cas 3, à payer : CHF 2'529.30 ; touchers : 8
  pc ligne ajoutée, focus : <input data-q="6" ...> · total CHF 3'366.25
  pc Échap ferme : true
  pc cas 3, à payer : CHF 2'356.35
  erreurs JS : aucune
$ check.js MAQUETTE-facturer.html
  bureau : largeur 1440/1440, images 5, cassées 0, kit oui
  telephone : largeur 390/390, images 5, cassées 0, kit oui
$ grep -cP (tirets U+2014, U+2013 et leur entité HTML) MAQUETTE-facturer.html
  0
```
