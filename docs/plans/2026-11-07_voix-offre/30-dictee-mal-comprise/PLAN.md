# Plan : dictée mal comprise (sous-chantier 30)

Entrées : `SPEC.md` (décisions D1 à D8 du 2026-11-21), `MAQUETTE-dictee.html`. Le chantier traverse la base, le serveur et le navigateur : contrat technique d'abord, lot traceur avant la logique.

## Contrat technique

### Flux, de bout en bout (vérifié dans le code le 2026-11-21)

1. L'enregistreur (`public/js/app.js`, `demarrer`, l. 573) ouvre le micro par `getUserMedia`, enregistre par `MediaRecorder` et range chaque tranche à l'abri (sous-chantier 27). **À créer** : la jauge et la détection Bluetooth branchées sur le même flux, dans `public/js/jauge-voix.js`.
2. L'envoi passe par `POST /import` (`ImportControleur::envoyer`), qui crée la ligne `enregistrement` (`DepotEnregistrements::creer`) au statut `recu`. **À créer** : le champ facultatif `remplace` (identifiant d'une offre de l'artisan) pour « Refaire » depuis une offre.
3. `Traiteur::avancer` (`src/Audio/Traiteur.php`) fait `recu` → `transcription` → `transcrit` → `pret`. **À créer** entre `transcrit` et l'extraction : la mesure de la voix (`MesureVoix`) et le verdict (`JugeDictee`). Verdict `bon` : extraction comme aujourd'hui. Verdict `douteux` ou `inexploitable` : statut `a_confirmer`, pas d'extraction, pas de courriel « offre prête ».
4. `FileTraitement::aReprendre` et `noterAbandon` excluent `a_confirmer` (cron ne le reprend pas). `Statut::estFinal` le compte pour final.
5. La page de suivi (`/enregistrements/{uuid}`, `EnregistrementControleur`) interroge `/etat` chaque seconde (`app.js`). À `a_confirmer`, elle se recharge et montre la fenêtre de choix : constat, lecteur, onde, texte compris, « Refaire la dictée », « Créer l'offre quand même ».
6. L'audio est servi par une route **à créer**, `GET /enregistrements/{uuid}/audio`, seulement au compte propriétaire (`Portee`), 404 sinon, et seulement tant que `fichier` n'est pas purgé.
7. « Créer l'offre quand même » : `POST /enregistrements/{uuid}/creer` (**à créer**) remet `transcrit` avec `dictee_forcee = 1`, puis `Traiteur::avancer` fait l'extraction ; l'offre porte le bandeau.
8. « Refaire la dictée » depuis la fenêtre : `POST /enregistrements/{uuid}/retirer` (existe, `RangementControleur::retirer`), puis l'enregistreur s'ouvre.
9. L'abri du téléphone (`reprendre`, `app.js` l. 668) oublie une dictée quand `/etat` rend une offre ou 404. **À changer** : `a_confirmer` compte aussi comme arrivé (le serveur a l'audio).
10. La liste (`templates/accueil.html.twig`, `DepotEnregistrements::enCours`) montre la dictée `a_confirmer` avec un point orange ; l'offre créée quand même aussi, tant que `dictee_gardee_le` est vide (`_liste_offres_pc.html.twig` pour le PC).
11. L'offre créée quand même (`templates/offre.html.twig`) montre le bandeau, le lecteur, le texte compris ; « Garder » : `POST /offres/{id}/dictee-gardee` (**à créer**). « Refaire » ouvre l'enregistreur avec `remplace={id}` ; le `Traiteur` remplace alors les lignes de cette offre (`DepotOffres::remplacerLignes`, **à créer**), numéro et client gardés.

### Endpoints

| Route | Verbe | Droit | Entrée | Sortie |
|---|---|---|---|---|
| `/enregistrements/{uuid}/audio` | GET | artisan propriétaire | aucune | le fichier, `Content-Type` du fichier, `Accept-Ranges: bytes` (Safari l'exige pour lire), `Cache-Control: private, no-store` ; 404 sinon |
| `/enregistrements/{uuid}/creer` | POST | artisan propriétaire, CSRF | `_csrf` | 303 vers `/enregistrements/{uuid}` ; 409 si le statut n'est pas `a_confirmer` |
| `/enregistrements/{uuid}/etat` | GET | existe | aucune | ajoute `"a_confirmer": bool` |
| `/offres/{id}/dictee-gardee` | POST | artisan propriétaire, CSRF | `_csrf` | 303 vers `/offres/{id}` |
| `/import` | POST | existe | ajoute `remplace` (entier, facultatif) | inchangé ; `remplace` d'une offre d'un autre compte : 404 |

### Structures

Colonnes ajoutées à `enregistrement` (lot 2) :

| Colonne | Type | Contenu |
|---|---|---|
| `verdict` | `VARCHAR(20) NULL` | `bon`, `douteux`, `inexploitable` ; NULL avant la mesure |
| `verdict_cause` | `VARCHAR(30) NULL` | `voix_faible`, `voix_couverte`, `peu_de_mots`, `rien_compris` |
| `mots` | `INT UNSIGNED NULL` | mots du texte compris |
| `duree_ms` | `INT UNSIGNED NULL` | durée mesurée |
| `voix_db` | `SMALLINT NULL` | niveau de la voix en dixièmes de dB (-412 pour -41,2 dB) |
| `no_speech_max` | `SMALLINT UNSIGNED NULL` | plus haut `no_speech` des segments, en millièmes |
| `niveaux` | `TEXT NULL` | JSON, liste d'entiers : dB par seconde, pour l'onde du lecteur |
| `dictee_forcee` | `TINYINT(1) NOT NULL DEFAULT 0` | « Créer l'offre quand même » touché |
| `remplace_offre_id` | `INT UNSIGNED NULL` | offre dont la nouvelle dictée remplace les lignes |

Colonne ajoutée à `offre` : `dictee_gardee_le DATETIME NULL`.

Réponse de transcription en `verbose_json` (mesurée le 2026-11-21) : `{"duration": float, "segments": [{"start": float, "end": float, "text": string, "no_speech_prob": float, "avg_logprob": float, "compression_ratio": float}], "text": string}`.

### Signatures (sans corps)

```php
// src/Audio/Transcription.php (à créer)
final class Transcription {
    /** @param list<array{debut: float, fin: float, texte: string, sansParole: float}> $segments */
    public function __construct(public readonly string $texte, public readonly array $segments, public readonly ?float $duree) {}
    public function mots(): int;
    public function sansParoleMax(): float;
}

// src/Audio/Transcripteur.php (contrat changé)
public function resultat(string $reference): ?Transcription;

// src/Audio/MesureVoix.php (à créer) : ffmpeg astats par seconde
public function mesurer(string $chemin): ?Voix;
// src/Audio/Voix.php (à créer)
final class Voix { /** @param list<int> $niveaux dB par seconde */ public function __construct(public readonly array $niveaux, public readonly float $niveauParole, public readonly float $duree) {} }

// src/Audio/JugeDictee.php (à créer) : règles calibrées au lot 1
public function juger(Transcription $t, ?Voix $v): VerdictDictee;
// src/Audio/VerdictDictee.php (à créer)
final class VerdictDictee { public function __construct(public readonly string $verdict, public readonly ?string $cause) {} public function aConfirmer(): bool; }

// src/Donnees/FileTraitement.php (ajouts)
public function transcrit(string $uuid, string $jeton, Transcription $t): void;   // contrat changé
public function aConfirmer(string $uuid, string $jeton, VerdictDictee $v, ?Voix $voix, \DateTimeImmutable $supprimerApres): void;
public function noterMesure(string $uuid, string $jeton, VerdictDictee $v, ?Voix $voix): void;

// src/Donnees/DepotEnregistrements.php (ajouts)
public function forcer(string $uuid): bool;   // a_confirmer -> transcrit, dictee_forcee = 1
// src/Donnees/DepotOffres.php (ajouts)
public function remplacerLignes(int $offreId, array $lignes, \DateTimeImmutable $quand): void;
public function garderDictee(int $offreId, \DateTimeImmutable $quand): bool;
```

```js
// public/js/jauge-voix.js (à créer), fonctions pures testables
niveauParole(trames /* dB par trame de 50 ms, 4 s */) -> number   // 80e centile : les pauses ne comptent pas
estBluetooth(nomMicro, frequence) -> boolean
```

### Existant réutilisé

- `Traiteur`, `FileTraitement`, `Statut`, `DureeAudio` (même `Commande`, même ffmpeg `FFMPEG` du `.env`), `StockageAudio::chemin`, `Portee`, `RangementControleur::retirer`, `Purge` (la date `supprimer_apres` est posée à `a_confirmer` aussi).
- Styles : `.alerte warn`, `.alerte info`, `.feuille` de `public/css/app.css`.
- Banc du 2026-11-21 : `banc_infomaniak.sh`, `segments.py` (scratchpad), repris au lot 1.

### Décisions fragiles

1. J'ai supposé que le 80e centile des niveaux par seconde donne le niveau de la voix. Si les pauses d'une dictée lente dépassent 20 % du temps sous la voix, il le surestime peu ; si la voix d'à côté dure plus de 20 % du temps, il prend sa voix pour celle de l'artisan et rate la cause `voix_faible` (le mot par seconde la rattrape). Mesuré au lot 1, sinon le lot 4 est à revoir.
2. J'ai supposé que « Prendre le micro du téléphone » peut changer de micro sans couper l'enregistrement, en enregistrant un nœud Web Audio (`MediaStreamDestination`) au lieu du flux du micro. Si Safari sur iPhone ne le permet pas, le bouton recommence l'enregistrement et le lot 6 change de forme.
3. J'ai supposé qu'une dictée `a_confirmer` se range avec les dictées en préparation de la liste (sous-chantier 21). Si Xavier la veut parmi les offres, le lot 3 change d'écran.

## Lots

### Lot 1 : calibrer les seuils

- **Objectif** : sur tous les enregistrements dont l'audio est encore sur le test, mesurer mots par seconde, `no_speech` maximal, niveau de la voix ; fixer les seuils pour que les dictées ratées sortent en `douteux` et aucune dictée normale.
- **Fichiers** : aucun fichier de l'application. Scripts du scratchpad (banc du 2026-11-21 étendu), tableau dans `ETAT.md`.
- **Test** : le tableau lui-même, une ligne par enregistrement, avec le verdict des règles et le jugement « normale » ou « ratée » (texte compris lu à côté de l'offre). Les cas incertains sont listés pour Xavier.
- **Vérification** : tableau collé dans `ETAT.md`, zéro dictée normale en `douteux`, offres 60 et 61 en `douteux`.
- **Dépend de** : rien.

### Lot 2 : migration

- **Objectif** : les colonnes de la section Structures existent.
- **Fichiers** : `migrations/Version2026112….php` (à créer), `tests/Donnees/SchemaTest.php`.
- **Test** : `SchemaTest` vérifie chaque colonne et son type.
- **Vérification** : `vendor/bin/doctrine-migrations migrations:migrate` puis `migrations:up-to-date` ; `vendor/bin/phpunit tests/Donnees` vert. Alimente les préalables de la recette.
- **Dépend de** : rien.

### Lot 3 : traceur, de la dictée douteuse à la fenêtre de choix

- **Objectif** : un juge qui répond « douteux » en dur (classe de test) mène, sans extraction, à la fenêtre de choix avec le lecteur qui joue l'audio ; « Créer l'offre quand même » mène à une offre ; un autre compte reçoit 404 sur l'audio.
- **Fichiers** : `src/Donnees/Statut.php`, `src/Donnees/FileTraitement.php`, `src/Donnees/DepotEnregistrements.php`, `src/Audio/Traiteur.php`, `src/Audio/VerdictDictee.php`, `src/Audio/JugeDictee.php` (interface du juge), `src/Http/Controleur/EnregistrementControleur.php`, `config/routes.php`, `templates/enregistrement.html.twig`, `templates/accueil.html.twig`, `public/js/app.js` (état `a_confirmer`, abri).
- **Tests** : `tests/Audio/TraiteurTest.php` (douteux : pas d'appel à l'extracteur, statut `a_confirmer`, pas de courriel ; forcé : offre créée) ; `tests/Http/IsolationRoutesTest.php` (audio et `creer` d'un autre compte : 404) ; `tests/Http/DicteeDouteuseTest.php` (à créer, navigateur : fenêtre visible, lecteur, « Créer l'offre quand même » ouvre l'offre).
- **Vérification** : `vendor/bin/phpunit` vert, `vendor/bin/phpstan analyse` sans erreur, captures de la fenêtre au téléphone et au PC ouvertes.
- **Dépend de** : 2.

### Lot 4 : mesure, transcription détaillée, vrai verdict

- **Objectif** : à la réception, la voix est mesurée, la transcription est demandée en `verbose_json`, le verdict suit les seuils du lot 1 et ses chiffres sont gardés ; le constat de la fenêtre dit la cause mesurée. Voix nette et verdict douteux : relance coupée aux pauses (`DecoupeAuxPauses`, à créer, ffmpeg `silencedetect`), morceaux envoyés ensemble, texte le plus long gardé, nouveau verdict (SPEC, point 7).
- **Fichiers** : `src/Audio/TranscripteurApi.php`, `src/Audio/TranscripteurFactice.php`, `src/Audio/Transcripteur.php`, `src/Audio/Transcription.php`, `src/Audio/MesureVoix.php`, `src/Audio/Voix.php`, `src/Audio/JugeDictee.php`, `src/Http/Conteneur.php`, `templates/enregistrement.html.twig`.
- **Tests** : `tests/Audio/TranscripteurApiTest.php` (réponse `verbose_json` réelle du banc, anonymisée) ; `tests/Audio/JugeDicteeTest.php` (à créer : les chiffres du lot 1, une ligne par enregistrement, verdict attendu) ; `tests/Audio/MesureVoixTest.php` (à créer : deux sons fabriqués par ffmpeg à -24 et -42 dB) ; `tests/Audio/DecoupeAuxPausesTest.php` (à créer : un son fabriqué avec trois silences, coupé aux trois, aucun morceau de plus de 25 s) ; `tests/Audio/TraiteurTest.php` (relance : texte le plus long gardé, verdict refait).
- **Vérification** : `vendor/bin/phpunit` vert ; sur le test, la dictée de l'offre 61 renvoyée par l'enregistreur sort en `a_confirmer`, celle de l'offre 59 en offre directe (journal de l'application et base, sortie collée).
- **Dépend de** : 1, 3.

### Lot 5 : offre créée quand même, Garder, Refaire

- **Objectif** : l'offre créée quand même montre bandeau, lecteur et texte compris ; « Garder » retire bandeau et point orange ; « Refaire » remplace les lignes, numéro et client gardés.
- **Fichiers** : `templates/offre.html.twig`, `templates/_liste_offres_pc.html.twig`, `templates/accueil.html.twig`, `src/Http/Controleur/ImportControleur.php`, `src/Donnees/DepotOffres.php`, `src/Audio/Traiteur.php`, `config/routes.php`, `public/js/app.js`.
- **Tests** : `tests/Audio/TraiteurTest.php` (remplace : mêmes id, numéro, client ; lignes nouvelles) ; `tests/Http/IsolationRoutesTest.php` (`remplace` et `dictee-gardee` d'un autre compte : 404) ; `tests/Http/DicteeDouteuseTest.php` (Garder, Refaire par l'interface).
- **Vérification** : `vendor/bin/phpunit` vert, captures téléphone et PC ouvertes.
- **Dépend de** : 3, 4.

### Lot 6 : jauge lissée et message Bluetooth

- **Objectif** : pendant l'enregistrement, la jauge suit le niveau de parole des 4 dernières secondes (pauses ignorées), « Voix trop faible » après 4 s sous le seuil, ligne « Micro Bluetooth » quand le micro en est un.
- **Fichiers** : `public/js/jauge-voix.js` (à créer), `public/js/app.js`, `templates/_nouvelle_offre.html.twig`, `public/css/app.css`.
- **Tests** : `tests/Http/JaugeVoixTest.php` (à créer, navigateur Chromium avec `--use-file-for-fake-audio-capture` : son fabriqué à -24 dB avec pauses, la jauge reste verte ; à -45 dB, le message apparaît avant 10 s) ; `estBluetooth` sur une liste de noms de micros relevés.
- **Vérification** : `vendor/bin/phpunit tests/Http/JaugeVoixTest.php` vert, captures ouvertes. iPhone : `non_verifie` jusqu'à l'essai de Xavier dans sa voiture.
- **Dépend de** : rien (indépendant du serveur), placé après 5 pour ne pas toucher `app.js` en même temps.

### Lot 7 : revue, déploiement sur le test, recette

- **Objectif** : revue (surface sensible : nouvelle route qui sert un fichier, isolation), déploiement sur le test selon `docs/DEPLOIEMENT.md`, `RECETTE.md` écrite.
- **Vérification** : sonde verte sur le test, `REVUE.md` sans trouvaille ouverte, recette de Xavier par l'interface.
- **Dépend de** : 1 à 6.

## Où coder

`~/offreGen` porte des fichiers non commités d'autres sessions (`public/css/app.css`, `config/routes.php`, le roadmap, le 2026-11-21). Les lots 2 à 6 se codent dans une copie à soi, sur la tête de la branche (mémoire « Cadrage offreGen ») : `git worktree add --detach ~/offreGen-sc30 origin/chantier-voix-offre`, `.env` et `.env.test.local` copiés, `composer install`, puis `git push origin HEAD:chantier-voix-offre` sur demande de Xavier. Les documents de ce dossier y sont recopiés au premier commit.
