<?php

declare(strict_types=1);

namespace OffreGen\Http;

use Monolog\Handler\NullHandler;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use OffreGen\Audio\DureeAudio;
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
use OffreGen\Offre\Messagerie;
use OffreGen\Offre\MessagerieFichier;
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
            if ($this->environnement() === 'test') {
                $journal->pushHandler(new NullHandler());
            } else {
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

    public function enregistrements(Portee $p): DepotEnregistrements
    {
        return new DepotEnregistrements($this->base(), $p);
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
        // 14 jours sans revenir : l'artisan sur le chantier ne retape pas son mot de passe chaque matin.
        return new NativeSessionStorage([
            'name' => 'offregen',
            'cookie_lifetime' => 14 * 86400,
            'gc_maxlifetime' => 14 * 86400,
            'gc_probability' => 1,
            'gc_divisor' => 100,
            'cookie_httponly' => true,
            'cookie_secure' => 'auto',
            'cookie_samesite' => 'lax',
            'use_strict_mode' => true,
        ], new NativeFileSessionHandler($dossier));
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
            $twig->addExtension(new Formats());
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
                new PreparationAudio($this->parametre('FFMPEG', 'ffmpeg')),
                $this->parametreRequis('TRANSCRIPTION_URL'),
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
                $this->parametreRequis('EXTRACTION_URL'),
                $this->parametre('EXTRACTION_CLE'),
                $this->parametreRequis('EXTRACTION_MODELE'),
                $this->journal(),
                $this->parametre('EXTRACTION_SANS_RAISONNEMENT', '1') === '1',
            );
        });
    }

    public function http(): HttpClientInterface
    {
        return $this->une(HttpClientInterface::class, static fn (): HttpClientInterface => HttpClient::create());
    }

    private function parametreRequis(string $cle): string
    {
        $valeur = $this->parametre($cle);
        if ($valeur === '') {
            throw new \RuntimeException("Réglage manquant dans .env : $cle");
        }
        return $valeur;
    }

    public function messagerie(): Messagerie
    {
        return $this->une(Messagerie::class, fn (): Messagerie => new MessagerieFichier(
            $this->parametre('COURRIELS_DOSSIER', $this->racine . '/var/courriels'),
            $this->horloge(),
        ));
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
        ));
    }

    public function purge(): Purge
    {
        return $this->une(Purge::class, fn (): Purge => new Purge($this->fileTraitement(), $this->stockageAudio(), $this->horloge(), $this->journal()));
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
