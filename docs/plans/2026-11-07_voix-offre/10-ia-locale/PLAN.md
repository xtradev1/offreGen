# Plan du sous-chantier 10 : transcription et extraction réelles, en local d'abord

Demande de Xavier (2026-11-12) : « dans un premier temps fais en sorte que tout puisse être fait en
local comme ça je teste déjà en local et ensuite on ajoutera juste les clés API et on le mettra sur
le serveur ».

**But :** l'audio importé est vraiment transcrit (Whisper sur la RTX 3090) et l'offre vraiment
extraite du catalogue (Qwen 27B dans Ollama), sur le PC de Xavier. Le code PHP parle le protocole
d'Infomaniak : passer au serveur ne change que des réglages du `.env`.

## Faits vérifiés le 2026-11-12

| Fait | Source |
|---|---|
| Transcription Infomaniak : `POST /1/ai/{produit}/openai/audio/transcriptions` (format OpenAI, `model: whisper`), réponse `{"batch_id"}` ; `GET /1/ai/{produit}/results/{batch_id}` rend `status` (`pending`, `processing`, `success`, `failed`, `cancelled`) et `data` | documentation Infomaniak, rendue par Playwright |
| Fichier de 50 000 Ko au plus ; formats mp3, mp4, aac, wav, flac, ogg, opus, wma, m4a, webm (ni amr ni 3gp) | idem |
| Discussion : `POST /2/ai/{produit}/openai/v1/chat/completions`, compatible OpenAI, `tools`, `tool_choice`, `response_format` `json_schema` | idem (la route v1 est dépréciée) |
| Ollama 0.34 sous Windows, joignable depuis WSL par la passerelle (`172.25.96.1:11434`), pas par `localhost` | essai |
| `qwen3.8:27b` par `/v1/chat/completions` avec outil imposé et `reasoning_effort: none` : cinq lignes justes sur une dictée fictive, aucun raisonnement | essai |
| Contexte par défaut de 131 072 jetons : modèle débordé sur le processeur, 59 s ; variante `offregen-qwen3.8` (contexte 16 384) entière en carte, 13 s chargement compris | essai |
| WSL voit la RTX 3090 ; modèles faster-whisper turbo et large-v3 déjà téléchargés sous Windows | essai |

## Contrat technique

### Réglages (`.env`)

| Clé | Local | Infomaniak |
|---|---|---|
| `TRANSCRIPTION` | `api` (ou `factice`) | `api` |
| `TRANSCRIPTION_URL` | `http://127.0.0.1:8090` | `https://api.infomaniak.com` |
| `TRANSCRIPTION_PRODUIT` | `0` | numéro du produit AI Tools |
| `TRANSCRIPTION_CLE` | vide | jeton d'API |
| `TRANSCRIPTION_MODELE` | `whisper` | `whisper` |
| `EXTRACTION` | `api` (ou `factice`) | `api` |
| `EXTRACTION_URL` | `http://172.25.96.1:11434/v1` | `https://api.infomaniak.com/2/ai/{produit}/openai/v1` |
| `EXTRACTION_CLE` | vide | jeton d'API |
| `EXTRACTION_MODELE` | `offregen-qwen3.8` | nom du modèle Qwen chez Infomaniak (à lire dans `GET /1/ai/models`) |

### Serveur Whisper local (`outils/ia-locale/`)

Outil de développement, jamais déployé. Python, faster-whisper turbo en `int8_float16` sur la carte,
modèle chargé à la demande et libéré après chaque fichier. Il imite les deux routes d'Infomaniak
ci-dessus, mêmes chemins, mêmes champs, mêmes statuts. Il n'écoute que `127.0.0.1`.

### Flux

1. `Traiteur`, statut `recu` : `TranscripteurApi::demarrer` prépare l'audio (`PreparationAudio` :
   conversion en Opus 32 kbit/s mono par ffmpeg si le format n'est pas accepté ou si le fichier
   dépasse 49 Mo), l'envoie, garde le `batch_id`, note `transcription_depuis`.
2. Statut `transcription` : `resultat` lit le lot. `pending` ou `processing` : on attend.
   `failed` ou `cancelled` : l'enregistrement repart en `recu` et la tentative compte.
   Plus de 2 heures sans résultat : idem.
3. Statut `transcrit` : `ExtracteurApi::extraire` envoie le catalogue de l'artisan et la
   transcription, outil `proposer_offre` imposé. Le code PHP valide chaque ligne :
   - produit du catalogue : désignation, unité et prix repris du catalogue, jamais du modèle ;
   - identifiant inconnu : ligne libre, « à vérifier » ;
   - ligne libre : prix seulement s'il a été dicté, sinon prix manquant ; toujours « à vérifier » ;
   - quantité en millièmes, arrondie ; nulle ou absente : ligne refusée et notée au journal ;
   - l'extrait entendu est gardé avec la ligne (`ligne.entendu`) et affiché sous elle.
4. Le titre de l'offre vient du modèle (90 caractères au plus), sinon « Visite du JJ.MM.AAAA ».

## Signatures

```php
// src/Audio/
final class TranscripteurApi implements Transcripteur { public function __construct(HttpClientInterface $http, PreparationAudio $prep, string $url, string $produit, string $cle, string $modele); }
final class PreparationAudio { public const FORMATS_ACCEPTES = ['mp3','mp4','aac','wav','flac','ogg','opus','wma','m4a','webm']; public const TAILLE_MAX = 49 * 1024 * 1024; public function preparer(string $chemin): FichierPrepare; }
final class TranscriptionEchouee extends \RuntimeException {}
// src/Offre/
final readonly class ResultatExtraction { public function __construct(public ?string $titre, /** @var list<LigneExtraite> */ public array $lignes); }
interface Extracteur { public function extraire(string $transcription, array $catalogue): ResultatExtraction; }
final class ExtracteurApi implements Extracteur { public function __construct(HttpClientInterface $http, string $url, string $cle, string $modele, LoggerInterface $journal); }
// LigneExtraite gagne ?string $entendu.
```

Commande : `bin/verifier-ia` (dit si chaque service répond, sans rien envoyer de personnel).

## Décisions fragiles

1. J'ai supposé que `data` d'un lot réussi contient le texte quand on demande `response_format=text`.
   La documentation dit seulement « The result ». Le client accepte aussi un JSON `{"text": …}`.
   À vérifier au premier appel réel chez Infomaniak (sous-chantier 04).
2. J'ai supposé que l'hébergement Cloud a ffmpeg. Sans lui, un amr, un 3gp ou un fichier de plus de
   49 Mo est refusé avec un message clair ; les m4a courts passent sans conversion.
3. Le contexte de 16 384 jetons couvre environ 40 minutes de dictée. Au-delà, l'extraction locale
   échoue ; chez Infomaniak, la limite est la leur.

## Lots

| Lot | Contenu | Preuve |
|---|---|---|
| 1 | Serveur Whisper local, `bin/ia-locale` (démarre, dit l'adresse d'Ollama) | `curl` sur la dictée fictive : `batch_id`, puis `success` et le texte |
| 2 | `symfony/http-client` ; `TranscripteurApi`, `PreparationAudio`, reprise d'un lot échoué, délai de 2 h (migration : `transcription_depuis`, `ligne.entendu`) | tests avec `MockHttpClient` ; mutations |
| 3 | `ExtracteurApi`, `ResultatExtraction`, validation des lignes | tests avec une réponse réelle enregistrée ; mutations |
| 4 | Réglages, `bin/verifier-ia`, extrait affiché sous la ligne, test de bout en bout (groupe `ia-locale`, hors suite par défaut), parcours cliqué, `RECETTE.md` | import de la dictée par l'interface, offre juste, captures regardées |
