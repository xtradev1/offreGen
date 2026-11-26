# Concurrents et stratégie

Relevé du 18 novembre 2026, à la demande de Xavier. Remplace la section « Concurrents » de
`docs/plans/2026-11-07_voix-offre/26-couts-ia/PRIX-ABONNEMENT.md`, qui disait à tort « aucun produit suisse en
français ».

## Méthode et limites

- Quatre recherches déléguées (Opus), fiches détaillées dans `docs/strategie/concurrents-2026-11-18/` :
  `offerto.md`, `suisse-dach.md`, `francophones.md`, `avis-marche.md`, `offerto-technique.md`.
- Relu à la source par Claude (pages téléchargées le 18.11) : accueil, prix et confidentialité de Chiffr ; accueil de
  SecretarIA (prix, OVH) ; accueil et prix d'Opus ; accueil, en-têtes et confidentialité d'Offerto ; date du domaine
  getofferto.ch au registre (RDAP nic.ch).
- Aucun compte ouvert chez un concurrent : tout ce qui se passe après la connexion est inobservé (qualité réelle de
  la dictée, durée maximale, justesse des prix).
- Les autres faits viennent des fiches des agents, marqués « page officielle », « source secondaire » ou « non
  trouvé » dans les fiches.

## En bref

1. Trois outils suisses romands, en français, font déjà « je dicte, le devis sort » avec TVA 8,1 % et facture QR :
   **Chiffr**, **SecretarIA**, **Opus**. Tous sont sortis en 2026.
2. **Chiffr** est le plus dangereux : application iPhone, signature en ligne, relances, liaison bexio, prix de
   lancement à CHF 9.90 et 24.90 HT « garantis à vie ».
3. **Offerto** (alémanique) a six semaines, aucune vente visible, pas de français.
4. Ce qui distingue encore offreGen : **la voix et le texte traités en Suisse** (les quatre autres envoient l'IA aux
   États-Unis), et la dictée longue de la visite (jusqu'à 30 minutes, justesse mesurée). Le second point reste à
   prouver face à eux.
5. Le prix prévu (CHF 39 HT) est au-dessus de Chiffr et de SecretarIA.
6. Le coût de l'IA ne départage personne : moins de 5 % d'un abonnement, chez tous.

## Les concurrents directs en Suisse

### Ce qu'ils font

| | Chiffr | SecretarIA | Opus | Offerto | offreGen |
|---|---|---|---|---|---|
| Éditeur | Webie, agence web romande | Indépendant, Lausanne | Raison individuelle, Romont | Kaco GmbH, Stäfa, un associé | XtraDev |
| En ligne depuis | Application iPhone du 30.08.2026 | Conditions générales du 28.09.2026 | Liste des sous-traitants du 27.05.2026 | Domaine du 03.10.2026 | Pas encore en production |
| Langue | Français | Français | Français, allemand | Allemand | Français |
| Prix par mois | 9.90 ou 24.90 HT (lancement), puis 19.90 ou 39.90 | 0, ou 29 (lancement), puis 39 | 39, 89, 199 | 39 HT (50 premiers), puis 69 | 39 HT prévu |
| Limites | 10 devis (Solo), 1'000 (Pro) | 3 par mois gratuits, 100 en Pro | 60 à 800 crédits IA | Non trouvé | 50 offres |
| Entrée | Voix, texte, photo d'un ancien devis | Voix, texte, photo de notes | Voix ou texte dans un assistant | Courriel, texte, note dictée | Voix (jusqu'à 30 min) |
| Dictée dans d'autres langues | Portugais, italien, allemand | Portugais, espagnol, italien, albanais, anglais | Non trouvé | Suisse allemand | Non |
| Rangement par pièce | Non annoncé | Oui | Non annoncé | Non, par positions | Oui |
| Catalogue | Se construit à l'usage, import Excel | Oui | Import Excel | Tiré des anciennes offres ou de bexio | Saisi à la main |
| Photo, mesure | Photo de notes ; mesure non trouvée | Photo de notes ; mesure non trouvée | Photos ; plans non trouvés | Caméra bloquée | Non |
| Application | iPhone (5/5, 2 avis) ; Android par le navigateur | Site installable | Non trouvée | Site seulement | Site |
| Facture QR | Oui | Oui | Oui | Oui | Oui |
| Envoi par courriel | Oui | Oui | Oui | Oui (Gmail de l'artisan) | En cours |
| Acceptation en ligne | Oui, signature par code | Non trouvée | Oui | Oui | Non |
| Relances | Oui | Non trouvées | Oui | Non trouvées | Non |
| Liaison bexio | Oui | Non | Non trouvée (comptabilité intégrée) | Oui | Non |
| Données | Supabase à Zurich, Vercel à Francfort | OVH en France | Infomaniak à Genève, base à Zurich | Supabase à Zurich, Vercel à Francfort | Infomaniak |
| IA | OpenAI, contenus gardés jusqu'à 30 jours | OpenAI | Claude (Anthropic) ; transcription Deepgram, hors de Suisse | Claude (Anthropic), États-Unis | Whisper et Qwen chez Infomaniak |

### Avantages et inconvénients de chacun

**Chiffr** (chiffr.ch)
- Avantages : le plus complet après l'offre (signature, relances, acompte, rapprochement bancaire, avis Google,
  bexio) ; application iPhone ; prix très bas garanti à vie ; vocabulaire romand ; référencement déjà lancé (six
  pages comparatives du 10.11.2026, guides, prix des travaux).
- Inconvénients : IA chez OpenAI ; dictée libre sans parcours par pièce ; formule Solo limitée à 10 devis ; éditeur
  non nommé dans la politique de confidentialité ; 2 avis seulement ; leur comparatif contient une erreur sur
  Tolteck.

**SecretarIA** (mysecretaria.ch)
- Avantages : presque le même parcours que offreGen (peintre, une section par pièce) ; essai sans inscription ;
  formule gratuite ; dictée en plusieurs langues ; pages par ville et par métier.
- Inconvénients : données en France et IA chez OpenAI ; ni acceptation en ligne ni relances trouvées ; pas de liaison
  comptable ; un homme seul.

**Opus** (opus-ia.ch)
- Avantages : suite complète (comptabilité, salaires, heures) ; français et allemand ; chaque prix indique sa source ;
  sous-traitants publiés ; garantie « satisfait ou remboursé ».
- Inconvénients : outil de PME, lourd et cher pour un peintre seul ; crédits IA comptés ; IA et transcription hors de
  Suisse.

**Offerto** (getofferto.ch)
- Avantages : avance vite (bexio, facture QR, Gmail, programme partenaires et dix pages par métier en six semaines) ;
  programme pour fiduciaires et associations ; catalogue tiré des anciennes offres.
- Inconvénients : allemand seulement (« Die Oberfläche folgt, sobald der erste Romandie-Partner an Bord ist ») ;
  « noch 50 Plätze » sur 50, aucun client visible ; pas d'application, pas de photo ; note dictée courte après la
  visite, transcrite par le navigateur ; IA aux États-Unis.

**Côté alémanique, à surveiller** : Werkli (Horgen, CHF 50, brouillon d'offre depuis un récit en suisse allemand,
application iPhone et Android hors ligne), Offeroo (CHF 19, dictée des positions).

## Les outils français et belges

Aucun outil français lu ne mentionne la facture QR. Trois annoncent la Suisse :

| Outil | Voix vers devis | Prix | Signe d'arrivée en Suisse |
|---|---|---|---|
| Renalto (France) | Oui, application et WhatsApp, lignes par pièce | 0 + dès 4 € HT par devis ; 30 € HT par mois | « Suisse » proposée comme TVA par défaut ; 30'000 artisans inscrits annoncés |
| Vertuoza (Belgique) | Oui, « devis dicté en 45 secondes » | Sur demande | Site fr-ch, 11,2 M€ levés |
| Tolteck (France) | Non | CHF 270 HT par an | Site fr-ch, abonnement en francs |
| Obat (France) | Oui, option à 29 € HT | 49 € HT | obat.ch en page d'attente |

Le jour où Renalto ou Vertuoza ajoute la facture QR, ils arrivent avec des moyens sans rapport avec les nôtres.

## La technique et les coûts

- Offerto et Chiffr ont le même montage : Next.js chez Vercel (Francfort), Supabase (Zurich), Resend, une IA
  américaine. C'est un assemblage de services loués, montable en quelques semaines.
- Même offre (dictée de 10 minutes, 30 lignes, catalogue de 500 produits : 18'401 jetons envoyés, 1'641 reçus,
  banc du 17.11) :

| Modèle | Tarif par million de jetons (entrée / sortie) | Coût de l'extraction |
|---|---|---|
| Qwen 27B, Infomaniak (offreGen) | CHF 0.40 / 3.00 | 1,2 ct (mesuré) |
| Claude Haiku 5.5 | $0.10 / 0.50 | environ 0,3 ct |
| Claude Sonnet 5.5 | $2 / 10 | environ 5 ct |
| Claude Opus 5.5 | $4 / 20 | environ 11 ct |

- Tarif Infomaniak déduit de deux appels facturés (21 et 50 jetons : CHF 0.0001584 ; 1'208 et 192 : CHF 0.0010592).
  Tarifs Anthropic : grille du 3 novembre 2026. Un dollar compté pour un franc ; le découpage en jetons diffère d'un
  modèle à l'autre : ordres de grandeur.
- Par client et par mois (30 offres) : offreGen CHF 0.68 tout compris ; un concurrent sur Sonnet environ 1.60 ; sur
  Haiku moins de 0.10. Les frais Stripe (CHF 1.70 à 39 francs) dépassent le coût de l'IA.
- Conséquence utile : une offre coûte 2 centimes, donc un essai généreux sans carte ne coûte presque rien.

## Le marché

- Peinture et plâtrerie : 2'590 entreprises en Suisse romande (FR, VD, VS, NE, GE, JU), 8'988 en Suisse. Second
  œuvre : 8'367 en Suisse romande (OFS, STATENT 2024).
- 85 % des entreprises du second œuvre ont de 1 à 9 personnes (Suisse, 2023).
- 37 % des artisans utilisent un logiciel pour leurs offres (enquête Ofri 2020, 166 réponses : indication faible).
- CHF 10'000 par mois à 39 francs, ce sont 286 clients : 11 % de tous les peintres et plâtriers romands.

## Ce que disent les artisans (avis publics)

- Sur la voix et l'IA : très peu d'avis, aucun sur l'accent, le bruit ou une erreur de quantité. Personne n'a encore
  gagné ce terrain.
- Reproches fréquents aux logiciels : hausses de prix en cours de contrat, bogues après les mises à jour,
  application mobile réduite par rapport au bureau, support absent après la vente, engagement annuel.
- Raisons d'adoption : le devis fait sur place, la simplicité, un humain qui rappelle vite.
- Demandes sans réponse : acompte, relances, import des clients et des articles, pouvoir repartir avec ses données,
  fonctionner sans réseau.
- Comment les éditeurs vendent : négoces de matériaux (Tolteck chez Point P), associations (rabais aux membres),
  démonstration par téléphone, vente par le fondateur au contact.

## Où se situe offreGen

Ce qui tient :
- IA hébergée en Suisse, voix comprise. Parmi les cinq outils lus, c'est le seul.
- Dictée longue de la visite, rangée par pièce, avec contrôle du prix dicté contre le catalogue ; justesse mesurée
  sur banc (93 à 98 % jusqu'à 30 minutes).
- Coût par offre de 2 centimes.
- Un développeur sur place, qui peut aller voir l'artisan.

Ce qui manque :
- La production, le paiement, l'envoi par courriel.
- L'acceptation en ligne, les relances.
- Un catalogue qui démarre sans saisie (import, ou construit à l'usage).
- La dictée dans d'autres langues (portugais, italien, albanais), que Chiffr et SecretarIA annoncent.
- Une application installable et un essai sans friction.
- Le référencement : rien n'est en ligne.

## Recommandation

1. **Cesser de polir.** Accent jaune, nom de travail, plus de tour de maquette avant la production.
2. **Cette semaine** : envoi par courriel, production, Stripe.
3. **Prix** : s'aligner. CHF 29 HT par mois pour les 50 premiers, garanti, puis 39. Essai de 10 offres sans carte
   (coût : 20 centimes).
4. **Dans les trois semaines qui suivent** : acceptation en ligne avec « offre vue » ; import du catalogue depuis
   Excel et ajout au catalogue depuis la relecture ; essai de la dictée en portugais et en italien.
5. **Ne pas faire** : bexio, envoi par Gmail, lecture de courriels, application native.
6. **Vendre dès maintenant, en personne** : Luigi et dix peintres du Jura, du Jura bernois et de Neuchâtel ; deux ou
   trois négoces de peinture ; une fiduciaire. Objectif : 20 clients payants, puis décider de la suite.
7. **Dire ce que les autres ne peuvent pas dire** : « votre voix et les adresses de vos clients ne quittent pas la
   Suisse », avec la preuve (page des sous-traitants).
8. **Mesurer contre eux** : passer la même dictée de Luigi dans l'essai de SecretarIA et de Chiffr, et comparer les
   lignes. C'est la seule manière de savoir si la dictée longue est un avantage réel.

## Non vérifié, à ne pas affirmer en public

- La qualité de la dictée des concurrents et sa durée maximale.
- Le nombre de clients de chacun.
- Que l'IA d'Infomaniak traite tout en Suisse : à appuyer sur la page d'Infomaniak avant de l'écrire sur le site.
- L'intérêt réel des artisans pour l'hébergement suisse de l'IA : à demander aux dix premiers.
