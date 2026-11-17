# Sous-chantier 29 : mesurer la qualité de l'extraction sur de vraies dictées

## But

Savoir, chiffres à l'appui, si une offre sort juste. Objectif de Xavier (2026-11-17) : 95 % de lignes justes, une ou deux corrections au plus par offre. Aucun changement de consigne, de modèle ou de réglage ne part sur un site sans être passé par ce banc.

## Le corpus

Une dictée du corpus, c'est :

| Pièce | Qui la fait | Où |
|---|---|---|
| L'audio | Xavier (ou un artisan qui a donné son accord) | `OffreGen/corpus/audio/` sur le Synology, hors des dépôts git |
| La transcription | Claude, une fois, par le Whisper d'Infomaniak (le même que l'application) | `corpus/NN/transcription.txt` |
| Le catalogue utilisé | fictif du métier (peintre, menuisier, sanitaire), ou celui que Xavier fournit | `corpus/NN/catalogue.json` |
| L'offre attendue | Claude l'écrit d'après la transcription ; **Xavier la valide** | `corpus/NN/attendu.json` |

L'offre attendue suit les décisions en vigueur : produit du catalogue quand il existe, prix et unité du catalogue, prix dicté gardé à côté ; ligne libre sinon, avec son unité et son prix dits ; pas de ligne pour une simple précision ; un prix « en bloc » appartient au poste qui précède. La pièce s'y ajoutera avec le sous-chantier 28.

### Ce que Xavier dépose

- Un fichier audio par dictée (m4a, mp3, ogg, webm : ce que le téléphone donne), dans `corpus/audio/`, nommé `NN-court-titre` (`01-salon-cuisine.m4a`).
- Facultatif, à côté, un `NN-court-titre.txt` d'une ligne : le métier, et ce qu'il voulait obtenir s'il y a un piège voulu.

### Ce qu'il faut couvrir (une vingtaine pour commencer)

- Durées : de 2 à 15 minutes, quelques-unes vers 30.
- Avec et sans prix dits ; des prix « en bloc » ; des forfaits de fournisseur.
- Pièce par pièce, et des allers-retours (« ah, dans la cuisine j'ai oublié… »).
- Des corrections en parlant (« 40, non, 45 »), des précisions qui ne sont pas des postes, du bruit, des hésitations.
- Des postes absents du catalogue.

## La note

Pour chaque dictée et chaque réglage, ligne attendue par ligne attendue :

- **juste** : bon produit (ou ligne libre au bon nom), bonne quantité, bonne unité pour une ligne libre, bon prix (ligne libre) ou bon prix dicté (produit du catalogue) ;
- sinon **fausse**, avec la raison (absente, quantité, unité, prix, prix dicté) ;
- toute ligne proposée sans correspondance compte **en trop**.

Taux = justes / (attendues + en trop). Corrections par offre = fausses + en trop. Le rapport donne aussi les jetons, le coût en francs et la durée.

## Réglage et contrôle

- Les dictées impaires servent à régler, les paires à contrôler. Le partage est fixé avant tout réglage et ne change plus.
- Chaque réglage passe 3 fois sur chaque dictée : le modèle varie d'une passe à l'autre (mesuré le 2026-11-17, à température 0).
- Un réglage n'entre en service que si, sur la moitié de contrôle, son taux est au moins celui du réglage en service et qu'aucune dictée ne perd plus d'une ligne.

## Réglages à comparer en premier

1. En service : 27B sans raisonnement, consigne du 2026-11-17.
2. 27B avec raisonnement (7 sur 7 trois fois sur la dictée de Luigi ; 15 à 30 s et 1,3 à 2,7 ct de plus par offre ; durée sur 30 min de dictée à mesurer, délai de 300 s).
3. L'extraction optimisée du sous-chantier 20, quand elle existera.

## Ce qui reste dans l'application

Le banc est un outil du poste de travail (`outils/banc-corpus.php`) : il lit le corpus, appelle l'API comme l'application (mêmes classes, même validation) et écrit le rapport. L'audio et les transcriptions restent hors du dépôt. Le sous-chantier 23 donnera plus tard la mesure en usage réel : la proposition du modèle comparée à l'offre corrigée par l'artisan.
