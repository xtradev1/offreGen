<?php

declare(strict_types=1);

namespace OffreGen\Http;

use Monolog\Handler\NullHandler;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use OffreGen\Adresse\Adresse;
use OffreGen\Adresse\RegistreAdresses;
use OffreGen\Adresse\RegistreFactice;
use OffreGen\Adresse\RegistreSwisstopo;
use OffreGen\Audio\DureeAudio;
use OffreGen\Compte\PlafondEssai;
use OffreGen\Compte\TraitementLogo;
use OffreGen\Audio\PreparationAudio;
use OffreGen\Audio\Purge;
use OffreGen\Audio\StockageAudio;
use OffreGen\Audio\Traiteur;
use OffreGen\Audio\Transcripteur;
use OffreGen\Audio\TranscripteurApi;
use OffreGen\Audio\TranscripteurFactice;
use OffreGen\Audio\ValidateurAudio;
use OffreGen\Compte\Authentification;
use OffreGen\Compte\Comptes;
use OffreGen\Compte\LiensAcces;
use OffreGen\Donnees\DepotLiensAcces;
use OffreGen\Donnees\Base;
use OffreGen\Donnees\DepotArtisans;
use OffreGen\Donnees\DepotClients;
use OffreGen\Donnees\DepotFactures;
use OffreGen\Donnees\DepotEnregistrements;
use OffreGen\Donnees\DepotOffres;
use OffreGen\Donnees\DepotProduits;
use OffreGen\Donnees\DepotReglages;
use OffreGen\Donnees\FabriqueConnexion;
use OffreGen\Donnees\FileTraitement;
use OffreGen\Donnees\JournalConnexions;
use OffreGen\Donnees\Portee;
use OffreGen\Offre\DocumentPdf;
use OffreGen\Offre\Extracteur;
use OffreGen\Offre\ExtracteurApi;
use OffreGen\Offre\ExtracteurFactice;
use OffreGen\Systeme\Messagerie;
use OffreGen\Systeme\Alertes;
use OffreGen\Systeme\GestionnaireAlertes;
use OffreGen\Systeme\MessagerieFichier;
use OffreGen\Systeme\MessagerieRedirigee;
use OffreGen\Systeme\MessagerieSmtp;
use Symfony\Component\Clock\ClockInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Clock\NativeClock;
use Symfony\Component\Dotenv\Dotenv;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Storage\Handler\NativeFileSessionHandler;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\HttpFoundation\Session\Storage\NativeSessionStorage;
use Symfony\Component\HttpFoundation\Session\Storage\SessionStorageInterface;
use Symfony\Component\PasswordHasher\Hasher\NativePasswordHasher;
use Symfony\Component\PasswordHasher\PasswordHasherInterface;
use Symfony\Component\Security\Csrf\CsrfTokenManager;
use Symfony\Component\Security\Csrf\TokenStorage\SessionTokenStorage;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;
use Twig\TwigFunction;
use OffreGen\Compte\ArtisansEquipe;
use OffreGen\Compte\Equipe;
use OffreGen\Compte\Role;
use OffreGen\Donnees\DepotEquipe;
use OffreGen\Donnees\JournalAdmin;

/**
 * Fabrique les services de l'application, chacun une seule fois et seulement s'il sert.
 */
final class Conteneur
{
    /** @var array<string, object> */
    private array $services = [];

    /** @param array<string, string> $env */
    public function __construct(
        private readonly string $racine,
        private readonly array $env,
        private readonly ?ClockInterface $horlogeImposee = null,
        private readonly ?\PDO $pdoImpose = null,
    ) {
    }

    public static function depuisEnvironnement(string $racine): self
    {
        return new self($racine, self::lireEnvironnement($racine));
    }

    /**
     * Les réglages : le .env, puis les variables posées par le système ou le serveur web, qui l'emportent.
     * Le PHP d'Ubuntu (variables_order = "GPCS") ne met jamais les variables du système dans $_ENV :
     * constaté le 2026-11-07, elles ne sont que dans getenv() sous php -S, dans getenv() et $_SERVER en
     * ligne de commande. Les clés HTTP_* de $_SERVER viennent du client et ne sont jamais lues.
     *
     * @return array<string, string>
     */
    public static function lireEnvironnement(string $racine): array
    {
        if (is_file($racine . '/.env')) {
            (new Dotenv())->usePutenv(false)->bootEnv($racine . '/.env', 'dev');
        }
        $env = [];
        // getenv() en dernier : Dotenv écrit les valeurs du .env dans $_ENV et $_SERVER sans voir les variables
        // que php -S ne met que dans getenv() ; lues avant, elles perdaient contre le fichier (2026-11-12).
        foreach ([$_ENV, $_SERVER, getenv()] as $source) {
            foreach ($source as $cle => $valeur) {
                $cle = (string) $cle;
                if (is_string($valeur) && !str_starts_with($cle, 'HTTP_')) {
                    $env[$cle] = $valeur;
                }
            }
        }
        return $env;
    }

    /**
     * Réglages des migrations : ceux du site, plus .env.structure, où vit sur les serveurs le mot de passe de
     * l'utilisateur de structure. Le site web ne lit jamais ce fichier (revue du 2026-11-18, M12, docs/SECURITE.md).
     *
     * @return array<string, string>
     */
    public static function lireEnvironnementMigrations(string $racine): array
    {
        $env = self::lireEnvironnement($racine);
        $fichier = $racine . '/.env.structure';
        if (is_file($fichier)) {
            $env = (new Dotenv())->parse((string) file_get_contents($fichier), '.env.structure') + $env;
        }
        return $env;
    }

    public function environnement(): string
    {
        return $this->env['APP_ENV'] ?? 'prod';
    }

    public function parametre(string $cle, string $defaut = ''): string
    {
        return $this->env[$cle] ?? $defaut;
    }

    public function racine(): string
    {
        return $this->racine;
    }

    /**
     * @template T of object
     * @param class-string<T> $classe
     * @param \Closure(): T $fabrique
     * @return T
     */
    private function une(string $classe, \Closure $fabrique, string $cle = ''): object
    {
        $cle = $classe . $cle;
        if (!isset($this->services[$cle])) {
            $this->services[$cle] = $fabrique();
        }
        /** @var T */
        return $this->services[$cle];
    }

    public function horloge(): ClockInterface
    {
        return $this->horlogeImposee ?? $this->une(NativeClock::class, static fn (): NativeClock => new NativeClock('UTC'));
    }

    public function journal(): LoggerInterface
    {
        return $this->une(Logger::class, function (): Logger {
            $journal = new Logger('offregen');
            // Monolog appelle le dernier posé en premier. Ordre voulu : le fichier du journal, puis l'alerte ; en test,
            // l'alerte, puis le gestionnaire muet (il arrête la chaîne, il doit donc passer en dernier).
            if ($this->environnement() === 'test') {
                $journal->pushHandler(new NullHandler());
            }
            // ALERTES_A : les erreurs partent aussi par courriel à l'exploitant (Q3, 2026-11-18). Vide : rien ne part.
            $destinataire = $this->parametre('ALERTES_A');
            if ($destinataire !== '') {
                $journal->pushHandler(new GestionnaireAlertes(
                    fn (): Alertes => new Alertes(
                        $this->messagerie(),
                        $destinataire,
                        $this->parametre('ALERTES_DOSSIER', $this->racine . '/var/alertes'),
                        $this->horloge(),
                        $this->parametre('APP_URL', 'http://localhost:8000'),
                    ),
                    function (string $message): void {
                        if ($this->environnement() !== 'test') {
                            error_log($message);
                        }
                    },
                ));
            }
            if ($this->environnement() !== 'test') {
                $journal->pushHandler(new StreamHandler($this->racine . '/var/log/app.log'));
            }
            return $journal;
        });
    }

    public function base(): Base
    {
        return $this->une(Base::class, fn (): Base => new Base($this->pdoImpose ?? (new FabriqueConnexion($this->env))->pdo()));
    }

    public function hacheur(): PasswordHasherInterface
    {
        return $this->une(NativePasswordHasher::class, static fn (): NativePasswordHasher => new NativePasswordHasher(null, null, null, PASSWORD_ARGON2ID));
    }

    public function artisans(): DepotArtisans
    {
        return $this->une(DepotArtisans::class, fn (): DepotArtisans => new DepotArtisans($this->base()));
    }

    public function authentification(): Authentification
    {
        return $this->une(Authentification::class, fn (): Authentification => new Authentification(
            $this->artisans(),
            new JournalConnexions($this->base()),
            $this->hacheur(),
            $this->horloge(),
        ));
    }

    public function comptes(): Comptes
    {
        return $this->une(Comptes::class, fn (): Comptes => new Comptes($this->base(), $this->artisans(), $this->hacheur(), $this->stockageAudio(), $this->horloge()));
    }

    public function journalAdmin(): JournalAdmin
    {
        return $this->une(JournalAdmin::class, fn (): JournalAdmin => new JournalAdmin($this->base()));
    }

    public function depotEquipe(): DepotEquipe
    {
        return $this->une(DepotEquipe::class, fn (): DepotEquipe => new DepotEquipe($this->base()));
    }

    public function equipe(): Equipe
    {
        return $this->une(Equipe::class, fn (): Equipe => new Equipe($this->base(), $this->artisans(), $this->depotEquipe(), $this->journalAdmin(), $this->comptes(), $this->horloge()));
    }

    public function artisansEquipe(): ArtisansEquipe
    {
        return $this->une(ArtisansEquipe::class, fn (): ArtisansEquipe => new ArtisansEquipe($this->base(), $this->depotEquipe(), $this->journalAdmin(), $this->horloge()));
    }

    public function liensAcces(): LiensAcces
    {
        return $this->une(LiensAcces::class, fn (): LiensAcces => new LiensAcces(
            $this->base(),
            new DepotLiensAcces($this->base()),
            $this->artisans(),
            $this->messagerie(),
            $this->hacheur(),
            $this->horloge(),
            $this->journal(),
            $this->parametre('APP_URL', 'http://localhost:8000'),
            $this->nomApplication(),
        ));
    }

    /** Le nom affiché dans les pages et les courriels. Dicta : choix de Xavier du 2026-11-18 (docs/identite/). */
    public function nomApplication(): string
    {
        return $this->parametre('APP_NOM', 'Dicta');
    }

    public function enregistrements(Portee $p): DepotEnregistrements
    {
        return new DepotEnregistrements($this->base(), $p);
    }

    /** Plafond total d'un compte d'essai, avec ce qu'il reste ; null pour un compte sans plafond. */
    public function plafondEssai(int $artisanId): ?PlafondEssai
    {
        $offresMax = $this->artisans()->parId($artisanId)['offres_max'] ?? null;
        return $offresMax === null ? null : PlafondEssai::de($offresMax, $this->enregistrements(new Portee($artisanId))->recus());
    }

    public function offres(Portee $p): DepotOffres
    {
        return new DepotOffres($this->base(), $p);
    }

    public function produits(Portee $p): DepotProduits
    {
        return new DepotProduits($this->base(), $p);
    }

    public function documentPdf(): DocumentPdf
    {
        return $this->une(DocumentPdf::class, fn (): DocumentPdf => new DocumentPdf($this->racine . '/templates', $this->racine . '/var/cache/pdf'));
    }

    public function factures(Portee $p): DepotFactures
    {
        return new DepotFactures($this->base(), $p);
    }

    public function clients(Portee $p): DepotClients
    {
        return new DepotClients($this->base(), $p);
    }

    public function reglages(Portee $p): DepotReglages
    {
        return new DepotReglages($this->base(), $p);
    }

    public function garde(): Garde
    {
        return $this->une(Garde::class, function (): Garde {
            $pile = new RequestStack();
            return new Garde(
                $this->authentification(),
                new CsrfTokenManager(null, new SessionTokenStorage($pile)),
                $pile,
                fn (): SessionStorageInterface => $this->stockageSession(),
            );
        });
    }

    private function stockageSession(): SessionStorageInterface
    {
        if ($this->environnement() === 'test') {
            return new MockArraySessionStorage();
        }
        $dossier = $this->racine . '/var/sessions';
        if (!is_dir($dossier)) {
            mkdir($dossier, 0700, true);
        }
        return new NativeSessionStorage($this->optionsSession(), new NativeFileSessionHandler($dossier));
    }

    /**
     * 14 jours sans revenir : l'artisan sur le chantier ne retape pas son mot de passe chaque matin.
     * Secure dès que l'adresse de l'application est en https, et par défaut (revue du 2026-11-18, H3 : la valeur
     * « auto » est ignorée par Symfony hors framework, le cookie partait sans Secure sur le site de test).
     *
     * @return array{name: string, cookie_lifetime: int, gc_maxlifetime: int, gc_probability: int, gc_divisor: int, cookie_httponly: bool, cookie_secure: bool, cookie_samesite: string, use_strict_mode: bool}
     */
    public function optionsSession(): array
    {
        return [
            'name' => 'offregen',
            'cookie_lifetime' => 14 * 86400,
            'gc_maxlifetime' => 14 * 86400,
            'gc_probability' => 1,
            'gc_divisor' => 100,
            'cookie_httponly' => true,
            'cookie_secure' => !str_starts_with($this->parametre('APP_URL', 'https://'), 'http://'),
            'cookie_samesite' => 'lax',
            'use_strict_mode' => true,
        ];
    }

    public function twig(): Environment
    {
        return $this->une(Environment::class, function (): Environment {
            $dev = $this->environnement() !== 'prod';
            $twig = new Environment(new FilesystemLoader($this->racine . '/templates'), [
                'cache' => $dev ? false : $this->racine . '/var/cache/twig',
                'strict_variables' => true,
                'autoescape' => 'html',
            ]);
            $twig->addFunction(new TwigFunction('csrf', fn (): string => $this->garde()->jeton()));
            $twig->addFunction(new TwigFunction('messages', fn (): array => $this->garde()->messages()));
            $twig->addFunction(new TwigFunction('compte', fn (): ?int => $this->garde()->artisanCourant()));
            // Le rôle d'équipe du compte connecté : l'entrée « Administration » de « Plus » et du menu n'existe que pour lui.
            $twig->addFunction(new TwigFunction('role_equipe', function (): ?Role {
                $id = $this->garde()->artisanCourant();
                return $id === null ? null : $this->authentification()->role($id);
            }));
            $twig->addExtension(new Formats());
            $twig->addGlobal('pays_adresse', Adresse::PAYS);
            $twig->addGlobal('nom_application', $this->nomApplication());
            $twig->addGlobal('duree_max_secondes', $this->dureeMaxSecondes());
            $twig->addFunction(new TwigFunction('essai', function (): ?PlafondEssai {
                $id = $this->garde()->artisanCourant();
                return $id === null ? null : $this->plafondEssai($id);
            }));
            return $twig;
        });
    }

    public function traitementLogo(): TraitementLogo
    {
        return $this->une(TraitementLogo::class, fn (): TraitementLogo => new TraitementLogo($this->parametre('FFMPEG', 'ffmpeg')));
    }

    /** Les logos des artisans, hors du dossier public : servis par /reglages/logo au seul artisan connecté. */
    public function dossierLogos(): string
    {
        return $this->parametre('LOGOS_DOSSIER', $this->racine . '/var/logos');
    }

    /** Règle de Xavier (2026-11-17) : une dictée dure 30 minutes au plus (banc : au-delà, des postes sont oubliés). Le navigateur s'arrête là, le serveur refuse au-delà. */
    public function dureeMaxSecondes(): int
    {
        return max(1, (int) $this->parametre('DUREE_MAX_SECONDES', '1800'));
    }

    public function dureeAudio(): DureeAudio
    {
        return $this->une(DureeAudio::class, fn (): DureeAudio => new DureeAudio($this->parametre('FFPROBE', 'ffprobe')));
    }

    public function validateurAudio(): ValidateurAudio
    {
        return $this->une(ValidateurAudio::class, static fn (): ValidateurAudio => new ValidateurAudio());
    }

    public function stockageAudio(): StockageAudio
    {
        return $this->une(StockageAudio::class, fn (): StockageAudio => new StockageAudio($this->parametre('AUDIO_DOSSIER', $this->racine . '/var/audio')));
    }

    public function fileTraitement(): FileTraitement
    {
        return $this->une(FileTraitement::class, fn (): FileTraitement => new FileTraitement($this->base()));
    }

    /**
     * TRANSCRIPTION=api : protocole d'Infomaniak, vers TRANSCRIPTION_URL (le Whisper local ou Infomaniak).
     * Autre valeur ou absente : transcripteur factice.
     */
    public function transcripteur(): Transcripteur
    {
        return $this->une(Transcripteur::class, function (): Transcripteur {
            if ($this->parametre('TRANSCRIPTION') !== 'api') {
                return new TranscripteurFactice();
            }
            return new TranscripteurApi(
                $this->http(),
                new PreparationAudio($this->parametre('FFMPEG', 'ffmpeg'), PreparationAudio::TAILLE_MAX, null, $this->dureeMaxSecondes() + DureeAudio::TOLERANCE_SECONDES),
                $this->adresseService('TRANSCRIPTION_URL'),
                $this->parametre('TRANSCRIPTION_PRODUIT', '0'),
                $this->parametre('TRANSCRIPTION_CLE'),
                $this->parametre('TRANSCRIPTION_MODELE', 'whisper'),
            );
        });
    }

    /**
     * EXTRACTION=api : route chat/completions au format OpenAI, vers EXTRACTION_URL (Ollama ou Infomaniak).
     * Autre valeur ou absente : extracteur factice.
     */
    public function extracteur(): Extracteur
    {
        return $this->une(Extracteur::class, function (): Extracteur {
            if ($this->parametre('EXTRACTION') !== 'api') {
                return new ExtracteurFactice();
            }
            return new ExtracteurApi(
                $this->http(),
                $this->adresseService('EXTRACTION_URL'),
                $this->parametre('EXTRACTION_CLE'),
                $this->parametreRequis('EXTRACTION_MODELE'),
                $this->journal(),
                $this->parametre('EXTRACTION_SANS_RAISONNEMENT', '1') === '1',
                $this->parametre('EXTRACTION_MODELE_PIECES') === '' ? null : $this->parametre('EXTRACTION_MODELE_PIECES'),
            );
        });
    }

    /**
     * ADRESSES=swisstopo : suggestions et vérification par le registre officiel de swisstopo (gratuit, sans clé).
     * Autre valeur ou absente : registre factice, rien ne sort de la machine.
     */
    public function registreAdresses(): RegistreAdresses
    {
        return $this->une(RegistreAdresses::class, fn (): RegistreAdresses => $this->parametre('ADRESSES') === 'swisstopo'
            ? new RegistreSwisstopo($this->http())
            : new RegistreFactice());
    }

    public function http(): HttpClientInterface
    {
        return $this->une(HttpClientInterface::class, static fn (): HttpClientInterface => HttpClient::create());
    }

    /** Adresse d'un service qui reçoit le jeton : https exigé hors développement (revue du 2026-11-18, B4). */
    private function adresseService(string $cle): string
    {
        $adresse = $this->parametreRequis($cle);
        if ($this->environnement() === 'prod' && !str_starts_with($adresse, 'https://')) {
            throw new \RuntimeException("Réglage $cle : https exigé en production, le jeton partirait en clair.");
        }
        return $adresse;
    }

    private function parametreRequis(string $cle): string
    {
        $valeur = $this->parametre($cle);
        if ($valeur === '') {
            throw new \RuntimeException("Réglage manquant dans .env : $cle");
        }
        return $valeur;
    }

    /**
     * MESSAGERIE=smtp : envoi réel par MAILER_DSN, au nom de COURRIELS_EXPEDITEUR. « fichier » ou absente : chaque
     * courriel devient un fichier .eml, rien ne part. Toute autre valeur est refusée (« SMTP » passait en fichiers sans
     * bruit, revue du 2026-11-18). COURRIELS_REDIRIGER_VERS envoie tout à une seule adresse (site de test).
     */
    public function messagerie(): Messagerie
    {
        return $this->une(Messagerie::class, function (): Messagerie {
            $messagerie = match ($this->parametre('MESSAGERIE')) {
                'smtp' => new MessagerieSmtp($this->parametreRequis('MAILER_DSN'), $this->parametreRequis('COURRIELS_EXPEDITEUR')),
                '', 'fichier' => new MessagerieFichier($this->parametre('COURRIELS_DOSSIER', $this->racine . '/var/courriels'), $this->horloge()),
                default => throw new \RuntimeException('Réglage MESSAGERIE inconnu : « smtp » ou « fichier ».'),
            };
            $redirection = $this->parametre('COURRIELS_REDIRIGER_VERS');
            return $redirection === '' ? $messagerie : new MessagerieRedirigee($messagerie, $redirection);
        });
    }

    /** Les courriels partent vraiment (MESSAGERIE=smtp) ; sinon ils restent en fichiers dans var/courriels. */
    public function messagerieReelle(): bool
    {
        return $this->parametre('MESSAGERIE') === 'smtp';
    }

    public function traiteur(): Traiteur
    {
        return $this->une(Traiteur::class, fn (): Traiteur => new Traiteur(
            $this->base(),
            $this->fileTraitement(),
            $this->transcripteur(),
            $this->extracteur(),
            $this->messagerie(),
            $this->artisans(),
            $this->stockageAudio(),
            $this->horloge(),
            $this->journal(),
            $this->parametre('APP_URL', 'http://localhost:8000'),
            $this->dureeAudio(),
            $this->dureeMaxSecondes(),
            $this->nomApplication(),
        ));
    }

    public function purge(): Purge
    {
        return $this->une(Purge::class, fn (): Purge => new Purge($this->fileTraitement(), $this->stockageAudio(), $this->horloge(), $this->journal(),
            $this->parametre('PURGE_AUDIO') === '1'));
    }

    /** Démarre le traitement juste après la réponse, sauf si TRAITEMENT_IMMEDIAT=0 : cron s'en charge alors. */
    public function programmerTraitement(string $uuid): void
    {
        if ($this->parametre('TRAITEMENT_IMMEDIAT', '1') === '0') {
            return;
        }
        $this->apresReponse()->ajouter(function () use ($uuid): void {
            $this->traiteur()->avancer($uuid, Traiteur::ATTENTE_IMMEDIATE_SECONDES);
        });
    }

    public function apresReponse(): ApresReponse
    {
        return $this->une(ApresReponse::class, fn (): ApresReponse => new ApresReponse($this->journal()));
    }

    public function noyau(): Noyau
    {
        return $this->une(Noyau::class, function (): Noyau {
            /** @var callable(self): list<Route> $declarer */
            $declarer = require $this->racine . '/config/routes.php';
            return new Noyau($declarer($this), $this->twig(), $this->garde(), fn (string $m) => $this->journal()->error($m));
        });
    }
}
