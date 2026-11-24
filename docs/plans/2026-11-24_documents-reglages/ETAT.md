---
chantier: Documents et réglages (LC-3 liste trop longue, OV-3 filigrane « Aperçu », quantité et unité des variantes)
slug: 2026-11-24_documents-reglages
phase: 8
statut: gate_ouvert
gate_attendu: prod (déploiement sur le test et recette de Xavier)
profil: .claude/atoz-profile.md
---

## Suivi Origin

- Projet 44 (offreGen). Aucune tâche « documents et réglages » au 2026-11-24 (relevé de toutes les tâches ouvertes du projet) : conversation rattachée au projet seul. Création de la tâche à demander à Xavier.

## Prochaine action

Déployé (2ddda06) et compte `documents-recette@exemple.ch` créé. Attendre la recette de Xavier (`RECETTE.md`) ; s'il remonte des défauts, retour en phase 5. Clôture : supprimer `offregen_documents_test`, `offregen_documents_vue`, le compte de relevé et la copie ; `/roadmap-sync` (les deux roadmaps, par ajouts ciblés).

## En attente de toi

- Dérouler `RECETTE.md` sur le test et dire ce qui casse.
- Dire s'il faut créer la tâche Origin du chantier (projet 44).
- Au choix : le résumé de la rubrique compte-t-il aussi la formule d'appel, le début et la fin (`REVUE.md`) ?

## Environnement

- Copie : `~/offreGen-documents`, worktree détaché sur `origin/chantier-voix-offre` (ff1ee21 à l'ouverture).
- Bases : `offregen_documents_test` (phpunit, par `.env.test.local`) et `offregen_documents_vue` (relevés, serveur local `php -S localhost:8095 -t public public/index.php`). Les deux à supprimer à la clôture.
- Compte de relevé : `documents-vue@exemple.ch` (démo Peintre, base `offregen_documents_vue` seulement), fichier `~/.offregen-documents-vue-compte`.
- Scripts de relevé : scratchpad de la session (`releve/*.js`), non versionnés.

## Constats sur le code (ff1ee21, vérifiés le 2026-11-24)

### Origine des trois demandes

- LC-3 et OV-3 sont des pas de `MAQUETTE-recette-generale.html` (copie `~/offreGen-recette`, non commitée, chantier de la mise en production). LC-3 : « Sur l'offre, change le texte avant les lignes, le texte après, la validité et le paiement. Ouvre le document en Sobre puis en Lettre. » OV-3 : « Facturer : Choix du client en haut ; coche l'option ; acompte 30 %, puis Facture finale. »
- LC-3 « la liste des règles » : deux candidats, non tranché par le dépôt.
  1. Ma marque, section « 4. Les phrases du document » (`templates/_reglages_marque.html.twig:70-119`) : formule d'appel, début et fin de l'offre et de la facture (quatre champs avec tournures), puis 14 phrases tirées de l'offre (9 pour l'offre, 5 pour la facture). Pour passer en Lettre, LC-3 fait passer par cet écran.
  2. Le bloc « Conditions » du document (`templates/document/_blocs.html.twig:256`) : en Sobre, trois puces longues (paiement et escompte, début et durée, validité et TVA) et la colonne « Non compris » ; en Lettre, un seul paragraphe de cinq lignes.

### OV-3, filigrane « Aperçu »

- Il ne marque que deux vues HTML : l'aperçu de la facture pas encore créée dans la feuille « Facturer » (`templates/_apercu_facture.html.twig:8`) et le document d'un brouillon de facture (`templates/facture_document.html.twig:17`). Aucun PDF ne le porte.
- Il ne marque pas les démos. Aucun document d'une démo ne porte de marque : facture 2026-001 créée par l'interface depuis la démo `documents-vue`, PDF lu par PyMuPDF : ni « Aperçu », ni « Démonstration », ni « exemple » (preuve ci-dessous).
- La marque « Démonstration » sur les PDF est décidée pour l'espace de démonstration des commerciaux (chantier commerciaux, Q1 ; Origin 557), non codée. Origin 509 : code QR de démonstration pour une facture de démo sans IBAN.
- Donc : retirer le filigrane n'ouvre rien de nouveau. Une démo sort déjà une facture sans marque.

### Variantes : quantité et unité

`LigneControleur::ajouter` (`src/Http/Controleur/LigneControleur.php:243-261`), `pageAjout` (ligne 217), `templates/ajout.html.twig:81-83, 106-112`. Relevé par l'interface, variante de « Rebouchage et ponçage des murs » (42 m², 9.00) sur l'offre 2 de la démo :

| Ajout | Résultat aujourd'hui |
|---|---|
| Produit de même unité (Peinture anti-moisissure, m²) | 42 m² |
| Produit d'une autre unité (Laquage de porte, pce) | 1 pce, 160.00, écart −218.00 |
| Ligne tapée dans la recherche (« Peinture minérale silicate ») | 42 **forfait**, prix manquant |
| Ligne libre détaillée, quantité vide, unité laissée | 42 **forfait** × 34 = 1'428.00, écart +1'050.00 |

- La quantité reprise n'est visible nulle part avant l'ajout (placeholder « 42 » dans la ligne libre détaillée seulement).
- L'unité par défaut de la ligne libre est « forfait » (`pageAjout`, ligne 217) : une ligne libre en variante donne « 42 forfait ». Défaut.
- La règle « autre unité : quantité 1 » vient de la revue du chantier options-variantes (C6 : « 42 m² ne font pas 42 rouleaux »).

## Journal

- 2026-11-24 : chantier ouvert sur la consigne de Xavier (« Maquette avant le code »). Copie, bases, compte de relevé créés. Relevé du code et parcours par l'interface en local. Phase 1 menée par les constats ci-dessus ; le gate intention et le gate visuel se tiennent ensemble sur la maquette (les trois sujets sont des retouches d'écrans existants, chaque choix y a sa capture). Phase 2 (décomposition) sautée : trois retouches indépendantes, un lot chacune au plan.
- 2026-11-24 : à la demande de Xavier, recette complète sur le test par Claude, avec sa démo `documents-claude@exemple.ch` (fichier `~/.offregen-test-documents-claude-compte`) : les quatre scénarios conformes, sans erreur JS.
- 2026-11-24 : accord de Xavier (« oui déploie, pas de vraies données »). Une autre session avait poussé 2ddda06 ; copie avancée sur la tête, déployée, sonde verte, compte de recette créé, contrôle en lecture seule sur le test.
- 2026-11-24 : réponses de Xavier à la maquette (L0 « tous le point 4 », L1 A, L2 non, O1 A, O2 hors chantier, V1 A, V2 A, V3 validé). PLAN.md, trois lots, tests écrits d'abord et vus rouges, code, suite complète verte, recette locale par l'interface, revue (six mineurs corrigés), RECETTE.md. Commit et push sur `chantier-voix-offre` ; déploiement en attente de l'accord de Xavier.
- 2026-11-24 : LC-3 relu : la section 4 de Ma marque (14 phrases et quatre champs, 53 % de l'écran) est la lecture la plus probable ; la question L0 reste posée. Maquette livrée, gate ouvert (intention et visuel). Rien n'est codé, commité ni déployé.

## Décisions

| Date | Décision | Raison |
|---|---|---|
| 2026-11-24 | Gates intention et visuel tenus ensemble, sur la maquette. | Trois retouches d'écrans existants ; Xavier a demandé la maquette avant le code. |
| 2026-11-24 | LC-3 : les deux lectures présentées, la question posée sous la capture. | Le dépôt ne dit pas de quelle liste parle Xavier. |
| 2026-11-24 | L0 : la liste est toute la section 4 de Ma marque. | Xavier : « tous le point 4 » |
| 2026-11-24 | L1 A : rubrique « Phrases du document » à part ; Ma marque garde logo, couleur, modèle. | Réponse de Xavier à la maquette |
| 2026-11-24 | L2 : l'assistant du premier lancement montre logo, couleur et modèle seulement. | idem |
| 2026-11-24 | O1 A : filigrane « Aperçu » retiré de Facturer et du brouillon ; la légende reste. | idem |
| 2026-11-24 | O2 : la marque des démos reste hors du chantier (Origin 557 et 509). | idem |
| 2026-11-24 | V1 A : variante = quantité et unité de la ligne remplacée, prix du produit ; produit d'une autre unité : la ligne passe dans « Relire ». | idem |
| 2026-11-24 | V2 A : champ « Quantité » sous « Elle remplace », prérempli, modifiable avant l'ajout. | idem |
| 2026-11-24 | V3 : ligne libre en variante, quantité et unité de la ligne remplacée préremplies. | idem |

## Artefacts

| Chemin | Phase | État |
|---|---|---|
| `SPEC.md` | 1 | à valider avec la maquette |
| `MAQUETTE-documents-reglages.html` | 3 | gate ouvert ; 15 images rendues sur les vraies pages locales, « après » retouchés à l'écran |

## Preuves

### Phase 5 et 6 : lots 1 à 3 (base offregen_documents_test, copie ~/offreGen-documents)

```
Avant le code (référence) : OK (1486 tests, 12326 assertions)
Tests écrits d'abord, rouges : FiligraneTest 2 sur 2 en échec ; PhrasesRubriqueTest Tests: 5, Failures: 5 ;
  VarianteQuantiteTest Tests: 8, Failures: 6 (les 2 verts décrivent l'existant gardé : même unité, ajout hors variante)
Lot 1 : FiligraneTest + EnvoyerFacturerTest : OK (13 tests, 109 assertions)
Lot 2 : PhrasesRubriqueTest OK (5 tests, 33 assertions) ; PhrasesLettreEcranTest OK (5, 56) ; ActiviteReglageTest OK (7, 28) ;
  ReglagesRangesTest OK (9, 103) ; MarqueEcranTest OK (9, 151)
Lot 3 : VarianteQuantiteTest + OptionsGestesTest + OptionsEcranTest : OK (26 tests, 145 assertions)
node --check app.js, fluide.js : JS OK
Fin : phpstan [OK] No errors ; OK (1501 tests, 12422 assertions)
```
Tests existants modifiés, et pourquoi : `RevueGestesTest::testUneVarianteDuCatalogueDAutreUnite…` (C6 remplacée par V1 A) ; `PhrasesLettreEcranTest`, `ActiviteReglageTest` (adresse `/reglages/phrases`) ; `ReglagesRangesTest` (« sur 5 complètes »).

### Recette locale par l'interface (Playwright, 390 px, JavaScript actif, serveur 8095, base offregen_documents_vue)

```
sommaire : Mon entreprise … / Ma marque Sans logo · modèle Sobre / Phrases du document Dans Lettre seulement / Conditions… / TVA…
Ma marque : hauteur 1793 , section phrases : 0
rubrique : titre "Réglages\nPhrases du document" , phrases 14
état affiché : Réécrite ; enregistrement : "Enregistré"
résumé après : Phrases du document Dans Lettre seulement · 1 phrase réécrite
après rechargement : Réécrite "Offre valable jusqu'au [date]. Bon pour un mois."
Facturer : filigranes 0 , légende "Aperçu : rien n'est encore créé. Touchez la facture pour la voir en grand."
quantité proposée : 42 m²
après frappe : caché "30" , mention "30 m², à relire" , libre détaillée "30"
libre détaillée : quantité "30" , unité m²
Variante Peinture anti-moisissure | 42 m² | … Prix manquant
Variante Laquage de porte, 2 faces | 30 m² | … m² 4'800.00 +4'422.00 … À vérifier C'est juste
Variante Peinture silicate recette | 30 m² | … Prix manquant
Variante Peinture lessivable recette | 30 m² | … 1'020.00 +642.00
Relire montre Laquage : true
erreurs JS []
```
Captures regardées : rubrique, page d'ajout (feuille sur l'offre), Facturer, lignes de l'offre.

### Phase 7 : revue et corrections

```
Relecteur Opus : 6 trouvailles mineures, toutes relues dans le code (REVUE.md)
Tests de la revue sur l'ancien code : Tests: 5, Assertions: 11, Failures: 5 (puis fichiers restaurés)
Après corrections : node --check JS OK ; phpstan [OK] No errors ; OK (1506 tests, 12437 assertions)
Parcours rejoué : illisible : mention "42 m², à relire" , caché "42" ; après frappe : caché "30" , mention "30 m², à relire" ;
  Laquage 30 m² À vérifier ; silicate 30 m² ; lessivable 30 m² 1'020.00 ; Relire montre Laquage : true ; erreurs JS []
Fichiers touchés : aucun BOM, aucun tiret long, aucune fin de ligne CR (23 fichiers)
``` Non vérifié : la ligne provisoire hors réseau (quantité et hors du total) ; le chemin sans JavaScript du bouton « OK » de la quantité (couvert par le test serveur `?quantite=30`, pas cliqué) ; « Envoyer » quand une ligne est à vérifier (décision fragile n° 2) ; le site de test.

### Relevé des variantes (Playwright, serveur local 8095, base `offregen_documents_vue`)

```
peut remplacer : [["4","Protection des sols et des meubles"],["5","Rebouchage et ponçage des murs"],["6","Peinture des murs, 2 couches"],["7","Peinture des plafonds, 2 couches"],["8","Laquage des plinthes"],["9","Déplacement"]]
base choisie : Rebouchage et ponçage des murs
ajout 1 : « Peinture anti-moisissure » ajoutée
détail : quantité placeholder = 42 , unité = forfait
après :
  Rebouchage et ponçage des murs m² 378.00
  Variante Peinture anti-moisissure m² Retenue par le client Prix manquant
  Variante Laquage de porte, 2 faces pce 160.00 −218.00 Retenue par le client
  Variante Peinture minérale silicate forfait Retenue par le client Pas dans votre catalogue Ajoutez-le pour la prochaine dictée Prix manquant
  Variante Peinture lessivable forfait 1’428.00 +1’050.00 Retenue par le client Pas dans votre catalogue Ajoutez-le pour la prochaine dictée
erreurs JS []
```

### Déploiement sur le test (accord de Xavier, 2026-11-24 : « oui déploie, pas de vraies données »)

```
copie avancée sur la tête ; HEAD = tête : 2ddda06 (contient ce510c5, ce chantier)
LU     /sante : {"commit":"2ddda06df48cc127c687b2f37930f9a7f29d38f5","base":"ok","migrations":"ok"}
SONDE VERTE
DÉPLOYÉ : 2ddda06df48cc127c687b2f37930f9a7f29d38f5 sur https://easy-offres.xtradev.ch
Compte créé : documents-recette@exemple.ch (démo, 10 imports par jour), catalogue Peintre
```
Contrôle en lecture seule sur le test (une connexion, Playwright 390 px) :
```
sommaire : Mon entreprise à compléter | Ma marque | Phrases du document | Conditions de l'offre | TVA et arrondi
Ma marque : hauteur 1793 , phrases 0
rubrique : phrases 14
assistant marque : phrases 0 , modèles 12
offre du séjour : /offres/91
Facturer : filigranes 0 , légende 1
ajout variante : quantité "42" m² , à relire 5 , libre "42" m²
erreurs JS []
```
Capture de la page d'ajout regardée.

### Recette complète sur le test par Claude (demande de Xavier, compte documents-claude@exemple.ch, démo inventée)

Playwright, 390 px, JavaScript actif, gestes enregistrés sur la démo de Claude seulement.
```
S2 assistant : phrases 0 , formule d'appel 0 , modèles 12
S1.1 sommaire : … | Ma marque Sans logo · modèle Marge › | Phrases du document Dans Lettre seulement › | … | 4 rubriques sur 5 complètes
S1.2 Ma marque : hauteur 1793 , phrases 0
S1.3 état : Réécrite , enregistrement : "Enregistré"
S1.4 résumé : Phrases du document Dans Lettre seulement · 1 phrase réécrite ›
S1.5 après retour : "Offre valable jusqu'au [date]. Bon pour un mois."
S3.1 Facturer : filigranes 0 , légende "Aperçu : rien n'est encore créé. Touchez la facture pour la voir en grand."
S3.2 en grand : ouvert 1 , filigranes 0 , texte APERÇU dans la facture 0
S3.3 brouillon : /factures/21 ; aperçu du brouillon : titre "Brouillon Aperçu du brouillon" , filigranes 0
S4.2 quantité : "42" m² | reprise de la ligne remplacée, à changer si besoin
S4.4 mentions : 30 m², à relire (×5)
S4.6 ligne libre : quantité "30" , unité m²
S4.7 Variante Peinture anti-moisissure | 42 m² | … Prix manquant
S4.7 Variante Laquage de porte, 2 faces | 30 m² | … m² 4'800.00 +4'422.00 … À vérifier C'est juste
S4.7 Variante Peinture silicate | 30 m² | … Prix manquant
S4.7 Variante Peinture lessivable | 30 m² | … 1'020.00 +642.00
bouton principal visible : "Relire 3 lignes CHF 3'106.80 -> /offres/93/relire" ; après : /offres/93/relire , Laquage affiché 1
« C'est juste » sur la ligne Laquage de l'offre : Laquage encore à vérifier 0 ; bouton principal "Relire 2 lignes CHF 3'106.80"
erreurs JS []
```
Décision fragile n° 2 levée : avec une ligne à vérifier, le bouton principal de l'offre mène à « Relire », pas à « Envoyer ». Un premier essai de « C'est juste » dans « Relire » a touché la mauvaise ligne (Relire montre une ligne à la fois, la première était la variante sans prix) : défaut du script, pas de l'application. Captures regardées : brouillon, page d'ajout, Relire, offre après « C'est juste ». Non vérifié : la ligne provisoire hors réseau.

### Mesures de Ma marque (Playwright, 390 px, DOM retouché sans JavaScript, rien enregistré)

```
mesure Ma marque 390 px {"total":4165,"debut4":1722.78125,"haut4":2212,"phrases":14}
Ma marque sans section 4 1938
Ma marque, variante B 3338
```

### Contrôle de la maquette

```
bureau : largeur 1440/1440, images 15, cassées 0, kit oui
telephone : largeur 390/390, images 15, cassées 0, kit oui
OK.
```
Captures de chaque section regardées au PC et au téléphone ; corrigé avant livraison : débord de 5 px au téléphone (règle `max-width: 360px` reprise d'une ancienne maquette), échelles différentes entre l'avant et l'après de Ma marque, document en Lettre capturé sans JavaScript (déformé), barre d'action posée au milieu des captures pleine page.

### Facture d'une démo (créée par « Créer la facture », PDF lu par PyMuPDF)

```
créée : http://localhost:8095/factures/1/apercu
1 page(s)
Facture n° 2026-001 : Façade de la maison
Acompte 1 sur 2 (30 %), selon offre n° 1 ... Total TTC CHF 2'549.00
Aperçu False
APERÇU False
émo False
xemple False
Démonstration False
```
