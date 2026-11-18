# Prix de l'abonnement, marge, paiement et concurrents

Établi le 17 novembre 2026 à la demande de Xavier. Coûts de l'IA : `PROFILS-USAGE.md`. Recherches du jour : `scratchpad/recherche-prix/` (paiement, GPU, concurrents), chiffres clés relus par Claude sur les pages officielles.

## Hypothèses

- **Coûts fixes : CHF 400 à 500 par an** (Xavier, 2026-11-17), soit CHF 37.50 par mois au milieu.
- **Abonnement plafonné à 50 offres par mois** (Xavier).
- **Paiement par carte avec Stripe** : « 2.9% + CHF0.30 » par paiement avec une carte suisse, plus « 0.7% » pour la gestion des abonnements (stripe.com/en-ch, relu). Soit 3,6 % + CHF 0.30 par mois et par client.
- **IA, marge ×2 comprise** : clientèle type CHF 2.15 par client et par mois ; pire client possible CHF 8.10 (50 offres de 30 minutes à 200 postes, environ 8 ct chacune, ×2).
- **Hors calcul** : le temps de Xavier, les charges sociales, les impôts.

## Marge selon le prix (prix hors TVA)

| Prix HT | Le client paie TTC | Frais Stripe | Marge, client type | Marge, pire client | Clients pour CHF 10'000 par mois |
|---|---|---|---|---|---|
| CHF 20 | 21.62 | 1.02 | 16.83 (84 %) | 10.88 (54 %) | 597 |
| CHF 25 | 27.02 | 1.20 | 21.65 (87 %) | 15.70 (63 %) | 464 |
| CHF 29 | 31.35 | 1.34 | 25.50 (88 %) | 19.56 (67 %) | 394 |
| CHF 35 | 37.84 | 1.56 | 31.29 (89 %) | 25.34 (72 %) | 321 |
| **CHF 39** | **42.16** | **1.70** | **35.14 (90 %)** | **29.20 (75 %)** | **286** |
| CHF 45 | 48.64 | 1.92 | 40.93 (91 %) | 34.98 (78 %) | 246 |
| CHF 49 | 52.97 | 2.06 | 44.78 (91 %) | 38.84 (79 %) | 225 |
| CHF 50 | 54.05 | 2.10 | 45.75 (91 %) | 39.80 (80 %) | 220 |

- Les coûts fixes sont couverts dès 2 ou 3 clients, quel que soit le prix.
- Même le pire client possible reste rentable dès CHF 20, grâce au plafond de 50 offres.

## Si le prix affiché est TTC

Pour une entreprise assujettie à la TVA, un prix affiché TTC laisse 8,1 % de moins :

| Prix affiché TTC | Reste HT | Marge, client type | Clients pour CHF 10'000 |
|---|---|---|---|
| CHF 29 | 26.83 | 23.33 | 431 |
| CHF 39 | 36.08 | 32.22 | 312 |
| CHF 49 | 45.33 | 41.11 | 245 |

Une entreprise non assujettie (chiffre d'affaires sous le seuil légal, à confirmer avec un fiduciaire) encaisse le prix entier, sans TVA à reverser. Pour des artisans, qui récupèrent la TVA, l'usage est d'afficher HT.

## Paiement

| Solution | Coût par abonné à CHF 39 | Frais fixes | Ce qui reste à faire |
|---|---|---|---|
| Stripe, carte + abonnements | environ CHF 1.70 | aucun | TVA et factures conformes (Stripe génère des factures) |
| Stripe, TWINT | 1,9 % + CHF 0.30 | aucun | TWINT est indiqué « B2C » chez Stripe : à confirmer pour des entreprises |
| Payrexx Standard | 1,65 % + CHF 0.18 | CHF 19 par mois (source : blog Payrexx) | rentable vers 30 abonnés ; récurrent non publié |
| Wallee Basic | environ 1,55 % + CHF 0.20 | CHF 19.95 par mois | récurrent non publié |
| Paddle (revendeur, gère la TVA) | 5 % + 0,50 $ | aucun | presque rien, mais la Suisse et TWINT ne sont pas confirmés |

Conseil : Stripe au départ (aucun frais fixe, prélèvement automatique, relance des cartes refusées), à revoir vers 30 abonnés. Lemon Squeezy est exclu : il ferme le 31 mars 2027.

## Concurrents (relevé du 17 novembre 2026)

Avec la voix :

| Produit | Pays, langue | Prix par mois | Ce qui est compris | Différence |
|---|---|---|---|---|
| Renalto | France | 30 € HT (relu) | 50 devis IA, 2 utilisateurs | le plus proche ; QR-facture suisse, hébergement et pièces non vérifiés |
| Angebotsmeister | Allemagne, sortie en allemand | 49 € HT, 44 € en annuel (relu) | 50 offres IA, utilisateurs illimités | voix traitée par des IA aux États-Unis |
| Obat | France | 49 € HT + option voix 29 € HT | devis vocal en option | option relue en résumé seulement |
| Batappli | France | 39 € HT / 79 € HT | 8 / 16 devis IA | beaucoup moins de devis |
| Jottle | Royaume-Uni, anglais | £17 / £29 / £59 | 50 / 200 / 500 devis | anglais |
| VoxTrade | Royaume-Uni, anglais | £14.99 | devis illimités, 120 min de voix | anglais |
| Jobber | États-Unis, anglais | $29 à $49 | la voix n'est pas située dans les formules | anglais |
| Offerto (getofferto.ch, Kaco GmbH, Stäfa) | Suisse, allemand | CHF 39 HT prix fondateur pour 50 entreprises, ensuite 69 (relu le 18.11) | brouillon d'offre depuis un courriel, un texte ou une dictée après le téléphone ; prix appris des anciennes offres ou de bexio ; acceptation en ligne, relances, synchronisation bexio ; données à Zurich | **le concurrent le plus proche, en Suisse** : peintres et plâtriers cités ; part d'une demande de client, pas de la visite dictée pièce par pièce ; pas de français vu (signalé par Xavier le 18.11) |
| Werkli | Suisse | CHF 50, 40 en annuel | rapport de chantier vocal, pas d'offre dictée | autre usage |

Sans la voix, en Suisse : Magic Heidi CHF 24.90 TTC en annuel ou CHF 39 (relu), offres illimitées ; bexio dès CHF 35 HT en annuel, 45 en mensuel ; Smallinvoice CHF 15 / 30 / 45. Aucun produit suisse en français qui transforme une dictée en offre n'a été trouvé (ce n'est pas une preuve d'absence). En allemand, Offerto existe (ligne ci-dessus, trouvé le 18.11).

## Whisper sur un serveur à soi

- Infomaniak loue des GPU à l'heure (Public Cloud) : L4 CHF 0.27 HT de l'heure (relu), soit environ CHF 197 par mois allumé en continu. Éteint, le GPU reste facturé (FAQ Infomaniak, relue) : pas d'économie à l'éteindre.
- L'API de Whisper coûte 0,03 à 0,07 ct par minute d'audio (CHF 0.006 la minute de calcul, rapport 1:9 à 1:20). CHF 197 par mois, ce sont 300'000 à 650'000 minutes d'audio : **600 à 1'300 clients** à 500 minutes de dictée par mois.
- Vitesse : mesurée sur la RTX 3090 de Xavier, 35 à 65 fois le temps réel (large-v3), 75 à 140 fois (turbo). Une dictée de 30 minutes en 15 à 50 s, sans file d'attente.
- Conclusion : pas rentable avant plusieurs centaines de clients. À reconsidérer pour la vitesse seulement, si la file d'attente d'Infomaniak se révèle longue (le relevé des temps d'attente du sous-chantier 26 le dira).

## Conseil de prix

**CHF 39 par mois HT (42.16 TTC), 50 offres, et CHF 390 par an (deux mois offerts).**

- Au milieu du marché avec la voix : au-dessus de Renalto (30 € HT, environ CHF 28), sous Angebotsmeister (49 €, environ CHF 46) et sous Obat avec l'option voix (78 € HT).
- Au-dessus des logiciels suisses sans voix (Magic Heidi CHF 24.90 à 39, bexio 35 à 45), ce que la dictée justifie.
- Sous les CHF 50 que Luigi trouvait corrects, et loin des 100 qu'il trouvait trop chers.
- Marge de 90 % pour un client type, 75 % pour le pire ; 286 clients pour CHF 10'000 par mois.
- À CHF 49, il faut 225 clients au lieu de 286 ; à vérifier auprès d'autres artisans avant de monter.
