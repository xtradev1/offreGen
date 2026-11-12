---
chantier: De la voix à l'offre, 10 IA réelle en local
slug: 2026-11-07_voix-offre/10-ia-locale
phase: 8
statut: gate_ouvert
gate_attendu: prod
profil: .claude/atoz-profile.md
---

## Prochaine action

Recette de Xavier (`RECETTE.md`) avec ses propres dictées fictives. Ensuite : commit et PR sur son accord ; brancher Infomaniak (sous-chantiers 03 et 04 : clés, numéro de produit, nom du modèle, vérifier les décisions fragiles 1 et 2 du `PLAN.md`).

## En attente de toi

- Recette selon `RECETTE.md` : une ou deux dictées de ton téléphone, sans client. Dis-moi format, durée, attente et lignes fausses.
- Accord pour commiter la branche `chantier-voix-offre` (sous-chantiers 01 et 10) et ouvrir la PR.

## Journal

- 2026-11-12 : Xavier : « dans un premier temps fais en sorte que tout puisse être fait en local comme ça je teste déjà en local et ensuite on ajoutera juste les clés API et on le mettra sur le serveur ». Sous-chantier 10 ouvert. Phases 1 à 3 sautées : l'intention est la sienne, mot pour mot ; l'aspect est celui de la maquette v4 (seul ajout : l'extrait entendu sous la ligne, comme dans la maquette).
- 2026-11-12 : documentation d'Infomaniak lue par Playwright (elle ne s'affiche que par JavaScript). Transcription asynchrone en v1 (`batch_id`, puis `results`), 50 000 Ko au plus, ni amr ni 3gp ; discussion en v2, compatible OpenAI, avec outils.
- 2026-11-12 : environnement : Ollama 0.34 sous Windows, joignable depuis WSL par la passerelle seulement ; `python3.12-venv` installé dans Ubuntu (root) ; faster-whisper 1.2.1 dans `~/offregen-ia`. Un premier `pip install` a refusé un paquet pour empreinte SHA-256 fausse (téléchargement tronqué) ; le second, sans cache, est passé avec toutes les empreintes vérifiées.
- 2026-11-12 : Whisper sur la carte depuis WSL : plantage dans `encode` de ctranslate2, quels que soient le type de calcul, cuDNN (9.28, 9.5) et ctranslate2 (4.9, 4.5). Sur le processeur : correct, 28 s de dictée en 9 s. Décision : processeur.
- 2026-11-12 : Qwen 27B dans Ollama, contexte par défaut de 131 072 jetons : 59 s, modèle à moitié hors de la carte. Variante `offregen-qwen3.8` (16 384 jetons) : 13 s, entière en carte.
- 2026-11-12 : défauts trouvés par le test de bout en bout et le parcours, pas par les tests unitaires : envoi par morceaux sans longueur (refusé par le serveur local, aurait pu l'être chez Infomaniak : longueur maintenant annoncée) ; traitement immédiat qui rendait la main avant la fin de la transcription (attente de 90 s ajoutée) ; tiret demi-cadratin dans un titre écrit par Qwen (le code le remplace) ; commentaire XML invalide dans `phpunit.xml`, fait par moi.
- 2026-11-12 : défaut à moi : un tiret demi-cadratin collé en clair dans un fichier de test, remplacé par son échappement JSON. Contrôle du dépôt vert.

## Décisions

| Date | Décision | Raison |
|---|---|---|
| 2026-11-12 | Le Whisper local imite le protocole d'Infomaniak, refus compris (taille, formats). | Passer au serveur ne change que le `.env`, et la conversion PHP est exercée dès les essais locaux. |
| 2026-11-12 | Whisper sur le processeur, en `int8`. | Plantage sur la carte depuis WSL ; et la carte reste entière pour Qwen, sous la limite de 95 % de Xavier. |
| 2026-11-12 | `PreparationAudio` convertit en Opus 32 kbit/s mono tout format refusé et tout fichier de plus de 49 Mo. | Infomaniak refuse amr, 3gp et plus de 50 000 Ko ; la limite de 200 Mo de Xavier reste valable à l'import. |
| 2026-11-12 | Un lot échoué, introuvable ou sans résultat après 2 h remet l'enregistrement en « reçu » et compte une tentative. | Sinon le traiteur redemanderait un lot mort jusqu'à l'échec. |
| 2026-11-12 | Le traitement juste après l'import attend la transcription 90 s, par pas de 3 s ; cron n'attend pas. | Une dictée courte devient une offre sans attendre cron. |
| 2026-11-12 | Le modèle choisit produits et quantités ; désignation, unité et prix viennent du catalogue. Ligne libre et produit inconnu : toujours « à vérifier ». | « Le code fait tous les calculs » (cadrage, section 5) ; le modèle peut se tromper d'identifiant. |
| 2026-11-12 | Dossier `src/Ia/` ajouté (lecture des réponses des services d'IA), hors de l'arborescence de `CODING_RULES.md`. | Code partagé par `Audio` et `Offre` ; à inscrire dans `CODING_RULES.md` si Xavier l'accepte. |
| 2026-11-12 | `symfony/http-client` et `symfony/mime` ajoutés, en 7.4. | Prévus par `CODING_RULES.md` ; `mime` sert aussi aux courriels. |

## Artefacts

| Chemin | Phase | État |
|---|---|---|
| `PLAN.md`, `RECETTE.md` | 4, 8 | écrits |
| `outils/ia-locale/{whisper_serveur.py,installer.sh,demarrer.sh,Modelfile,README.md}` | 5, lot 1 | fait |
| `migrations/Version20261112000001.php`, `src/Audio/{TranscripteurApi,PreparationAudio,FichierPrepare,TranscriptionEchouee}.php`, `src/Ia/*` | 5, lot 2 | fait, migration jouée sur les deux bases |
| `src/Offre/{ExtracteurApi,ResultatExtraction}.php`, `Extracteur`, `LigneExtraite`, `ExtracteurFactice` | 5, lot 3 | fait |
| `src/Http/Conteneur.php`, `bin/verifier-ia`, `templates/offre.html.twig`, `tests/Bout/IaLocaleTest.php`, `tests/fixtures/audio/dictee-peintre.ogg` | 5, lot 4 | fait |
| `captures/*.jpg` | 6 | parcours cliqué sur les vrais services |

## Preuves

### Lot 1 : Whisper local

```
$ curl -F file=@dictee.wav -F model=whisper -F language=fr -F response_format=text .../1/ai/0/openai/audio/transcriptions
{"batch_id": "4ce6e592-f8ac-4485-860f-2c135496c53a"}
après 10 s : {"status": "success", ..., "data": "Visite chez Madame Exemple, à Bulles. Dans le salon, il faut d'abord protéger les sols et les meubles. Les murs du salon, environ 42 mètres carrés, deux couches. ..."}
== refus amr
{"result": "error", "error": {"code": "validation_failed", "description": "file type not allowed"}} 422
== refus de plus de 50 000 Ko (54 400 078 octets)
{"result": "error", "error": {"code": "file_too_large"}} 413
== modèle inconnu
{"result": "error", "error": {"code": "validation_failed", "description": "unknown model"}} 422
== lot inconnu
{"result": "error", "error": {"code": "not_found"}} 404
== écoute
LISTEN 0      5          127.0.0.1:8090      0.0.0.0:*
journal : lot reçu : 870 Ko, wav / 28 s d'audio en 8.4 s (aucun mot de la dictée au journal)
```

### Lots 2 à 4

```
$ vendor/bin/phpunit
OK (121 tests, 371 assertions)
$ vendor/bin/phpstan analyse
 [OK] No errors

== mutation : lot échoué pris pour un lot en cours
1) TranscripteurApiTest::testLotEchoueAnnuleOuIntrouvableDemandeUnRenvoi
== mutation : lot perdu jamais renvoyé
1) TraiteurTest::testLotDeTranscriptionPerduRenvoieLAudio
2) TraiteurTest::testTranscriptionSansResultatApresDeuxHeuresRenvoieLAudio
== mutation : délai de 3 h au lieu de 2
1) TraiteurTest::testTranscriptionSansResultatApresDeuxHeuresRenvoieLAudio
== mutation : attente sans fin
1) OffreGen\Tests\Audio\TraiteurTest::testTranscriptionPasEncorePreteRendLaMain
2) OffreGen\Tests\Audio\TraiteurTest::testTranscriptionSansResultatApresDeuxHeuresRenvoieLAudio
3) OffreGen\Tests\Audio\TraiteurTest::testTraitementImmediatRendLaMainACronApresQuatreVingtDixSecondes
4) OffreGen\Tests\Audio\TraiteurTest::testCronNAttendPas
== mutation : prix du modèle cru pour un produit du catalogue
1) ExtracteurApiTest::testLePrixDuModeleEstIgnorePourUnProduitDuCatalogue
== mutation : aucun doute retenu
1) ExtracteurApiTest::testReponseReelleDevientDesLignesDuCatalogue
2) ExtracteurApiTest::testProduitSansPrixToujoursAVerifier
== mutation : aucune conversion
1) OffreGen\Tests\Audio\PreparationAudioTest::testFormatRefuseConvertiEnOpus@amr with data ('visite.amr')
2) OffreGen\Tests\Audio\PreparationAudioTest::testFormatRefuseConvertiEnOpus@3gp with data ('visite.3gp')
3) OffreGen\Tests\Audio\PreparationAudioTest::testFichierTropGrosConvertiPourPasserSousLaLimite
4) OffreGen\Tests\Audio\PreparationAudioTest::testTropLongMemeCompresseRefuse
5) OffreGen\Tests\Audio\PreparationAudioTest::testSansFfmpegLeMessageLeDit
6) OffreGen\Tests\Audio\TranscripteurApiTest::testAmrConvertiAvantEnvoiEtCopieEffacee
== rétabli
OK (120 tests, 368 assertions)

$ bin/verifier-ia   (Whisper local arrêté, puis lancé)
Transcription : injoignable : Failed to connect to 127.0.0.1 port 8090 ...   code=1
Transcription : joignable, http://127.0.0.1:8090 (lot inconnu : 404, attendu)
Extraction : joignable, modèle « offregen-qwen3.8 » présent, http://172.25.96.1:11434/v1   code=0

$ vendor/bin/phpunit --group ia-locale   (vrais services, dictée fictive de 28 s)
[ia-locale] offre prête en 28 s
Protection des sols et des meubles : 1, à vérifier 0
Peinture des murs, 2 couches : 42, à vérifier 1, entendu « Les murs du salon, environ 42 mètres carrés, deux couches »
Peinture des plafonds, 2 couches : 18, à vérifier 1
Laquage de porte, 2 faces : 3, à vérifier 0, entendu « trois portes à la queue, les deux faces »
Peinture anti-moisissure : 12, à vérifier 1
Déplacement : 1, à vérifier 0
OK (1 test, 18 assertions)

Parcours cliqué (Playwright, 390 px puis 1280 px sombre), import par le champ de fichier, aucune action manuelle :
page d'état après 17 s, offre prête après 17 s
Sous-total HT CHF 2’472.00 TVA 8,1 % CHF 200.23 Arrondi CHF 0.02 Total TTC CHF 2’672.25
erreurs JavaScript : favicon.ico 404 seulement
Barre du bas après défilement : tel {"totalBas":681,"barreHaut":771}, bureau {"totalBas":738,"barreHaut":827} : total visible
```
