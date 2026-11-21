# Les formules d'abonnement

Établi le 17 novembre 2026 à la demande de Xavier : quatre prix de base entre 39 et 49 francs, l'annuel, les packs d'offres, une formule plus grosse et une formule d'entreprise. Rien n'est décidé ; base de discussion. Hypothèses de coût : `PRIX-ABONNEMENT.md` (Stripe 3,6 % + CHF 0.30 par paiement, coûts fixes CHF 37.50 par mois, IA avec marge ×2).

## Solo : 50 offres par mois, quatre prix

| Prix HT | TTC | Marge, client type | Marge, pire client | Clients pour CHF 10'000 par mois |
|---|---|---|---|---|
| CHF 39 | 42.16 | 35.14 (90 %) | 29.20 | 286 |
| CHF 42 | 45.40 | 38.03 (91 %) | 32.09 | 264 |
| CHF 44.90 | 48.54 | 40.83 (91 %) | 34.88 | 246 |
| CHF 49 | 52.97 | 44.78 (91 %) | 38.84 | 225 |

44.90 HT reste sous 50 francs TTC ; c'est le prix d'Angebotsmeister (49 €, environ CHF 46) pour le même nombre d'offres.

## L'annuel : un ou deux mois offerts

| Prix HT | Annuel, 1 mois offert | Par mois | Marge par mois | Annuel, 2 mois offerts | Par mois | Marge par mois |
|---|---|---|---|---|---|---|
| CHF 39 | 429 | 35.75 | 32.28 | 390 | 32.50 | 29.15 |
| CHF 42 | 462 | 38.50 | 34.94 | 420 | 35.00 | 31.56 |
| CHF 44.90 | 493.90 | 41.16 | 37.50 | 449 | 37.42 | 33.89 |
| CHF 49 | 539 | 44.92 | 41.12 | 490 | 40.83 | 37.18 |

Ce que fait le marché (relevé du 17 novembre) : Renalto 36 € par mois ou 30 € en annuel (environ 2 mois offerts, relu) ; Angebotsmeister 49 € ou 44 € (environ 1 mois) ; bexio CHF 45 ou 35 ; Werkli CHF 50 ou 40 ; Magic Heidi CHF 39 ou 24.90. Un à deux mois offerts est courant ; certains vont plus loin.

Ce que l'annuel rapporte, même avec une marge par mois plus basse : l'argent d'une année d'un coup, un client qui ne part pas pendant 12 mois (un client au mois peut partir chaque mois), un seul paiement par an au lieu de douze (environ CHF 5 de frais Stripe en moins par an pour Solo : les 30 centimes ne sont payés qu'une fois). Sans données sur les départs des clients, **un mois offert** est le choix prudent ; on passera à deux si les clients au mois partent vite.

## Le pack d'offres : utile, à un prix qui ne casse pas l'abonnement

Idée de Xavier : 25 offres pour CHF 50 (2 francs l'offre), à utiliser quand on veut.

| Pack de 25 offres | Prix par offre | Marge (pire cas) |
|---|---|---|
| CHF 50 | 2.00 | 43.85 (88 %) |
| CHF 62.50 | 2.50 | 55.90 (89 %) |
| CHF 75 | 3.00 | 67.95 (91 %) |

La marge est bonne dans tous les cas : le danger est ailleurs. Un artisan qui fait 10 offres par mois paie CHF 539 par an en Solo à 44.90, et seulement CHF 240 avec des packs à 2 francs. À 2 francs l'offre, tout artisan sous 22 offres par mois a intérêt à quitter l'abonnement pour les packs ; or ce sont les clients les plus rentables. **À 2 francs, le pack casse le système.**

Décision de Xavier (17 novembre) : pas de pack sans abonnement, « ça casse le système, et puis c'est pas ce que je vise ». Reste la **recharge pour les abonnés** : 25 offres de plus pour CHF 25 (1 franc l'offre), valables 12 mois, quand le quota du mois est épuisé. Elle complète l'abonnement sans le remplacer.

## Pro : payer le double, avoir le triple

IA d'un client type : 70 % du quota utilisé, 6 centimes l'offre ; pire cas : tout le quota, offres de 30 minutes à 200 postes (16.2 centimes, marge ×2 comprise).

| Formule | Prix HT | TTC | Prix par offre | Marge, client type | Marge, pire client |
|---|---|---|---|---|---|
| 100 offres | CHF 69 | 74.59 | 0.69 | 62.02 (90 %) | 50.02 (72 %) |
| 100 offres | CHF 79 | 85.40 | 0.79 | 71.66 (91 %) | 59.66 (76 %) |
| 150 offres | CHF 79 | 85.40 | 0.53 | 69.56 (88 %) | 51.56 (65 %) |
| **150 offres** | **CHF 89** | **96.21** | **0.59** | **79.20 (89 %)** | **61.20 (69 %)** |
| 150 offres | CHF 99 | 107.02 | 0.66 | 88.84 (90 %) | 70.84 (72 %) |

Avec Solo à 44.90 : Pro à 89 francs pour 150 offres, c'est le double du prix pour le triple d'offres (0.59 l'offre au lieu de 0.90).

## Entreprise : plusieurs comptes, offres partagées

3 comptes, 300 offres par mois partagées entre eux :

| Prix HT | TTC | Par compte | Prix par offre | Marge, client type | Marge, pire client |
|---|---|---|---|---|---|
| CHF 149 | 161.07 | 49.67 | 0.50 | 130.74 (88 %) | 94.74 (64 %) |
| **CHF 179** | **193.50** | **59.67** | **0.60** | **159.66 (89 %)** | **123.66 (69 %)** |
| CHF 199 | 215.12 | 66.33 | 0.66 | 178.94 (90 %) | 142.94 (72 %) |

Compte supplémentaire : à fixer (par exemple CHF 29 par mois et 50 offres de plus). L'application ne connaît aujourd'hui qu'un compte par entreprise : plusieurs comptes sur un même catalogue, ce serait un sous-chantier à part.

## Proposition

| Formule | Prix HT par mois | Offres par mois | Annuel (1 mois offert) |
|---|---|---|---|
| Solo | CHF 44.90 | 50 | CHF 493.90 |
| Pro | CHF 89 | 150 | CHF 979 |
| Entreprise | CHF 179 | 300, 3 comptes | CHF 1'969 |
| Recharge (abonnés) | CHF 25 | 25 de plus, 12 mois | |

À construire avant de vendre : le paiement (Stripe), le quota d'offres remis à zéro chaque mois (aujourd'hui seul existe le plafond total des comptes d'essai), les recharges, et pour Entreprise les comptes multiples.
