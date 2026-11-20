---
chantier: Modèles d'offre du bâtiment
slug: 2026-11-19_modeles-offre
phase: 8
statut: gate_ouvert
gate_attendu: prod
profil: .claude/atoz-profile.md
---

## Suivi Origin

- Projet 44 (offreGen), tâche 488 (Douze modèles d'offre pour le bâtiment), créée le 2026-11-20 à la demande de Xavier.
- Sous-tâches : 489 analyse et maquettes ; 490 essai dompdf ; 491 plan ; 492 lots 0 à 2 ; 493 lots 3 et 4 ; 494 lot 5 ; 495 lot 6 ; 496 lot 7 ; 497 lot 8 ; 498 revue et recette.
- Tâche 499 : options et variantes, à cadrer dans son propre chantier (décision O6).
- Conversation rattachée à 498 (revue et recette) le 2026-11-20, après 492 à 497.

## Prochaine action

Recette de Xavier sur le site de test (`RECETTE.md`, commit `7bd9348`). Corrections éventuelles dans la copie propre `~/offreGen-modeles`, rejouée sur la tête de la branche avant chaque poussée. À la clôture : `/roadmap-sync`, avec les deux lignes à reporter au `PROJECT_ROADMAP.md` (polices du document et leur source ; dossier de polices de dompdf sous `var/cache/pdf`), suppression de la base `offregen_modeles_test` et de la copie `~/offreGen-modeles`.

## En attente de toi

- Dérouler `RECETTE.md` sur le site de test (`https://easy-offres.xtradev.ch`, commit `7bd9348`) et dire ce qui casse ou ce qui déplaît. Les comptes de démonstration ouvrent encore l'assistant du premier lancement : le finir ou le passer d'abord.
- Regarder `rendus/douze-modeles.pdf` (24 pages : les douze modèles, une offre longue, trois factures, trois cas limites).
- Trancher quatre points : la facture d'une page sans pied ; la couleur de l'artisan à 4,5 de contraste (7 la foncerait) ; le lieu dicté pris pour l'adresse du chantier ; les phrases fixes de Lettre.
- Savoir que `~/offreGen` garde, non commités, les fichiers de ce chantier mêlés à ceux du chantier responsive : la session du responsive devra, comme celle-ci, commiter depuis une copie propre posée sur la tête de la branche.

## Journal

- 2026-11-19 : demande de Xavier : une analyse des six modèles notés sur 10, « monter jusqu'à 10 modèles, 10-15 […] on peut dire 12 », pourquoi ceux sous 8 n'y sont pas, des propositions, et une maquette de « beaux modèles […] principalement là pour du bâtiment ». Pas de SPEC.md : il a demandé l'analyse et la maquette d'emblée.
- 2026-11-19 : notes de Claude sur les rendus de l'application : Technique 6,5 ; Bandeau 6 ; Sobre 5,5 ; Atelier 5,5 ; Moderne 5 ; Épuré 4. Six défauts communs : police par défaut, rien sur le chantier, pas de « non compris », une seule signature, ni page ni IBAN en pied, adresse jamais mesurée contre une fenêtre.
- 2026-11-19 : `MAQUETTE-modeles.html` (avant / après des six, douze modèles), puis réponses de Xavier : Technique « Valider · attention au bordure foncé le total ttc il fut que le haut soit aussi foncé » ; Bandeau et Atelier « Valider » ; Sobre « Valider · total en noir/anthracite car sinon le contraste peut être mauvais » ; Moderne « À retravailler · total ttc trop gros ça fais gros point noir changer ce petit aspect » ; Épuré « À retravailler · total un peut trop grand » ; C1 à C6 « Valider » ; Lettre, Latéral, Récapitulatif, Chantier « Garder » ; Métré, En bloc « Écarter » ; nombre « Douze · ajoute la possibilité d'avoir des options et variantes a prendre en compte dans la dicté. » ; modèle selon le métier et quatre familles « Valider ».
- 2026-11-19 : `MAQUETTE-modeles-v2.html` : corrections, totaux retouchés, Enseigne et Marge, options et variantes.
- 2026-11-20 : réponses de Xavier sur la reprise : Moderne et Épuré retouchés « Valider » ; Enseigne et Marge « Garder » ; O1, O2, O3 « Valider » ; O4 sans choix, « il faut que quand on ajoute un produit ont puisse juste dire option ou variante en un clic » ; O5 « Valider · l'offre est mis à jour, l'artisan peut la renvoyer et en généré une offre ou un acompte en un clicl » ; O6 « Les modèles d'abord, les options ensuite ». Gate visuel franchi.
- 2026-11-20 : essai par dompdf 3.1.6 (`essai-pdf/`, lancé par `essai.php`, feuilles sorties par `exporter.js`) : douze modèles, une offre avec options, quatre offres de quarante lignes. Résultat dans `essai-pdf/essai-complet.pdf` (24 pages). Marge redessiné sans cellule fusionnée.
- 2026-11-20 : Xavier, sur le PDF de l'essai : « tu peux créer la tache origin, la le problème c'est que les lettres sont pas centré a corriger. c'est bien le 1. O4 oui, O5 oui 3 ok ont peut utiliser le nom officiel de l'entreprise et on regarde dans un premier temps si ça joue. go pour le plan ».
- 2026-11-20 : lettres du Récapitulatif corrigées : la lettre passe dans une cellule de tableau (cinq variantes essayées par dompdf, mesure dans Preuves). Les douze pages relues : le bloc de signature touche le pied dans Chantier, Atelier et l'offre avec options ; réglage laissé aux lots 5 et 6, avec un test.
- 2026-11-20 : trois sondes pour le plan : dompdf donne la position des blocs par le rappel `end_frame` ; les polices en sous-ensemble latin (celles de l'essai, et Inter du lot « 9c ») n'ont ni š, č, ć, đ, ž, ł, ř, ğ, ş ; la feuille se réduit à l'écran par le mécanisme des vignettes.
- 2026-11-20 : `PLAN.md` écrit : contrat technique, signatures, trois décisions fragiles, dix lots. Phase 2 (décomposition) sautée : un seul chantier, les lots du plan suffisent.
- 2026-11-20 : Xavier : « oui c'est ça, pars des fichiers tels quels, go lot 0 à 9 ». Le préalable du plan est confirmé ; je pars des fichiers du lot « 9c » non commités. Suite de départ : 659 tests verts.
- 2026-11-20 : lot 0 fait (traceur Latéral). `templates/_document.html.twig` devient un aiguillage ; les six modèles d'avant passent dans `templates/document/_six.html.twig`, leurs classes `doc-*` renommées `anc-*` (gabarit et feuille de style) pour que les règles de la nouvelle feuille ne les touchent pas ; `Marque::FEUILLES` liste les modèles redessinés. Nouveaux : `document/_base.html.twig`, `_blocs.html.twig`, `lateral.html.twig`, `public/css/document-ecran.css` (ce que dompdf ne doit pas lire), `DocumentPdf::blocs()` et `rendreEtCompter()`, filtre Twig `iban`, `papiers()` dans `app.js`.
- 2026-11-20 : lot 1 fait (polices). 23 TTF complets pris au dépôt de chaque éditeur (Roboto, Archivo, Barlow Condensed, IBM Plex Sans et Mono, Source Serif 4), 46 WOFF2 par sous-ensemble (Fontsource) pour l'écran, relevé `public/fonts/SOURCES.md` avec empreintes. `archivo-700.ttf` du lot « 9c » (sous-ensemble latin, 40 Ko) est remplacé par le fichier complet (192 Ko). Inter reste déclarée tant que les six modèles d'avant existent.
- 2026-11-20 : piège trouvé au lot 1 : dompdf copiait les polices déclarées dans `vendor/dompdf/dompdf/lib/fonts`, par adresse et sans regarder le contenu. Le poste gardait donc les polices de l'essai et du lot « 9c », et un test « vert » lisait ces copies. `DocumentPdf` range maintenant ces copies dans `var/cache/pdf/polices-<empreinte des tailles>` (`setFontDir`), et le test rend avec un dossier neuf.
- 2026-11-20 : lot 2 fait (socle commun et Sobre). `document/sobre.html.twig` ; macros `tete`, `infos`, `ref` ; `tests/Outils/FactureDEssai.php` et `TextePdf.php`. Trois défauts trouvés en regardant les rendus et corrigés : les totaux débordaient à droite (`.doc table { width: 100% }` l'emportait : un tableau à largeur propre s'écrit `table.doc-x`) ; l'interligne du PDF était 18 % trop haut (voir Décisions) ; l'adresse du client montait quand l'entreprise a peu de coordonnées (`.doc-haut`, hauteur minimale de 79 px).
- 2026-11-20 : lot 2, factures. La géométrie de la section de paiement suit la feuille (pas de marge de côté, 46 px en haut). Une facture d'une page perd son pied : la section en prend la place, et le nom et le numéro IDE passent sous les conditions (`.doc-mentions`). Mesuré : la section tient au bas de la page 1 jusqu'à trois lignes en Sobre, quatre en Latéral ; au-delà elle a sa page.
- 2026-11-20 : lot 3 fait (migration `Version20261120120001`, huit colonnes facultatives). Jouée en dév (montée, descente, remontée) et sur la base de test. Pas encore sur le site de test ni en production.
- 2026-11-20 : lot 4 fait (chantier, début, durée, non compris, visite, contact). `src/Offre/Chantier.php`, `DepotOffres::modifierChantier`, champs sur l'en-tête de l'offre, « Personne de contact » dans « Mon entreprise », `Reglages::$contactNom`. Le lieu dicté devient l'adresse du chantier à la création de l'offre. Une facture ne montre pas le chantier.
- 2026-11-20 : une autre session (`~/offreGen-accueil`) lance phpunit sur la même base de test : deux de mes séries ont échoué par collision (« Duplicate entry 'a@exemple.ch' »). Mes scripts attendent maintenant qu'aucun `php vendor/bin/phpunit` ne tourne.
- 2026-11-20 : lot 5 fait (Bandeau, Atelier, Technique, Moderne, Épuré sur la feuille A4). `templates/document/_six.html.twig`, les règles `anc-*`, `Marque::FEUILLES` et les faces d'Inter sortent ; `inter-400.ttf` et `inter-700.ttf` (lot « 9c », plus aucun appelant : grep sur `src`, `templates`, `public`, `tests`) sont effacés. Les tests du document passent en fournisseur de données : cinq tests par modèle.
- 2026-11-20 : lot 5, C6 pris à la lettre : l'adresse du client à 182 px du haut et 446 px du bord gauche dans tous les modèles (48 et 118 mm). Sur la maquette, elle variait de 145 à 193 px selon le modèle ; Sobre, Bandeau, Technique, Moderne et Épuré descendent donc de 12 à 37 px. Le test la tient à 6 points près.
- 2026-11-20 : lot 6 fait (Lettre, Enseigne, Récapitulatif, Chantier, Marge). Filtre Twig `date_longue`, macros `meta`, `lettre`, `lignes_marge`, `accord` ; `Marque::corpsEnseigne()` ; `Marque::MODELES` dans l'ordre de l'écran. Lettre tient six lignes sur une page (huit passent sur deux) ; Chantier, sans rien de dit sur le chantier, montre une fiche « Références ».
- 2026-11-20 : lot 7 fait (écran « Ma marque »). `Marque::FAMILLES`, `Marque::pourMetier()`, quatre familles à l'écran, étiquette « Proposé pour votre métier », modèle réglé à la création d'un compte qui a un métier. Les vingt-quatre images de repli (`public/img/modeles/`, petites et grandes) sont tirées de la page 1 du vrai PDF. Défaut vu sur les captures et corrigé : la règle `.modele img` d'`app.css` étirait le logo dans les vignettes.
- 2026-11-20 : lot 8 fait (les douze en facture). `tests/Offre/GabaritsFactureTest.php` : cinq tests par modèle. Onze modèles gardent la section de paiement au bas de la page pour une facture d'une ligne ; Lettre la porte toujours sur une page à part (formule d'appel, phrases et signature ne laissent pas la place). Limites mesurées : deux lignes en Sobre et en Bandeau, trois dans les autres, quatre en Latéral.
- 2026-11-20 : lot 9 fait (cas limites). `tests/Offre/GabaritsCasLimitesTest.php` : logo haut et logo large, couleur claire, nom de 42 caractères, compte et offre sans aucun champ facultatif, offre par pièce, pour chaque modèle. Deux logos d'essai inventés dans `tests/fixtures/images/`. Corrigé au passage : l'expéditeur de Lettre passait à la ligne et descendait l'adresse ; le cartouche de Bandeau est borné à 280 px.
- 2026-11-20 : phase 6 (vérification). Sondes du skill `recette-visuelle` sur « Ma marque » (clair et sombre), l'en-tête de l'offre, « Mon entreprise » et la feuille du document : aucun défaut de disposition ; aucun défaut de contraste sur les écrans de l'application. Dans la feuille : le gris des libellés (6,26 calculé, sous 7) est foncé en #4b525b (7,9) ; un titre de poste de Chantier tombait à 4,49 sur fond teinté, `Marque::surTeinte()` le remonte à 5,12. `Marque::version()` suit maintenant le contenu de la feuille de couleur (le navigateur la garde un jour).
- 2026-11-20 : la base de test est partagée avec une session qui lance sa suite en boucle : mes suites complètes s'y faisaient percuter (échecs différents à chaque passe). Base à part créée sur le poste, `offregen_modeles_test`, avec les droits de `offregen_test` ; les migrations y sont jouées. Un test (`ConteneurTest`) lit la variable `DB_NOM` que cette base impose : il se lance sans elle.
- 2026-11-20 : phase 7 (revue). Trois relecteurs indépendants sur Opus, en lecture seule : correction, sécurité, régression. Aucun bloquant ; trois majeurs, tous retenus : la classe `doc-cote` de la colonne de Latéral déformait le bouton « Document à côté » ; « 1 décembre » au lieu de « 1er » dans Lettre ; un montant sur la ligne « Général » d'une offre par pièce. Dix mineurs retenus, détail et verdicts dans `REVUE.md`. Corrections faites test d'abord (21 tests rouges, puis verts).
- 2026-11-20 : après la revue : classes `doc-colonne`, `doc-u`, `doc-pos` ; un test interdit à une classe du document de porter le nom d'une classe de l'application ; verrou sur le premier rendu de dompdf ; `Marque` valide son modèle ; à l'écran, la feuille d'une facture s'arrête à son contenu et la section de paiement la suit ; `reinitialiserDemo` règle le modèle du métier. Images de repli refaites.
- 2026-11-20 : phase 8. `RECETTE.md` écrit (dix scénarios), `rendus/douze-modeles.pdf` assemblé. Gate de recette ouvert. Rien n'est commité ni déployé. Reste sur le poste : la base `offregen_modeles_test` (à supprimer à la clôture, en root : `DROP DATABASE offregen_modeles_test`), le serveur local du port 8017 arrêté.
- 2026-11-20 : Xavier : « Tip top, tu vas pouvoir commit et puis push le tout sur le test. » La copie `~/offreGen` porte aussi le chantier responsive, jamais commité (facture annulée, vignettes vivantes de « Ma marque », barre du bas, unités), et dix-sept commits de retard sur la branche. Décision de Claude : commiter ce chantier seul, depuis une copie propre (`~/offreGen-modeles`) posée sur la tête de `origin/chantier-voix-offre` (08fc4df). Dix-neuf fichiers portés par leur diff entier, sans conflit ; sept repris à la main avec les seuls changements de ce chantier (`app.css`, `app.js`, `FactureControleur`, `ReglagesControleur`, `_reglages_marque`, `facturer`, `MarqueEcranTest`) ; le reste est neuf. Du lot « 9c », seul reste le troisième paramètre de `DocumentPdf` dans `Conteneur`.
- 2026-11-20 : ce que le commit seul change par rapport à ce que j'avais regardé jusque-là : « Ma marque » montre les images de repli (tirées du vrai PDF, dans la couleur de l'exemple), les vignettes vivantes restent au chantier responsive ; le PDF d'une facture annulée reste celui de l'émission. `RECETTE.md` corrigé (préalables, scénarios 1, 2 et 9). Cette combinaison n'avait jamais tourné : suite, analyse et écrans repassés dans la copie propre.
- 2026-11-20 : commit `7bd9348` poussé sur `chantier-voix-offre`, rejoué une fois (la branche avait avancé d'un commit de l'accueil, sans fichier commun). Défaut trouvé au commit : git normalisait les fins de ligne de `public/fonts/OFL-IBMPlex.txt`, dont l'empreinte est dans `SOURCES.md` ; le test des empreintes aurait échoué sur toute copie fraîche. `.gitattributes` : `*.ttf binary` et `public/fonts/*.txt -text` ; les 73 fichiers du relevé sont identiques dans le commit.
- 2026-11-20 : déployé sur le site de test par `outils/deployer-test.sh` depuis `~/offreGen-modeles` ; les tests du script tournent sur `offregen_modeles_test`, désignée par le `.env.test.local` de cette copie. Sonde verte. Contrôle sur le site avec `demo-macon`, une connexion, rien de tapé : douze modèles en quatre familles, « Bandeau » proposé ; document d'une offre à 390 et à 1280 px ; PDF d'une page avec Roboto et Archivo embarquées, aucune police de repli. Le dossier `var/cache/pdf` du serveur accepte donc l'écriture. Les démos du test ouvrent encore l'assistant du premier lancement : l'accueil renvoie à `/bienvenue/entreprise`, une offre se trouve par la fiche d'un client.
- 2026-11-20 : vu sur les captures du site de test, laissé en l'état : sans adresse dans les réglages, le nom de l'entreprise paraît deux fois dans l'en-tête de Sobre (à la place du logo et en tête des coordonnées) ; à 390 px, l'étiquette « Proposé pour votre métier » tient sur trois lignes.

## Décisions

| Date | Décision | Raison |
|---|---|---|
| 2026-11-19 | Douze modèles, quatre familles, un modèle proposé selon le métier du compte. | Réponses de Xavier. |
| 2026-11-19 | Polices intégrées, bloc chantier, « non compris », deux signatures, pied avec IBAN et page, adresse à la même place. | C1 à C6 validés. |
| 2026-11-20 | Les douze : Sobre, Lettre, Atelier ; Bandeau, Moderne, Latéral, Enseigne ; Technique, Récapitulatif, Chantier ; Épuré, Marge. Métré et En bloc écartés. | Réponses de Xavier. |
| 2026-11-20 | Options sous le total avec cases à cocher et total avec toutes les options ; variante sous la ligne remplacée avec l'écart de prix ; mots de la dictée ; options cochées avant de facturer. Les modèles d'abord, les options ensuite. | O1, O2, O3, O5, O6. |
| 2026-11-20 | À l'ajout d'un produit, un toucher « Option » ou « Variante » ; la variante remplace la dernière ligne du même poste, modifiable. Après les options cochées, l'artisan renvoie l'offre et crée une facture ou un acompte en un toucher. | « O4 oui, O5 oui ». |
| 2026-11-20 | Enseigne prend le nom officiel de l'entreprise, sans champ de nom court. | « on regarde dans un premier temps si ça joue ». |
| 2026-11-20 | Montants dans une police à chiffres de même largeur (Roboto, IBM Plex, Source Serif 4). | Mesure de dompdf ci-dessous. |
| 2026-11-20 | Polices du document en fichiers complets, pas en sous-ensemble latin. | Noms de clients à caractères d'Europe centrale et de Turquie. |
| 2026-11-20 | « Page n / N » : le numéro par le compteur CSS de dompdf (`counter(page)`), le total par un second rendu quand le document dépasse une page (`OffreControleur::rendrePdf`). Remplace `page_text` du plan. | La place et la couleur du numéro restent dans la feuille de style de chaque modèle (blanc dans la bande de Bandeau), sans géométrie en PHP. |
| 2026-11-20 | Les classes de couleur de `Marque::css()` portent `!important`, et valent aussi sous `.pdf`. | La règle d'un modèle, plus précise, pose un filet sans couleur et l'emportait ; dans le PDF, pied, bande et colonne sortent de `.doc`. |
| 2026-11-20 | À l'écran, la réduction se fait dans le papier (`.paper > [data-feuille]`, `papiers()` d'`app.js`), pas autour. | Les vignettes, le modèle en grand, le document à côté et l'aperçu des factures gardent leurs règles ; un observateur suit les papiers qui arrivent plus tard. |
| 2026-11-20 | Pas de balise `p` dans la feuille ; règles communes en graisses 400 et 700 seulement. | La marge d'office d'un `p` l'emportait sur celle du modèle ; une graisse sans face (Roboto 600) retombe sur DejaVu Sans. |
| 2026-11-20 | Polices de l'écran en WOFF2 par sous-ensemble, celles du PDF en TTF complets. | 23 TTF complets pèsent 5,7 Mo : trop pour l'écran « Ma marque » d'un téléphone, qui montre les douze modèles. |
| 2026-11-20 | À reporter au `PROJECT_ROADMAP.md` (modifié sans commit par une autre session, je n'y touche pas) : polices du document et leur source ; dossier de polices de dompdf sous `var/cache/pdf`. | Règle du projet : jamais le roadmap modifié par une autre session. |
| 2026-11-20 | Interlignes du PDF : ceux de l'écran divisés par un facteur propre à chaque police (Roboto 1,451 ; Archivo 1,197 ; Barlow Condensed 1,320 ; IBM Plex 1,430 ; Source Serif 4 1,508), dans un bloc à part de `document.css`. Remplace le constat 3 de l'essai (« 1,22 pour 1,45 »), qui valait pour les sous-ensembles latins. | dompdf donne à une ligne la hauteur « interligne x corps x 1,1 x hauteur de la police » ; les fichiers complets sont plus hauts que les sous-ensembles. Un test mesure ces facteurs. |
| 2026-11-20 | Facture d'une page : pas de pied, la section de paiement à sa place ; nom et IDE sous les conditions. Décision de Claude, à valider par Xavier. | Avec le pied, une facture d'une ligne ne tenait plus sur une page avec sa section ; l'IBAN est dans la section, le numéro de page n'apporte rien sur une page. |
| 2026-11-20 | Une facture va sans le titre « Conditions » ; sa référence se dit « selon offre n° … du … » dans une case « Référence ». | Une facture n'a rien à accepter ; les tests existants lisent cette phrase. |
| 2026-11-20 | L'adresse du client se pose à hauteur fixe dans chaque modèle (en-tête à hauteur minimale). | C6 : la fenêtre de l'enveloppe ; sinon l'adresse suit le nombre de lignes des coordonnées. |
| 2026-11-20 | Hors périmètre, à traiter ailleurs : à l'écran sur PC, la feuille est agrandie à la largeur de sa colonne (échelle 1,24 à 1280 px). | Constaté sur les captures ; lisible, pas un défaut de ce chantier. |
| 2026-11-20 | L'adresse du client à la même place dans les douze modèles : 182 px du haut, 446 px du bord gauche (83 px pour Lettre, fenêtre à gauche). | C6 validé par Xavier ; la maquette ne la tenait pas (145 à 193 px). |
| 2026-11-20 | Lettre : « Madame, Monsieur, », la visite citée si elle est datée, les conditions en phrases (« Début des travaux : … Durée : … Paiement : … »), formule de politesse fixe. Textes à relire par Xavier. | Les champs sont du texte libre : une phrase rédigée (« débuter semaine 3 ») sonnerait faux. |
| 2026-11-20 | Enseigne ignore le logo et écrit le nom officiel ; sous le nom, la localité (le métier du compte n'est pas un texte à montrer au client). | « on peut utiliser le nom officiel de l'entreprise et on regarde dans un premier temps si ça joue ». |
| 2026-11-20 | Un compte de métier « autre » n'a pas de modèle marqué « Proposé pour votre métier ». | Sobre n'est pas proposé pour un métier que l'application ne connaît pas. |
| 2026-11-20 | Limite connue de Marge : quand un poste passe d'une page à l'autre, son nom reste sur la première. | Sans cellule fusionnée (constat 6 de l'essai), le nom ne se répète pas ; à reprendre si la recette le demande. |
| 2026-11-20 | Lettre : la facture porte toujours sa section de paiement sur une page à part. | Mesuré : une facture d'une ligne finit à 591 points pour une limite de 530 ; la raccourcir assez la rendrait fragile. |
| 2026-11-20 | Gris des textes secondaires du document : #4b525b au lieu de #5a616b (maquette). | Sonde de contraste : 6,26 sous le seuil de 7 pour des libellés de 9 px ; 7,9 après. |
| 2026-11-20 | Hors périmètre, à trancher par Xavier : la couleur de l'artisan employée en texte tient 4,5 sur le blanc (`Marque::CONTRASTE_MIN`, décision du 2026-11-12), sous le seuil de 7 du skill `recette-visuelle`. Mesuré : 5,13 pour #2E7D32. | La porter à 7 fonce la couleur de marque de tous les artisans sur leurs documents. |
| 2026-11-20 | Hors périmètre, à traiter ailleurs : `app.css`, `app.js` et `document.css` sont servis sans numéro de version ; après une mise en ligne, un navigateur peut garder l'ancienne feuille quelque temps. | Vaut pour tout le projet, pas pour ce chantier ; la recette le dit. |
| 2026-11-20 | Une classe de `document.css` commence par `doc-` et ne porte le nom d'aucune classe de l'application (test `ReglesDuDocumentTest`). | `document.css` se charge dans les écrans de l'application ; `doc-cote` et `u` y ont fait deux collisions. |
| 2026-11-20 | Sur une offre par pièce, « Général » n'a pas de montant sur sa ligne de titre ni dans la marge ; il le garde dans la liste de Récapitulatif. | Sous-chantier 28 : il se lirait comme le total de l'offre ; la liste de Récapitulatif doit tomber juste. |
| 2026-11-20 | Une facture ne montre plus l'IBAN au pied ni dans la colonne. | Celui de sa section de paiement est figé à l'émission ; le pied lisait les réglages du jour. |
| 2026-11-20 | Le chantier est commité seul, sans les changements non commités du chantier responsive qui partageaient sa copie de travail. | Chaque session commite son chantier ; le gate du responsive (accord de Xavier pour déployer) n'est pas celui de ce chantier. Interprétation de Claude du mot « le tout » : tout ce chantier. |

## Ce que l'essai dompdf a établi

1. Rendu fidèle à la maquette : les douze modèles tiennent sur une page, 0,1 à 0,2 s et 49 à 74 Ko par PDF, polices réduites aux caractères employés.
2. Chaque graisse employée doit avoir sa face déclarée : une graisse 600 sans fichier retombe sur DejaVu Sans Bold.
3. L'interligne est plus haut que dans le navigateur : 1,22 dans le PDF pour 1,45 à l'écran avec Roboto, 1,1 avec IBM Plex. dompdf met tout le supplément d'interligne au-dessus du texte : une hauteur de ligne ne centre pas un texte dans une case.
4. `box-sizing` est ignoré : une hauteur se donne hors marges intérieures.
5. Un élément fixe (pied, bande du bas, colonne de Latéral) se répète sur chaque page seulement s'il est enfant direct de `body`, et si la feuille n'a pas de fond qui le recouvre.
6. Une cellule fusionnée qui passe d'une page à l'autre décale les colonnes : Marge se dessine sans fusion.
7. L'en-tête du tableau se répète d'office ; « Page n / N » se pose par `page_text` après le rendu ; les totaux et le bloc de fin restent entiers avec `page-break-inside: avoid`.
8. Largeurs mesurées par dompdf, à 10 pt : Inter « 1111 » 16,28 pt et « 0000 » 25,24 pt ; Roboto 22,48 et 22,48 ; IBM Plex 24,00 et 24,00 ; Source Serif 4 21,16 et 21,16.
9. Le rappel `end_frame` de dompdf donne la page et la position de chaque bloc : un test peut refuser un bloc qui touche le pied.

Reste à régler dans les lots : le numéro de page passe sous la bande du bas de Bandeau en offre longue ; un logo large est coupé dans la colonne de Latéral ; le bloc de signature touche le pied dans Chantier, Atelier et l'offre avec options ; au saut de page de Marge, le nom de la pièce reste sur la page d'avant. Sans preuve : les factures avec la section QR, l'enveloppe à fenêtre, un logo réel, une couleur claire.

## Preuves

```
rendre.js : 16 feuilles, toutes « TIENT », aucune table trop large
check.js (v2) : bureau 1440/1440, téléphone 390/390, images 22, cassées 0, kit oui ; parcours cliqué sans erreur JS
essai.php : dompdf, sous-ensemble des polices : oui ; dpi 96
  12 modèles et sobre-options : 1 page chacun ; sobre-long, bandeau-long, lateral-long : 3 pages ; marge-long : 2 pages
  Inter 1111 = 16.28 pt, 0000 = 25.24 pt ; Roboto 22.48 et 22.48 ; IBM Plex Sans 24.00 et 24.00 ; Source Serif 4 21.16 et 21.16
recap-crop.py, lettres du Récapitulatif dans le PDF : 4 carrés de 15.0 x 15.0 pt ; A, B, C, D : blanc au-dessus 4.7, en dessous 4.5, écart horizontal 0.0
  avant la correction (lettres.py, variante a) : blanc au-dessus 8.6, en dessous 0.6
sonde.php : sobre : fin de 665 à 804 pt, pied de 820 à 838 pt ; chantier : fin de 697 à 824 pt, pied de 820 à 838 pt (le bloc de fin passe sous le haut du pied)
  roboto-400-normal.ttf 229 caractères ; manquent : š č ć đ ž ł ř ğ ş ; inter-400.ttf 230 caractères, mêmes manques
lot 0, vendor/bin/phpunit : OK (663 tests, 5240 assertions) ; vendor/bin/phpstan analyse : [OK] No errors
  voir.py lateral-40 : page 1/3 ['Page 1 / 3'], page 2/3 ['Page 2 / 3'], page 3/3 ['Page 3 / 3'] ; images des pages 1 à 3 regardées (colonne sur chaque page, en-tête du tableau répété, filet du total dans la couleur)
  ecran.js lateral-390 : 200, papier 358x506, feuille 358x506, échelle 0.4508, débordement 0 ; aucune erreur JS ni réponse en erreur
lot 1, phpunit --filter PolicesDocumentTest : OK (6 tests, 392 assertions), cache de polices vidé avant
  voir.py lateral-8 : polices Archivo-Bold, Archivo-ExtraBold, Roboto-Bold, Roboto-Regular ; grep -c Inter sur le PDF : 0
  voir.py noms (Kovačević & Çelik Sàrl, « Façade Dvořák, Łódź, İstanbul, Muğla, Şişli ») : mêmes quatre polices, image regardée, tous les caractères dessinés
  avant la correction (dossier de polices propre, Roboto 600 demandé) : polices Roboto, Archivo, DejaVuSans : le test échouait
lot 2, mesure-blocs.php sobre 1 essai (facture d'une ligne, section en bas) : 1 page ; adresse de 170 à 240 px ; fin de 561+29 px ; limite du contenu 706 px
  mesure-facture.php : sobre 1, 2, 3 lignes : 1 page (fin à 484, 506, 527 pt, limite 530 pt), 4 lignes : 2 pages ; lateral 1 à 4 lignes : 1 page, 6 lignes : 2 pages ; adresse à 128 pt (Sobre) et 136 pt (Latéral)
  images regardées : sobre-8, sobre-40 (3 pages), f-sobre-1 (section au bas de la page 1), f-sobre-41 (section sur sa page, consigne à 187 mm), f-lateral-3, écran sobre-1280
  sonde-interligne.php, Roboto 12 px : interligne 1 donne 17.41 px, 1.22 donne 21.24 px, 1.45 donne 25.25 px (l'écran donne 17.4 px à 1.45)
lot 3, migrer.sh : dév « Successfully migrated », descente : 0 colonne chantier_%, remontée : 3 ; « Up-to-date! » en dév et en test ; ColonnesChantierTest rouge avant, vert après
lot 4, phpunit --filter ChantierTest : 5 échecs avant le code, puis OK (13 tests, 95 assertions)
  vendor/bin/phpunit : OK (686 tests, 5783 assertions) ; phpstan : une erreur dans ChantierTest (accès « ?-> » inutile), corrigée, à relancer avec le lot 5
  images regardées : sobre-ch et lateral-ch (chantier, contact, non compris, signature) ; écrans entete-390 et document-390, aucune erreur JS
lot 5, phpunit --filter 'GabaritsPdfTest|PolicesDocumentTest' : OK (45 tests, 658 assertions) du premier coup pour sept modèles
  images regardées : bandeau-ch, atelier-ch, technique-ch, moderne-ch, epure-ch, bandeau-40 (bande et « Page 2 / 3 » sur chaque page), f-atelier-2 (section au bas de la page 1)
  défaut vu et corrigé : la bande du bas de Bandeau sortait sous le bord de la page (hauteur non dite)
  vendor/bin/phpunit : OK (718 tests, 5978 assertions)
lot 6, mesure-offre.php, 8 lignes et chantier rempli : onze modèles sur 1 page, jeu de 48 à 133 pt au-dessus du pied ; adresse à 136 ou 138 pt, à gauche 334 pt (Lettre : 139 et 62 pt) ; Lettre : 6 lignes 1 page, 8 lignes 2 pages
  phpunit --filter 'GabaritsPdfTest|MarqueTest|MarqueEcranTest' : OK (79 tests, 504 assertions)
  images regardées : lettre-ch, enseigne-ch, recap-ch (lettres centrées), chantier-ch, chantier-nu (fiche « Références »), marge-40 page 2 (colonnes en place)
  vendor/bin/phpunit : OK (749 tests, 6178 assertions) ; phpstan : [OK] No errors
lot 7, quatre tests rouges avant le code ; puis phpunit --filter 'MarqueTest|MarqueEcranTest|CreationCompteTest|ReglagesRangesTest' : OK (32 tests, 329 assertions)
  ecran.js marque-390 et marque-1280 : 200, 13 feuilles, débordement 0, aucune erreur JS ; capture regardée (quatre familles, douze vignettes, logo aux bonnes proportions)
  vendor/bin/phpunit : OK (753 tests, 6210 assertions)
lot 8, mesure-facture.php, une ligne : 1 page en onze modèles (fin de 440 à 501 pt, limite 530), Lettre 2 pages ; trois lignes : 1 page sauf Sobre, Bandeau, Lettre
  phpunit --filter GabaritsFactureTest : OK (60 tests, 417 assertions) ; section à 529,33 pt au bas de la page (187 mm moins 0,75 pt de trait, comme avant le chantier), à 530,08 pt sur sa page
  images regardées : f-lettre-1, f-lateral-30 page 3 (colonne au-dessus de la section), f-marge-ann (tampon « Annulée »), f-atelier-2, f-sobre-1, f-sobre-41 page 3
lot 9, phpunit --filter GabaritsCasLimitesTest : 4 échecs au premier passage (expéditeur de Lettre, et trois motifs trop larges de mon test), puis OK (60 tests, 858 assertions)
  planches regardées : planche-a (logos large et haut, compte sans rien de facultatif), planche-b (nom de 42 caractères, couleur claire #F5E663 assombrie, offre par pièce)
phase 6, sondes.js : disposition « debordements 0, textesCoupes 0, chevauchements 0, rangeesDesalignees 0, scrollHorizontalDeLaPage false » sur marque (clair, sombre), entete, entreprise, document, apercu-lateral, apercu-recap, apercu-chantier
  contraste : entete 0, entreprise 0 ; marque 40, tous dans les vignettes du document (couleur de l'artisan 5,13 ; libellés 5,48 avant correction) ; apercu-chantier : td.c-teinte 4,49 avant, 5,12 après
  DB_NOM=offregen_modeles_test vendor/bin/phpunit : Tests: 875, Assertions: 7515, Failures: 1 (ConteneurTest, qui lit DB_NOM) ; vendor/bin/phpunit --filter ConteneurTest sans la variable : OK (7 tests, 14 assertions)
  vendor/bin/phpstan analyse : [OK] No errors
phase 7, 21 tests rouges avant les corrections (ReglesDuDocumentTest, ChantierTest, GabaritsCasLimitesTest, GabaritsFactureTest, CreationCompteTest), puis phpunit --filter 'ReglesDuDocumentTest|ChantierTest|Gabarits|CreationCompteTest|MarqueTest|MarqueEcranTest|PolicesDocumentTest|DemoTest|EnTeteChantierTest' : OK (247 tests, 2883 assertions)
  doc-a-cote.js (1400 px) : bouton avant [1238,230,134,48] position static ; après ouverture [663,230,129,48] position static, dans l'en-tête ; feuille de la colonne 555x785, échelle 0.699 ; aucune erreur JS ; capture regardée
  images regardées : f-bandeau-30 page 3 (rien ne dépasse de la section), facturer-1300 (feuille et section visibles dans l'aperçu réduit), planche-c (noms de 60 caractères), planche-douze-a et planche-douze-b (les douze, état final)
  DB_NOM=offregen_modeles_test vendor/bin/phpunit : Tests: 878, Assertions: 7787, Failures: 1 (ConteneurTest, qui lit DB_NOM) ; vendor/bin/phpunit --filter ConteneurTest sans la variable : OK (7 tests, 14 assertions)
  vendor/bin/phpstan analyse : [OK] No errors
phase 8, commit : copie propre ~/offreGen-modeles sur 08fc4df ; git apply --3way de 19 fichiers : « Applied patch … cleanly » pour chacun, aucun marqueur de conflit
  vendor/bin/phpstan analyse : [OK] No errors
  APP_ENV=test vendor/bin/phpunit (base offregen_modeles_test recréée, migrations jouées : Up-to-date) : OK (912 tests, 8080 assertions)
  ecran.js sur la copie propre : marque-390, marque-1280, modele-recap-390, document-390, document-1280, facturer-1300, entete-390, offre-1400 : 200 partout, débordement 0 ; seule erreur : 404 du logo (le dossier des logos n'est pas dans cette copie) ; cinq captures regardées
  doc-a-cote.js (1400 px) : bouton [1238,226,134,40] puis [663,226,129,40], position static ; feuille de la colonne 555x785, échelle 0.699 ; aucune erreur JS
  sondes de disposition (marque 390 et 1280, document 390, facturer 1300, en-tête 390) : 0 débordement, 0 chevauchement, 0 texte coupé
phase 8, déploiement : git push : 47b4f3d..7bd9348  HEAD -> chantier-voix-offre
  outils/deployer-test.sh : OK (912 tests, 8080 assertions) ; analyse statique : sans erreur ; 1 migrations executed, 2 sql queries ; SONDE VERTE ; /sante : {"commit":"7bd9348b88e5cc069209a921ae6850003b1922aa","base":"ok","migrations":"ok"}
  test-site.js macon : marque {modeles 12, familles Classiques, Couleur, Détaillés, Simples, choisi sobre, propose bandeau, images 12, debordement 0} ; document 390 : doc m-sobre, feuille 358x506, police Roboto ; document 1280 : feuille 984x1392 ; pdf : 200 application/pdf ; aucune erreur JS ni réponse en erreur
  t-pdf.py : 1 page ; polices : Roboto-Regular, Archivo-ExtraBold, Roboto-Bold ; polices de repli : aucune ; trois images regardées (page 1 du PDF, document à 390 px, « Ma marque » à 390 px)
```
