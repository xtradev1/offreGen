# Contexte : application d'offres vocales pour artisans

Tu reprends le cadrage d'un projet de Xavier, discuté le 2026-11-05. Rien n'est construit. Ce document sépare ce qui a été **mesuré**, ce qui est **publié** par un fournisseur et ce qui reste une **hypothèse**. Ne présente jamais une hypothèse comme un fait.

## 1. Le produit
- Un artisan visite un logement et décrit les travaux à voix haute, jusqu'à 30 min (60 min au pire).
- Whisper transcrit l'audio, puis un LLM produit une offre structurée à partir du catalogue de l'artisan.
- L'artisan corrige ensuite à la voix (« ajoute une remise de 5 % », « le parquet du salon à 65 ») ou à l'écran.
- L'offre gère des variantes (section 7).
- Le catalogue de l'artisan s'enrichit avec l'usage (section 6).

## 2. Statut et décisions en attente
- **Statut :** idée. Aucun spike ouvert, rien n'est inscrit dans `IDEES.md`.
- **Périmètre :** le labo de Xavier accepte seulement l'auto-hébergé et le non commercial. Ce projet est commercial et passe par une API, il faut donc une décision de Xavier avant de continuer.
- **Code :** passer en vrai projet demande une décision explicite et une réécriture. Les scripts de mesure de la section 3 ne doivent pas être repris tels quels.

## 3. Mesures réelles (sur la machine de Xavier, RTX 3090 24 Go)

### Whisper (2026-11-05)
Audio de 600 s en français (150 s bouclées, 41 % de silence, 128 mots/min), faster-whisper fp16, filtre de silence actif :

| Modèle | Par lots (16) | Séquentiel, beam 5 |
|---|---|---|
| large-v3-turbo | x128 à x141 | x29 |
| large-v3 | x65 | x9,2 |

- **Vraie réunion :** turbo à x75 (2 h 26 en 116 s, spike du 2026-09-27 ; la note de ce spike indique x79, le calcul donne x75,5).
- **Estimation pour `large-v3` sur de la parole dense :** x35 par lots, x5 en séquentiel.
- **Leçon :** le réglage logiciel fait varier la vitesse d'un facteur 7 sur la même carte (lots contre séquentiel).

### Débit de parole
- 284 tokens/min et 182 mots/min, mesurés sur une réunion de 2 h 26 à trois personnes (41 406 tokens, 26 607 mots).
- C'est un majorant : un artisan seul parlera probablement moins vite.

### Correction vocale (2026-11-05)
- **Montage :** `qwen3.8:27b` en local via Ollama, sans raisonnement, temperature 0, `num_ctx` 4096. Offre fictive de 8 lignes, 6 outils (`update_line`, `add_line`, `remove_line`, `set_global_discount`, `set_line_discount`, `ask_clarification`).
- **Commandes :** 12 commandes passées 3 fois chacune, dont 3 pièges : « change le prix du parquet » (deux parquets possibles), « mets 70 », « le client va peut-être demander une remise ».
- **Comptage :** un nombre aléatoire en tête du prompt casse le cache de préfixe, pour que `prompt_eval_count` compte tous les tokens.
- **Résultats :**

| Mesure | Valeur |
|---|---|
| Commandes correctes | 36 sur 36, pièges compris |
| Tokens d'entrée | 868 à 887 |
| Tokens de sortie | 42 en médiane, 109 au maximum |
| Coût au tarif Infomaniak de Qwen3.8 | 0,00058 CHF en médiane, 0,00078 CHF au maximum (Whisper compris : 10 s à x10) |
| Latence locale | 3,45 s en médiane |
| Pic de VRAM | 21 112 Mo |

- **Limites :** commandes propres écrites à la main, sans erreur de Whisper ni bruit de chantier, une seule offre fictive.
- **Gemma 4 31B :** non testé. Le garde-fou l'a déchargé à 23 312 Mo, et la limite de Xavier est de 95 % de VRAM (23 347 Mo).
- **Scripts (dossier temporaire, à copier dans le spike s'il est ouvert) :** `C:\Users\xavie\AppData\Local\Temp\claude\61ede551400d91d9\09296dee-21ec-4812-87b6-d3ba5c27c3c8\scratchpad\` (`bench_whisper.py`, `test_correction.py`, `cout_offre.py`, `cout_sans_boucle.py`, `prix_abo.py`).

## 4. Infomaniak AI Services (page des prix lue le 2026-11-05)
Prix en CHF HT par million de tokens (entrée / sortie) :

| Modèle | Entrée | Sortie |
|---|---|---|
| google/gemma-4-31B-it | 0.20 | 0.40 |
| mistralai/Mistral-Small-4-119B-2603 | 0.20 | 0.75 |
| mistralai/Ministral-3-14B-Instruct-2512 | 0.30 | 0.40 |
| Qwen/Qwen3.8-27B-FP8 | 0.40 | 3.00 |
| moonshotai/Kimi-K2.6 | 0.60 | 3.00 |
| swiss-ai/Apertus-v1.5-70B | 0.70 | 2.50 |
| Qwen/Qwen3.5-397B-A17B-FP8 | 0.80 | 3.60 |

- **Whisper V3 :** CHF 0.006 par minute de **calcul**. Citation de la page : « It is this generation time that is billed ». Le prorata n'est confirmé que pour les images ; un minimum par requête n'est pas exclu.
- **Cache :** aucun tarif réduit publié, le mot n'apparaît pas sur la page.
- **Non publiés :** les limites de débit, les modèles qui gèrent les appels d'outils, et les GPU qui servent AI Services. La gamme cloud d'Infomaniak liste B300, H200, H100, A100, L40S, L4, A2 et T4.
- **Kimi K2.6 moins cher que Qwen3.5 397B :** aucune raison publiée, et le matériel ne l'explique pas. Kimi a 1 T de paramètres, 32 G actifs en INT4, 595 Go ; Qwen a 397 G de paramètres, 17 G actifs en FP8, 406 Go.
- **Lecture de la page :** WebFetch la rend vide. Elle se lit avec `curl` suivi d'une extraction du HTML.

## 5. Architecture et règles d'optimisation (obligatoires)
Le coût vient surtout des tokens d'entrée. Une boucle d'outils classique renvoie tout le contexte à chaque appel (instructions, outils, transcription, historique) : environ 500 000 tokens pour une offre de 30 min, soit 87 % de la facture.

### Génération d'une offre
1. Whisper transcrit l'audio.
2. Un appel au LLM, sans outils, transforme la transcription en lignes JSON (désignation, quantité, unité, pièce, prix s'il est dit).
3. Le code cherche chaque ligne dans le catalogue de l'artisan et fait tous les calculs. Le LLM ne calcule jamais un total.
4. Un second appel au LLM, seulement si nécessaire, reçoit les lignes ambiguës avec 3 à 5 candidats du catalogue.
5. L'artisan relit l'offre. La transcription n'est plus jamais renvoyée au LLM.

### Corrections
- **Ce que reçoit le LLM :** les instructions, les outils, l'offre compacte (une ligne de texte par poste) et la commande. Sans transcription ni historique.
- **Ce qu'il renvoie :** des appels d'outils (modifications ciblées), jamais l'offre entière. Le code les applique et les valide (identifiant inconnu = refus).
- **Commande floue ou incomplète :** `ask_clarification`, sans rien modifier.
- **Correction à l'écran :** coût nul, à garder à côté de la voix.

### Plafonds techniques
- 2 appels au LLM par génération, une seule relance.
- Longueur de sortie plafonnée, raisonnement désactivé sauf preuve qu'il améliore le résultat.
- Le catalogue n'est jamais envoyé en entier : seulement les candidats trouvés par le code.

### Contre les abus
- **Corrections :** non facturées. Seule limite, contre les scripts automatisés : environ 30 par heure (proposition).
- **Quota :** les offres générées et les minutes d'audio. Une régénération complète compte comme une nouvelle offre ; une offre avec variantes compte pour une seule.
- **Surveillance :** une alerte quand le coût mensuel d'un client dépasse un seuil.

### Cache
Un bonus, à ne pas compter dans le modèle économique : Infomaniak n'annonce aucune remise, et cette architecture répète très peu de contexte.

## 6. Catalogue de l'artisan
- **Stockage :** une base de données propre à chaque artisan, hors de la mémoire du LLM : désignation, unité, prix, synonymes, date du dernier usage.
- **Recherche :** « mon parquet » est retrouvé par le code. Si plusieurs articles correspondent, l'application demande lequel.
- **Apprentissage :** après validation d'une offre, l'application propose d'ajouter les nouveaux articles au catalogue.
- **Prix :**
  - Un prix dit pour une offre ne remplace jamais le prix du catalogue sans confirmation.
  - Un prix ancien est signalé.
  - Sans prix connu, la ligne reste « prix manquant » et le LLM n'invente rien.
- **Non testés :** le catalogue vide au premier jour (prévoir un import de liste existante) et l'article mal reconnu, qui donne une offre fausse d'apparence correcte.
- **Pour plus tard, à inscrire comme idées distinctes :** apprendre la manière dont l'artisan construit ses offres (ensembles d'articles, par exemple parquet + sous-couche + plinthes), et la facturation, qui ajoute des obligations légales.

## 7. Variantes
- **Alternative :** le client choisit une option parmi plusieurs (chêne à 60 CHF/m² ou stratifié à 35 CHF/m²).
- **Option :** une plus-value que le client coche ou non.
- **Version :** une offre entière copiée par le code, sans appel au LLM (rénovation complète ou salon seul).
- **Données :** chaque ligne appartient à la base, à un groupe de variantes ou à une option.
- **Calculs :** le code produit un total par variante.
- **Règle de remise à fixer :** sur chaque total de variante, et sur les options ou non.
- **Coût estimé :** 10 lignes de plus coûtent environ 0,002 CHF par offre et 0,0001 CHF par correction.
- **Risque principal :** confondre « remplace » et « mets en variante ». « Le client hésite entre chêne et stratifié » crée une variante, « mets du stratifié à la place » modifie la ligne, « je pense qu'on part sur du stratifié » demande une question.

## 8. Économie (hypothèses, sauf les prix)

### Coût par offre (Qwen3.8 27B, CHF HT, Whisper compris)
| Scénario | Boucle d'outils | Architecture de la section 5 |
|---|---|---|
| 15 min, 4 corrections | 0,188 | 0,017 |
| 30 min, 4 corrections | 0,245 | 0,030 |
| Lourd, avec boucle : 30 min, 40 appels d'outils, 10 régénérations, raisonnement 20 000 tokens | 1,61 | - |
| Lourd, sans boucle : 60 min, 60 lignes, 50 corrections, Whisper x5, une relance | - | 0,20 |

Une correction : 0,023 CHF avec boucle, 0,0006 à 0,0008 CHF sans boucle (mesuré sur 8 lignes, estimé sur 30). Dans l'architecture de la section 5, Whisper devient le premier poste.

### Volume d'offres
Aucune statistique fiable trouvée. Estimation : 5 à 15 offres par mois pour un indépendant, 10 à 30 pour une petite équipe, 100 pour une grosse entreprise ou un métier à forte rotation. À vérifier auprès de 10 artisans (nombre d'offres envoyées l'an dernier).

### Prix de vente proposé (HT)
| Formule | Prix par mois | Offres incluses | Marge au plafond, cas central | Marge au plafond, cas lourd |
|---|---|---|---|---|
| Solo | 49 CHF | 20 | 96 % | 89 % |
| Équipe | 89 CHF | 50 | 95 % | 86 % |
| Offre supplémentaire | 2,50 CHF | - | - | - |

Ces marges couvrent seulement l'IA et environ 3 % de frais de paiement (hypothèse). Elles excluent l'hébergement, le support, la prospection et les impôts.

Avec 300 CHF de frais fixes et un revenu visé de 10 000 CHF par mois, il faut environ 235 clients à 49 CHF (calcul fait avec l'ancienne architecture). Le frein commercial est l'acquisition de clients et l'import de leur catalogue, pas le coût de l'IA.

### Concurrents (pages d'éditeurs, non revérifiées)
| Produit | Prix par mois | Offres incluses |
|---|---|---|
| Speak to Quote | 11,45 à 19,95 £ | selon formule |
| Jottle | 17 / 29 / 59 £ | 50 / 200 / 500 devis |
| Quoted | 19 / 39 $ | 75 / 200 devis |
| Batappli | 39 / 79 € HT | 8 / 16 devis IA |
| Tolteck | 19 à 25 € HT | sans IA citée |
| bexio | 35 à 45 CHF | gestion, sans voix |

Le prix de 49 CHF est dans le haut du marché des applications vocales. Il se justifie seulement par le français, le catalogue de l'artisan, l'hébergement suisse et des offres justes.

### Modèles
- **Premier candidat :** Qwen3.8 27B.
- **Candidat bon marché :** Gemma 4 31B, à tester.
- **Référence de qualité :** Kimi K2.6.
- **Écarté sauf preuve :** Qwen3.5 397B, le plus cher des cinq.
- **Règle :** choisir sur la justesse. L'écart de prix entre modèles est de quelques centimes par offre.

## 9. Prochains tests, dans l'ordre
1. **Extraction complète (risque principal).**
   - Données : 5 vraies visites enregistrées, avec le devis que l'artisan aurait rédigé.
   - Modèles : 3.
   - Échec si : une seule quantité ou un seul prix faux est présenté comme sûr, ou si la correction prend plus de temps que la rédaction à la main.
   - Timebox : 2 jours.
2. **Variantes.**
   - Données : 10 commandes, dont 4 phrases floues.
   - Échec si : une ligne est écrasée ou mal rattachée sans question préalable.
3. **Disposition à payer.** Proposer 49 CHF à 10 artisans avec un engagement réel (précommande ou acompte).
4. **Infomaniak.**
   - Ratio réel de Whisper : un fichier de 10 min et un de 60 min.
   - Minimum facturé par requête.
   - Appels d'outils et limites de débit.
   - Cache : envoyer deux fois le même prompt et lire `cached_tokens`.

## 10. Points à trancher par Xavier
- Le périmètre du labo (commercial et API) ou la sortie du projet hors du labo.
- L'ouverture d'un spike et son inscription dans `IDEES.md`.
- L'accès à de vrais enregistrements de visite.
- Les types de variantes à couvrir et la règle de remise.
- La cible : indépendants ou équipes.
- La liaison avec un logiciel de facturation.
- Le cadre LPD, puisque des adresses et l'état des logements partent chez un tiers.

## Sources
- Infomaniak, prix : https://www.infomaniak.com/en/hosting/ai-services/prices
- Infomaniak, cloud : https://www.infomaniak.com/en/hosting/public-cloud
- Kimi K2.6 : https://huggingface.co/moonshotai/Kimi-K2.6
- Qwen3.5 397B : https://huggingface.co/Qwen/Qwen3.5-397B-A17B-FP8
- Jottle : https://jottle.pages.dev/pricing
- Quoted : https://quoted.it.com/terms
- Speak to Quote : https://www.softwareadvice.com.au/software/696237/Speak-to-Quote
- Logiciels de devis du bâtiment : https://independant.io/logiciel-devis-facture-batiment/
