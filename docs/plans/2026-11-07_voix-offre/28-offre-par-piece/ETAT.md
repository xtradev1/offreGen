---
chantier: De la voix à l'offre, 28 offre par pièce
slug: 2026-11-07_voix-offre/28-offre-par-piece
phase: 8
statut: gate_ouvert
gate_attendu: prod
profil: .claude/atoz-profile.md
---

## Prochaine action

Recette de Xavier sur le site de test : dicter pièce par pièce, puis ajouter, renommer, déplacer (voir Journal).

## En attente de toi

- Sur le site de test, compte de démonstration : dicter une offre pièce par pièce, vérifier le rangement, ajouter une ligne dans une pièce, renommer une pièce, déplacer une ligne depuis sa fiche, ouvrir le PDF.

## Suivi Origin

- Projet 44 offreGen, tâche 442 « Offre par pièce (sous-chantier 28) », 8 h.
- Sous-tâches : 443 maquette et décisions, 444 extraction et stockage, 445 offre et PDF groupés, 446 revue et recette.

## Décisions

- 2026-11-17, Xavier : « il parlait en pièces […] Il faudrait voir si l'IA est capable de prendre ça aussi en compte […] qu'il mette les éléments dans la pièce » ; « Oui, tu peux faire une maquette d'abord pour le sous-chantier offre par pièce ».
- 2026-11-17, Xavier, maquette 28 : P1 A (par pièce quand la dictée en donne, sinon par catégorie) ; P2 A (« Général » en tête) ; P3 valider (sous-total par pièce dans le PDF, dès deux lignes) ; P4 valider (l'ajout demande la pièce, dernière utilisée par défaut, « Autre pièce ») ; P5 valider (renommer depuis le titre, déplacer depuis la fiche). Gate visuel franchi.

## Journal

- 2026-11-17 : faisabilité mesurée (détail dans `26-couts-ia/ETAT.md`) : champ « piece » par ligne et une phrase de consigne ; pièce juste sur 100 % des lignes justes (27/27 à 10 min, 88/88 à 30 min, 85/85 avec la pièce annoncée une seule fois). Dictées inventées : à remesurer sur le corpus réel du sous-chantier 29. Aujourd'hui l'offre groupe ses lignes par catégorie du catalogue.
- Le cadrage (section 5) prévoit déjà la pièce dans l'extraction optimisée (sous-chantier 20).

- 2026-11-17 : maquette faite (`MAQUETTE-pieces.html`) : offre par pièce avant / après, PDF avec sous-total par pièce, ajout d'une ligne avec choix de la pièce, renommer et déplacer. Décisions P1 à P5. Contrôle `check.js` vert (bureau 1440, téléphone 390), captures relues.

- 2026-11-17, lots 1 à 4 faits. Mesure décisive : demander la pièce dans l'appel principal faisait perdre des prix dictés sur la dictée de Luigi (champ obligatoire : 6, 5, 5, 4, 5 sur 7 ; facultatif : 6, 4, 4, 4, 1 ; autre formulation : 4 cinq fois ; sans pièce : 7, 7, 7, 7, 6), alors que le banc inventé ne bougeait presque pas. Choix de Claude : un second appel, sans le catalogue, range les lignes déjà trouvées ; l'extraction reste celle qui a été mesurée ; si ce second appel échoue, l'offre sort sans pièces. Cadrage respecté (deux appels au plus). Circuit complet mesuré : banc inventé 10 min 28 sur 30, 30 min 87 sur 90, pièce annoncée une fois 87 sur 90, pièce juste sur 100 % des lignes justes ; dictée de Luigi 7 sur 7 deux fois sur trois, la troisième met 4 lignes dans « Général » au lieu de « Séjour » (le rangement est corrigeable à la main). Choix de Claude : pas de sous-total pour « Général » dans le PDF (« Total général » se confondrait avec le total de l'offre) ; il garde son sous-total à l'écran, comme dans la maquette.
- 2026-11-17 : la page d'ajout propose les pièces par des liens (la pièce part en champ caché avec chaque ajout) : elle marche sans JavaScript, chaque produit étant son propre formulaire.
- Point ouvert : une facture faite depuis une offre rangée par pièce reste rangée par catégorie (les lignes de facture n'ont pas de pièce).

- 2026-11-17, question de Xavier : coût de la seconde passe, et un modèle moins cher ? Mesuré (`scratchpad/pieces-modeles.php`, seconde passe seule, mêmes lignes pour tous, 3 passes) : dictée inventée de 30 min, Qwen 27B 85/85 trois fois, 10 012 + 1 449 jetons, CHF 0.0084, 6 à 9 s ; Gemma 4 31B 85/85 trois fois, CHF 0.0025, 26 à 61 s ; Ministral 14B 69/85 et un dépassement de délai ; Mistral Small 4, 3 à 7/85. Dictée de Luigi : Qwen 7/7 trois fois (CHF 0.0007) ; Gemma 7/7 deux fois, 4/7 une fois (« Mur » pris pour une pièce) ; Ministral 7/7 trois fois ; Mistral Small 1 à 6/7. Qwen reste en service : le plus sûr et le plus rapide ; l'écart de coût est de 0,6 ct par offre de 30 min. `EXTRACTION_MODELE_PIECES` permet d'en changer sans code. Seconde passe avec Qwen : environ un tiers du coût de l'extraction (0,8 ct sur 2,4 ct pour 30 min).

## Preuves

```
$ phpunit : OK (508 tests, 3120 assertions) ; phpstan : No errors
$ php banc-longues.php 30 --implicites --complet : justes 87 (97 %) ; pièce juste sur les lignes justes : 87 sur 87 (100 %)
$ php banc-longues.php 10 --complet : justes 28 (93 %) ; pièce 28 sur 28 ; 30 --complet : justes 87 (97 %) ; pièce 87 sur 87
$ php luigi-complet.php 3 : passes 1 et 3, 7 lignes, prix dictés relevés, toutes en « Séjour » ; passe 2, évacuation sans prix, 4 lignes en « Général »
$ node recette-pieces.js (serveur local, téléphone 390 et PC 1440) :
PC, groupes : Général 180.00 | Salon 1’406.00 Renommer | Chambre des parents 1’184.00 Renommer | Salle de bain 342.00 Renommer
PC, document : Général | Salon | Total salon | Chambre des parents | Total chambre des parents | Salle de bain
2. ajout, pièce choisie : Salle de bain ; après « Salon » : Salon
3. après ajout : Salon 1’418.00 ; 4. après renommage : Chambre 1’184.00 ; 5. après déplacement : Chambre 1’526.00, plus de Salle de bain
erreurs JS : aucune
```
