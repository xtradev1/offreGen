---
chantier: De la voix à l'offre, 24 ergonomie au pouce
slug: 2026-11-07_voix-offre/24-ergonomie
phase: 4
statut: en_cours
gate_attendu: null
profil: .claude/atoz-profile.md
---

## Prochaine action

Lot 9 du `PLAN.md` (revue, `RECETTE.md`, démo remise à zéro), après le sous-chantier 25 : sa recette porterait sur des écrans de facturation qui changent (décision du 2026-11-15).

## En attente de toi

Rien : D10 acceptée (« D10 tu peux oui »), D9 réponse donnée, lots 1 à 9 validés (« oui tu peux tout faire je te valide les ajouts et modifs »).

## Suivi Origin

- Projet 44 offreGen, tâche 403 « Ergonomie au pouce, version PC et TVA (sous-chantier 24) ».
- Sous-tâches : 404 maquette et décisions, 405 navigation, 406 offre, 407 relecture, 408 ligne hors catalogue, 409 PDF.js, 410 envoyer et facturer, 411 TVA, 412 version PC, 413 revue et recette.
- Conversation rattachée à 404 le 2026-11-15.

## Décisions

- 2026-11-15, réponses de Xavier à la maquette (copiées telles quelles) : D1 micro au centre de la barre, sur tous les écrans : Valider (remplace la variante B du 2026-11-12) ; D2 Catalogue et Réglages rangés dans « Plus » : Valider ; D3 total fixé en bas et action principale qui change selon l'état : Valider ; D4 retirer une ligne : « A · corbeille gardée, 48 px, écartée du montant · fais A et B » (corbeille de 48 px et glisser vers la gauche) ; D5 relecture ligne par ligne : Valider ; D6 « Créer le produit » et « Rattacher », mot entendu ajouté aux synonymes : Valider ; D7 PDF dans l'application avec PDF.js : A ; D8 envoyer par le partage du téléphone : Valider, « ça veut dire quoi envoyer avec le téléphone ? ça envoie comment ? » ; D9 facturer dans une seule feuille : Valider, « si on veut changer la valeur de l'acompte ou de la facture finale, par exemple une réduction car il y a eu moins à faire que prévu ? » ; D10 : pas de réponse ; D11 écrans de la TVA : Valider ; D12 PC en trois colonnes : Valider ; D13 document à côté des lignes sur PC : Valider, « où est le micro sur PC ? car cela doit aussi fonctionner sur PC ! ».
- 2026-11-15 : micro sur PC : « Dicter une offre » en premier bouton du menu de gauche, « Importer un fichier » dessous, touche D ; l'enregistrement dans le navigateur marche déjà sur PC (sous-chantier 11), avec le micro du portable ou d'un casque. Écran PC de la maquette mis à jour.
- 2026-11-15 : TVA (Xavier) : un taux pour l'entreprise (8,1, 2,6, 3,8 ou 0 %), une ligne peut en avoir un autre ; le document détaille la TVA par taux dès qu'il y en a plusieurs.
- 2026-11-15, mise en œuvre de la TVA (décisions prises seul) : une ligne sans exception garde un taux nul (celui de son produit, sinon de l'entreprise) ; la facture finale copie le taux effectif de chaque ligne, produit compris, et le taux de l'entreprise se fige à l'émission comme avant ; un acompte sur une offre à plusieurs taux fait une ligne par taux (« …, prestations à 2,6 % »), un seul taux garde une seule ligne ; avec plusieurs taux, le document marque chaque ligne qui n'est pas au taux de l'entreprise (LTVA, art. 26) ; le choix du taux d'une ligne se cache quand l'entreprise n'est pas assujettie.
- 2026-11-15, version PC (décisions prises seul) : menu à gauche dès 1024 px ; la colonne des offres et le « Document à côté » à partir de 1280 px seulement (à 1024 px, la désignation n'aurait plus que 30 px) ; D12 et D13 se partagent la même place : le bouton « Document à côté » remplace la colonne des offres par le document, choix gardé dans le navigateur ; l'offre plafonnée à 1000 px de large ; touches : Entrée passe au même champ de la ligne suivante, J dit « c'est juste » (aussi depuis la quantité ou le prix), Suppr retire la ligne hors d'un champ, D ouvre la dictée hors d'un champ ; la recherche de la colonne filtre titre, client et numéro.

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

- 2026-11-15 : lot 6 fait. « Envoyer » (aperçu PDF de l'offre et d'une facture émise) : partage du téléphone, PDF joint ; partage réussi : offre notée envoyée (migration additive `offre.envoyee_le`), pastille « Envoyée le … », action principale « Facturer » ; partage annulé : rien ; sans partage : le PDF se télécharge, rien n'est noté. « Facturer » en un écran : pourcentage de l'acompte modifiable (montant précis ou remise : « Vérifier avant d'émettre » puis la ligne), « Émettre et envoyer » (créée, émise, PDF prêt à partager ; si l'émission est empêchée, le brouillon reste et la facture dit pourquoi) ou « Vérifier avant d'émettre ». L'émission passe par une seule méthode pour les deux chemins. Trouvé au parcours : champ du pourcentage visible pour une facture finale, corrigé ; le partage de Windows existe dans Chrome sans affichage et ne s'ouvre jamais (le test « sans partage » le retire).

- 2026-11-15 : lot 7 fait. Réglages : quatre taux (8,1 normal, 2,6 réduit, 3,8 hébergement, non assujetti). Fiche d'une ligne (offre et facture en brouillon) : « Taux de l'entreprise » ou « du produit », ou un des quatre taux. Fiche produit : taux dans la partie repliée. Offre, facture, document et PDF : une ligne « TVA x % sur … » par taux dès qu'il y en a deux, sinon la ligne d'avant ; HT et TTC suivent les taux réels. Migration additive `Version20261115000002` (colonnes nullables sur `ligne`, `facture_ligne`, `produit`). Trouvé au contrôle : le document ne disait pas quelle ligne portait l'autre taux (exigé par la LTVA), corrigé ; mon test attendait 810 sur une ligne de finale sans exception, alors que le taux nul est voulu (figé à l'émission), test corrigé.

- 2026-11-15 : lot 8 fait. Menu de gauche (même barre, rangée par le CSS : Offres, Clients, Factures, Catalogue, Réglages, « Dicter une offre » touche D, « Importer un fichier ») ; fenêtres au milieu de l'écran ; colonne des offres à côté de l'offre avec recherche ; « Document à côté » qui suit chaque correction sans recharger ; aide des touches sous les lignes. Au téléphone, rien ne change (contrôlé à 390 px). Trouvé aux captures : désignation écrasée lettre par lettre avec le document à côté (colonnes du PC trop larges), corrigé ; colonne des offres intenable à 1024 px, repoussée à 1280 px ; offre étirée sur 1350 px à 1920 px, plafonnée ; et un défaut du lot 2 : le total fixé en bas ne suivait pas une correction faite dans la ligne (téléphone compris), corrigé.

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

Lot 6, envoyer et facturer (2026-11-15) :

```
$ phpunit : OK (441 tests, 2655 assertions) ; phpstan : [OK] No errors ; migration jouée (test et développement)
$ node parcours-lot6.js (390 px, démo remise à zéro)
  sans partage : téléchargement « Offre-2.pdf » ; pastille « Prête » ; action « Générer l'offre »
  partage (simulé, réussi) : « Offre 2 envoyée » ; pastille « Envoyée le 15.11.2026 » ; action « Facturer »
  facturer : Acompte 1 sur 3 (30 %) / Facture finale ; pourcentage 30 ; Vérifier avant d'émettre 71 px / Émettre et envoyer 71 px
  émise : « Facture 2026-001 émise » ; 1 page ; partagée : Facture-2026-001.pdf application/pdf 62306 octets ; erreurs JS : aucune
```

Lot 7, TVA (2026-11-15) :

```
$ phpunit : OK (450 tests, 2703 assertions) ; phpstan : [OK] No errors ; migration jouée (test et développement)
$ node parcours-lot7.js (390 px, démo)
  réglages : 810* 260 380 0
  offre avant : Sous-total HT CHF 2'874.00 TVA 8,1 % CHF 232.79 Arrondi CHF 0.01 Total TTC CHF 3'106.80
  ligne : * 810 260 380 0 ; premier choix « Taux de l'entreprise (8,1 %) »
  offre après (ligne de 180.00 à 2,6 %) : TVA 8,1 % sur CHF 2'694.00 CHF 218.21 ; TVA 2,6 % sur CHF 180.00 CHF 4.68 ; Arrondi CHF 0.01 ; Total TTC CHF 3'096.90
  document : TVA 8,1 % sur 2'694.00 218.21 ; TVA 2,6 % sur 180.00 4.68 ; Total TTC CHF 3'096.90
  offre remise : Total TTC CHF 3'106.80 ; produit : * 810 260 380 0 ; erreurs JS : aucune
$ node parcours-lot7b.js : dernière ligne des totaux à 566 px, barre fixe à 655 px (rien de caché) ; PDF A4 vérifié à l'image
```

Lot 8, version PC (2026-11-15) :

```
$ phpunit : OK (453 tests, 2733 assertions) ; phpstan : [OK] No errors
$ node parcours-lot8.js (1280 px puis 390 px, démo locale)
  menu : x 0, largeur 232 ; entrées Offres / Clients / Dicter une offre / Factures / Catalogue / Réglages / Importer un fichier
  offre : liste x 232, largeur 340 ; offre x 572, largeur 708 ; courante « Rafraîchissement du séjour 3'106.80 Famille Exemple · 1 à vérifier »
  Entrée : de l575 à l576 (champ quantite)
  document à côté : x 742, largeur 538 ; liste visible : false ; bouton « Revenir à la liste »
  correction 180 → 280 : document « Total TTC CHF 3'106.80 » → « Total TTC CHF 3'214.90 » ; rechargée : document toujours à côté
  Suppr : 6 → 5 lignes ; bandeau « « Protection des sols et des meubles » supprimée Annuler » ; Annuler : 6 lignes
  J : ligne l576 encore à vérifier : 0 ; curseur dans l576
  D : feuille au milieu, 560 px de large
  tel : barre en bas ; entrées Offres / Clients / Dicter une offre / Factures / Plus ; liste et bouton document cachés ; largeur 390
  erreurs JS : aucune
$ node parcours-lot8b.js
  1024 px : désignation 356 px, liste non, bouton document non ; 1280 px : 272 px, liste oui ; 1920 px : 564 px, liste oui
  390 px : total fixé CHF 3'106.80 → CHF 3'214.90 après correction du prix, remis à CHF 3'106.80
```
