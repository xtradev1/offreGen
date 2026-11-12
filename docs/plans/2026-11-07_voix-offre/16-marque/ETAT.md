---
chantier: De la voix à l'offre, 16 marque et modèles dans l'application
slug: 2026-11-07_voix-offre/16-marque
phase: 8
statut: gate_ouvert
gate_attendu: prod
profil: .claude/atoz-profile.md
---

## Prochaine action

Recette de Xavier : Réglages, carte « Ma marque » (logo, couleur, modèle), puis « Générer l'offre » et le PDF.

## En attente de toi

- Envoyer ton vrai logo (PNG ou JPEG), vérifier la couleur proposée, choisir un modèle, ouvrir le document et le PDF.

## Journal

- 2026-11-12 : réponses de Xavier : « moderne gardé » ; les six modèles de la maquette 14 entrent dans l'application. Facture : sous-chantier 18. Envoi par courriel : « pdf pour l'instant ». Extraction optimisée : « plus tard ».
- 2026-11-12 : l'extension GD est absente. Logo traité par ffmpeg (déjà là pour l'audio) : PNG ou JPEG seulement, réencodé, réduit à 600 x 240, transparence aplatie sur blanc, enregistré en JPEG hors du site public. dompdf intègre ce JPEG sans GD (essai fait avant le code).
- 2026-11-12 : la CSP refuse les styles en ligne : la couleur passe par /reglages/marque.css (aperçu) et par la balise style du PDF ; pastilles des réglages en SVG ; logo de l'aperçu par /reglages/logo (la CSP refuse les images en data:), logo du PDF intégré en base64.
- 2026-11-12 : défauts trouvés et corrigés : fixture PNG fausse (rectangle à opacité nulle, ffmpeg drawbox ne touche pas la transparence) ; blanc du logo teinté 255, 253, 255 qui dessinait un cadre sur le papier (composition en RVB, pleine plage) ; « Autre » des couleurs n'ouvrait pas le champ (règle CSS limitée aux pastilles des conditions) ; .doc .c-filet ne visait pas le bloc du modèle Technique.
- 2026-11-12 : Marque et son test écrits ensemble, sans voir le test rouge d'abord (écart à la méthode, valeurs du test calculées à la main).
- 2026-11-12 : migration 6 redescendue puis rejouée en dev et en test pour ajouter couleur_logo (jamais commitée ni en production).

## Décisions

| Date | Décision | Raison |
|---|---|---|
| 2026-11-12 | Logo : PNG ou JPEG, 5 Mo, 6000 px de côté au plus ; réencodé par ffmpeg ; SVG refusé. | Un SVG peut porter du script ; réencoder ne laisse passer rien du fichier d'origine. |
| 2026-11-12 | La couleur du logo est proposée d'office et devient celle des documents ; six couleurs et « Autre » restent au choix. | Parcours validé dans la maquette. |
| 2026-11-12 | Une couleur trop claire est assombrie pour le texte jusqu'à un contraste de 4,5 sur le blanc (WCAG AA) ; la couleur choisie reste enregistrée telle quelle. | Un jaune pâle tiré d'un logo serait illisible sur le papier. |
| 2026-11-12 | Les six modèles en CSS 2.1 seulement (tableaux, bordures, fonds). | dompdf ne rend ni grille ni flexbox ni variables. |

## Preuves

```
$ vendor/bin/phpunit
OK (228 tests, 1042 assertions)
$ vendor/bin/phpstan analyse
 [OK] No errors
Tests vus rouges avant le code : TraitementLogoTest (classe absente, puis blanc teinté), MarqueEcranTest (404 et marque inchangée).

Parcours sur le compte de démo (390 px) :
  logo envoyé : « Logo enregistré » ; aperçu visible ; couleur cochée #1D4E89
  sobre, bandeau, atelier, technique, moderne, épuré : aperçu à la bonne classe, PDF 200 de 30 à 40 Ko, image incluse
  couleur #f5e663 : .doc .c-texte { color: #7D7733; }
  seule erreur console : /favicon.ico (404)
PDF lus : Technique (barre, grille, positions), Bandeau (bande, cartouche, total plein).
```
