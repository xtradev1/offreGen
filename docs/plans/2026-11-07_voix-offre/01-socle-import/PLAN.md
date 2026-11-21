# Plan du sous-chantier 01 : socle et import

Spec parente : `../SPEC.md`, points 1 à 6, 11, et la part de 4 et 9 qui sert le traceur. Maquette :
`docs/plans/2026-11-06_maquette-ux/MAQUETTE-offre-vocale.html` (vues liste, nouvelle, traitement,
offre). Exécution dans la session principale, lot par lot.

**But :** un artisan se connecte, importe un fichier audio, le traitement démarre tout de suite et
cron le termine ; une offre apparaît avec des lignes produites par un transcripteur et un
extracteur factices. Tout tourne dans WSL, sans jeton et sans site de test.

**Pile :** PHP 8.5, `symfony/http-foundation`, `routing`, `security-csrf`, `dotenv` (7.4),
`twig/twig` 3, `doctrine/migrations` 3, `monolog/monolog` 3 ; en développement `phpunit/phpunit`,
`phpstan/phpstan`. Les autres bibliothèques de `CODING_RULES.md` arrivent avec le sous-chantier
qui s'en sert (YAGNI).

## Contrat technique

### Endpoints

Toutes les routes sont déclarées dans `config/routes.php` avec un droit : `public` ou `artisan`.
Une route sans droit répond 403. Tout `POST` passe par la vérification CSRF du noyau, avant le
contrôleur. Un visiteur non connecté sur une route `artisan` est redirigé (303) vers `/connexion`.

| Route | Verbe | Droit | Entrée | Sortie |
|---|---|---|---|---|
| `/connexion` | GET | public | rien | page de connexion |
| `/connexion` | POST | public | `courriel`, `mot_de_passe`, `_csrf` | 303 vers `/` ; ou page avec erreur, 200 ; ou 429 après trop d'échecs |
| `/deconnexion` | POST | artisan | `_csrf` | 303 vers `/connexion` |
| `/` | GET | artisan | rien | liste : enregistrements en cours, offres |
| `/import` | GET | artisan | rien | page d'import, rappel de l'accord du client |
| `/import` | POST | artisan | `audio` (fichier), `_csrf` | 303 vers `/enregistrements/{uuid}` ; ou page avec erreur, 422 ; ou 429 si le plafond démo est atteint |
| `/enregistrements/{uuid}` | GET | artisan | rien | état du traitement, rafraîchi toutes les 5 s jusqu'à `pret` ou `echec` |
| `/offres/{id}` | GET | artisan | rien | offre en lecture : lignes, total HT, TVA, total TTC |

Une ressource d'un autre artisan répond 404, comme une ressource absente : on ne révèle pas son
existence.

### Structures (tables)

Toutes les tables métier portent `artisan_id`. Montants en centimes (`INT`), quantités en
millièmes (`INT`), pour ne jamais calculer en flottant.

| Table | Colonnes |
|---|---|
| `artisan` | `id` INT PK, `courriel` VARCHAR(190) UNIQUE, `mot_de_passe` VARCHAR(255) (argon2id), `entreprise` VARCHAR(190), `demo` TINYINT, `plafond_jour` INT NULL, `cree_le` DATETIME |
| `journal_connexion` | `id` BIGINT PK, `artisan_id` INT NULL, `courriel_essaye` VARCHAR(190), `ip` VARCHAR(45), `reussi` TINYINT, `quand` DATETIME |
| `enregistrement` | `id` INT PK, `artisan_id` INT FK, `uuid` CHAR(36) UNIQUE, `nom_origine` VARCHAR(255), `type_mime` VARCHAR(100), `taille` INT, `fichier` VARCHAR(255) (relatif à `var/audio/`), `statut` VARCHAR(20), `transcription_ref` VARCHAR(100) NULL, `transcription` MEDIUMTEXT NULL, `tentatives` INT, `erreur` TEXT NULL, `verrou_jeton` CHAR(32) NULL, `verrou_jusqu_a` DATETIME NULL, `offre_id` INT NULL, `recu_le` DATETIME, `pret_le` DATETIME NULL, `supprimer_apres` DATETIME NULL |
| `offre` | `id` INT PK, `artisan_id` INT FK, `enregistrement_id` INT NULL UNIQUE, `titre` VARCHAR(190), `cree_le` DATETIME |
| `ligne` | `id` INT PK, `artisan_id` INT FK, `offre_id` INT FK, `rang` INT, `designation` VARCHAR(255), `quantite_milliemes` INT, `unite` VARCHAR(20), `prix_centimes` INT, `a_verifier` TINYINT, `produit_id` INT NULL |
| `produit` | `id` INT PK, `artisan_id` INT FK, `categorie` VARCHAR(100), `designation` VARCHAR(255), `unite` VARCHAR(20), `prix_centimes` INT, `synonymes` TEXT NULL |

`offre.enregistrement_id` UNIQUE : un enregistrement ne peut produire qu'une offre. C'est la
garantie, en base, qu'une étape rejouée ne double rien.

Statuts d'un enregistrement : `recu`, `transcription`, `transcrit`, `pret`, `echec`.
L'extraction et la création de l'offre forment une seule étape, dans une transaction, de `transcrit` à `pret`.

### Flux

1. `POST /import` : `ControleurImport` reçoit le fichier.
2. `ValidateurAudio` lit le type sur le contenu (`finfo`) et la taille.
3. `StockageAudio` écrit `var/audio/<artisan_id>/<uuid>.<ext>`, hors de `public/`.
4. `DepotEnregistrements::creer` insère la ligne `recu`.
5. Le contrôleur inscrit `Traiteur::avancer(uuid)` dans `ApresReponse`, puis rend une 303.
6. `public/index.php` envoie la réponse, appelle `fastcgi_finish_request()` s'il existe, puis exécute `ApresReponse`.
7. `Traiteur::avancer` prend le verrou de l'enregistrement, puis enchaîne les étapes jusqu'au blocage :
   - **`recu` :** `Transcripteur::demarrer`, puis statut `transcription` ;
   - **`transcription` :** `Transcripteur::resultat` ; s'il n'y a rien encore, il rend la main ; sinon, statut `transcrit` ;
   - **`transcrit` :** `Extracteur::extraire`, puis, dans une transaction :
     - création de l'offre et de ses lignes ;
     - statut `pret`, `supprimer_apres` = +30 jours.

     Après le commit, `Messagerie::envoyer` écrit le courriel « offre prête ».
8. Une exception augmente `tentatives`, garde le message dans `erreur` et rend le verrou. À 5 tentatives, statut `echec`.
9. `bin/traiter-file`, lancé par cron chaque minute, appelle `Traiteur::avancer` pour chaque enregistrement ni `pret` ni `echec` dont le verrou est libre ou expiré.
10. `GET /enregistrements/{uuid}` lit le statut. Quand l'enregistrement est `pret`, la page mène à `/offres/{id}`.

### Existant réutilisé

| Élément | Chemin |
|---|---|
| Règles du serveur web (refus par défaut, CSP fermée) | `public/.htaccess` |
| Sonde d'exposition et fichiers témoins | `scripts/securite/sonde-exposition.sh`, `public/sonde-temoin.*` |
| Aspect, couleurs, composants | `docs/plans/2026-11-06_maquette-ux/MAQUETTE-offre-vocale.html` (CSS à extraire dans `public/css/app.css`) |
| Catalogue fictif des trois métiers | même maquette, constante `METIERS` (à transcrire en PHP, lot 8) |
| Tout le reste | à créer |

## Signatures

Espace de noms `OffreGen\`, racine `src/`. Sans corps.

```php
// src/Http/
final class Noyau { public function __construct(Conteneur $c); public function traiter(Request $r): Response; }
final class ApresReponse { public function ajouter(callable $tache): void; public function executer(): void; }
final class Route { public function __construct(public string $nom, public string $chemin, public array $verbes, public string $controleur, public ?string $droit); }
final class Conteneur { public static function depuisEnvironnement(): self; /* fabrique paresseuse des services */ }

// src/Donnees/
final class Base { public function __construct(\PDO $pdo); public function lire(string $sql, array $p = []): array; public function un(string $sql, array $p = []): ?array; public function ecrire(string $sql, array $p = []): int; public function dernierId(): int; public function transaction(callable $f): mixed; }
final class Portee { public function __construct(public readonly int $artisanId); }
final class DepotArtisans { public function parCourriel(string $courriel): ?array; public function creer(string $courriel, string $hache, string $entreprise, bool $demo, ?int $plafond): int; public function parId(int $id): ?array; }
final class JournalConnexions { public function noter(?int $artisanId, string $courriel, string $ip, bool $reussi): void; public function echecsRecents(string $courriel, string $ip, int $minutes): int; }
final class DepotEnregistrements { public function __construct(Base $b, Portee $p); public function creer(string $uuid, string $nomOrigine, string $mime, int $taille, string $fichier): int; public function parUuid(string $uuid): ?array; public function enCours(): array; }
final class FileTraitement { /* seule lecture hors portée, pour cron */ public function aReprendre(int $limite): array; public function prendreVerrou(string $uuid, string $jeton, int $secondes): bool; public function rendreVerrou(string $uuid, string $jeton): void; }
final class DepotOffres { public function __construct(Base $b, Portee $p); public function creerDepuisEnregistrement(int $enregistrementId, string $titre, array $lignes): int; public function parId(int $id): ?array; public function lignes(int $offreId): array; public function liste(): array; public function creeesAujourdhui(): int; }
final class DepotProduits { public function __construct(Base $b, Portee $p); public function tous(): array; public function remplacerTout(array $produits): void; }

// src/Compte/
final class Authentification { public function connecter(Request $r, string $courriel, string $motDePasse): ResultatConnexion; public function deconnecter(Request $r): void; public function artisanConnecte(Request $r): ?int; }
enum ResultatConnexion { case Reussie; case Refusee; case TropDEssais; }
final class Comptes { public function creer(string $courriel, string $entreprise, bool $demo, ?int $plafond): string /* mot de passe généré, affiché une fois */; public function reinitialiserDemo(string $courriel, string $metier): void; }

// src/Audio/
final class ValidateurAudio { public const TAILLE_MAX = 200 * 1024 * 1024; public function valider(UploadedFile $f): AudioValide; /* lève AudioRefuse */ }
final class AudioValide { public function __construct(public string $mime, public string $extension, public int $taille, public string $cheminTemporaire); }
final class StockageAudio { public function __construct(string $racine); public function ranger(int $artisanId, string $uuid, AudioValide $a): string; public function chemin(string $fichier): string; public function supprimer(string $fichier): void; }
interface Transcripteur { public function demarrer(string $cheminAudio): string; public function resultat(string $reference): ?string; }
final class TranscripteurFactice implements Transcripteur {}
final class Traiteur { public function avancer(string $uuid): string /* statut final atteint */; }

// src/Offre/
interface Extracteur { /** @return list<LigneExtraite> */ public function extraire(string $transcription, array $catalogue): array; }
final class LigneExtraite { public function __construct(public string $designation, public int $quantiteMilliemes, public string $unite, public int $prixCentimes, public bool $aVerifier, public ?int $produitId); }
final class ExtracteurFactice implements Extracteur {}
final class Totaux { public static function calculer(array $lignes, int $tvaPourDixMille = 810): array /* ht, tva, ttc en centimes, arrondi aux 5 centimes */; }
interface Messagerie { public function envoyer(string $destinataire, string $sujet, string $texte): void; }
final class MessagerieFichier implements Messagerie {}
```

Commandes : `bin/creer-compte`, `bin/reinitialiser-demo`, `bin/traiter-file`, `bin/purger-audio`.

## Décisions fragiles

1. J'ai supposé que le serveur Cloud d'Infomaniak sert PHP par PHP-FPM, donc que `fastcgi_finish_request()` existe. Si c'est faux, l'import attend la fin de la première étape avant de rendre la main. Avec Whisper, c'est un simple envoi, donc acceptable, et aucun lot n'est à refaire.
2. J'ai supposé que Whisper chez Infomaniak reçoit le fichier par envoi, puis se consulte plus tard (`demarrer` puis `resultat`). S'il exige une adresse publique d'où télécharger l'audio, il faut une route signée et le lot 6 est à reprendre en partie.
3. J'ai supposé que `doctrine/migrations` (qui apporte DBAL) cohabite sans friction avec un accès aux données en PDO brut. Si son installation ou sa configuration coûte plus d'une heure, je le remplace par des fichiers SQL numérotés et un petit exécuteur, avec une entrée au roadmap.

## Lots

### Lot 1 : socle outillé et route traceuse

| Champ | Contenu |
|---|---|
| Objectif | `GET /connexion` rend une page Twig ; une route inconnue rend 404 ; une route sans droit rend 403 ; tests et analyse statique tournent. |
| Fichiers | `composer.json`, `composer.lock`, `phpunit.xml`, `phpstan.neon`, `.env.example`, `.gitignore` (ajout de `/var/`), `public/index.php`, `config/routes.php`, `src/Http/{Noyau,Route,Conteneur,ApresReponse}.php`, `templates/base.html.twig`, `templates/connexion.html.twig`, `public/css/app.css` |
| Test | `tests/Http/NoyauTest.php` : 404 sur route inconnue ; 403 sur une route de test sans droit ; 200 sur `/connexion` ; chaque route de `config/routes.php` déclare un droit. |
| Vérification | `vendor/bin/phpunit` vert ; `vendor/bin/phpstan analyse` sans erreur ; `curl -s -o /dev/null -w '%{http_code}' localhost:8000/connexion` rend 200 avec le serveur local. |
| Dépend de | rien |

### Lot 2 : bases et migrations (à part : préalable de recette)

| Champ | Contenu |
|---|---|
| Objectif | Bases `offregen_dev` et `offregen_test`, utilisateur d'application (lecture, écriture) et utilisateur de structure ; les six tables créées par migration. |
| Fichiers | script root hors dépôt (scratchpad) pour les bases et les utilisateurs ; `migrations.php`, `migrations-db.php`, `migrations/Version20261107000001.php` ; `.env` local (ignoré) |
| Test | `tests/Donnees/SchemaTest.php` : les six tables existent ; l'utilisateur d'application ne peut pas `CREATE TABLE`. |
| Vérification | `vendor/bin/doctrine-migrations migrate --no-interaction` puis `migrations:up-to-date` sur les deux bases ; phpunit vert. |
| Dépend de | 1 |

### Lot 3 : couche d'accès aux données et règle du SQL unique

| Champ | Contenu |
|---|---|
| Objectif | Les dépôts filtrent par `Portee` ; aucun SQL hors de `src/Donnees/`. |
| Fichiers | `src/Donnees/*.php` |
| Test | `tests/Donnees/IsolationDepotsTest.php` : avec la portée de A, `parUuid` et `parId` d'une donnée de B rendent `null`. `tests/Regles/SqlHorsDonneesTest.php` : aucun `SELECT`, `INSERT`, `UPDATE` ou `DELETE` dans `src/` hors de `src/Donnees/` et `migrations/`. |
| Vérification | phpunit vert ; le test de la règle échoue si on ajoute une requête dans `src/Http/` (essai fait puis retiré, sortie collée). |
| Dépend de | 2 |

### Lot 4 : connexion

| Champ | Contenu |
|---|---|
| Objectif | Connexion et déconnexion par session, CSRF central sur tout `POST`, 303 vers `/connexion` pour une route `artisan`, 429 après 5 échecs en 15 minutes (même courriel ou même IP), journal des connexions. |
| Fichiers | `src/Compte/{Authentification,ResultatConnexion,Comptes}.php`, `src/Http/Controleur/ConnexionControleur.php`, `bin/creer-compte`, gabarits |
| Test | `tests/Compte/ConnexionTest.php` : bon mot de passe, 303 et session régénérée ; mauvais, 200 avec erreur et une ligne au journal ; sixième échec, 429 ; `POST` sans jeton CSRF, 403 ; `GET /` sans session, 303 vers `/connexion`. |
| Vérification | phpunit vert ; connexion faite à la main dans le navigateur avec un compte créé par `bin/creer-compte`. |
| Dépend de | 3 |

### Lot 5 : import d'un fichier audio

| Champ | Contenu |
|---|---|
| Objectif | Un fichier audio valide est rangé hors de `public/` et crée un enregistrement `recu` ; un faux audio, un fichier trop gros ou un compte démo au plafond sont refusés. |
| Fichiers | `src/Audio/{ValidateurAudio,AudioValide,AudioRefuse,StockageAudio}.php`, `src/Http/Controleur/ImportControleur.php`, gabarits |
| Test | `tests/Audio/ValidateurAudioTest.php` : vrais petits fichiers m4a, mp3, ogg, wav créés pour le test (ffmpeg, sinon fichiers générés en PHP pour wav), acceptés ; texte renommé `.m4a`, refusé ; au-delà de la taille, refusé. `tests/Http/ImportTest.php` : 303 et ligne `recu` ; compte démo au plafond, 429. |
| Vérification | phpunit vert ; import d'un vrai m4a de téléphone par le navigateur (serveur local lancé avec `-d upload_max_filesize=200M -d post_max_size=210M`). |
| Dépend de | 4 |

### Lot 6 : traitement immédiat, cron et factices

| Champ | Contenu |
|---|---|
| Objectif | Après l'import, le traitement démarre tout de suite et produit une offre ; cron reprend ce qui a échoué ; jamais deux offres pour un enregistrement. |
| Fichiers | `src/Audio/{Transcripteur,TranscripteurFactice,Traiteur}.php`, `src/Offre/{Extracteur,ExtracteurFactice,LigneExtraite,Totaux,Messagerie,MessagerieFichier}.php`, `src/Http/ApresReponse.php`, `bin/traiter-file` |
| Test | `tests/Audio/TraiteurTest.php` : parcours `recu` à `pret` ; exception à l'extraction, tentatives à 1 et statut `transcrit`, puis reprise et `pret` avec une seule offre ; deux traiteurs sur le même uuid, un seul prend le verrou ; cinq échecs, `echec` ; courriel écrit dans `var/courriels/`. `tests/Offre/TotauxTest.php` : TVA 8.1 % et arrondi aux 5 centimes sur des cas calculés à la main. |
| Vérification | phpunit vert ; import par le navigateur, puis la page d'état passe à `pret` sans lancer cron ; avec le démarrage immédiat coupé, `bin/traiter-file` termine. |
| Dépend de | 5 |

### Lot 7 : pages liste, état et offre, isolation route par route

| Champ | Contenu |
|---|---|
| Objectif | La liste, la page d'état et l'offre s'affichent dans l'aspect de la maquette, au pouce ; l'artisan A n'atteint aucune donnée de B par aucune route. |
| Fichiers | `src/Http/Controleur/{AccueilControleur,EnregistrementControleur,OffreControleur}.php`, gabarits, `public/css/app.css` |
| Test | `tests/Http/IsolationRoutesTest.php` : pour chaque route `artisan` à paramètre, A demande la ressource de B, 404 ; parcourt `config/routes.php` pour qu'une nouvelle route sans cas d'isolation fasse échouer le test. |
| Vérification | phpunit vert ; captures Playwright à 390 px et en bureau, ouvertes et regardées (skill `recette-visuelle`). |
| Dépend de | 6 |

### Lot 8 : comptes démo et purge de l'audio

| Champ | Contenu |
|---|---|
| Objectif | `bin/reinitialiser-demo` remet un compte démo à l'état d'origine (catalogue fictif du métier choisi, rien d'autre) ; refuse un compte qui n'est pas démo ; `bin/purger-audio` supprime les fichiers dont `supprimer_apres` est passé. |
| Fichiers | `src/Compte/Comptes.php`, `src/Catalogue/CatalogueFictif.php`, `bin/reinitialiser-demo`, `bin/purger-audio` |
| Test | `tests/Compte/DemoTest.php` : après réinitialisation, zéro offre, zéro enregistrement, fichiers supprimés, catalogue du métier ; compte non démo, refus. `tests/Audio/PurgeTest.php`. |
| Vérification | phpunit vert ; commandes lancées à la main, sorties collées. |
| Dépend de | 6 |

## Hors de ce sous-chantier

`/sante`, déploiement, workflows GitHub (02) ; vrai Whisper (04) ; vraie extraction (03) ;
corrections à l'écran (05) ; double authentification (09).
