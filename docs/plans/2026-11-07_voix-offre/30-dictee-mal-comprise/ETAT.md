---
chantier: Dictée mal comprise (sous-chantier 30 de voix-offre)
slug: 2026-11-07_voix-offre/30-dictee-mal-comprise
phase: 5
statut: en_cours
gate_attendu: aucun
profil: .claude/atoz-profile.md
---

## Prochaine action

Lot 3 (traceur, de la dictée douteuse à la fenêtre de choix), sous-tâche Origin 518, dans `~/offreGen-sc30`, base `offregen_sc30_test` (son `.env.test.local` porte déjà `DB_NOM=offregen_sc30_test`). Le lot 2 est commité et poussé le 2026-11-24.

À savoir à la reprise :
- Ordre des migrations : la mienne, `Version20261122183001`, a un numéro plus ancien que deux migrations arrivées sur la branche entre-temps (`Version20261124100001`, `Version20261124140001`). Doctrine joue toute migration absente de la table des versions, quel que soit son rang ; à vérifier au déploiement du lot 7 (`migrations:status` sur le test avant et après), car le test aura déjà joué celles du 24.
- Scripts du banc (lot 1 et enquête Infomaniak) recopiés dans `banc/` de ce dossier, sans audio et sans clé (ils lisent le `.env` du test). Ils servent au lot 4 : `banc_pauses.sh` est le modèle de la relance coupée aux pauses, `analyse_calib.py` celui du verdict.
- Copies des audios du test dans le scratchpad de la session du 2026-11-21 (`scratchpad/audio/`, 22 fichiers) : données inventées, hors dépôt, à supprimer ; le scratchpad peut disparaître, les audios restent sur le test.
- À la clôture du chantier : supprimer la base `offregen_sc30_test` (root : `DROP DATABASE`, puis retirer les droits de `offregen_app` et `offregen_structure`) et la copie `~/offreGen-sc30`.
- Non essayé : `silencedetect` de ffmpeg sur les vraies dictées (les pauses de l'essai venaient des repères du Whisper local) ; changement de micro sans couper l'enregistrement sur Safari (décision fragile 2) ; détection du Bluetooth sur iPhone.

## En attente de toi

- D4 (lecteur seulement sur les dictées douteuses) contredit ta demande du 21 ; appliqué tel que choisi, à confirmer.
- Ma lecture de « moins familier les textes » : ton plus neutre.
- Finir la phrase du 2026-11-21 : « ce matin, vu que j'avais pas trop le… ».

## Suivi Origin

- Projet 44 (offreGen), tâche 514 « Dictée mal comprise : alerter au lieu d'une offre fausse (sous-chantier 30) », 12 h.
- Sous-tâches : 515 diagnostic, SPEC, maquette, D1 à D8 ; 516 calibrage ; 517 mesure, transcription détaillée, verdict ; 518 fenêtre de choix, lecteur, refaire ; 519 jauge et Bluetooth ; 520 revue et recette.

## Journal

- 2026-11-21 : chantier ouvert après les offres 60 et 61 du compte commercial (Aliette, 10h07 et 10h09) sur le test. Diagnostic par la base, l'audio, Whisper local et un banc chez Infomaniak (preuves ci-dessous).
- 2026-11-21 : Xavier confirme que les données du test sont inventées par son équipe, accès maintenu.
- 2026-11-21 : phase 0 sautée, le profil existe. Il est en retard (il dit encore « commandes à créer » et « accès au test à décider ») : à mettre à jour hors de ce sous-chantier.
- 2026-11-21 : phase 2 sautée, sous-chantier assez petit pour un seul plan.
- 2026-11-21 : SPEC écrite, gate intention ouvert.
- 2026-11-21 : Xavier demande la maquette sans répondre à Q1 à Q6, et ajoute deux idées : message quand le micro est en Bluetooth (Aliette dictait dans la voiture, siège passager, Bluetooth de la voiture : le conducteur couvrait sa voix), volume analysé en direct avec un message quand il est trop faible. Gate intention franchi par sa demande ; Q1 à Q6 reposées dans la maquette (D3 à D8), avec D1 et D2 pour ses deux idées.
- 2026-11-21 : maquette écrite (phase 3), contrôlée par check.js et des captures pleine page en clair et en sombre (bureau 1440, téléphone 390), aucune erreur JS. Couleur de l'onde recalée : moyenne en énergie sur 3 s, seuil -38 dB, seul le passage du conducteur ressort en vert sur l'offre 61. Gate visuel ouvert.
- 2026-11-21 : gate visuel franchi, réponses D1 à D8 de Xavier (texte collé ci-dessous). SPEC corrigée (section 3 réécrite), tâche Origin 514 créée, ligne 30 ajoutée à l'index de voix-offre.
- 2026-11-21 : phase 4, PLAN.md écrit (contrat technique, sept lots). Flux tracé dans le code : `Traiteur`, `FileTraitement`, `Statut`, `EnregistrementControleur`, `app.js` (enregistreur et abri), `accueil.html.twig`.
- 2026-11-21 : lot 1 (calibrage) fait sur les 22 enregistrements du test. Seuils calibrés retenus, aucune dictée normale en douteux. Trouvé un second type de panne (dictée 14) : voix nette, transcription tronquée chez Infomaniak. Question posée à Xavier. Mesure par tiers de seconde dans le banc (filtre avant le rééchantillonnage) : sans effet sur les centiles, à corriger dans `MesureVoix` (aresample dans la chaîne).
- 2026-11-21 : Xavier demande pourquoi le local comprend mieux qu'Infomaniak, et le coût d'une découpe. Enquête (preuves ci-dessous) : Infomaniak fait tourner Whisper V3 avec un filtre de silence et sans repères de temps, réglages imposés (nos paramètres sont ignorés) ; reproduit en local au mot près sur l'offre 61. Coupe aux pauses : la dictée 14 remonte à 118 mots, la 19 (voix faible) non. Les longues dictées normales sortent complètes : pas de découpe systématique.
- 2026-11-22 : Xavier valide la relance coupée aux pauses (« ok ça me va ») et demande l'impact sur les tests de qualité. Constat : les bancs de qualité (sous-chantiers 20 et 26) partent de dictées écrites, sans Whisper ; ils ne mesurent que l'extraction. Mesure sur les 22 dictées du test, Infomaniak contre local sans filtre : pertes sur 14, 18 et 19 seulement, les trois que le verdict marque douteuses ; les 19 autres à 11 % près (mots de remplissage).
- 2026-11-22 : Xavier, sur le sous-chantier 29 : « oui pour le 29 » (offre attendue écrite d'après l'audio). Reporté dans les décisions du 29.
- 2026-11-22 : lot 2 fait. Copie `~/offreGen-sc30` (tête 8385e78), base à soi `offregen_sc30_test` (droits recopiés de `offregen_test`), migration `Version20261122183001`, `SchemaTest::testLaDicteeDouteuseASesColonnes` vu rouge puis vert, retour arrière vérifié, suite complète verte sur la base migrée (preuves ci-dessous). Rien de commité.
- 2026-11-23 : Xavier demande ce qui reste et comment l'ensemble marche techniquement. Réponses données sans nouvelle décision ; elles suivent PLAN.md (flux, seuils, routes, statut `a_confirmer`, relance). Point du jour Origin fait.
- 2026-11-24 : arrêt demandé par Xavier. ETAT complété, lot 2 commité dans `~/offreGen-sc30` avec les documents du dossier, la ligne 30 de l'index de voix-offre et la décision du 29, puis poussé sur `chantier-voix-offre` après la suite complète sur la base migrée. Rien déployé sur le test, roadmap non touché.

## Réponses de Xavier à la maquette (2026-11-21, texte collé)

```
D1. Le message Bluetooth (E2) : A | il faut pas dire que c'est la voiture, dire juste que si un outils bluethoot cela peut baisser la qualtié, mais le point important c'est surout le sont
D2. La jauge et le message « voix faible » (E1 et E3) : A | il faut que la jauge soit une forme de moyenne sinon quand on respire ça devient pas assez fort
D3. Quand la dictée est douteuse : B | comment il sait le nombre de mots par whisper ?
D4. Le lecteur : B
D5. Ce que fait la nouvelle dictée : A
D6. La version PC et le point orange dans la liste : Valider | moins familier les text
D7. Seuils calibrés sur la vingtaine d'enregistrements du test avant d'être fixés (aucune dictée normale marquée douteuse) : Valider
D8. Tâche Origin « Dictée mal comprise » dans offreGen, 12 h (estimation de Claude) : Créer
```

## Décisions

| Date | Décision | Raison |
|---|---|---|
| 2026-11-21 | Sous-chantier 30 de voix-offre, pas un chantier à part | Touche la chaîne enregistrement, transcription, offre de ce chantier. |
| 2026-11-21 | Nettoyage du son écarté | Normalisation mesurée sans gain, avec une hallucination en plus. Séparer deux voix demande un modèle lourd. |
| 2026-11-21 | `avg_logprob` écarté comme alarme | -0,18 sur l'offre 61, aussi bon qu'une dictée correcte. |
| 2026-11-21 | Réécoute de l'artisan distincte du sous-chantier 23 | Le 23 est réservé à l'exploitant (« oui seul moi », 2026-11-14). |
| 2026-11-21 | Dictée douteuse : statut `a_confirmer`, aucune extraction avant le choix de l'artisan | D3 : B. L'offre fausse n'existe jamais, l'extraction n'est payée que pour une offre voulue. |
| 2026-11-21 | Onde du lecteur calculée au serveur (dB par seconde, en base) | Safari ne décode pas sûrement le webm d'un autre appareil ; ffmpeg est déjà là. |
| 2026-11-21 | Jauge : 80e centile des trames de 50 ms sur 4 s | D2 : une respiration ou une pause ne fait pas baisser la jauge. |
| 2026-11-21 | Code dans une copie à soi (`~/offreGen-sc30`) | `~/offreGen` porte des fichiers non commités d'autres sessions (mémoire « Cadrage offreGen »). |
| 2026-11-21 | Seuils du verdict : voix (80e centile) sous -32 dB, ou moins de 1 mot par seconde de parole (parole : niveau de -38 dB et plus), ou un segment à `no_speech` au-dessus de 0,6 | Lot 1 : écart entre normales et ratées de 13,7 dB (-24,1 contre -37,8), de 1,59 contre 1,03 mot par seconde de parole, de 0,38 contre 0,75. Seuils au milieu. Trois ratées seulement : les chiffres restent en base pour recalibrer. |
| 2026-11-21 | Mots par seconde de parole plutôt que par seconde d'enregistrement | Une dictée lente avec des pauses (27 : 1,11 mot/s) serait tombée près du seuil ; comptée sur la parole, elle est à 1,59. |
| 2026-11-21 | Seuil des mots par seconde de parole : 1 au lieu de 1,3 | Xavier : « Tu peux mettre moins de 1 mot par seconde plutôt qu'un virgule 3, parce que quelqu'un qui parle lentement, c'est ok. » La dictée 14 (1,03) n'est plus prise par cette règle ; elle reste douteuse par `no_speech` (0,75, seuil 0,6). |
| 2026-11-22 | Relance automatique coupée aux pauses quand la voix est nette et le verdict douteux, puis nouveau verdict | Validée par Xavier (« ok ça me va »). Dictée 14 : 118 mots au lieu de 55. Inutile sur une voix faible (19 : 28 mots). Pas de découpe des autres dictées : elles sortent complètes. |
| 2026-11-22 | Migration `Version20261122183001`, numéro pris sur l'heure | Six migrations du 22 existent déjà sur la branche (000001 à 000006) et d'autres copies travaillent en parallèle : un numéro à l'heure évite la collision. |
| 2026-11-22 | `remplace_offre_id` avec clé étrangère vers `offre`, `ON DELETE SET NULL` | Une offre supprimée ne bloque pas la suppression et n'emporte pas l'enregistrement. |
| 2026-11-22 | Statut `a_confirmer` sans changement de type | `statut` est un `VARCHAR(20)`, pas une énumération. |

## Hors champ, noté

- Le compte commercial d'Aliette (id 16) n'a ni métier ni produit : toute dictée y sort à 0 CHF. Une démo doit se faire depuis un compte de démonstration avec métier, ou l'espace commercial doit y conduire. À trancher par Xavier, hors de ce sous-chantier.
- Les prix non dictés des offres 59, 62 à 66 viennent de corrections à la main (`modifiee_le` rempli), l'extraction n'y est pour rien.
- Réécoute sur toutes les offres : écartée par D4, à rouvrir si Xavier change d'avis.
- Enregistrement 16 (offre 58) : une conversation, pas une dictée d'offre ; le son est bon, le verdict « bon » est juste. Repérer qu'une dictée ne décrit aucun travail relève de l'extraction (sous-chantier 29), pas de ce sous-chantier.

## Artefacts

| Chemin | Phase | État |
|---|---|---|
| `SPEC.md` | 1 | corrigée avec D1 à D8 |
| `MAQUETTE-dictee.html` | 3 | validée (D1 à D8) ; ses textes de messages sont remplacés par ceux de la SPEC, section 3 |
| `PLAN.md` | 4 | écrit, sept lots ; lot 1 fait |

## Preuves

### Phase 1 : diagnostic de l'offre 61

Base du test (heures en UTC, la dictée de 10h09 heure suisse) :

```
id 19 | 16/dd5a2160-ff78-46c5-9aae-796d61ce95aa.webm | 379786 octets | recu 09:10:54 | pret 09:10:58
transcription : Il y a une personne qui s'est retrouvée bloquée et puis ça a bloqué tout le monde.
ligne : Intervention pour personne bloquée | 1 forfait | 0.00 | a_verifier 1 | produit_id NULL
offre 61 : modifiee_le NULL (jamais retouchée)
```

Volume par passage (ffmpeg volumedetect, WSL) :

```
salon-10m2 [1.6-13.4 s] mean_volume: -40.4 dB max_volume: -19.7 dB
dictee [17-20.3 s] mean_volume: -41.6 dB max_volume: -25.5 dB
dictee-parquet [28.4-33 s] mean_volume: -41.8 dB max_volume: -22.6 dB
personne-bloquee [33-40 s] mean_volume: -34.1 dB max_volume: -14.6 dB
plafond [40-51.2 s] mean_volume: -41.6 dB max_volume: -18.3 dB
fin [51.3-60 s] mean_volume: -48.4 dB max_volume: -23.4 dB
--- témoin (Xavier, offre 59)
mean_volume: -24.2 dB max_volume: -1.6 dB
```

Banc chez Infomaniak (modèle `whisper`, langue `fr`, 2026-11-21 vers 15h20) :

```
===== original.webm (text)
"data":"Il y a une personne qui s'est retrouvée bloquée et puis ça a bloqué tout le monde.\n"
===== original.webm (text)
"data":"Il y a une personne qui s'est retrouvée bloquée et puis ça a bloqué tout le monde.\n"
===== original.webm (verbose_json)
"duration": 59.915, "segments": [{"id": 0, "seek": 0, "start": 1.58, "end": 44.14, "text": " Il y a une personne qui s'est retrouvée bloquée et puis ça a bloqué tout le monde.", "temperature": 0.0, "avg_logprob": -0.18494591976587588, "compression_ratio": 1.0481927710843373, "no_speech_prob": 0.4794921875}]
===== remux.webm (text)
"data":"Il y a une personne qui s'est retrouvée bloquée et puis ça a bloqué tout le monde.\n"
===== debut30.ogg (text)
"data":"OK. Je suis dans le salon. Je me repeins une fois quatre minutes. Et ensuite, je vais partir.\n"
===== fin30.ogg (text)
"data":"Il y a une personne qui s'est retrouvée bloquée et puis ça a bâti tout le monde.\n"
===== normalise.ogg (text)
"data":"Il y a une personne qui s'est retrouvée bloquée et puis ça a bloqué tout le monde.\nSous-titrage FR ?\n"
```

Témoins par segment chez Infomaniak :

```
===== 5/fec9ce41 (Xavier, offre 59)
durée 27.1 s, 84 mots, 3.10 mot/s
  [  0.00- 26.00] ns=0.09 lp=-0.03 cr=1.52  Je donne le micro. Alors, je suis du coup avec LCA, ...
===== 16/f5de67e4 (Aliette, offre 60)
durée 84.9 s, 55 mots, 0.65 mot/s
  [  1.04- 50.06] ns=0.41 lp=-0.29 cr=0.97  C'est les gens qui arrivent pour... C'est une barrière.
  [ 50.42- 71.79] ns=0.24 lp=-0.20 cr=1.47  Il faut lever la barrière, tu rentres, tu lèves la barrière. Pourquoi tu mets d
===== 22/57a81a56 (démo génie civil, offre 64, m4a importé)
durée 41.9 s, 64 mots, 1.53 mot/s
  [  0.78- 29.58] ns=0.02 lp=-0.10 cr=1.62  Offre Baudinelle, 300 mètres carrés de terre végétale criblée à 5 centimètres d
  [ 29.58- 41.49] ns=0.02 lp=-0.26 cr=1.16  et des graviers 6,16 en principe qu'il faut compter et puis attendre offre du d
```

Whisper large-v3-turbo local, sans filtre de silence, offre 61 :

```
[   0.0 ->   18.0] (no_speech 0.00, logprob -0.35) Ok. Je suis dans le salon. Je veux reprendre le salon. J'ai prévu une fois 10 mètres carrés, une fois 5 mètres carrés, une fois 4 mètres carrés.
[  18.0 ->   33.0] (no_speech 0.00, logprob -0.57) Et ça se rend beaucoup plus chaud de peinture à 4 mètres carrés. Ensuite, j'ai fait le parquet avec des cartons qui sont les deux qui sont les deux.
[  33.0 ->   40.0] (no_speech 0.00, logprob -0.57) Et puis, il y a une personne qui s'est retrouvé bloquée et puis ça a bloqué tout le monde.
[  40.0 ->   48.0] (no_speech 0.00, logprob -0.58) Et finalement, il y a le plafond, si tu peux te reprendre, il y a une fonction de la maternité.
```

Scripts du banc (scratchpad de la session du 2026-11-21, périssables) : `banc_infomaniak.sh`, `banc_temoin.sh`, `transcrire.py`, `segments.py`.

### Phase 3 : gate visuel

Contrôle de la maquette, 2026-11-21 : `check.js` OK au bureau (1440) et au téléphone (390), largeur de page égale à la fenêtre, aucune erreur JS ; captures pleine page en clair et en sombre ouvertes.

### Lot 1 : calibrage sur les 22 enregistrements du test (2026-11-21)

Banc `calib.sh` sur le site de test (lecture seule : ffmpeg astats par tiers de seconde, transcription Infomaniak en `verbose_json`), tableau par `analyse_calib.py`. « m/s par » : mots par seconde de parole. « départ » : règles de la SPEC du matin ; « calibré » : seuils retenus.

```
 id  durée  mots  mot/s m/s par ns max voix p80    p50  net%  départ | calibré
  7   27.9    67   2.40    2.93   0.00    -18.9  -20.8    82  bon | bon ()
  8   70.4   104   1.48    1.75   0.02    -21.3  -24.4    84  bon | bon ()
  9   57.5   115   2.00    2.83   0.08    -20.8  -25.5    71  bon | bon ()
 10   20.4    53   2.60    2.83   0.14    -20.9  -25.1    92  bon | bon ()
 11   27.9    53   1.90    2.61   0.01    -22.4  -29.6    73  bon | bon ()
 12   60.9   118   1.94    2.73   0.12    -20.3  -25.7    71  bon | bon ()
 13   16.2    24   1.48    1.77   0.01    -15.7  -20.3    84  bon | bon ()
 14   71.8    55   0.77    1.03   0.75    -20.4  -26.0    74  douteux | douteux (peu_de_mots, sans_parole)
 15   20.2    34   1.69    1.94   0.06    -15.4  -18.8    87  bon | bon ()
 16   12.9    46   3.57    4.22   0.38    -20.3  -25.4    85  bon | bon ()
 17   27.1    84   3.10    4.31   0.09    -21.5  -26.3    72  bon | bon ()
 18   84.9    55   0.65    3.12   0.41    -37.8  -45.1    21  douteux | douteux (voix_faible)
 19   59.9    17   0.28    1.89   0.48    -39.4  -48.5    15  douteux | douteux (voix_faible)
 20   15.9    24   1.51    1.91   0.20    -21.0  -25.0    79  bon | bon ()
 21   31.6    71   2.25    2.93   0.23    -21.1  -24.9    77  bon | bon ()
 22   41.9    64   1.53    1.69   0.02    -17.2  -19.2    90  bon | bon ()
 23   17.7    61   3.44    4.22   0.15    -22.4  -25.5    81  bon | bon ()
 24   23.2    52   2.24    2.96   0.15    -23.6  -28.5    76  bon | bon ()
 25   98.6   126   1.28    2.19   0.13    -24.1  -32.5    58  bon | bon ()
 26  164.1   198   1.21    1.66   0.17    -20.6  -25.5    73  bon | bon ()
 27   38.6    43   1.11    1.59   0.12    -20.4  -26.8    70  bon | bon ()
 28   23.0    69   2.99    3.55   0.07    -22.0  -25.0    84  bon | bon ()
```

Jugement de chaque ligne : 18 et 19, voix d'Aliette couverte (diagnostic de la phase 1) ; 14, dictée tronquée (ci-dessous) ; les 19 autres, texte compris cohérent avec une dictée d'offre ou une conversation complète, lu à côté de l'offre.

Dictée 14, Whisper large-v3-turbo en local (sans filtre de silence) contre Infomaniak :

```
[   0.0 ->   10.1] Ok, je suis chez mon client qui s'appelle Froulichot-Aliette.
[  10.1 ->   17.7] Il veut construire un escalier extérieur qui va de la terrasse à un jardin.
[  18.4 ->   21.7] Et donc, je dois leur faire une offre pour cet escalier-là.
[  21.7 ->   37.1] Non seulement il faut que je fasse un deal de ferronnerie et que j'ajoute un carrelage quitte à déléguer et commissionner les deux autres travaux auprès de nos collègues.
[  39.9 ->   43.7] Et puis, qu'est-ce que je dois faire comme ma sonnerie encore pour eux ?
[  43.7 ->   52.2] Oui, récupérer donc le crépit sur les endroits et autour de la porte et un coup de peinture également.
[  53.9 ->   61.2] Ça, c'est également des choses qui pourront être commissionnées, mais tu peux me les mettre comme si c'était moi qui les faisais.
[  62.3 ->   68.3] Je regarderai ensuite pour que la structure puisse avoir ces passages.
[  68.9 ->   69.5] Voilà, merci.
----- mots : 131
INFOMANIAK (un segment, 0,56 à 57,74 s, no_speech 0,75) : Ok, je suis chez mon client qui s'appelle Frédéric Aliette. Ils veulent construire un quartier extérieur sur la terrasse de leur jardin. Je dois leur dire que j'ajoute à déléguer et commissionner ces deux autres travaux auprès de nos collègues. Oui, récupérer donc le crépit des endroits autour de la porte, qui pourront être commissionnés.
offre 54 : une seule ligne, « Crépi de mur », 1 x 32.00
```

### Pourquoi Infomaniak comprend moins que Whisper en local (2026-11-21)

Modèles proposés par Infomaniak (`GET /1/ai/models`, clé du site de test) : un seul pour la parole.

```
{"id":2,"name":"whisper","type":"stt",...,"description":"Whisper V3","info_status":"ready",...,"version":"3.0"}
GET /1/ai/{produit}/openai/models : whisper, flux, bge_multilingual_gemma2, mini_lm_l12_v2
```

Réglages envoyés avec la dictée 14 (banc `banc_vad.sh`) : `vad_filter=false`, `vad=false`, `temperature=0.2` rendent le même texte au caractère près que l'envoi par défaut (55 mots, un segment de 0,56 à 57,74 s, no_speech 0,75) ; ils sont ignorés. `prompt` change quelques mots (48 mots), sans rien récupérer.

Réglages reproduits en local (faster-whisper large-v3-turbo, `reglages_local.py`) :

```
o61 | sans filtre, repères        | 110 mots
o61 | filtre, repères             |  54 mots | [1.6 -> 36.1] [36.1 -> 40.1] [40.1 -> 44.1]
o61 | filtre, sans repères        |  17 mots | [1.6 -> 44.1] Il y a une personne qui s'est retrouvée bloquée et puis c'est un petit tout le monde.
Infomaniak, même fichier          |  17 mots | [1.58 -> 44.14] Il y a une personne qui s'est retrouvée bloquée et puis ça a bloqué tout le monde.
o54 | sans filtre, repères        | 131 mots
o54 | filtre, sans repères        |  80 mots | [0.6 -> 47.1] [47.1 -> 61.4]
Infomaniak, même fichier          |  55 mots | [0.56 -> 57.74]
```

Lecture : le filtre de silence prend une voix faible pour du silence et l'enlève (offre 61) ; il recolle ensuite la parole gardée bout à bout, et sans repères de temps Whisper saute des phrases dans ces longs morceaux recollés (offre 54). En local, par défaut, ni l'un ni l'autre : d'où 110 et 131 mots. Le modèle d'Infomaniak (V3) n'est pas celui du local (V3 turbo), d'où les petits écarts de mots.

Découpe de la dictée 14 chez Infomaniak :

```
morceaux fixes de 30 s (banc_vad.sh)        : 27 + 54 + 15 = 96 mots, escalier et ferronnerie perdus (coupes au milieu des phrases)
coupée aux pauses 18,0 / 38,5 / 53,0 s       : 21 + 36 + 31 + 30 = 118 mots (banc_pauses.sh), seule la ferronnerie manque
  attente de chaque morceau, envoyés ensemble : 4 s, 4 s, 4 s, 3 s
dictée 19 coupée aux pauses 17,5 / 33 / 40 s : 5 + 5 + 17 + 1 = 28 mots (voix faible : la coupe n'y change rien)
```

Longues dictées normales, Infomaniak contre local sans filtre (`compter_local.py`) : 8 : 104 contre 105 ; 12 : 118 contre 114 ; 25 : 126 contre 136 ; 26 : 198 contre 205. La perte de la dictée 14 n'est pas générale.

Coût d'une découpe (mesures du sous-chantier 26) : Whisper facturé au temps de calcul, CHF 0.006 la minute, arrondi à la seconde par appel (19 s d'audio facturées 1 s, 28 s facturées 3 s). Un morceau ajoute au plus 1 s de calcul, soit 0,01 ct.

Limites : une seule mesure par cas ; les pauses de la dictée 14 ont été prises sur les repères du local, l'application devra les trouver par ffmpeg (`silencedetect`), non essayé.

### Impact sur les 22 dictées du test : Infomaniak contre Whisper local sans filtre (2026-11-22)

`compter_local.py` (large-v3-turbo, sans filtre, avec repères) sur les 22 audios, `comparer_tous.py` contre les transcriptions d'Infomaniak du lot 1. Écart en mots ; « perte » au-delà de -15 %.

```
 id  durée Infomaniak  local  écart
  7   27.9         67     67     0 %
  8   70.4        104    105    -1 %
  9   57.5        115    115     0 %
 10   20.4         53     59   -10 %
 11   27.9         53     53     0 %
 12   60.9        118    114     4 %
 13   16.2         24     24     0 %
 14   71.8         55    131   -58 %  <== perte
 15   20.2         34     38   -11 %
 16   12.9         46     49    -6 %
 17   27.1         84     84     0 %
 18   84.9         55    110   -50 %  <== perte
 19   59.9         17    110   -85 %  <== perte
 20   15.9         24     24     0 %
 21   31.6         71     67     6 %
 22   41.9         64     64     0 %
 23   17.7         61     62    -2 %
 24   23.2         52     56    -7 %
 25   98.6        126    136    -7 %
 26  164.1        198    205    -3 %
 27   38.6         43     36    19 %
 28   23.0         69     69     0 %
```

Textes relus pour 10, 15 et 25 : l'écart vient des mots de remplissage (« voilà », « tu vois », « disons ») et de « 4 » contre « quatre » ; rien d'utile à l'offre ne manque, sauf « d'oeuvre » après « 7-8 heures de main » (15).

Bancs de qualité existants : sous-chantier 20, « quatre dictées écrites à la main » (`banc/donnees.php`) ; sous-chantier 26, dictées longues inventées en texte. Aucun ne passe par Whisper.

### Lot 2 : migration (2026-11-22)

Test écrit d'abord, rouge sur la base sans la migration :

```
-    'voix_db' => Array &9 [ 0 => 'smallint(6)', 1 => 'YES', 2 => 'NULL' ],
-]
+Array &0 []
/home/xavie/offreGen-sc30/tests/Donnees/SchemaTest.php:82
FAILURES!  Tests: 5, Assertions: 15, Failures: 1.
```

Migration jouée, à jour, `tests/Donnees` et phpstan :

```
 [OK] Successfully migrated to version: OffreGen\Migrations\Version20261122183001
 [OK] Up-to-date! No migrations to execute.
--- tests/Donnees
OK (31 tests, 134 assertions)
--- phpstan
 [OK] No errors
```

Retour arrière puis remontée (colonnes comptées dans `information_schema`, 9 sur `enregistrement` et 1 sur `offre`) :

```
colonnes avant : 10
[notice] finished in 86ms, used 8M memory, 1 migrations executed, 3 sql queries
 [OK] Successfully migrated version(s): OffreGen\Migrations\Version20261122183001: [DOWN]
colonnes apres down : 0
 [OK] Successfully migrated to version: OffreGen\Migrations\Version20261122183001
colonnes apres up : 10
OK (5 tests, 18 assertions)
```

Contrôle de compatibilité (`docs/DEPLOIEMENT.md`, Migrations) : le code de la branche, qui ne lit aucune des nouvelles colonnes, sur la base migrée :

```
............................................................. 1037 / 1076 ( 96%)
.......................................                       1076 / 1076 (100%)
Time: 02:32.135, Memory: 110.50 MB
OK (1076 tests, 9245 assertions)
```
