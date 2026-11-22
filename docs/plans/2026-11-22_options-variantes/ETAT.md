---
chantier: Options et variantes dans l'offre
slug: 2026-11-22_options-variantes
phase: 4
statut: en_cours
gate_attendu: null
profil: .claude/atoz-profile.md
---

## Suivi Origin

- Projet 44 (offreGen), tâche 499 (Options et variantes dans l'offre). Origin n'ajoute pas de sous-tâches à une tâche existante : tâche 547 « Options et variantes, suite : les lots du chantier » créée le 2026-11-22 à la demande de Xavier, 26 h.
- Sous-tâches : 548 spec et maquette ; 549 banc et corpus écrit ; 550 données, totaux, facturation ; 551 écran ; 552 douze modèles ; 553 extraction ; 554 revue et recette.
- Le banc touche la tâche 447 (sous-chantier 29), sous-tâche 450 (banc sur le corpus).
- Conversation rattachée à 499, puis à 548 le 2026-11-22.

## Prochaine action

Phase 4 : `PLAN.md` (contrat technique de bout en bout : colonnes de `ligne`, `Totaux`, facturation, écran, douze modèles, extraction, banc).

## En attente de toi


## Journal

- 2026-11-22 : chantier ouvert à la demande de Xavier (« Options et variantes (tâche Origin 499) »), décisions O1 à O6 et réponses du 22.11 reprises. Copie à part `~/offreGen-options`, détachée sur `origin/chantier-voix-offre` (8385e78), `.env` et `.env.test.local` copiés de `~/offreGen`, `composer install` fait. Base de test à soi : pas encore créée (au premier test).
- 2026-11-22 : phase 0 sautée : profil et `CODING_RULES.md` présents et lus.
- 2026-11-22 : relevé du code (agent Explore, puis contrôlé à la main pour le banc et la consigne) : aucune nature de ligne en base ; `Totaux` compte toutes les lignes ; consigne sans option ; aucune commande vocale ; `outils/banc-corpus.php` absent, `OffreGen/corpus/audio/` vide (vérifié par `ls`). Xavier veut que la dictée passe par le banc du 29 : il est à écrire dans ce chantier.
- 2026-11-22 : `SPEC.md` écrit, gate intention ouvert.
- 2026-11-22 : Xavier : « 1 oui, 2 ok, 3 oui, 4 oui, 5 crée-les ». Gate intention franchi ; le périmètre et le hors périmètre ne sont pas contestés. Tâche 547 et sous-tâches 548 à 554 créées.
- 2026-11-22 : phase 2 (décomposition) sautée : un seul chantier, découpé en lots dans `PLAN.md`.
- 2026-11-22 : phase 3. `MAQUETTE-options-variantes.html`, prototype bâti sur celui des lignes (v3) : téléphone, document Sobre, factures, marche à suivre de dix étapes, cinq choix d'écran (E1 à E5). Défauts vus sur mes captures et corrigés avant de la montrer : la ligne de base remplacée reparaissait sur le document (contraire au point 2) ; la liste « Choix du client » de « Facturer » passait un mot par ligne (une règle `.champ input` étirait les cases) ; l'étape 6 de la marche se défaisait à l'étape 10 ; un raccourci rangeait « porte » d'office dans Chambre (retiré, l'ajout va dans Divers comme dans la v3). Gate visuel ouvert.
- 2026-11-22 : réponses de Xavier : « E1 : A, E2 : A, E3 : Valider, E4 : A, E5 : Valider », sans remarque. Gate visuel franchi. « ensuite tu peux commit et push » : SPEC, ETAT et maquette commités et poussés sur `chantier-voix-offre`.

## Décisions

| Date | Décision | Raison |
|---|---|---|
| 2026-11-22 | Le banc du sous-chantier 29 s'écrit dans ce chantier, selon la SPEC du 29, avec la nature de la ligne en plus. | Xavier : « La dictée passe par le banc du sous-chantier 29 » ; le banc n'existe pas. |
| 2026-11-22 | La façon d'extraire (champ dans l'appel principal ou second appel) se choisit au banc. | Sous-chantier 28 : un champ de plus dans l'appel principal a fait perdre des prix dictés. |
| 2026-11-22 | Interprétation du 22.11 : le rabais en % s'applique aussi aux options dans « Total avec toutes les options » ; le montant de chaque option se lit avant rabais, comme celui d'une ligne. | « l'option est comprise dans le rabais si c'est pourcentage, c'est sur tout, sinon le rabais est chiffré ». À corriger s'il voulait le montant de l'option déjà rabattu. |
| 2026-11-22 | Corpus : une quinzaine de dictées écrites d'abord (six sans option), puis cinq à huit vraies dictées de Xavier avant la mise en service sur le test. | Point 1 : « oui ». |
| 2026-11-22 | Après le choix : option retenue dans les lignes sans mention, variante retenue à la place de sa ligne de base, le non-retenu dans les blocs, case vide. | Point 2 : « ok ». |
| 2026-11-22 | Plusieurs variantes d'une même ligne, une seule retenue. | Point 3 : « oui ». |
| 2026-11-22 | « Total avec toutes les options » : les options seules, sans les variantes. | Point 4 : « oui ». |
| 2026-11-22 | Le choix du client se coche sur l'offre, et les mêmes cases sont reprises dans « Facturer ». | E1 A. |
| 2026-11-22 | À l'écran, les options forment un groupe « Options » sous le total, comme sur le document. | E2 A. |
| 2026-11-22 | À l'ajout et dans la fenêtre d'une ligne : « Dans le total · Option · Variante » ; pour une variante, la ligne remplacée (dernière du groupe, proposée) ; « + Variante » depuis une ligne. Une ligne qui a des variantes reste dans le total. | E3 Valider. |
| 2026-11-22 | Le montant d'une option est celui de la ligne, avant rabais ; le rabais en % se déduit dans « Total avec toutes les options ». | E4 A (confirme l'interprétation du 22.11). |
| 2026-11-22 | À l'écran, une variante retenue laisse sa ligne de base visible, montant barré ; le document ne montre que la variante. | E5 Valider. |

## Artefacts

| Chemin | Phase | État |
|---|---|---|
| `SPEC.md` | 1 | validé le 2026-11-22 |
| `MAQUETTE-options-variantes.html` | 3 | validée le 2026-11-22 (E1 à E5) |

## Preuves

### Phase 1

```
$ ls outils/ bin/
bin/: administrateur creer-compte metiers-saisis offres-essai passer-en-essai purger-audio reinitialiser-demo traiter-file verifier-ia
outils/: compte-essai-test.sh deployer-test.sh ia-locale localites.php
$ ls -la ".../OffreGen/corpus/audio"
total 0 (dossier vide)
```

### Phase 3

```
$ python inject_kit.py MAQUETTE-options-variantes.html && node check.js MAQUETTE-options-variantes.html cap
bureau : largeur 1440/1440, images 0, cassées 0, kit oui
telephone : largeur 390/390, images 0, cassées 0, kit oui
OK.
$ node parcours.js 1400   (scratchpad, dix étapes au clic)
apres etape 1 | 1 sur 10 faites
apres etape 2 | 2 sur 10 faites
titre ajout variante : Variante de « Peinture des plafonds » | base proposee : 3
apres etape 3 | 4 sur 10 faites
totaux ecran : Sous-total 2'964.00 Rabais fidélité 5 % -148.20 Total HT 2'815.80 TVA 8,1 % 228.08 Arrondi 0.02 Total TTC CHF 3'043.90
  Total avec toutes les options CHF 3'882.90
apres etape 5 | 5 sur 10 faites
bandeau visible : true
apres etape 6 | 7 sur 10 faites
totaux ecran apres choix : Sous-total 3'636.00 Rabais fidélité 5 % -181.80 Total HT 3'454.20 TVA 8,1 % 279.79 Arrondi 0.01 Total TTC CHF 3'734.00
bandeau apres renvoi : false
acompte : Acompte de 30 %, selon offre n° 42 (CHF 3'734.00) 1'120.20
finale (portes décochées dans Facturer) : Sous-total 3'216.00 Rabais fidélité 5 % -160.80 ... Total TTC CHF 3'302.65 Acompte, facture 2026-031 -1'120.20 À payer CHF 2'182.45
apres etape 10 | 10 sur 10 faites
largeur page 1400/1400
erreurs JS : aucune
$ node parcours.js 390
apres etape 10 | 10 sur 10 faites
largeur page 390/390
erreurs JS : aucune
Recalcul à la main : 1176+768+840+60+120 = 2964 ; options 312+85+420 : 3781 - 5 % = 3591.95, TVA 290.95, 3882.90 ; après le choix 2964-1176+1428+420 = 3636 ; acompte 3734.00 x 30 % = 1120.20 ; finale 3302.65 - 1120.20 = 2182.45.
Tirets longs : 0 ; insécables : 56 ; BOM : non.
Captures regardées : bureau, doc-propose-1400, tel-choix-1400, doc-choix-390, tel-facturer-390 et 1400 (après correction), doc-finale-1400.
```
