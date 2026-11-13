---
chantier: De la voix à l'offre, 18 facture
slug: 2026-11-07_voix-offre/18-facture
phase: 5
statut: en_cours
gate_attendu: null
profil: .claude/atoz-profile.md
---

## Prochaine action

Lot 6b du `PLAN.md` : QR-facture (dépendances verrouillées, `QrFacture`, section de paiement à l'écran et au PDF, référence RF ou QRR à l'émission), dès que bcmath est installé dans WSL.

## En attente de toi

- Installer l'extension bcmath dans WSL, sans mot de passe, depuis PowerShell sous Windows : `wsl -d Ubuntu -u root -- apt install -y php8.5-bcmath`. Le lot 6b (code QR) l'attend.
- Avant la mise en production : une phrase dans la politique de confidentialité sur l'envoi des adresses tapées à swisstopo (`docs/SECURITE.md`, 6 bis).
- L'adresse de la démo, « Rue de la Gare 4, 2800 Delémont », n'existe pas au registre (Delémont n'a qu'une « Place de la Gare ») : l'écran dit « introuvable ». La garder (elle montre le message) ou en prendre une vraie ?
- Si tu scannes le code de la maquette : il est en adresse « combinée » (type K), lue par les banques jusqu'au 21.11.2026 seulement. L'application émettra des adresses structurées (type S).

## Suivi Origin

- Projet 44 offreGen, tâche 400 « De la voix à l'offre, suite : clients et facture » (la tâche 315 ne prend pas de sous-tâche de plus par l'outil).
- Sous-tâches : 401 Clients (sous-chantier 17), 402 Facture (ce sous-chantier). Conversation rattachée à 402 le 2026-11-13.

## Décisions

- 2026-11-12 : décisions de Xavier à la maquette 14 : « Facturer » depuis l'offre (« avec la possibilité de mettre soi-même le numéro ») ; QR-facture validée ; même modèle et même couleur que l'offre ; facture plus longue qu'une page : section de paiement sur une page à part.
- 2026-11-13 : bibliothèque retenue pour la QR-facture : sprain/swiss-qr-bill 5.3.1 (seule version qui accepte PHP 8.5 et Symfony 7), pour la charge utile et sa validation. Elle exige l'extension bcmath. Le dessin du code ne passe pas par elle : son écrivain SVG appelle GD pour la croix suisse (`imagecreatefromstring`), et GD manque. Le code est dessiné par bacon/bacon-qr-code (déjà dans ses dépendances, sans GD), niveau de correction M, croix suisse ajoutée en SVG.
- 2026-11-13 : dompdf lit le `viewBox` d'un SVG en pixels CSS et ignore la taille demandée en mm : le code se dessine sur 174 unités (46 mm à 96 dpi). Avec `viewBox="0 0 46 46"`, il sortait à 12 mm, illisible.
- 2026-11-13 : proposé à Xavier comme réglé : IBAN dans « Mon entreprise » ; référence RF bâtie sur le numéro (IBAN ordinaire) ou référence QR (QR-IBAN) ; client copié sur la facture le jour de sa création ; la facture garde le lien avec son offre.
- 2026-11-13 : réponses de Xavier à la maquette : facture et section de paiement « Valider » ; A2, une facture par acompte ; C2, un onglet Factures, « mais un lien depuis l'offre (voir la facture) » ; D1, 2026-001 ; points réglés « D'accord ».
- 2026-11-13 : B, facture figée une fois émise. Xavier n'a pas choisi d'option ; il a demandé « c'est quoi le mieux » pour de petites organisations, puis conclu : « si son offre est envoyée il doit s'y tenir donc la facture devrait être figée ? ». Retenu : brouillon modifiable, « Émettre » fige, « Annuler » ou « Annuler et refaire » corrige (numéro jamais repris). Plus simple pour un artisan qu'une note de crédit, que la maquette évoquait.
- 2026-11-13 : bcmath existe en production (Xavier). Manque encore dans WSL (`php -m` : iconv, intl, ni bcmath ni gd).
- 2026-11-13 : adresses structurées (type S) seulement dans le code QR : le type K n'est plus permis à l'émission depuis le 21.11.2025, et les banques ne garantissent sa lecture que jusqu'au 21.11.2026 (fiches SIX, ZKB, PostFinance). L'essai et la maquette sont en type K : à refaire au lot 6.
- 2026-11-13 : le PDF émis est gardé en base (`facture_pdf`) et servi tel quel : c'est la seule façon sûre de rendre le même document dans dix ans, quels que soient le logo, l'adresse ou le taux d'alors. Rien n'est figé avant l'émission.
- 2026-11-13 : aucun PDF de brouillon, pour qu'aucun client ne paie un brouillon. L'aperçu à l'écran suffit avant « Émettre et PDF ». Écart avec la maquette : « Émettre et PDF » est sur l'aperçu, pas sur l'écran des lignes, pour qu'on voie la facture avant de la figer.
- 2026-11-13 : la dernière tranche des acomptes est la facture finale : elle reprend toutes les lignes et déduit le TTC des acomptes émis. Un acompte vaut le pourcentage du HT de l'offre le jour de sa création, corrigeable en brouillon. Échéance d'un acompte : 30 jours, aucun réglage ne la donne.
- 2026-11-13 : le client d'un brouillon ne se change pas : il est copié de l'offre. Pour en changer, supprimer le brouillon, changer le client de l'offre, refacturer.
- 2026-11-13 : onglet Factures en troisième position, après Clients (Clients deuxième, Xavier, 2026-11-12). Cinq onglets à 390 px : à vérifier à la capture.
- 2026-11-13 : Xavier : « dans les réglages, quand tu mets adresse, il y a rue, NPA et ville […] c'est hyper important » ; recherche d'adresses : « tu peux faire Suisse Topo […] B avec A comme base ». Retenu : rue, n°, NPA, localité, pays en champs séparés (longueurs de la QR-facture), pour l'entreprise et les clients ; NPA contrôlé sur le répertoire officiel des localités de swisstopo copié chez nous (la liste de la Poste ne répondait plus : 404) ; suggestions et « trouvée ou pas » par le service de recherche de swisstopo, appelé par notre serveur.
- 2026-11-13 : NPA inconnu refusé ; localité ramenée à son nom officiel (casse, accents, « Saint » abrégé ; « Lausanne » accepté pour 1000) ou refusée ; rue facultative (norme), numéro sans rue refusé ; numéro tapé au bout de la rue mis dans son champ. Une adresse introuvable au registre s'enregistre quand même : le registre ne connaît pas tout (bâtiment neuf), et le NPA est déjà contrôlé.
- 2026-11-13 : migration 10 additive (`docs/DEPLOIEMENT.md`) : `artisan.adresse` et `client.adresse` gardent le texte, rendu depuis les champs, que l'ancienne version lit encore. Une première version renommait `artisan.adresse` : reprise avant tout commit, l'ancienne version n'aurait pas tourné sur la nouvelle base.
- 2026-11-13 : une adresse d'avant que le découpage ne lit pas (trois lignes, NPA inconnu) reste en texte, et l'écran la montre « à reporter dans les champs » ; enregistrer un autre champ ne l'efface pas.
- 2026-11-13 : la facture copie l'adresse du client en champs à sa création (`client_rue` … `client_pays`), à côté du texte : la section de paiement du lot 6b la lira.
- 2026-11-13 : lot 6 coupé en deux : 6a adresses (fait), 6b QR-facture (attend bcmath).

## Journal

- 2026-11-13 : sous-chantier ouvert (Xavier : « oui vas-y »). Relu ce que la maquette 14 avait validé pour la facture.
- 2026-11-13 : essai technique hors dépôt (`~/essai-qr`) avant la maquette, pour lever le risque de la QR-facture :
  - sprain/swiss-qr-bill 4.10 s'installe, mais ne connaît que Symfony 6 ; la 5.3.1 accepte PHP 8.5 et Symfony 7 et exige bcmath ;
  - la référence RF appelle `bcmod` même en 4.10 : bcmath est nécessaire dans tous les cas ;
  - charge utile sans référence : aucune violation ;
  - premier rendu dompdf : code de 12 mm, non lu ; après passage en pixels : 46,1 mm, relu par OpenCV, accents intacts.
- 2026-11-13 : maquette écrite : parcours en trois écrans, facture A4 avec la vraie section de paiement (code de l'essai, IBAN d'exemple de la norme), quatre choix, points réglés. Défauts trouvés sur les captures et corrigés avant de la montrer : montants faux (ligne Déplacement manquante, TVA et arrondi recalculés), facture rangée dans la colonne des téléphones et recouverte par la section de paiement (tailles passées à l'échelle de la page).
- 2026-11-13 : gate visuel franchi par les réponses de Xavier. Phase 4 : `PLAN.md` écrit, sept lots. Le format suit les plans du chantier (contrat technique et lots) plutôt que celui du skill `writing-plans`, comme les sous-chantiers 01, 10 et 11.
- 2026-11-13 : lot 1 fait. Migration 9 (`Version20261113000001` : `artisan.iban`, `facture`, `facture_ligne`, `facture_pdf`), jouée sur dev et test, défaite puis rejouée sur test. `Numerotation`, `PlanAcomptes`, `DepotFactures` (écritures de lignes réservées au brouillon dans la requête même), `Conteneur::factures()`, remise à zéro de la démo (factures avant offres). Tests écrits avant le code ; trois tests faux corrigés (un demi-centime qui monte, deux `?? 'absente'` qui masquaient un null). Six fautes volontaires, toutes détectées. `BaseDeTest::vider` détache `refaite_depuis` avant d'effacer : sans cela, 96 tests tombaient après le test de la démo.
- 2026-11-13 : lot 2 fait. `Iban` (CH et LI seulement, clé modulo 97 calculée par morceaux, sans bcmath ; QR-IBAN reconnu à l'IID 30000 à 31999 ; forme lisible par groupes de quatre), champ IBAN dans « Mon entreprise », « IBAN manque » au sommaire (« adresse, IDE et IBAN manquent »). Exemples d'IBAN vérifiés à part en Python avant d'écrire les tests. Trouvé au parcours cliqué : la remise à zéro de la démo gardait l'IBAN tapé par le visiteur précédent ; corrigé (`assistantARefaire` l'efface), test rouge puis vert. Le 404 de la console est `favicon.ico`, présent avant ce lot.
- 2026-11-13 : Xavier : « il faut absolument que ça corresponde au standard » ; il demande s'il existe une API pour vérifier une adresse en la tapant, et d'enchaîner les lots 3, 4, 5. Trouvé : la recherche d'adresses de swisstopo (registre officiel des adresses de bâtiments), gratuite, environ 20 requêtes par minute en moyenne. Option à lui soumettre avant le lot 6 : chaque recherche ferait sortir l'adresse d'un client de l'application.
- 2026-11-13 : lot 3 fait. `SupportLignes` (interface commune à `DepotOffres` et `DepotFactures`) ; `LigneControleur` sert l'offre et le brouillon de facture (409 par `Conflit` sur une facture figée) ; `FactureControleur` (liste, nouvelle facture, création, écran, en-tête, suppression du brouillon) ; `Montants` ; `_lignes.html.twig` partagé, en lecture seule sur une facture figée ; onglet Factures en troisième ; liens « Facture 2026-001 » sur l'offre ; « Facturer » sur l'aperçu de l'offre. Une offre sans client confirmé ne se facture pas. Le rang et le pourcentage d'un acompte viennent du serveur, jamais du formulaire. Tests d'écran écrits avant le contrôleur ; trois tests faux corrigés (jeton pris sur une page sans formulaire, ordre des attributs, apostrophe échappée). Six fautes volontaires, toutes détectées. Parcours cliqué en 390 px : cinq onglets de 78 px chacun ; « Ligne libre » retirée des factures après lecture des captures.
- 2026-11-13 : lot 4 fait. `_document.html.twig` sert l'offre et la facture (titre, « selon offre n° … du … », échéance, acomptes déduits, à payer ; ni validité ni signature sur la facture) ; aperçu `facture_document.html.twig` avec les alertes (ligne sans prix, adresse, IBAN). Non-régression de l'offre : HTML des six modèles et de l'aperçu relevé avant et après, identique hors blancs.
- 2026-11-13 : lot 5 fait. Confirmation « Émettre la facture ? » sans JavaScript (comme la suppression d'un produit), émission en une transaction (valeurs figées, PDF gardé dans `facture_pdf`, rendu avec les valeurs figées), PDF servi tel quel, 409 pour un brouillon ; annulation et « Annuler et refaire » (brouillon copié au numéro suivant, échéance au même délai) ; un acompte déduit par une finale émise ne s'annule pas avant elle ; émission refusée sans ligne, avec une ligne sans prix ou un montant à payer négatif. Rendu PDF partagé avec l'offre (`OffreControleur::rendrePdf`). Trouvé au parcours : l'écran d'une facture émise n'avait plus de barre d'action (ni aperçu, ni PDF, ni annulation) ; ajoutée, avec son test. Une espace mangée par l'outil d'édition dans `routes.php` corrigée.
- 2026-11-13 : lots 1 à 5 commités (5de18b4) et poussés sur `chantier-voix-offre`, accord de Xavier.
- 2026-11-13 : lot 6a fait (adresses). `Adresse`, `SaisieAdresse`, `Localites` (3 190 NPA, `outils/localites.php` les refait), migration 10 jouée sur dev et test, défaite puis rejouée ; réglages, assistant et fiche client en champs séparés (`_adresse.html.twig`) ; `RegistreSwisstopo` (délai 3 s, rien de gardé) et `RegistreFactice` (tests et développement) ; `AdresseControleur` (localités d'un NPA, suggestions, vérification ; 20 appels par minute et par session) ; app.js : suggestions pendant la frappe, NPA qui remplit la localité, « trouvée ou pas » en quittant l'adresse, enregistrement automatique qui attend qu'on quitte l'adresse. Trouvé par les tests : le `.env` local (`ADRESSES=swisstopo`) aurait fait appeler swisstopo par les tests ; `BaseDeTest::env` l'écarte, test à l'appui. Trouvé au parcours : rue et numéro tapés ensemble restaient dans la rue (« Bundesplatz 3 », n° 4) : le numéro au bout de la rue passe dans son champ, au serveur et à l'écran ; les suggestions mêlaient l'ancien numéro et l'ancien NPA ; une liste arrivée après la sortie du champ restait ouverte ; le message de NPA inconnu tenait sur cinq lignes ; un vide sous « Pays ». Tous corrigés. Contrôle de compatibilité : un premier essai avec `vendor` en lien symbolique chargeait le nouveau `src` sous les anciens tests (faux rouge) ; refait avec une copie, l'ancienne version passe.

## Preuves

Lot 1 (2026-11-13) :

```
$ vendor/bin/phpunit
OK (303 tests, 1473 assertions)
$ composer analyse
 [OK] No errors
$ scratchpad/mutations-18.sh
détectée : lignes d'une facture émise modifiables
détectée : facturer l'offre d'un autre
détectée : déduire un acompte en brouillon
détectée : supprimer une facture émise
détectée : numéro pris chez un autre artisan
fichier remis à l'identique
$ scratchpad/verif-lot1.sh
détectée : démo sans effacement des factures
Comptes.php remis
[notice] Migrating down to OffreGen\Migrations\Version20261112000008 ... [OK]
[notice] Migrating up to OffreGen\Migrations\Version20261113000001 ... [OK]
OK (24 tests, 103 assertions)
```

Lot 2 (2026-11-13) :

```
$ vendor/bin/phpunit
OK (316 tests, 1497 assertions)
$ vendor/bin/phpstan analyse
 [OK] No errors
$ node parcours-18-iban.js (390 px, démo remise à zéro avant)
  badge IBAN avant : true
  IBAN faux, état : « Pas enregistré : corrigez le champ signalé. » ; erreur : « Un IBAN suisse, par exemple CH93 0076 2011 6238 5295 7. »
  IBAN juste, état : « Enregistré » ; badge visible : false
  après rechargement : « CH93 0076 2011 6238 5295 7 »
  sommaire : Mon entreprise Peinture Morel Sàrl · Delémont ›
$ controle-texte.py
22 fichiers contrôlés, 0 en faute
```

Captures relues : `captures-18/180-iban-faux.png`, `181-iban-juste.png` (scratchpad).

Lot 3 (2026-11-13) :

```
$ vendor/bin/phpunit
OK (336 tests, 1845 assertions)
$ vendor/bin/phpstan analyse
 [OK] No errors
$ muter.py mutations-lot3.json
détectée : lignes d'une facture émise ouvertes par le contrôleur
détectée : facturer sans client confirmé
détectée : rang de l'acompte pris dans le formulaire
détectée : échéance avant la date acceptée
détectée : deuxième finale permise
détectée : montants figés ignorés
$ node parcours-18-factures.js (390 px, démo remise à zéro avant)
  onglets : Offres 78px / Clients 78px / Factures 78px / Catalogue 78px / Réglages 78px
  aperçu, boutons : Modifier / Facturer / PDF
  nouvelle facture : numéro « 2026-001 », échéance 2026-12-13, choix : Acompte 1 sur 3 (25 %) / Facture finale
  créée : /factures/1 ; message « Facture 2026-001 créée » ; état : Brouillon ; Total TTC CHF 776.70
  quantité 1 → 3 ; Total TTC CHF 2’330.10
  ajout : titre « Ajouter à la facture »
  après ajout : 2 lignes ; Total TTC CHF 2’432.80
  liste : 2026-001 · Famille Exemple Fribourg · 13.11.2026 · Rafraîchissement du séjour Brouillon 2’432.80
  offre, liens : Facture 2026-001 → /factures/1
  erreurs : aucune
$ controle-texte.py
45 fichiers contrôlés, 0 en faute
```

Captures relues : `captures-18/184-nouvelle-facture.png`, `186-facture-corrigee.png`, `187-liste-factures.png`, `188-offre-lien.png`.

Lots 4 et 5 (2026-11-13) :

```
$ vendor/bin/phpunit
OK (345 tests, 2004 assertions)
$ vendor/bin/phpstan analyse
 [OK] No errors
$ composer audit
No security vulnerability advisories found.
$ releve-documents.js avant et après la généralisation de _document (6 modèles et l'aperçu de l'offre)
identique hors blancs (7 sur 7)
$ muter.py mutations-lot5.json
détectée : émettre une facture avec une ligne sans prix
détectée : annuler un acompte déjà déduit par la finale
détectée : brouillon refait sans lien vers l'ancienne
détectée : PDF servi pour un brouillon (rendu à la volée)
$ node parcours-18-emission.js (390 px, démo remise à zéro avant et après)
  aperçu : Facture n° 2026-001 | Objet : Rafraîchissement du séjour · selon offre n° 2 du 11.11.2026 | alertes : Sans IBAN, la facture n'a pas de section de paiement
  confirmation : Émettre la facture 2026-001 pour Famille Exemple, CHF 3’106.80 à payer jusqu'au 13.12.2026 ?
  émise : message « Facture 2026-001 émise » ; boutons : Annuler la facture / PDF
  PDF : 200 application/pdf 53607 octets
  facture figée : champs 0 ; état Émise, payable jusqu'au 13.12.2026 ; barre : true
  refaite : /factures/5 ; « Facture 2026-001 annulée ; corrigez la nouvelle, puis émettez-la » ; Facture 2026-002 ; Brouillon
  liste : 2026-002 … Brouillon 3’106.80 | 2026-001 … Annulée 3’106.80
  erreurs : aucune
$ controle-texte.py
50 fichiers contrôlés, 0 en faute
```

Captures relues : `189-apercu-facture.png`, `190-emettre.png`, `193-annuler.png`, `195-liste.png`, et la page 1 du PDF émis rendue en image (`196-pdf-page1.png`).

Essai QR (`scratchpad/qr-essai2.php`, `scratchpad/decoder-qr.py`) :

```
violations : 0
version : 9, modules : 53
pdf : 12385 octets
taille : 46.1 mm
décodé : oui
SPC / 0200 / 1 / CH9300762011623852957 / K / Peinture Morel Sàrl / Rue de l'Exemple 12 / 2800 Delémont / CH / 3106.80 / CHF / NON / Facture n° 2026-001 / EPD
```

Maquette : `check.js` bureau 1440/1440 et téléphone 390/390, image 1, cassée 0 ; capture de la facture relue par OpenCV : « décodé : oui », même contenu ; aucune erreur JavaScript.

Non vérifié : lecture du code par une vraie application bancaire (à faire par Xavier) ; présence de bcmath chez Infomaniak.

Lot 6a, adresses (2026-11-13) :

```
$ vendor/bin/phpunit
OK (367 tests, 2222 assertions)
$ vendor/bin/phpstan analyse
 [OK] No errors
$ migration 10 sur dev : avant, montée, retour à la 9, remontée
artisan 2 avant  : "Rue de la Gare 4\n2800 Delémont"
artisan 2 après  : rue "Rue de la Gare", n° "4", NPA "2800", localité "Delémont", CH ; texte inchangé
retour à la 9    : colonnes retirées, texte identique ; test : même aller-retour, OK
$ contrôle de compatibilité : 5de18b4 (copie de vendor, src chargé depuis le worktree) sur la base de test en version 10
OK (345 tests, 2004 assertions)
$ muter.py mutations-adresses.json
détectée : NPA inconnu accepté
détectée : localité qui ne correspond pas acceptée telle quelle
détectée : fiche client : l'adresse d'avant effacée par un autre champ
détectée : réglages : l'adresse d'avant effacée par un autre champ
détectée : facture sans l'adresse du client en champs
détectée : registre sollicité sans limite
détectée : vérification : un autre numéro compte comme trouvé
$ node parcours-18-adresses.js (390 px, vrai registre swisstopo, adresses publiques, démo remise à zéro avant et après)
  suggestions : Rue de la Préfecture 1, 2800 Delémont / Ruelle de la Préfecture 1, 1964 Conthey / … / Rue de la Préfecture 2, 2800 Delémont / …
  choisie : Rue de la Préfecture | 2 | 2800 | Delémont | CH ; « Adresse trouvée au registre officiel des bâtiments. » ; « Enregistré »
  numéro 99 : « Adresse introuvable au registre officiel : vérifiez la rue et le numéro. Elle s'enregistre quand même. »
  NPA 2800 : localité « Delémont »
  NPA 2801 : « Ce NPA n'existe pas. » ; « Pas enregistré : corrigez le champ signalé. »
  fiche client, « Bundesplatz 3 » tapé dans la rue : Bundesplatz 3, 3011 Bern en tête ; choisie : Bundesplatz | 3 | 3011 | Bern
  fiche client, « Rue de la Préfecture 1 » tapé sans choisir : Rue de la Préfecture | 1 | 2800 | Delémont ; trouvée
  document : « Peinture Morel Sàrl Rue de la Gare 4 2800 Delémont … » ; « Delémont, le 11.11.2026 »
  erreurs : favicon.ico (connu) et le 422 voulu du NPA 2801
$ swisstopo, à la main : « Rue de la Gare 4 2800 » ne rend que la Place de la Gare : « introuvable » est juste
```

Captures relues : `captures-18/201-entreprise.png`, `202-suggestions.png`, `204-introuvable.png`, `206-npa-faux.png`, `207b-fiche-tapee.png`.
