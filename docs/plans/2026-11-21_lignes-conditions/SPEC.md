# SPEC : lignes offertes, rabais, conditions par offre, représentant, acomptes, escompte

Rassemble les décisions de Xavier des 21 et 22 novembre 2026 (D1 à D16, règles du 21, demandes du 22).
Aucune décision nouvelle ici : ce qui reste ouvert est sous Risques.

## Problème (constaté dans le code, e48eab4 puis 77954ce)

- Une ligne gratuite est impossible : prix vide et prix 0 se confondent (`src/Offre/Saisie.php`), un 0 s'affiche
  « Prix manquant » et sort « ? » sur le document (`templates/document/_blocs.html.twig`).
- Aucun rabais, aucun escompte dans le calcul (`src/Offre/Totaux.php`).
- Validité, paiement : réglages du compte seulement. Textes avant et après les lignes : modèle Lettre seulement,
  réglés pour tout le compte.
- Une offre n'a qu'un client ; pas de régie, gérance ou architecte qui le représente.
- Libellé d'acompte figé (`src/Facture/PlanAcomptes.php`).
- Une ligne ne s'ouvre qu'en touchant son nom (Xavier, 2026-11-22).

## Utilisateurs

L'artisan (et son équipe), sur téléphone et sur PC. Le client et son représentant reçoivent les documents.

## Périmètre

1. Une ligne peut être **offerte** : prix barré (prix gardé) ou « Offert » seul (prix 0). Taper 0 rend la ligne
   offerte ; un prix vide reste « Prix manquant ». Pas de ligne « Dont offert » (D1, D2, D3, D17 proposé).
2. Toucher n'importe où sur une ligne ouvre sa fenêtre, sauf un champ, un bouton, une note, un défilement ou un
   glissé (Xavier, 2026-11-22). La fenêtre de la ligne porte « Facturée / Offerte ».
3. Aucun « ? » sur une offre ni une facture : un prix manquant laisse la case vide, et l'offre ne part pas tant
   qu'un prix manque (Xavier, 2026-11-21).
4. Rabais en % ou en CHF, plusieurs possibles, libellé libre, sous le sous-total avant la TVA (D4 B, D15).
5. Textes avant et après les lignes, validité, paiement, escompte : propres à chaque offre, remplis avec les
   réglages, sans « utiliser pour toutes » (D5, D7). Dans Lettre : le texte avant remplace la phrase de Ma marque
   (D6) ; le texte après se place avant la formule de politesse (D16 A).
6. « Client » et « Représenté par » ; offre adressée au représentant par défaut, au client au choix ; factures au
   client chez lui ou « p.a. » le représentant (D8, D9, D10).
7. Libellé d'acompte libre, prérempli, trois propositions ; la facture finale liste chaque acompte (D11, D12).
8. Escompte sur chaque facture, acomptes compris, en mention ; QR au montant plein (D13, D14).
9. Une offre reste modifiable en tout temps, envoyée ou facturée ; un bandeau « Modifiée depuis l'envoi · Renvoyer »
   (Xavier, 2026-11-21). Une facture émise s'annule, jamais ne se supprime (déjà le cas).

## Hors périmètre

- Les « prix ? » du **catalogue** (produits sans prix) : ce ne sont pas des offres.
- Correction de TVA sur un escompte pris : le document donne le montant net, la comptabilité de l'escompte reste
  au client et à sa fiduciaire.
- Versions numérotées d'une offre renvoyée.
- Ligne de prix négatif dans la liste : le rabais la remplace (D4 B).

## Critères de succès (observables sur le site de test)

- Offrir une ligne en 3 touchers (prix barré) ou en tapant 0 (« Offert ») ; le total baisse d'autant ; la facture
  finale garde la ligne offerte, à 0.
- Toucher la quantité ou le total d'une ligne ouvre la ligne ; défiler ne l'ouvre pas ; glisser la supprime toujours.
- Aucun « ? » dans un PDF d'offre ou de facture.
- Rabais, textes, conditions, représentant, libellé d'acompte, escompte : chacun visible sur le document, avec le
  nombre de gestes du tableau de la maquette v3.

## Contraintes

- `CODING_RULES.md`, `docs/SECURITE.md`, `docs/DEPLOIEMENT.md` (migrations additives).
- Montants en entiers (centimes), un seul endroit de calcul (`Totaux`).
- Douze modèles de document partagent `templates/document/_blocs.html.twig`.
- Recette sur le site de test, jamais en local.

## Risques

- D17 (« Offert » seul = taper 0) et D18 (offre baissée sous les acomptes) : sans réponse de Xavier. Le lot 1
  applique D17 tel que proposé ; le refaire coûterait une bascule de plus dans la fenêtre de la ligne.
- L'extraction renvoie 0 pour un prix inconnu : sans effet, une ligne n'est offerte que par un geste de l'artisan
  (colonne `offerte` à 0 par défaut).
- Toucher perdu possible quand l'écran remplace les lignes à la réponse du serveur (`public/js/app.js:24-37`) :
  plausible, non reproduit.
