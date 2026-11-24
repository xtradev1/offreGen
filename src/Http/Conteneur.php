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
use OffreGen\Audio\Rejeu;
use OffreGen\Audio\Traiteur;
use OffreGen\Audio\Transcripteur;
use OffreGen\Audio\TranscripteurApi;
use OffreGen\Audio\TranscripteurFactice;
use OffreGen\Audio\ValidateurAudio;
use OffreGen\Compte\Activite;
use OffreGen\Compte\Authentification;
use OffreGen\Compte\Comptes;
use OffreGen\Compte\DemandesAppel;
use OffreGen\Compte\DemandesEssai;
use OffreGen\Compte\Demos;
use OffreGen\Compte\MoisGratuit;
use OffreGen\Compte\SuppressionDemos;
use OffreGen\Donnees\DepotAppels;
use OffreGen\Compte\LiensAcces;
use OffreGen\Donnees\DepotLiensAcces;
use OffreGen\Donnees\Base;
use OffreGen\Donnees\DepotArtisans;
use OffreGen\Donnees\DepotClients;
use OffreGen\Donnees\DepotFactures;
use OffreGen\Donnees\DepotEnregistrements;
use OffreGen\Donnees\DepotEnvois;
use OffreGen\Donnees\DepotOffres;
use OffreGen\Donnees\DepotProduits;
use OffreGen\Donnees\DepotReglages;
use OffreGen\Donnees\FabriqueConnexion;
use OffreGen\Donnees\DepotEtapes;
use OffreGen\Donnees\FileTraitement;
use OffreGen\Donnees\JournalConnexions;
use OffreGen\Donnees\Portee;
use OffreGen\Offre\DocumentPdf;
use OffreGen\Offre\PdfGarde;
use OffreGen\Offre\Extracteur;
use OffreGen\Offre\ExtracteurApi;
use OffreGen\Offre\ExtracteurFactice;
use OffreGen\Catalogue\Comparateur;
use OffreGen\Catalogue\ComparateurApi;
use OffreGen\Catalogue\ComparateurFactice;
use OffreGen\Catalogue\Lecteur;
use OffreGen\Catalogue\LecteurApi;
use OffreGen\Catalogue\LecteurFactice;
use OffreGen\Donnees\DepotImports;
use OffreGen\Systeme\Chronometre;
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
use OffreGen\Compte\Prospects;
use OffreGen\Donnees\DepotProspects;
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

    /** Les textes de l'onboarding (chantier 2026-11-21_onboarding). */
    public function textes(): Textes
    {
        return $this->une(Textes::class, fn (): Textes => new Textes($this->racine . '/config/textes-onboarding.json'));
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

    /** Le retour direct d'un membre depuis sa démo d'entraînement (chantier onboarding, lot 8). */
    public function retourEquipe(): \OffreGen\Compte\RetourEquipe
    {
        return new \OffreGen\Compte\RetourEquipe($this->artisans(), $this->depotEquipe(), $this->horloge());
    }

    public function equipe(): Equipe
    {
        return $this->une(Equipe::class, fn (): Equipe => new Equipe($this->base(), $this->artisans(), $this->depotEquipe(), $this->journalAdmin(), $this->comptes(), $this->horloge()));
    }

    public function depotProspects(): DepotProspects
    {
        return $this->une(DepotProspects::class, fn (): DepotProspects => new DepotProspects($this->base()));
    }

    /** Le catalogue des prospects (docs/plans/2026-11-24_prospects). */
    public function prospects(): Prospects
    {
        return $this->une(Prospects::class, fn (): Prospects => new Prospects(
            $this->base(),
            $this->depotProspects(),
            new Demos($this->base(), $this->comptes(), $this->artisans(), $this->depotEquipe(), $this->journalAdmin(), $this->horloge()),
            $this->journalAdmin(),
            $this->horloge(),
        ));
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

    /** Rappels de fin de démo, envoyés par bin/rappeler-demos (Xavier, 2026-11-24). */
    public function rappelsDemo(): \OffreGen\Compte\RappelsDemo
    {
        return new \OffreGen\Compte\RappelsDemo($this->depotEquipe(), $this->liensAcces(), $this->messagerie(), $this->horloge(),
            $this->journal(), $this->nomApplication(), $this->parametre('APP_URL', 'http://localhost:8000'));
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
        $compte = $this->artisans()->parId($artisanId);
        $offresMax = $compte['offres_max'] ?? null;
        return $offresMax === null ? null : PlafondEssai::de($offresMax, $this->enregistrements(new Portee($artisanId))->recus(), ($compte['essai_fin'] ?? null) !== null);
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
        return $this->une(DocumentPdf::class, fn (): DocumentPdf => new DocumentPdf($this->racine . '/templates', $this->racine . '/var/cache/pdf', $this->racine . '/public/fonts'));
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

    /** Le PDF d'une offre gardé tant qu'il ne change pas ; la version du code qui le rend entre dans son empreinte. */
    public function pdfGarde(): PdfGarde
    {
        return $this->une(PdfGarde::class, function (): PdfGarde {
            $code = '';
            foreach (['/composer.lock', '/src/Http/Controleur/OffreControleur.php', '/src/Offre/DocumentPdf.php'] as $f) {
                $code .= is_file($this->racine . $f) ? (string) hash_file('xxh3', $this->racine . $f) : '';
            }
            // Pas var/cache/pdf : dompdf y range ses polices.
            return new PdfGarde($this->racine . '/var/cache/offres-pdf', $code);
        });
    }

    public function fichiers(): Fichiers
    {
        return $this->une(Fichiers::class, fn (): Fichiers => new Fichiers($this->racine . '/public'));
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
            $twig->addFunction(new TwigFunction('fichier', fn (string $chemin): string => $this->fichiers()->adresse($chemin)));
            $twig->addFunction(new TwigFunction('messages', fn (): array => $this->garde()->messages()));
            $twig->addFunction(new TwigFunction('compte', fn (): ?int => $this->garde()->artisanCourant()));
            // Le rôle d'équipe du compte connecté : l'entrée « Administration » de « Plus » et du menu n'existe que pour lui.
            $twig->addFunction(new TwigFunction('role_equipe', function (): ?Role {
                $id = $this->garde()->artisanCourant();
                return $id === null ? null : $this->authentification()->role($id);
            }));
            // Le ruban d'une démo créée par l'équipe : jours restants. Null pour tout autre compte, démonstration
            // partagée comprise (sans date de fin) : elle ne se passe pas à l'essai depuis l'écran.
            $twig->addFunction(new TwigFunction('demo', function (): ?array {
                $id = $this->garde()->artisanCourant();
                $demo = $id === null ? null : $this->depotEquipe()->demo($id);
                if ($demo === null || $demo['demo_fin'] === null) {
                    return null;
                }
                $maintenant = $this->horloge()->now();
                $reste = \OffreGen\Donnees\Dates::depuisBase($demo['demo_fin'])->getTimestamp() - $maintenant->getTimestamp();
                // terminee : dictée et import refusés ; avant_fin : jours de calendrier, pour la fenêtre des deux derniers jours.
                return ['jours' => max(0, (int) ceil($reste / 86400)), 'terminee' => Demos::terminee($demo['demo_fin'], $maintenant),
                    'avant_fin' => Demos::joursAvantLaFin($demo['demo_fin'], $maintenant)];
            }));
            // Le ruban du mois gratuit (maquette v5 de l'administration : « Mois gratuit · 7 jours restants »). Null pour
            // tout compte sans date de fin d'essai.
            $twig->addFunction(new TwigFunction('mois_gratuit', function (): ?array {
                $id = $this->garde()->artisanCourant();
                $fin = $id === null ? null : ($this->artisans()->parId($id)['essai_fin'] ?? null);
                if ($fin === null) {
                    return null;
                }
                $maintenant = $this->horloge()->now();
                return ['termine' => MoisGratuit::termine($fin, $maintenant), 'avant_fin' => Demos::joursAvantLaFin($fin, $maintenant)];
            }));
            // Un texte de l'onboarding, gras compris (src/Http/Textes.php) : il vient du dépôt, ses variables sont échappées.
            $twig->addFunction(new TwigFunction('texte', fn (string $id, array $variables = []): string => $this->textes()->texte($id, $variables), ['is_safe' => ['html']]));
            // La démo d'entraînement d'un membre, ouverte par lui : « Revenir à mon espace » et le guide du commercial (lot 8).
            $twig->addFunction(new TwigFunction('retour_equipe', function (): bool {
                $id = $this->garde()->artisanCourant();
                $requete = $this->garde()->requeteCourante();
                return $id !== null && $requete !== null && $this->retourEquipe()->present($requete, $id);
            }));
            // « Revoir le guide », dans « Plus » : les comptes qui ont un guide.
            $twig->addFunction(new TwigFunction('guide_possible', function (): bool {
                $id = $this->garde()->artisanCourant();
                return $id !== null && $this->reglages(new Portee($id))->guidePossible();
            }));
            // Le guide à anneau de l'artisan (chantier 2026-11-21_onboarding) : démo de l'équipe ou essai, pas encore fini.
            $twig->addFunction(new TwigFunction('guide_artisan', function (): bool {
                $id = $this->garde()->artisanCourant();
                return $id !== null && $this->reglages(new Portee($id))->guideAMontrer();
            }));
            $twig->addExtension(new Formats());
            $twig->addGlobal('pays_adresse', Adresse::PAYS);
            $twig->addGlobal('nom_application', $this->nomApplication());
            $twig->addGlobal('duree_max_secondes', $this->dureeMaxSecondes());
            $twig->addFunction(new TwigFunction('essai', function (): ?PlafondEssai {
                $id = $this->garde()->artisanCourant();
                return $id === null ? null : $this->plafondEssai($id);
            }));
            // Les mots des écrans selon l'activité du compte connecté (Compte\Activite) ; sans session, le Bâtiment.
            $twig->addFunction(new TwigFunction('activite', fn (): Activite => $this->activiteCourante()));
            $twig->addFunction(new TwigFunction('mots', fn (): array => $this->activiteCourante()->mots()));
            return $twig;
        });
    }

    /** L'activité du compte de la session ; le Bâtiment sans session (pages publiques). Relue à chaque appel : elle change dans les réglages. */
    public function activiteCourante(): Activite
    {
        $id = $this->garde()->artisanCourant();
        return $id === null ? Activite::Batiment : Activite::lire($this->artisans()->parId($id)['activite'] ?? null);
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

    /** Le chronomètre des dictées (sous-chantier 31), partagé par le traitement, la transcription et l'extraction. */
    public function chronometre(): Chronometre
    {
        return $this->une(Chronometre::class, fn (): Chronometre => new Chronometre(fn (): DepotEtapes => $this->etapes(),
            fn (): FileTraitement => $this->fileTraitement(), $this->horloge(), $this->journal()));
    }

    public function etapes(): DepotEtapes
    {
        return $this->une(DepotEtapes::class, fn (): DepotEtapes => new DepotEtapes($this->base()));
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
                $this->chronometre(),
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
                chrono: $this->chronometre(),
            );
        });
    }

    /**
     * Import du catalogue (chantier 2026-11-20_import-catalogue) : même service et même modèle que l'extraction.
     * EXTRACTION=api : lecture des offres par le modèle ; autre valeur ou absente : lecteur factice.
     */
    public function lecteur(): Lecteur
    {
        return $this->une(Lecteur::class, function (): Lecteur {
            if ($this->parametre('EXTRACTION') !== 'api') {
                return new LecteurFactice();
            }
            return new LecteurApi($this->http(), $this->adresseService('EXTRACTION_URL'), $this->parametre('EXTRACTION_CLE'),
                $this->parametreRequis('EXTRACTION_MODELE'), $this->journal(), $this->parametre('EXTRACTION_SANS_RAISONNEMENT', '1') === '1');
        });
    }

    /** Comparaison des libellés lus dans les offres : par le modèle avec EXTRACTION=api, sinon par le code seul. */
    public function comparateur(): Comparateur
    {
        return $this->une(Comparateur::class, function (): Comparateur {
            if ($this->parametre('EXTRACTION') !== 'api') {
                return new ComparateurFactice();
            }
            return new ComparateurApi($this->http(), $this->adresseService('EXTRACTION_URL'), $this->parametre('EXTRACTION_CLE'),
                $this->parametreRequis('EXTRACTION_MODELE'), $this->journal(), $this->parametre('EXTRACTION_SANS_RAISONNEMENT', '1') === '1');
        });
    }

    public function imports(Portee $p): DepotImports
    {
        return new DepotImports($this->base(), $p);
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
            return $redirection === '' ? $messagerie
                : new MessagerieRedirigee($messagerie, $redirection, $this->finEnvoiReel(), $this->horloge(), $this->journal());
        });
    }

    /**
     * Fin de la fenêtre d'envoi réel du site de test (COURRIELS_ENVOI_REEL_JUSQU_A, écrite par
     * outils/courriels-reels-test.sh) ; null si absente ou illisible : tout reste redirigé.
     */
    private function finEnvoiReel(): ?\DateTimeImmutable
    {
        $valeur = $this->parametre('COURRIELS_ENVOI_REEL_JUSQU_A');
        if ($valeur === '') {
            return null;
        }
        try {
            return new \DateTimeImmutable($valeur);
        } catch (\Exception) {
            $this->journal()->warning("offreGen, COURRIELS_ENVOI_REEL_JUSQU_A illisible : les courriels restent redirigés.");
            return null;
        }
    }

    /**
     * Les demandes d'essai de la page de présentation : un courriel à l'exploitant (DEMANDES_ESSAI_DESTINATAIRE),
     * plafonné par jour. Le compteur du jour tient dans DEMANDES_ESSAI_DOSSIER.
     */
    public function demandesEssai(): DemandesEssai
    {
        return $this->une(DemandesEssai::class, fn (): DemandesEssai => new DemandesEssai(
            $this->messagerie(),
            $this->parametre('DEMANDES_ESSAI_DESTINATAIRE', 'info@xtradev.ch'),
            $this->parametre('DEMANDES_ESSAI_DOSSIER', $this->racine . '/var/demandes-essai'),
            $this->horloge(),
        ));
    }

    public function depotAppels(): DepotAppels
    {
        return $this->une(DepotAppels::class, fn (): DepotAppels => new DepotAppels($this->base()));
    }

    public function demandesAppel(): DemandesAppel
    {
        return $this->une(DemandesAppel::class, fn (): DemandesAppel => new DemandesAppel(
            $this->depotAppels(),
            $this->depotEquipe(),
            $this->journalAdmin(),
            $this->messagerie(),
            $this->horloge(),
            $this->nomApplication(),
            $this->parametre('APP_URL', 'http://localhost:8000'),
        ));
    }

    /** Les démos finies depuis 30 jours, supprimées chaque nuit (bin/supprimer-demos). */
    public function suppressionDemos(): SuppressionDemos
    {
        return $this->une(SuppressionDemos::class, fn (): SuppressionDemos => new SuppressionDemos(
            $this->base(),
            $this->stockageAudio(),
            $this->dossierLogos(),
            $this->horloge(),
            $this->journal(),
        ));
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
            $this->chronometre(),
        ));
    }

    /** Rejouer une dictée depuis son rapport (sous-chantier 31) : mêmes transcripteur, extracteur et chronomètre. */
    public function rejeu(): Rejeu
    {
        return $this->une(Rejeu::class, fn (): Rejeu => new Rejeu($this->base(), $this->etapes(), $this->transcripteur(), $this->extracteur(),
            $this->artisans(), $this->stockageAudio(), $this->chronometre(), $this->journal()));
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
            $this->traiteur()->avancer($uuid, Traiteur::ATTENTE_IMMEDIATE_SECONDES, Chronometre::APRES_REPONSE);
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
            return new Noyau($declarer($this), $this->twig(), $this->garde(), fn (string $m) => $this->journal()->error($m),
                fn (int $artisan): DepotEnvois => new DepotEnvois($this->base(), new Portee($artisan)),
                fn (): \DateTimeImmutable => $this->horloge()->now());
        });
    }
}
