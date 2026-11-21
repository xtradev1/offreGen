---
chantier: De la voix à l'offre, 14 marque et modèles
slug: 2026-11-07_voix-offre/14-marque-modeles
phase: 3
statut: termine
gate_attendu: aucun
profil: .claude/atoz-profile.md
---

## Prochaine action

Aucune. Réponses de Xavier reçues le 2026-11-12 (voir Journal), code livré au sous-chantier 16.

## En attente de toi

- Rien.

## Journal

- 2026-11-12 : demande de Xavier : « personnaliser un petit peu ces factures [...] mettre son logo [...] des couleurs de son entreprise [...] en 2-3 clics [...] 4 à 6 modèles de factures, un peu différents, basés peut-être sur le type de travaux ». Question de Claude : offres seulement ou facture aussi ; réponse « les 2 ».
- 2026-11-12 : maquette écrite : essai en trois touchers (logo, couleur tirée du logo, modèle ; offre ou facture), six modèles en offre et en facture, facture créée depuis l'offre, QR-facture.
- 2026-11-12 : documents dessinés avec le seul CSS que dompdf rend (tableaux, flottants, bordures, couleurs) : le choix de Xavier sera tenable en PDF.
- 2026-11-12 : défauts trouvés en fabriquant la planche et corrigés : les six factures débordaient de l'A4 sous la QR-facture (1303 à 1552 px pour 1123) ; date mal placée et total en double dans deux modèles ; le zoom du kit captait les logos à choisir.

- 2026-11-12 : réponses de Xavier (texte copié de la maquette) : parcours en trois touchers validé ; Sobre, Bandeau, Atelier, Technique, Épuré gardés ; Par poste écarté (« par poste est nul fais en un autre ») ; « Facturer » validé « avec la possiblité de mettre soi meme le numéro » ; QR-facture validée. « enlève les petit texte sous le modèle, les couleurs et logo c'est bien ».
- 2026-11-12 : maquette reprise : « Moderne » remplace « Par poste » (grand titre en couleur, bandeau date, client, validité ; lignes alternées) ; petits textes de métier retirés ; points tranchés affichés comme réglés, sans boutons.
- 2026-11-18 : clos. « Moderne » a remplacé « Par poste » sur la maquette reprise et dans le code (sous-chantier 16). La variante A ou B du bouton Nouvelle offre est dépassée : décision D1 du sous-chantier 24, le micro au centre de la barre du bas.

## Décisions

| Date | Décision | Raison |
|---|---|---|
| 2026-11-12 | Six modèles : Sobre (tous métiers), Bandeau (façades, gros œuvre), Atelier (menuiserie), Technique (sanitaire, chauffage, électricité ; positions numérotées), Par poste (peinture, rénovation), Épuré (indépendant). | « 4 à 6 modèles [...] basés sur le type de travaux » ; Xavier en garde quatre à six. |
| 2026-11-12 | Logo : PNG et JPEG seulement, contrôlés, réduits, stockés hors du site public. Pas de SVG envoyé par l'artisan. | Un SVG peut contenir du code. |
| 2026-11-12 | Facture plus longue qu'une page : la QR-facture passe sur une page à part. | Le bulletin suisse ne se coupe pas. |

## Preuves

```
check.js : bureau 1440/1440, téléphone 390/390, images 12, cassées 0, kit oui
Planche : 12 images, toutes « TIENT » (contenu au-dessus du pied de page ; factures au-dessus du bulletin)
Essai au clic (390 px) :
  logo Aubry touché : zoom ouvert false, bouton choisi true, couleur proposée #8A5A2B
  aperçu : a4 m-bandeau facture, bandeau couleur rgb(138, 90, 43), QR présent true
  après rechargement, Atelier : true, commentaire « joli pour les cuisines »
  zoom de la planche, adresse inchangée : true
  erreurs JS : aucune
```
