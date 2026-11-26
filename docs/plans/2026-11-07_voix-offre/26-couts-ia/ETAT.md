---
chantier: De la voix à l'offre, 26 coût de l'IA
slug: 2026-11-07_voix-offre/26-couts-ia
phase: 4
statut: en_cours
gate_attendu: aucun
profil: .claude/atoz-profile.md
---

## Prochaine action

Écrire `PLAN.md` (lots, tests), y compris le lot « offre extrême » (délai de 300 s, limite de jetons de sortie), puis ajuster les sous-tâches Origin et implémenter.

## En attente de toi

- Créer dans le Manager Infomaniak un produit IA pour la production, et me donner son jeton le moment venu (pas dans la conversation : je te dirai où le poser).

## Suivi Origin

- Projet 44 offreGen, tâche 422 « Coût de l'IA : statistiques d'exploitation (sous-chantier 26) ».
- Sous-tâches : 423 spec et maquette, 424 jetons et coût de chaque extraction, 425 relevé quotidien Infomaniak, 426 écran de l'exploitant, 427 revue et recette.
- Conversation rattachée à 423 le 2026-11-17.

## Décisions

- 2026-11-17, Xavier : « c'est important pour les statistiques que j'aie le coût global des requêtes API » ; « juste pour moi, comme statistiques d'exploitation » (aucun écran pour l'artisan).
- 2026-11-17, Xavier : Infomaniak facture Whisper au temps de calcul du serveur, pas à la durée de l'audio.
- 2026-11-17, Xavier (F1) : « accessible avec des accès admin, pour que si un jour j'ai des collaborateurs ils y aient aussi accès, enfin si je le décide ». Droit administrateur sur le compte, donné et retiré par commande.
- 2026-11-17, Claude : F3 relevé chaque nuit, 7 jours réécrits ; F4 tarifs dans le code, datés. Restent pour Xavier : F2 et l'écran.
- 2026-11-17 : phase 1 (spec) et phase 3 (maquette) présentées ensemble, une seule attente : le chantier est petit et les décisions ouvertes tiennent dans la maquette.
- 2026-11-17, Xavier, sur la maquette du coût : « je préférerais franchement un écran admin vraiment séparé », « une partie dédiée » avec statistiques et autres éléments ; choisir le mois, l'année, ou une date de début et de fin ; objectif 1000 utilisateurs : pas tous les artisans, les 10 ou 20 plus gros, triés par nombre d'offres et par offres les plus chères ; analyser de façon anonyme pourquoi une offre coûte plus (produits, lignes, prix par ligne) ; écran validé, téléphone compris ; F2 = A, prorata (« on sait que c'est une estimation, il faut qu'on affine avec les données ») ; un produit Infomaniak séparé, autre jeton, possible ; plus tard, dans l'administration : la rentabilité (abonnements, frais, coût de l'IA, résultat), le mois passé, en direct et à l'avance.
- 2026-11-17 : la spec devient « partie administration, coût de l'IA et rentabilité ». Sous-tâches Origin à ajuster quand le plan sera écrit (estimation de 9 h trop basse pour cinq sections).
- 2026-11-17, Xavier : « on va limiter du coup à 30 minutes » ; plus tard, peut-être une limite plus longue pour les gros abonnements seulement. Fait : `DUREE_MAX_SECONDES` à 1800 par défaut, `LIGNES_MAX` de 200 à 300 (banc : 300 postes en 30 min extraits avec 2 oublis).
- 2026-11-17, Xavier : le téléphone garde l'enregistrement jusqu'à ce que le serveur l'ait reçu et que l'offre soit traitée ; ensuite il peut être supprimé. À cadrer (lot à part, voir Prochaine action).
- 2026-11-17, Xavier, maquette d'administration : G1 à G5 « Valider » (partie séparée et son menu ; période mois, année ou deux dates avec raccourcis ; artisans top 10 ou 20, tris, recherche, signal ; offres en analyse anonyme ; rentabilité plus tard, en direct et en projection). Gate visuel franchi.

## Journal

- 2026-11-17 : relevé `GET /1/ai/357/consumptions` lu, coût de Qwen vérifié égal à jetons × tarif ; projection donnée à Xavier (pire offre observée CHF 0.0020 avec le 27B, 50 offres par mois au prix × 10 : CHF 1.00). Il n'existe que deux droits d'accès (public, artisan) : la manière de réserver l'écran à l'exploitant est la décision F1.
- 2026-11-17, question de Xavier sur « 19 643 / 4 175 » : la colonne « Consommation » mêlait jetons envoyés, jetons reçus et temps de calcul, illisible. Maquette corrigée : trois colonnes nommées, une ligne d'explication d'un jeton, totaux (27 379 et 5 645 jetons, 19 s). L'écran réel suivra ces colonnes.
- 2026-11-17 : recherche demandée par Xavier, un coût par requête chez Infomaniak ? Spécification officielle `https://developer.infomaniak.com/openapi.json` (4,9 Mo, téléchargée) : points d'entrée de l'IA relevés ; aucun ne rend de coût par requête. Discussion : `usage` (jetons). Transcription : asynchrone, résultat sans durée ni coût. Consommation : pagination et tri seulement, schéma des éléments non décrit. Pages publiques : la grille parle d'un « temps de génération » selon la taille du fichier ; les conditions d'utilisation, de « durée du fichier » ; l'observé (19,2 s d'audio, 1 s facturée) suit le temps de calcul.
- 2026-11-17, simulation demandée par Xavier, offre extrême (1 h de dictée, catalogue de 500 produits), sans appel réel : environ CHF 0.07 avec le 27B (Whisper 1,8 ct au rapport 1:20, Qwen 5,2 ct dont 3,6 ct de sortie). Constats dans le code : aucune limite d'une heure trouvée (seul plafond : 200 Mo dans `ValidateurAudio::TAILLE_MAX`) ; l'extraction n'a pas de `max_tokens` et un délai de 300 s : environ 12 000 jetons de sortie risquent de dépasser ce délai ou la limite par défaut du modèle. À essayer sur le site de test avec une longue dictée inventée.
- 2026-11-17, Xavier : « il faut rajouter la limite à 1 heure » (30 minutes envisagées, 1 heure retenue selon le coût). Fait hors plan, petit et urgent : `DUREE_MAX_SECONDES` (3600 par défaut), import refusé au-delà (tolérance 5 s), enregistrement prévenu 5 min avant puis arrêté et envoyé seul.
- 2026-11-17, limites mesurées chez Infomaniak (`GET /1/ai/models`, puis une demande à `max_tokens` démesuré) : Qwen3.8-27B, fenêtre totale de 100 032 jetons (envoyé plus reçu), statut bêta ; 397B, 204 800. Sans `max_tokens`, vLLM laisse au modèle toute la place restante : pas de coupure silencieuse tant que la fenêtre suffit. Vitesse du 27B : 185 et 301 jetons/s (deux essais de 1500 jetons) ; 12 000 jetons prennent environ une minute, sous le délai de 300 s. Limite réelle : la fenêtre ; catalogue plus dictée d'une heure plus réponse doivent tenir dans 100 000 jetons, soit environ 1 400 produits au plus.
- 2026-11-17, robustesse vérifiée dans le code : l'audio est rangé sur le serveur et inscrit en base avant tout traitement ; cron (`bin/traiter-file`, chaque minute, constaté dans la crontab du site de test) reprend à l'étape enregistrée, 5 tentatives, transcription gardée en base avant l'extraction. Point faible : avant l'envoi, l'enregistrement n'existe que dans la mémoire du navigateur.
- 2026-11-17, banc des dictées longues demandé par Xavier (`scratchpad/banc-longues.php`) : catalogue inventé de 500 produits, dictées inventées de 10, 30 et 60 min (150 mots/min, 3 postes/min, phrases parasites et corrections), vrai appel et vraie validation, 27B. Résultat : 97 %, 98 %, 84 % de lignes justes. À 60 min, 20 postes oubliés sur 180, répartis dans toute la dictée (pas une coupure en fin), et 9 variantes voisines confondues. Le catalogue pèse environ 32 jetons par produit (16 000 pour 500), une ligne environ 57 jetons de réponse. Coût réel d'une heure : CHF 0.042 pour Qwen, environ 0.06 avec Whisper. Limites du banc : une seule passe par durée, dictée plus propre qu'une vraie, catalogue aux variantes très voisines (plus dur que la réalité pour le choix du produit).
- 2026-11-17 : `ExtracteurApi::LIGNES_MAX = 200` jette sans prévenir toute ligne au-delà de la 200e. Recherche lancée (trois agents Sonnet) sur le nombre de lignes des offres réelles et la taille des catalogues avant de trancher.
- 2026-11-17, résultat de la recherche (détail et sources : `scratchpad/recherche-lignes/{metiers,extremes,catalogue}.md`, non versionnés) : aucun devis d'artisan trouvé au-delà de 200 lignes ; un corps de métier plafonne vers 100 (appel d'offres sanitaire d'une piscine : 101 positions) ; rénovation de maison publiée à 132 (Saint-Gobain, compte de l'outil) ; descriptifs allemands par lot entre 34 et 143 ; Stripe plafonne une facture à 250 postes ; aucune limite publiée chez Bexio, Sorba, Obat et autres. Contrôlé par Claude : le devis réel de Clichy (Monsieur Peinture, 37 postes, 11 lots) met la cuisine et l'électricité en une ligne forfaitaire chacune, ce qui confirme l'intuition de Xavier ; Obat annonce 30 000 ouvrages dans sa bibliothèque. Aucun chiffre trouvé sur la part d'un catalogue réellement utilisée, ni sur la marge suisse des fournitures externes : questions pour les artisans.
- 2026-11-17 : seule l'extraction est plafonnée à 200 lignes ; l'ajout à la main ne l'est pas (grep de `LIGNES_MAX` sur src et templates).
- 2026-11-17, banc dense demandé par Xavier (30 min, un poste toutes les 10 s puis toutes les 6 s ; `--postes=` ajouté au banc, validation par tranches pour mesurer au-delà du plafond). Les « manquantes » du banc mêlaient deux choses : `apparier.php` sépare les vrais oublis des variantes voisines (quantité juste, extrait juste, mauvais produit du catalogue). Vrais oublis : 10 min 1 sur 30 ; 30 min 0 sur 90 ; 30 min dense 5 sur 180 et 2 sur 300 ; 60 min 20 sur 180. Les oublis suivent la longueur de la dictée, pas le nombre de postes. Variantes confondues : 0 à 13 par dictée, dues au catalogue inventé (5 surfaces × 5 options presque identiques). Une seule passe par cas : l'écart 89 % / 96 % entre les deux dictées denses est du bruit. 300 postes en 30 min supposent 215 mots par minute, plus vite qu'un débit normal (150).
- 2026-11-17, Xavier : après chaque appel à Whisper ou Qwen, relire le relevé de consommation et faire le delta, pour suivre le coût et le temps. Vérifié (`delta.php`) : la ligne du jour bouge moins de 15 s après la réponse. Qwen : delta égal au calcul sur `usage` (21 / 50 jetons, CHF 0.0001584), donc redondant. Whisper : seule source par appel, 27,9 s d'audio facturées 3 s (CHF 0.0003), alors que 19,2 s l'avaient été 1 s : le rapport de 1:20 ne tient pas, compter 1:9 à 1:20. Attente du résultat Whisper pour le même fichier : 301 s puis 47 s (envoi 0,2 s) ; c'est la file d'Infomaniak, non facturée. Limite du delta : deux appels simultanés (deux artisans) ne se séparent plus ; on ne l'attribue que si le relevé compte un seul appel de plus, sinon prorata (F2). À mettre au plan, lot du journal des appels (Origin 424).
- 2026-11-17, réunion de Xavier avec Luigi, peintre (`docs/terrain/2026-11-17_luigi-peintre.md`) : 200 lignes « largement assez », une rénovation fait plutôt 30 à 40 lignes. Le plafond de 300 garde donc une marge large.
- 2026-11-17, question de Xavier : l'IA peut-elle ranger chaque poste dans sa pièce ? Banc `--pieces` (champ « piece » ajouté à l'outil, une phrase de consigne) : 10 min, pièce juste sur 27 lignes justes sur 27 ; 30 min, 88 sur 88. Plus dur, `--implicites` (pièce annoncée une fois, « On passe maintenant à la cuisine. », puis « cette pièce ») : 85 sur 85. Étiquettes rendues propres (« salon du deuxième étage », « général » pour les forfaits de fin), relues dans `reponse-30.json`. Limite : une passe, dictée inventée ; une vraie dictée mêle des allers-retours entre pièces. Rien dans l'application : l'offre groupe ses lignes par catégorie du catalogue, le choix pièce ou catégorie est à maquetter.
- 2026-11-17 : limite à 30 min et plafond à 300 lignes livrés (commit 22fe2d7), déployés sur le test. Le `.env` du test ne fixe pas `DUREE_MAX_SECONDES` (constaté par SSH) : le défaut 1800 s'y applique.

## Preuves

```
$ php conso.php : GET /1/ai/357/consumptions HTTP 200 ; 16.11 Qwen3.8-27B 1208 / 192 jetons, cost 0.0010592 ; whisper 00:00:01, 0.0001
$ calcul : 1208 × 0.40 / 1e6 + 192 × 3.00 / 1e6 = 0.0010592
$ php sortie-max.php : Qwen3.8-27B « max_tokens=1000000 cannot be greater than max_model_len=100032 » ; 397B « max_model_len=max_total_tokens=204800 »
$ php vitesse.php : essai 1, 1500 jetons en 8.1 s (185 jetons/s) ; essai 2, 1500 jetons en 5.0 s (301 jetons/s)
$ phpunit (limite d'une heure) : OK (475 tests, 2936 assertions) ; phpstan : No errors
$ node limite-duree.js (serveur local, limite à 12 s, micro simulé) : à 3 s « Parlez, pièce par pièce. » ; à 10,5 s « Encore 1 min, puis l'enregistrement s'arrête et part. » ; à 16,5 s état « envoi », POST /import 303 ; erreurs JS : aucune
$ php banc-longues.php 10 30 60 (Qwen3.8-27B) :
=== 10 min : 1501 mots, 30 lignes attendues, 29 proposées (29 gardées) ; justes 29 (97 %), quantité fausse 0, manquantes 1, en trop 0 ; 18401 jetons envoyés, 1641 reçus, 7 s, CHF 0.0123
=== 30 min : 4503 mots, 90 lignes attendues, 90 proposées (90 gardées) ; justes 88 (98 %), quantité fausse 0, manquantes 2, en trop 2 ; 22637 jetons envoyés, 4963 reçus, 20 s, CHF 0.0239
=== 60 min : 9003 mots, 180 lignes attendues, 160 proposées (160 gardées) ; justes 151 (84 %), quantité fausse 0, manquantes 29, en trop 9 ; 28910 jetons envoyés, 10278 reçus, 39 s, CHF 0.0424
position des manquantes à 60 min (% du texte) : 0, 5, 5, 13, 14, 14, 18, 19, 23, 23, 25, 26, 26, 32, 40, 51, 52, 56, 66, 70, 72, 75, 76, 79, 81, 91, 92, 98, 98
$ php banc-longues.php 30 --postes=6 ; php banc-longues.php 30 --postes=10
=== 30 min, 6 postes/min : 4506 mots, 180 lignes attendues, 175 proposées ; justes 160 (89 %), quantité fausse 2, manquantes 18, en trop 13 ; 22832 envoyés, 9764 reçus, 49 s, CHF 0.0384
=== 30 min, 10 postes/min : 6441 mots, 300 lignes attendues, 299 proposées ; justes 287 (96 %), quantité fausse 1, manquantes 12, en trop 11 ; 26120 envoyés, 16639 reçus, 65 s, CHF 0.0604
$ php apparier.php manquantes-{10,30,60,30-p6,30-p10}.txt
10 min : 1 = 0 variante + 1 oubli ; 30 min : 2 = 2 + 0 ; 60 min : 29 = 9 + 20 ; 30 min dense 180 : 18 = 13 + 5 ; 30 min dense 300 : 12 = 10 + 2
$ phpunit (30 min, 300 lignes) : OK (476 tests, 2938 assertions) ; phpstan : No errors ; tests nouveaux rouges avant le code (« actual size 200 matches expected size 300 »)
$ deployer-test.sh : SONDE VERTE, DÉPLOYÉ 22fe2d7
$ php delta.php qwen ; php delta.php whisper
appel qwen : 21 jetons envoyés, 50 reçus ; attendu CHF 0.0001584
relevé changé 15 s après la réponse : +1 appel(s), CHF +0.0001584 ; Qwen3.8-27B 126406/47660 -> 126427/47710
appel whisper : audio 27,9 s, résultat en 301.2 s (370 caractères)
relevé changé 15 s après la réponse : +1 appel(s), CHF +0.0003000 ; whisper {"duration":"00:00:03"}
$ php whisper-temps.php
envoi : 0.2 s ; relecture 24 à 47 s : prêt ; attente du résultat : 47.2 s
```
