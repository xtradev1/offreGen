---
chantier: De la voix à l'offre, 11 écrans de la maquette
slug: 2026-11-07_voix-offre/11-ecrans
phase: 8
statut: gate_ouvert
gate_attendu: prod
profil: .claude/atoz-profile.md
---

## Prochaine action

Recette de Xavier (`RECETTE.md`). Ensuite : commit et PR sur son accord (sous-chantiers 01, 10 et 11).

## En attente de toi

- Recette selon `RECETTE.md` : parler dans le navigateur, corriger une offre, modifier le catalogue et les réglages.
- Accord pour commiter la branche `chantier-voix-offre` et ouvrir la PR.

## Journal

- 2026-11-12 : Xavier : « Où est-ce qu'il est le bouton pour que je puisse directement parler et puis l'interface, il manque pas mal d'éléments. » Choix : bouton pour parler, onglet Catalogue (consulter et modifier), corrections de l'offre, onglet Réglages. Phases 1 et 3 sautées : l'intention est son choix du jour, l'aspect celui de la maquette v4 validée.
- 2026-11-12 : reprend le sous-chantier 05 et la partie locale du 08 (le micro est permis sur `localhost` ; depuis le téléphone, il faudra HTTPS).
- 2026-11-12 : défaut à moi, du sous-chantier 10 : « Entendu : … » affiché sous chaque ligne, contre la règle « sans rappel de la dictée » (CODING_RULES, Ergonomie). Retiré de l'écran ; la colonne reste pour le banc.
- 2026-11-12 : défauts trouvés par le parcours cliqué, pas par PHPUnit : quantité et unité éclatées en deux cellules de la grille (unité dans la colonne Prix, corbeille à la ligne) ; focus perdu sur la ligne ajoutée (Chrome le retire en allant à l'ancre `#l…`) ; mention « absente du catalogue » sous le champ de quantité. Corrigés, parcours rejoué.
- 2026-11-12 : défauts de tests à moi (jeton pris sur l'accueil, qui n'a plus de formulaire ; apostrophe échappée ; message lu par une autre page). Corrigés.

## Décisions

| Date | Décision | Raison |
|---|---|---|
| 2026-11-12 | Chaque écriture est un formulaire HTML avec jeton, suivi d'une redirection 303 ; `app.js` ne fait qu'envoyer le champ modifié, filtrer et enregistrer. | Le serveur reste seul à calculer ; l'écran marche sans JavaScript, sauf le micro. |
| 2026-11-12 | Ligne supprimée marquée `supprimee_le`, « Annuler » la remet. | CODING_RULES : un clic, puis « Annuler » quelques secondes. |
| 2026-11-12 | Lignes groupées par catégorie du produit, dans l'ordre de la dictée ; une ligne sans produit va dans « Hors catalogue ». | Maquette v4 groupée par catégorie, sans colonne de catégorie sur la ligne. |
| 2026-11-12 | Réglages : nom de l'entreprise, TVA 8,1 % ou non assujetti, arrondi aux 5 centimes. Les totaux des offres déjà faites suivent le réglage. | Seuls réglages qui changent une offre aujourd'hui. Avant l'envoi au client, il faudra figer TVA et arrondi dans l'offre envoyée. |
| 2026-11-12 | Pas de bouton micro sur l'offre, pas de majoration, remise, achat + marge, aperçu ni envoi. | Aucun code ne les porte ; un bouton sans effet tromperait. |
| 2026-11-12 | Enregistrement en Opus 32 kbit/s (webm sous Chrome, mp4 sous Safari) ; échec d'envoi : « Réessayer » ou garder le fichier. | Une heure tient en 14 Mo ; une dictée ne se perd pas sur un réseau de chantier. |

## Preuves

```
$ vendor/bin/phpunit
OK (179 tests, 641 assertions)
$ vendor/bin/phpstan analyse
 [OK] No errors
$ composer audit
No security vulnerability advisories found.
Contrôle tirets longs, BOM, CR sur les fichiers modifiés et nouveaux : aucun défaut.

Avant les cas d'isolation, le test les réclamait :
IsolationRoutesTest::testChaqueRouteAParametreAUnCas
+    0 => 'offre_ajout', 1 => 'ligne_ajouter', 2 => 'ligne', 3 => 'ligne_corriger', 4 => 'ligne_juste',
+    5 => 'ligne_supprimer', 6 => 'ligne_restaurer', 7 => 'produit', 8 => 'produit_modifier',
+    9 => 'produit_suppression', 10 => 'produit_supprimer'

Parcours cliqué (Playwright, Chromium, faux micro qui lit la dictée fictive de 27,9 s), 390 px puis 1280 px sombre :
  recherche « facade » : Peinture de façade, 2 couches
  bandeau : « Peinture anti-moisissure » enregistré
  produit : Peinture anti-moisissure anti-moisissure, moisissure, champignons 24.00 / m²
  minuteur après 3,5 s : 00:03
offre prête 14 s après « Terminer »
  Sous-total HT CHF 2’760.00 TVA 8,1 % CHF 223.56 Arrondi CHF -0.01 Total TTC CHF 2’983.55
  après correction : Protection des sols et des meubles | 45 | forfait | 180 | 8’100.00
  « C'est juste » : lignes à vérifier 3 puis 2
  suppression : 6 lignes puis 5 ; bandeau « « Déplacement » supprimée Annuler »
  après « Annuler » : 6 lignes
  champ ouvert après l'ajout : quantite Laquage des plinthes
  ligne ajoutée : Laquage des plinthes | 22 | m | 12 | 264.00
  recherche sans résultat, désignation recopiée : moquette escalier
  ligne finale : Remplacement de la moquette de l'escalier | 1 | forfait | 350 | 350.00
  Sous-total HT CHF 11’294.00 TVA 8,1 % CHF 914.81 Arrondi CHF -0.01 Total TTC CHF 12’208.80
  (refait à la main : 8100 + 1176 + 576 + 480 + 288 + 264 + 60 + 350 = 11 294 ; 11 294 × 0,081 = 914,814)
  largeur de page égale à la fenêtre sur les 18 captures
erreurs JavaScript : favicon.ico 404 seulement
```
