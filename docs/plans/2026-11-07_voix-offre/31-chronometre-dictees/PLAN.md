# Plan : chronomètre des dictées (sous-chantier 31)

Entrées : `SPEC.md` (corrigée après D1 à D8 du 2026-11-24), `MAQUETTE-chronometre.html`. Le chantier traverse la base, le traitement de fond, le navigateur et l'administration : contrat technique d'abord.

## Contrat technique

### Flux, de bout en bout

1. **Téléphone** (`public/js/app.js`, `envoyer`) : ajoute au formulaire `origine` (`micro` ou `fichier`) et `duree_ms` (fin moins début de l'enregistrement, ou durée lue du fichier importé). Mesure `xhr.upload` de `loadstart` à `load`. Après la réponse (arrivée sur `/enregistrements/{uuid}`), `navigator.sendBeacon('/enregistrements/{uuid}/envoi', {_csrf, envoi_ms})`.
2. **Réception** (`ImportControleur::envoyer`) : `DepotEnregistrements::creer` reçoit en plus `navigateur` (User-Agent, 255 caractères), `origine` et la durée du téléphone (`duree_ms`, bornée de 0 à 2 h). Juste avant la redirection : étape `reception`, de `REQUEST_TIME_FLOAT` à maintenant, processus `web`.
3. **Envoi** (`EnregistrementControleur::envoi`, nouvelle route) : propriétaire seulement (404 sinon), CSRF, `envoi_ms` de 1 à 3 600 000 ; une seule étape `envoi` par dictée, de `debut(reception) - envoi_ms` à `debut(reception)`. Réponse 204.
4. **Traitement** (`Traiteur::avancer($uuid, $attente, $processus)`) : après la prise du verrou, `Chronometre::ouvrirPassage` (dictée, uuid, jeton, tentative = `tentatives + 1`, processus). Le passage écrit `attente` (aucune étape encore : depuis `recu_le` ; dernière étape en erreur : depuis sa fin) ou `reprise` (étapes laissées `en_cours` hors `transcription` : marquées `interrompue`, reprise depuis le plus ancien de leurs débuts). Puis : `mesure` autour de `verifierDuree` (durée gardée dans `duree_ms`) ; `conversion` et `transcription_envoi` dans `TranscripteurApi::demarrer` ; `transcription` ouverte quand le lot est reçu, fermée par le passage qui reçoit le texte ; `ia_extraction`, `ia_pieces`, `ia_options` dans `ExtracteurApi` ; `ecriture` autour du rapprochement et de la transaction, fermée après la validation. `fermerPassage` dans le `finally`.
5. **Pouls** : `Chronometre::pouls()` prolonge le verrou à maintenant + 150 s, au plus une fois toutes les 20 s. Appelé à chaque début et fin d'étape, et par `on_progress` des appels HTTP (IA et transcription). Verrou repris par un autre : `VerrouPerdu`, qui coupe l'appel ; le `Traiteur` le reconnaît aussi enveloppé (`getPrevious`) et ne compte pas de tentative.
6. **Écran d'attente** (`/enregistrements/{uuid}/etat`) : `interrompu` vrai quand la dictée n'est ni prête ni en échec, que `verrou_jeton` est posé et `verrou_jusqu_a` passé. `app.js` (`suivreEtapes`) et `_etapes.html.twig` montrent le message tant qu'il est vrai.
7. **Administration** (`DicteesControleur`, droit administrateur) : liste par pages de 100 et statistiques de la période, rapport d'une dictée (frise par passage, tableau), CSV de toute la période, rejeu.
8. **Rejeu** (`POST /admin/dictees/{id}/rejouer`) : crée une ligne `dictee_rejeu` (`en_cours`), puis après la réponse `Rejeu::executer` : contexte du chronomètre sur ce rejeu (processus `rejeu`, sans verrou, donc sans pouls), conversion, transcription (interrogée toutes les 3 s, 10 min au plus), extraction avec le catalogue et l'activité du compte ; garde le texte, le titre et le nombre de lignes. L'offre, les compteurs et les courriels ne sont pas touchés. Refusé (409) si un rejeu de la dictée est `en_cours` depuis moins de 15 minutes, ou si l'audio est purgé.

### Structures (migration `Version20261124200001`, additive)

`dictee_etape` : `id`, `enregistrement_id` (FK, `ON DELETE CASCADE`), `rejeu_id` (NULL pour la dictée elle-même, FK vers `dictee_rejeu`, `ON DELETE CASCADE`), `tentative TINYINT UNSIGNED`, `etape VARCHAR(24)`, `processus VARCHAR(16)`, `debut DATETIME(3)`, `fin DATETIME(3) NULL`, `duree_ms INT UNSIGNED NULL`, `issue VARCHAR(12)` (`en_cours`, `ok`, `erreur`, `interrompue`), `erreur VARCHAR(190) NULL`, `modele VARCHAR(100) NULL`, `jetons_envoyes INT UNSIGNED NULL`, `jetons_recus INT UNSIGNED NULL`, `premier_octet_ms INT UNSIGNED NULL` ; index `(enregistrement_id, rejeu_id, debut)` et `(debut)`.

`dictee_rejeu` : `id`, `enregistrement_id` (FK), `demande_par INT UNSIGNED` (artisan administrateur), `demande_le DATETIME`, `fini_le DATETIME NULL`, `statut VARCHAR(12)` (`en_cours`, `fini`, `echec`), `transcription MEDIUMTEXT NULL`, `titre VARCHAR(190) NULL`, `lignes SMALLINT UNSIGNED NULL`, `erreur VARCHAR(190) NULL`.

`enregistrement` : `navigateur VARCHAR(255) NULL`, `origine VARCHAR(10) NULL`. `duree_ms` existe (sous-chantier 30).

### Signatures

```php
// src/Systeme/Chronometre.php : sans passage ouvert, tout est sans effet (bancs, tests, outils)
final class Chronometre {
    public const WEB = 'web', TELEPHONE = 'telephone', APRES_REPONSE = 'apres_reponse', CRON = 'cron', REJEU = 'rejeu';
    public const BAIL_SECONDES = 150, POULS_SECONDES = 20;
    public function ouvrirPassage(int $enregistrementId, ?string $uuid, ?string $jeton, int $tentative, string $processus, ?int $rejeuId = null): void;
    public function fermerPassage(): void;
    public function commencer(string $etape, ?string $modele = null): EtapeChrono;   // écrit la ligne en_cours
    public function pouls(): void;                                                  // VerrouPerdu si le verrou est repris
}
final class EtapeChrono {
    public function finir(?array $usage = null, ?ResponseInterface $reponse = null): void;
    public function echouer(\Throwable $e): void;
}
// src/Donnees/DepotEtapes.php : hors portée (comme FileTraitement), le traitement écrit pour tous les comptes
// src/Donnees/DepotDictees.php : lecture de l'administration, exige PorteeEquipe avec toutVoir
// src/Donnees/FileTraitement.php : prolonger(string $uuid, string $jeton, \DateTimeImmutable $jusqua): bool ; noterDuree(string $uuid, int $ms): void
// src/Audio/Rejeu.php : executer(int $rejeuId): void
// src/Systeme/Navigateur.php : lisible(?string $userAgent): string   (« Safari, iPhone »)
```

`ExtracteurApi` et `TranscripteurApi` reçoivent `?Chronometre $chrono = null` en dernier paramètre : les bancs et les tests existants ne changent pas.

### Endpoints

| Route | Verbe | Droit | Entrée | Sortie |
|---|---|---|---|---|
| `/enregistrements/{uuid}/envoi` | POST | artisan propriétaire, CSRF | `envoi_ms` | 204 ; 404 autre compte ; 422 hors bornes |
| `/enregistrements/{uuid}/etat` | GET | existe | | ajoute `interrompu` |
| `/admin/dictees` | GET | administrateur | `du`, `au`, `filtre`, `page` | liste, statistiques |
| `/admin/dictees.csv` | GET | administrateur | `du`, `au`, `filtre` | CSV, `Content-Disposition: attachment` |
| `/admin/dictees/{id}` | GET | administrateur | | rapport ; 404 inconnu |
| `/admin/dictees/{id}/rejouer` | POST | administrateur, CSRF | | 303 vers le rapport ; 409 rejeu en cours ou audio purgé |

### Décisions fragiles

1. `on_progress` tourne au moins une fois par seconde pendant l'attente d'une réponse (contrat lu dans `HttpClientInterface.php`, client curl). À prouver sur un vrai serveur lent avant le lot 4 ; sinon le bail de 150 s tombe pour les appels longs.
2. ffmpeg (conversion) ne bat pas le pouls : il doit rester sous 150 s pour une dictée de 30 minutes. À mesurer en local sur un son de 30 minutes au lot 4.
3. Une exception lancée dans `on_progress` remonte à l'appelant, peut-être enveloppée par Symfony. Le `Traiteur` la reconnaît par `getPrevious` ; à vérifier par un test.
4. `REQUEST_TIME_FLOAT` marque le début de la réception : selon Apache, l'envoi du fichier peut être compté dedans (non vérifié sur Infomaniak). Les heures de l'envoi restent déduites et le rapport le dit.

## Lots

### Lot 1 : migration
- Fichiers : `migrations/Version20261124200001.php`, `tests/Donnees/SchemaTest.php`.
- Test : colonnes, types, clés étrangères ; vu rouge avant.
- Vérification : `migrations:migrate`, retour arrière (`migrations:execute --down`), `vendor/bin/phpunit tests/Donnees`.

### Lot 2 : chronomètre dans le traitement
- Fichiers : `src/Systeme/Chronometre.php`, `src/Systeme/EtapeChrono.php`, `src/Donnees/DepotEtapes.php`, `src/Donnees/FileTraitement.php`, `src/Audio/Traiteur.php`, `src/Http/Conteneur.php`, `bin/traiter-file`.
- Tests : `tests/Systeme/ChronometreTest.php` (sans passage : rien ; attente depuis `recu_le` ; reprise après une étape ouverte ; attente après erreur ; échec d'écriture du chronomètre sans effet sur la dictée) ; `tests/Audio/TraiteurTest.php` (étapes d'un parcours complet dans l'ordre, processus et tentative ; transcription sur deux passages).

### Lot 3 : appels à l'IA et transcription
- Fichiers : `src/Offre/ExtracteurApi.php`, `src/Audio/TranscripteurApi.php`, `src/Http/Conteneur.php`.
- Tests : `tests/Offre/ExtracteurApiTest.php` ou fichier à part (`MockHttpClient` : trois étapes avec modèle et jetons ; appel en erreur : issue `erreur`) ; `tests/Audio/TranscripteurApiTest.php` (conversion et envoi).

### Lot 4 : bail de 2 min 30, pouls, message de panne
- Preuves préalables : décisions fragiles 1 et 2 (script dans le scratchpad, sortie collée dans `ETAT.md`).
- Fichiers : `src/Audio/Traiteur.php`, `src/Systeme/Chronometre.php`, `src/Offre/ExtracteurApi.php`, `src/Audio/TranscripteurApi.php`, `src/Http/Controleur/EnregistrementControleur.php`, `src/Donnees/DepotEnregistrements.php`, `templates/_etapes.html.twig`, `public/js/app.js`.
- Tests : pouls qui prolonge pendant une réponse lente (`MockResponse` découpée, horloge factice) ; pouls qui trouve le verrou repris : appel coupé, aucune tentative comptée ; `/etat` rend `interrompu` ; message visible par l'interface (test navigateur).

### Lot 5 : réception et envoi mesuré
- Fichiers : `src/Http/Controleur/ImportControleur.php`, `src/Http/Controleur/EnregistrementControleur.php`, `src/Donnees/DepotEnregistrements.php`, `config/routes.php`, `public/js/app.js`.
- Tests : `tests/Http/ImportTest.php` (navigateur, origine, durée, étape `reception`) ; envoi : une fois, bornes, 404 pour un autre compte (`IsolationRoutesTest`), CSRF ; test navigateur : une dictée importée produit une étape `envoi`.

### Lot 6 : page des dictées
- Fichiers : `src/Donnees/DepotDictees.php`, `src/Systeme/Navigateur.php`, `src/Http/Controleur/DicteesControleur.php`, `config/routes.php`, `templates/admin/dictees.html.twig`, `templates/admin/dictee.html.twig`, `templates/admin/plus.html.twig`, `templates/admin/tableau.html.twig`, CSS de l'administration.
- Tests : droits (`DroitsEquipeTest`, `DroitAdministrateurTest` : modérateur et commercial reçoivent 404) ; statistiques (médiane, 90e centile, maximum, durée pour 100 jetons) sur un jeu connu ; marque à deux fois la médiane ; pages de 100 ; CSV (en-tête, une ligne par dictée, période entière) ; rapport (étapes, passages, tentative).
- Vérification : captures bureau et téléphone ouvertes (skill `recette-visuelle`).

### Lot 7 : rejeu
- Fichiers : `src/Audio/Rejeu.php`, `src/Donnees/DepotEtapes.php` (rejeux), `src/Http/Controleur/DicteesControleur.php`, `config/routes.php`, `templates/admin/dictee.html.twig`, `src/Http/Conteneur.php`.
- Tests : rejeu avec transcripteur et extracteur factices : étapes sous `rejeu_id`, offre inchangée (lignes, `modifie_le`), aucun courriel ; 409 si en cours ; 409 audio purgé ; CSRF ; droit administrateur.

### Lot 8 : revue, déploiement, recette
- Revue lourde : nouvelles routes, écriture hors portée, CSRF d'un message envoyé par `sendBeacon`, données d'administration.
- Déploiement sur le test (accord de Xavier à redemander), `migrations:status` avant et après ; recette par l'interface (une dictée sur le test, rapport, CSV, rejeu), `RECETTE.md`.

## Où coder

`~/offreGen-chrono`, base `offregen_chrono_test`. Commits par chemins, `git fetch` et rebase avant chaque push. Le sous-chantier 30 touche `Traiteur`, `FileTraitement`, `TranscripteurApi` : relire leur tête avant chaque lot.
