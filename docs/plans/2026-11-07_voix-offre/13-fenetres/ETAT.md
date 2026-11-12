---
chantier: De la voix à l'offre, 13 fenêtres qui montent
slug: 2026-11-07_voix-offre/13-fenetres
phase: 8
statut: gate_ouvert
gate_attendu: prod
profil: .claude/atoz-profile.md
---

## Prochaine action

Recette de Xavier dans le navigateur. Ensuite : commit et PR sur son accord (sous-chantiers 01, 10, 11, 12 et 13).

## En attente de toi

- Recette : bouton rond « + » de l'accueil, fiche produit (nouveau, modifier, supprimer) par-dessus la liste, bouton retour.
- Dire si « il faut que je voie des offres » visait bien les offres d'exemple du compte de démonstration.

## Journal

- 2026-11-12 : retour de Xavier : « quand je vais rajouter un nouveau produit [...] je veux juste que ça apparaisse en petit, en bas, comme si ça se levait » ; « quand je veux modifier un produit, je reste dans la liste des produits » ; « l'offre, il faut que je voie des offres déjà » ; « nouvelle offre, un petit bouton rond, on clique, et là, on peut créer cette offre », avec une animation.
- 2026-11-12 : phase 3 (maquette) sautée : Xavier recette directement dans l'application, comme pour 11 et 12.
- 2026-11-12 : « voir des offres » interprété : le compte de démonstration avait 0 offre (remis à zéro par Claude à la fin de 12). La remise à zéro crée désormais deux offres d'exemple. Interprétation à confirmer.
- 2026-11-12 : défauts trouvés au parcours et corrigés : deux bandeaux superposés après suppression ; un ancien bandeau masquait la fenêtre qui monte ; menu « Unité » décalé quand le prix porte une erreur (défaut antérieur) ; la recherche réaffichait les produits des autres catégories quand une catégorie était choisie (défaut antérieur, de 11).

- 2026-11-12 : second retour de Xavier : « Il faut que le bouton plus il soit au centre » ; textes Parler et Importer retirés ; « tu enlèves [...] demander son accord avant d'enregistrer [...] c'est au client de faire ça » ; « deux variantes [...] le micro il est sur la gauche [...] l'autre il est sur la droite » ; « des petites animations quand je clique ».
- 2026-11-12 : objection de Claude, dite une fois : la SPEC (point 7) et le roadmap demandent que l'application rappelle l'accord du client (art. 179ter CP). Sans rappel à l'écran, l'obligation doit figurer dans les conditions d'utilisation acceptées par l'artisan. Rappel retiré à la demande de Xavier.
- 2026-11-12 : Xavier : « Il faut absolument qu'on puisse choisir les conditions de paiement et la validité de l'offre. Dans les paramètres [...] facile à changer [...] des paramètres presets ». Les deux champs existaient (sous-chantier 12) : ajout des choix d'un toucher, carte remontée en tête des Réglages.
- 2026-11-12 : Xavier demande aussi logo, couleurs et 4 à 6 modèles de document. Reporté à un sous-chantier dédié (envoi d'image : contrôle, stockage, PDF), avec maquette des modèles d'abord.

- 2026-11-12 : Xavier choisit la variante B. Variante A, paramètre ?variante et méthode calques.remplacer retirés (plus aucun appelant). Sans JavaScript, l'import est un lien vers /import.
- 2026-11-12 : jetons consommés par l'extraction écrits au journal de l'application (nombres seuls, jamais le texte), pour chiffrer le coût d'une offre. Test écrit, vu rouge sans l'appel, vert avec. Premier relevé réel (dictée de synthèse de 28 s, catalogue de 12 produits, 6 lignes) : 1266 jetons envoyés, 333 reçus ; offre prête 22 s après l'envoi.

## Décisions

| Date | Décision | Raison |
|---|---|---|
| 2026-11-12 | Accueil : bouton rond « + » qui s'ouvre en deux pastilles (Parler, Importer) ; l'enregistrement monte ensuite dans une fenêtre du bas. Sans JavaScript, le bouton mène à /import. | Demande de Xavier : « un petit bouton rond, on clique ». |
| 2026-11-12 | Fiche produit chargée dans une fenêtre par-dessus la liste, à partir des pages existantes ; aucune route nouvelle. La liste se met à jour sans rechargement. | Rester dans la liste ; les pages restent le repli sans JavaScript, et l'isolation n'a rien de neuf à couvrir. |
| 2026-11-12 | Le retour du téléphone ferme la fenêtre du dessus ; pendant l'enregistrement, il ne ferme rien. | Sur Android, le retour quittait la page. |
| 2026-11-12 | Fiche produit : quatre champs visibles ; nouvelle catégorie et mots de la dictée repliés, ouverts d'office quand ils servent. | « Je clique les 3-4 éléments que j'ai à mettre ». |
| 2026-11-12 | Boutons ronds au centre, sans texte (nom pour les lecteurs d'écran seulement), onde au toucher : variante B, micro à gauche, import à droite. | Demande de Xavier : « le toucher avec le pouce », « deux variantes ». |
| 2026-11-12 | Rappel « demandez son accord » retiré de l'écran. | Décision de Xavier, contraire à la SPEC point 7 ; à reporter dans les conditions d'utilisation. |
| 2026-11-12 | Réglages : validité en 15, 30, 60, 90 jours et quatre conditions de paiement d'un toucher ; le champ reste libre. | « Facile à changer », « presets ». |
| 2026-11-12 | Remise à zéro de la démo : deux offres d'exemple par métier, clients inventés, lignes tirées du catalogue fictif. | Un compte de démonstration doit montrer des offres. |

## Preuves

```
Variantes (390 px) :
  A fermée : + au centre 195,729 (64 px) ; pastilles visibles false
  A ouverte : micro 113,649, import 277,651, + 195,729 ; textes visibles : aucun
  B : micro 147,729, import 245,729 ; gardée après navigation : b
  A et B : micro touché, enregistrement en cours true ; import ouvre le choix du fichier true
Réglages :
  première carte : Conditions de l'offre
  après le toucher : validité 60, paiement « 30 % à la commande, solde à 30 jours dès la fin des travaux. »
  document : Paiement : 30 % à la commande [...] / valable jusqu'au 09.01.2027 (offre du 10.11.2026 + 60 jours)
EcransTest::testValiditeEtPaiementSeChoisissentDUnToucher, avant le code : Failed asserting that 2443 is less than 698.

$ vendor/bin/phpunit
OK (200 tests, 783 assertions)
$ vendor/bin/phpstan analyse
 [OK] No errors
DemoTest, avant le code : Failed asserting that 0 is identical to 2.

Parcours cliqué (port 8000, 390 px) :
  offres listées : Rafraîchissement du séjour ... Offre 2 | Façade de la maison ... Offre 1
  pastille Parler à 40 ms : opacité 0.54, transform matrix(0.788871, ...)
  pastille Parler à 490 ms : opacité 1.00, transform none ; aria-expanded true
  retour du téléphone : pastilles cachées true, adresse /
  fenêtre à 60 ms : transform matrix(1, 0, 0, 1, 0, 69.9718) ; posée : transform none
  retour pendant l'enregistrement : fenêtre toujours ouverte true
  Importer ouvre le choix du fichier : true
  nouveau produit : adresse /catalogue, focus sur designation
  prix refusé : fenêtre ouverte true, désignation gardée
  enregistré : fenêtre cachée true, adresse /catalogue, ligne p206 ... 12.50 / pce
  modifié : ... 14.00 / pce
  retour du téléphone : fiche cachée true, adresse /catalogue
  supprimé : ligne présente false, messages « ... supprimé du catalogue »
  catégorie Préparation, « murs » : Rebouchage et ponçage des murs
  erreurs console : le 422 attendu du prix refusé, rien d'autre
```
