# Coût, durée et fiabilité de l'IA selon l'usage

Établi le 17 novembre 2026 à partir des mesures du jour (bancs du sous-chantier 26, dictée de Luigi, tarifs Infomaniak relevés le 5 novembre, hors TVA). Réglage en service : Whisper, puis Qwen 27B sans raisonnement pour l'extraction, puis Qwen 27B pour ranger les lignes par pièce.

## Hypothèses

- Débit de parole : 150 mots par minute, 3 postes par minute ; l'extrême : 10 postes par minute (un toutes les 6 secondes).
- Catalogue de 500 produits. Il pèse environ 16 000 jetons à chaque offre : avec 50 produits, le coût de l'extraction baisse de 60 à 80 %.
- Whisper : facturé au temps de calcul, CHF 0.006 la minute ; mesuré entre 1 s de calcul pour 19 s d'audio et 3 s pour 28 s (rapport 1:9 à 1:20). La fourchette de la colonne « Transcription » vient de là.
- Seconde passe (pièces) : mesurée pour 2 et 30 minutes, calculée pour les autres durées.

## Six profils d'artisan

| Profil | Offres par mois | Dictée | Postes par offre |
|---|---|---|---|
| Très léger | 5 | 2 min | 6 |
| Léger | 15 | 5 min | 15 |
| Moyen | 30 | 10 min | 30 |
| Important | 50 | 15 min | 45 |
| Très important | 100 | 20 min | 60 |
| Extrême | 100 | 30 min, très dense | 300 |

## Coût d'une offre, par poste

| Profil | Transcription | Extraction | Pièces | **Total par offre** |
|---|---|---|---|---|
| Très léger | 0,06 à 0,13 ct | 0,83 ct | 0,07 ct (mesuré) | **1,0 ct** |
| Léger | 0,15 à 0,33 ct | 1,0 ct | 0,15 ct | **1,3 à 1,5 ct** |
| Moyen | 0,3 à 0,67 ct | 1,3 ct (mesuré) | 0,3 ct | **1,9 à 2,3 ct** |
| Important | 0,45 à 1,0 ct | 1,6 ct | 0,44 ct | **2,5 à 3,0 ct** |
| Très important | 0,6 à 1,3 ct | 1,9 ct | 0,58 ct | **3,1 à 3,8 ct** |
| Extrême | 0,9 à 2,0 ct | 6,0 ct (mesuré) | 2,4 ct | **9,3 à 10,4 ct** |

Répartition typique : l'extraction fait la moitié à 60 % du coût (le catalogue surtout), la transcription 15 à 30 %, les pièces 10 à 25 %.

## Coût par mois et poids dans un abonnement

| Profil | Coût par mois | Avec une marge ×2 | Part d'un abonnement de CHF 50 (marge comprise) |
|---|---|---|---|
| Très léger | CHF 0.05 | CHF 0.10 | 0,2 % |
| Léger | CHF 0.22 | CHF 0.45 | 0,9 % |
| Moyen | CHF 0.68 | CHF 1.40 | 2,8 % |
| Important | CHF 1.50 | CHF 3.00 | 6 % |
| Très important | CHF 3.80 | CHF 7.60 | 15 % |
| Extrême | CHF 10.40 | CHF 20.80 | 42 % |

Pour comparer : 50 offres extrêmes coûtent CHF 5.20, soit CHF 10.40 avec la marge ×2.

## Temps d'attente après « Terminer »

| Profil | Envoi | Transcription (calcul) | Extraction | Pièces | **Total, hors file d'attente** |
|---|---|---|---|---|---|
| Très léger | 1 s | 6 à 13 s | 2 à 5 s | 1 à 3 s | **10 à 20 s** |
| Léger | 1 s | 15 à 35 s | 4 s | 2 s | **20 à 40 s** |
| Moyen | 2 s | 30 à 70 s | 6 s (mesuré) | 3 s | **40 à 80 s** |
| Important | 3 s | 45 à 100 s | 10 s | 5 s | **1 à 2 min** |
| Très important | 4 s | 60 à 135 s | 13 s | 6 s | **1,5 à 2,5 min** |
| Extrême | 7 s | 90 à 200 s | 65 s (mesuré) | 20 à 30 s | **3 à 5 min** |

La file d'attente de Whisper chez Infomaniak s'ajoute : 47 s et 301 s mesurées pour le même fichier de 28 s. Deux mesures seulement ; le relevé du sous-chantier 26 dira ce qui est habituel. L'application attend 90 s, puis la tâche de chaque minute prend le relais : l'offre arrive, au plus une minute plus tard.

## Fiabilité

| Profil | Lignes justes mesurées | Corrections attendues par offre | Source |
|---|---|---|---|
| Très léger | 86 à 100 % | 0 à 1 | dictée réelle de Luigi, 7 postes, consigne du 17 novembre |
| Léger | environ 95 % (entre les deux mesures) | environ 1 | interpolé |
| Moyen | 93 à 97 % | 1 à 2 | banc inventé, 10 min |
| Important | 96 à 98 % | 1 à 2 | banc inventé, 30 min normal |
| Très important | 96 à 98 % | 2 à 3 | banc inventé, 30 min normal |
| Extrême | 96 % | environ 12 (variantes voisines surtout), 2 oublis | banc inventé, 300 postes |

- Les pièces sont justes sur 100 % des lignes bien extraites du banc inventé ; sur la dictée de Luigi, deux passes sur trois.
- Au-delà de 30 minutes, la qualité chute (60 min : 84 %, 20 postes oubliés) : c'est la raison de la limite à 30 minutes.
- Le banc inventé est optimiste : la seule dictée réelle relue est descendue à 5 sur 7 avec l'ancienne consigne. Les chiffres de cette colonne ne valent que jusqu'au corpus réel du sous-chantier 29.
- En pourcentage, l'objectif de 95 % tient ; en nombre de corrections, il grandit avec la taille de l'offre : 95 % de 300 lignes, ce sont 15 lignes à revoir.

## Ce qui ferait baisser le coût

- Un catalogue plus petit, ou l'extraction optimisée du sous-chantier 20 qui n'envoie que les candidats : l'extraction coûterait trois à cinq fois moins.
- Un modèle moins cher pour les pièces (Gemma 4 : 0,25 ct au lieu de 0,84 pour 30 min) : écarté pour l'instant, il s'est trompé une fois sur trois sur la dictée de Luigi.
