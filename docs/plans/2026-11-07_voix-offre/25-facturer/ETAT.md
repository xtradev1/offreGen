---
chantier: De la voix à l'offre, 25 facturer en trois touchers
slug: 2026-11-07_voix-offre/25-facturer
phase: 5
statut: en_cours
gate_attendu: aucun
profil: .claude/atoz-profile.md
---

## Prochaine action

Lot 7 du `PLAN.md` : Ajouter (sobre, E7) et petits écrans sur PC (menu partout, fenêtres au milieu).

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
- 2026-11-15, lot 2 (7d66af0) : migration `Version20261115000003` (`facture.numero` NULL). Écart au plan, décidé en écrivant le test : pas de méthode `numeroter` ; c'est `DepotFactures::emettre` qui écrit le numéro dans la même requête que le reste, un brouillon n'est donc jamais numéroté sans être émis. `dernierNumero` ignore les NULL et range par `COALESCE(emise_le, cree_le)`. Le contrôleur donne au brouillon sans numéro le suivant au moment de l'émission ; numéro pris entre-temps : message, rien d'écrit. Mes premiers tests lisaient `['numero'] ?? 'absent'`, qui ne pouvait jamais valoir NULL : réécrits avant de conclure. Déployé sur le test, sonde verte.
- 2026-11-15, lot 3 (c5f72a4) : `PlanAcomptes` : acompte possible sans plan (la finale proposée d'abord), puces 20/30/40/50 % (plus celui des conditions), montant TTC réparti au prorata du HT des taux et ajusté au centime (recherche de ±2 ct par part quand la TVA arrondie fait sauter le total). Deux tests de l'écran actuel encodaient l'ancienne règle « pas d'acompte sans plan » : mis à jour. Défaut corrigé en relisant : `(int) $choisi['nombre']` aurait écrit « Acompte 1 sur 0 ».
- 2026-11-15, lots 4 et 5 ensemble (écart au plan) : les tests de l'ancien parcours passaient par le brouillon et la page de confirmation ; les mettre à jour deux fois aurait doublé le travail. Fait :
  - Feuille Facturer : genre en deux segments, puces 20/30/40/50 % et CHF, montant TTC, aperçu qui suit chaque choix (`GET /offres/{id}/facturer/apercu`, rien d'écrit), numéro, date, échéance repliés sous « Modifier », « Créer la facture · CHF … », phrase dessous. « Vérifier avant d'émettre » retiré. Entrée recalcule, ne crée rien. La miniature s'ouvre en grand (Échap ou « Retour » la ferment, la feuille reste).
  - Création : créée et émise d'un coup ; si l'émission est empêchée, le brouillon est effacé et la feuille dit pourquoi. « Corriger des lignes d'abord » (finale seulement) : brouillon sans numéro.
  - Une facture émise ou annulée se voit en PDF : `/factures/{id}` et `/document` y redirigent ; panneau (montant, état, client, date, offre, « Annuler la facture ») sous le PDF au téléphone, à droite sur PC ; « Télécharger » sur PC. Page de confirmation d'émission retirée (GET `/emettre` : 405).
  - Brouillon : lignes, « + », « Créer la facture », montant et numéro prévu dans la phrase ; sur PC le document à côté, d'office.
  - Corrigés en passant : la feuille dans une fenêtre perdait le bouton touché (`FormData` sans le bouton : « Corriger » devenait « Créer ») ; GET `/facturer` en 500 avec un QR-IBAN (référence calculée sur l'identifiant 0 de l'aperçu, identifiant provisoire désormais) ; « Annuler et refaire » ne recopiait pas le taux de TVA des lignes (lot 7 du 24) ; les bandeaux recouvraient les boutons quand une phrase les suit (hauteur du bas mesurée, `--bas`) ; « Pour / Famille Exemple / , Chemin… » éclaté (classe `client-facture` déjà prise).
  - Vu aux captures et laissé au lot 7 : sur PC, le PDF de la facture n'a pas le menu et reste dans la colonne de 640 px, la page du PDF ne fait que 264 px de large.
- 2026-11-15, lots 4 et 5 déployés sur le test (eb8cad2), sonde verte.
- 2026-11-15, lot 6 (Origin 419, E6) : étiquette orange « à confirmer » et loupe sur la carte du client ; sans client confirmé, « Générer l'offre » ouvre « Pour qui est cette offre ? » dans la feuille (`/offres/{id}/client?suite=apercu`) ; choisir une fiche, créer celle du nom dicté ou une nouvelle fiche mène au PDF de l'offre (seule la valeur `apercu` est suivie). Défaut trouvé par un ancien test : ma réécriture de `offre_client.html.twig` avait perdu l'espace insécable de « 100 % » ; relecture de tous mes textes visibles du jour, cinq autres corrigés (« : », « ; », « ? », « « … » »).

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
# lot 2
$ phpunit tests/Donnees/DepotFacturesTest.php (avant) : Tests: 17, Errors: 3 ; (après) OK (17 tests, 98 assertions)
$ doctrine-migrations migrations:status (test et développement) : Current Version20261115000003
$ phpunit : OK (457 tests, 2757 assertions) ; phpstan : [OK] No errors
$ outils/deployer-test.sh : SONDE VERTE, DÉPLOYÉ : 7d66af07f52578aeade3c5f13fbde4b741844e52, migrations à jour
# lot 3
$ phpunit tests/Facture/PlanAcomptesTest.php (avant) : Errors: 6, Failures: 1 ; (après) OK (12 tests, 38 assertions)
$ phpunit : OK (461 tests, 2778 assertions) ; phpstan : [OK] No errors
# lots 4 et 5
$ phpunit tests/Http/EnvoyerFacturerTest.php (avant le code) : Tests: 9, Failures: 8
$ phpunit : OK (466 tests, 2829 assertions) ; phpstan : [OK] No errors
$ node lot4-facturer.js lot4-390 390   (démo remise à zéro ; idem à 1440)
  feuille ouverte, url : /offres/140/apercu · bouton : Créer la facture · CHF 932.05
  40 % : Créer la facture · CHF 1'242.70
  après Entrée, toujours sur la feuille : true
  Échap ferme l'aperçu : true · la feuille reste : true
  après Créer : /factures/17/apercu · pages dessinées : 1
  Envoyer présent : true · touchers jusqu'à Envoyer compris : 3
  brouillon : /factures/18 · Facture finale, brouillon N° 2026-002 à la création
  débordement horizontal : 0 ; erreurs : aucune
  (CHF 1000 : bouton « Créer la facture · CHF 1'000.00 », vu à la capture 03-feuille-chf)
# lot 6
$ phpunit --filter testGenererLOffreFaitDAbordConfirmerLeClient (avant le code) : Failures: 1
$ phpunit : OK (467 tests, 2849 assertions) ; phpstan : [OK] No errors
$ node lot6-client.js lot6-390 390 143   (idem à 1440)
  étiquette : Famille Dubois Porrentruy à confirmer
  feuille : Pour qui est cette offre ?
  après le choix : /offres/143/apercu · touchers jusqu'au PDF : 2 · Envoyer : true
  client confirmé : Famille Dubois 2900 Porrentruy · Générer : /offres/143/apercu
  débordement horizontal : 0 ; erreurs : aucune
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
