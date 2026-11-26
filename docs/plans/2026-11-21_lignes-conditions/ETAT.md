---
chantier: Lignes offertes, rabais, conditions par offre, représentant, libellé d'acompte, escompte
slug: 2026-11-21_lignes-conditions
phase: 5
statut: en_cours
gate_attendu: aucun (lots 2 à 7 sur le test, d9f61e5 ; deux correctifs de la recette sur le test non commités, redéploiement à l'accord de Xavier)
---

## Suivi Origin

- Projet 44 (offreGen). Aucune tâche pour ce chantier au 2026-11-21 : à créer sur l'accord de Xavier.

## Prochaine action

Lots 2 à 7 déployés sur le test (d9f61e5, 2026-11-22), parcours cliqué sur le test fait. Deux correctifs trouvés par ce parcours, non commités dans `~/offreGen-lignes` : bandeau de renvoi en direct après un texte (`app.js`, `montrerRenvoi`), message de la fenêtre du rabais quand la finale est émise. Sur l'accord de Xavier : commit, push, redéploiement, puis sa recette. Ensuite : clôture (supprimer les bases `offregen_lignes_test` et `offregen_lignes_dev`, `/roadmap-sync`).

## En attente de Xavier

- D17 : « Offert » sans prix = taper 0 ; la bascule « Prix barré / Offert seul » de la v2 disparaît (D1 C tenue autrement).
- D18 : offre baissée sous les acomptes déjà facturés : facture finale refusée avec l'écart (prototype) ou facture finale négative (note de crédit).
- Création de la tâche Origin.
- Accord pour commit, push et redéploiement des deux correctifs de la recette sur le test.
- Recette des lots 2 à 7 sur le test.
- M2 de la revue : « Représenté par » sur une facture adressée au client chez lui, garder ou retirer.
- M5 de la revue : refuser un escompte dont le délai dépasse celui du paiement ?

## Constats sur le code (e48eab4, vérifiés le 2026-11-21)

- Prix vide = 0 (`src/Offre/Saisie.php:30`) ; un 0 s'affiche « Prix manquant », compte dans « Relire », sort « ? » sur le document (`templates/document/_blocs.html.twig:75`). Une ligne gratuite est impossible.
- Prix et quantité négatifs refusés (`Saisie.php:78`). Aucun rabais ni escompte dans `src/Offre/Totaux.php`.
- Validité et paiement : réglages du compte (`src/Compte/Reglages.php`). Début, durée, non compris : propres à l'offre (`src/Offre/Chantier.php`).
- Phrases de début et de fin : Lettre seulement, réglage du compte (`src/Compte/PhrasesLettre.php`).
- Libellé d'acompte figé (`src/Facture/PlanAcomptes.php:155`).
- L'extraction renvoie 0 pour un prix inconnu : elle devra renvoyer « vide », sinon chaque prix oublié devient un cadeau.
- Une offre envoyée reste modifiable dans l'application (`templates/offre.html.twig:28`, `modifiable: true`) : le verrou de la v2 était une invention de Claude.
- Seul un brouillon de facture se supprime (`FactureControleur::supprimer`, ligne 690, `supprimerBrouillon`) ; une facture émise s'annule (routes `facture_annulation` et `facture_annuler`). Conforme à la règle de Xavier.
- « ? » : placeholder `Prix ?` à l'écran (`templates/_lignes.html.twig:22`), « ? » sur le document (`templates/document/_blocs.html.twig:75-76`). Le bouton principal mène à « Relire » tant qu'un prix manque ; la garde de la route de l'aperçu n'est pas vérifiée.
- Risque plausible, non reproduit : quitter un champ remplace `.rows` et `.totals` à la réponse du serveur (`public/js/app.js:24-37`) ; une réponse arrivée entre l'appui et le relâché d'un toucher le ferait perdre. Le même défaut était certain dans la v2 (prouvé par Playwright), corrigé dans la v3.

## Décisions

| Date | Décision | Source |
|---|---|---|
| 2026-11-21 | D1 C : ligne offerte, prix barré ou « Offert » seul au choix par ligne, prix barré par défaut. | Réponses de Xavier à MAQUETTE-lignes-conditions.html |
| 2026-11-21 | D2 A : taper 0 rend la ligne offerte ; prix vide = prix manquant. | idem |
| 2026-11-21 | D3 B : pas de ligne « Dont offert » sous le total. | idem |
| 2026-11-21 | D5 A : textes avant et après, validité, paiement, escompte propres à chaque offre, remplis avec les réglages. Remplace L4 du 2026-11-20 (chantier des modèles). | idem |
| 2026-11-21 | D6 A : dans Lettre, le texte avant remplace la phrase réglée dans Ma marque. | idem |
| 2026-11-21 | D7 B : pas de « Utiliser pour toutes mes offres ». | idem |
| 2026-11-21 | D8 A : « Client » et « Représenté par » (le mot « mandant » écarté : il désigne celui qui donne le mandat). | idem |
| 2026-11-21 | D9 C : offre adressée au représentant par défaut, au client au choix. | idem |
| 2026-11-21 | D10 C : factures au client chez lui ou « p.a. » le représentant, au choix par offre. | idem |
| 2026-11-21 | D11 A : libellé d'acompte libre, prérempli, trois puces. | idem |
| 2026-11-21 | D12 A : la facture finale liste chaque acompte (libellé, numéro, montant). | idem |
| 2026-11-21 | D13 A : code QR au montant plein avec escompte. | idem |
| 2026-11-21 | D14 A : escompte sur chaque facture, acomptes compris. | idem |
| 2026-11-21 | D15 A : ordre de calcul : lignes, sous-total, rabais, HT, TVA, arrondi, acomptes, escompte en mention. | idem |
| 2026-11-21 | D4 B : le rabais paraît sous le sous-total, avant la TVA. | Réponses de Xavier à la v2 |
| 2026-11-21 | D16 A : dans Lettre, le texte après les lignes se place avant la formule de politesse, qui reste. | idem |
| 2026-11-21 | Une offre reste modifiable en tout temps, envoyée ou facturée. Une facture émise s'annule, jamais ne se supprime. | Xavier : « L'offre, on doit pouvoir faire ce qu'on veut avec » |
| 2026-11-21 | Aucun « ? » sur une offre : un prix vide laisse la case vide, et l'offre ne part pas tant qu'un prix manque. | Xavier : « je ne veux pas de point d'interrogation sur une offre » |
| 2026-11-21 | Moins de gestes, rien de superflu : v3 (rabais sous le sous-total, textes à leur place dans l'écran, bandeau « Renvoyer »). | Xavier : « least-click is best-click » |

## Journal

- 2026-11-22 : accord de Xavier (« oui, go, déploie sur le test »). Commit d9f61e5 poussé sur `chantier-voix-offre` (HEAD détaché : la branche est tenue par `~/offreGen`), déployé, sonde verte. Parcours cliqué sur le test avec un compte de démo à soi (`lignes-demo@exemple.ch`, Menuisier, fichier `~/.offregen-test-lignes-demo-compte` ; un compte d'essai vide `lignes-conditions@exemple.ch` créé d'abord, inutilisé). Trouvé et corrigé (non déployé) : le bandeau « Modifiée depuis l'envoi » ne paraissait qu'au rechargement après un texte avant ou après ; la fenêtre du rabais promettait que « la facture finale suivra » une offre dont la finale est déjà émise. Noté, laissé : une correction dans la même seconde que l'envoi ne montre pas le bandeau (dates à la seconde, comparaison stricte ; un humain ne va pas si vite).

- 2026-11-22 : recette du lot 1 sur le test par Xavier : « ça marche, attaque le lot 2 et les autres lot ». Lots 2 à 7 codés (contrat et décisions fragiles dans `PLAN.md`). Défaut du lot 1 trouvé et corrigé : « Annuler et refaire » une facture perdait la marque « offerte » (test rouge sans le correctif, vert avec). Revue indépendante (Opus) : deux défauts importants (rabais sous les acomptes émis, rabais au-delà du sous-total) et quatre mineurs corrigés, le reste noté (`REVUE.md`). D18 : la finale négative est déjà refusée à l'émission (code d'avant le chantier).

- 2026-11-22 : Xavier veut la vraie interface (« j'ai besoin d'avoir la vraie interface pour voir comment ça s'intègre ») ; toucher une ligne l'ouvre partout sauf au défilement ; la fenêtre de la ligne reçoit les nouveaux réglages. Gate intention tenu pour franchi par ses réponses D1 à D16 et ses règles du 21 : `SPEC.md` les rassemble sans choix nouveau. Phase 2 (décomposition) sautée : un seul chantier, découpé en lots dans `PLAN.md`. Phase 3 faite (maquettes v1 à v3). Copie avancée sur 77954ce, bases `offregen_lignes_test` et `offregen_lignes_dev` créées. Lot 1 codé ; modèle retenu : colonne `offerte`, pas de prix NULL (décision fragile n° 1 du plan). L'étiquette « Offerte » à côté du nom, vue sur capture, retirée : elle répétait le « Offert » du total.

- 2026-11-21 : demande de Xavier (ligne à 0, ligne offerte ou négative, conditions et textes par offre, client et mandant, libellé d'acompte, escompte). Constats dans le code, `MAQUETTE-lignes-conditions.html` (D1 à D15), réponses de Xavier, puis prototype `MAQUETTE-lignes-conditions-v2.html` à sa demande (« je fais les clics puis je vois avec un exemple prédéterminé »).
- 2026-11-21 : retour de Xavier sur la v2 (offre figée et « ? » refusés, options inaccessibles, D4 B, D16 A, demande d'analyse UX et de gestes comptés, marche à suivre). Analyse faite, `MAQUETTE-lignes-conditions-v3.html` : corrections, superflu retiré, options factices rendues actives, marche à suivre de 15 étapes, tableau des gestes v2 et v3.

## Preuves

- 2026-11-22, déploiement de d9f61e5 sur le test : `OK (1074 tests, 9233 assertions)`, analyse statique sans erreur, `5 migrations executed` (jusqu'à `Version20261122000006`), `SONDE VERTE`, `/sante` : `{"commit":"d9f61e5f015b00992ef80003a1b65b4d65cb653a","base":"ok","migrations":"ok"}`.
- Parcours Playwright sur https://easy-offres.xtradev.ch (téléphone 390 px, compte `lignes-demo`, offre 74) : assistant du premier lancement passé ; largeur 390/390 ; valeur du rabais cachée à 5 %, visible pour « Autre % » ; « Rabais fidélité 7,5 % − CHF 841.50 », Total HT CHF 10'378.50 ; carte « Valable 60 jours, à 30 jours / Escompte 2 % à 10 jours » ; texte avant gardé au rechargement ; représentant « Madame Essai » ; document : rabais, Total HT, escompte, texte avant, « Pour le compte de », aucun « ? » ; « Envoyer » (partage simulé) : « Envoyée le 22.11.2026 » ; texte après corrigé 2,5 s plus tard : bandeau absent sans recharger (défaut), présent au rechargement, « Renvoyer » mène à `/offres/74/apercu`, bandeau parti une fois renvoyée ; puce : « Acompte à la commande, selon offre n° 2 » ; aucune erreur JS.
- PDF de l'acompte 2026-001 (lu par PyMuPDF) : « Famille Exemple p.a. Madame Essai Rue du Modèle 7 1950 Sion », « Représenté par Madame Essai », « Acompte à la commande, selon offre n° 2 … 3'113.55 » (30 % de 10'378.50), « Total TTC CHF 3'365.75 », « Escompte 2 % si payé jusqu'au 02.12.2026 : CHF 3'298.45 » (3'365.75 × 0,98 = 3'298.435, aux 5 centimes), QR « Montant 3 365.75 ».
- PDF de la finale 2026-002 : « Sous-total 11'220.00 Rabais fidélité 7,5 % − 841.50 Total HT 10'378.50 TVA 8,1 % 840.66 Arrondi -0.01 Total TTC 11'219.15 Acompte à la commande, selon offre n° 2, facture 2026-001 du 22.11.2026 − 3'365.75 À payer CHF 7'853.40 », « Escompte 2 % si payé jusqu'au 02.12.2026 : CHF 7'696.35 », QR « Montant 7 853.40 ».
- Correctifs de la recette : `testFinaleEmiseLaFenetreDitQuElleGardeSesMontants` rouge avec l'ancien gabarit (`Failed asserting`), vert avec le nouveau ; `RabaisEcranTest` et `RenvoiOffreTest` : `OK (17 tests, 124 assertions)` ; phpstan `[OK] No errors` ; `node --check app.js` sans erreur. Contrôle local (serveur 8031) : bandeau « Modifiée depuis l'envoi du 22.11.2026 · Renvoyer » sans recharger, état « Enregistrée », aucune erreur JS. Non vérifié : sur le test (pas redéployé).

- 2026-11-22, lots 2 à 7, base `offregen_lignes_test` : avant la revue `Tests: 1069, Assertions: 9198, Failures: 1` (FacturesEcranTest attendait l'ancienne ligne « Acomptes déjà facturés », remplacée par D12 A ; test mis à jour, `OK (25 tests, 298 assertions)`). Après les corrections de la revue : `OK (1074 tests, 9233 assertions)`. phpstan `[OK]` (aucune erreur). 60 fichiers touchés, aucun BOM ni tiret long.
- Parcours Playwright local (serveur 8031, base `offregen_lignes_dev`) : largeur 390/390 ; valeur du rabais cachée à 5 %, visible pour « Autre % » ; rabais « Rabais fidélité 7,5 % − CHF 173.70 » à l'écran ; carte « Valable 60 jours, à 30 jours / Escompte 2 % à 10 jours » ; texte avant gardé après rechargement ; représentant choisi ; document : rabais, Total HT, escompte, texte avant, « Pour le compte de », aucun « ? » ; puce de libellé d'acompte remplit le champ ; PC : fenêtre du rabais par-dessus l'offre ; aucune erreur JS. Captures regardées : défauts trouvés et corrigés (carte Conditions sans cadre, champs d'escompte sans style). Non vérifié : sur le site de test lui-même.

- 2026-11-22, déploiement : commit 814750a poussé sur `chantier-voix-offre`, `outils/deployer-test.sh` : `DÉPLOYÉ : 814750a1ae2e90c6699f6ea1923702f95a856c02`, `/sante` `{"commit":"814750a…","base":"ok","migrations":"ok"}`, `SONDE VERTE`. Parcours cliqué sur le test lui-même : non fait (seulement en local).
- 2026-11-22, avant le lot 1, base `offregen_lignes_test` : `OK (989 tests, 8649 assertions)`.
- Tests du lot écrits d'abord : `Tests: 30, Assertions: 37, Errors: 13, Failures: 4` (rouge attendu), puis `OK (30 tests, 77 assertions)`.
- Après le lot : `OK (1006 tests, 8711 assertions)` ; phpstan `[OK] No errors` ; aucun BOM ni tiret long dans les 23 fichiers touchés.
- Parcours Playwright sur un serveur local (base `offregen_lignes_dev`, compte de démo `demo-lignes@exemple.ch`) : toucher la quantité reste sur l'offre ; toucher le total ouvre `/offres/2/lignes/4` ; « Offerte » donne `{"tt":"Offert","barre":true}` ; 0 tapé donne `{"tt":"Offert","prix":"0"}` ; document sans « ? », deux « Offert » ; PC : la fenêtre s'ouvre par-dessus l'offre ; largeur 390/390 ; aucune erreur JS. Captures regardées. Non vérifié : défiler au doigt sans ouvrir la ligne (un navigateur n'émet pas de clic après un défilement, non simulable à la souris).

- Maquette v1 : `check.js` OK (1440 et 390 px) ; parcours cliqué (offrir, taper 0, rabais en CHF, place du rabais, conditions, représentant, acompte, escompte et QR) sans erreur JS, sans défilement de côté.
- Prototype v2 : `check.js` OK ; parcours des 11 étapes du guide à 1400 et 390 px, « 11 sur 11 faites », refus d'un acompte au-delà du total vérifié, remise à zéro « 0 sur 11 », aucune erreur JS. Captures regardées ; défaut trouvé et corrigé : `.champ { display: grid }` l'emportait sur `hidden` (champ CHF et mentions « Propre à cette offre » toujours visibles).
- Prototype v3 : `check.js` OK (1440 et 390 px) ; parcours des 15 étapes par clics, sans touche Entrée, à 1400 et 390 px : « 15 sur 15 faites », toucher après saisie reçu (v2 : perdu, v3 : reçu), aucun « ? » dans le document, bandeau visible après modification puis caché après renvoi, plafond des acomptes refusé, facture finale inchangée après suppression d'une ligne de l'offre, D18 refusée avec l'écart (CHF 766.10), remise à zéro « 0 sur 15 », aucune erreur JS, 0 tiret long. Captures regardées.
