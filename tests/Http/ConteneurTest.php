<?php

declare(strict_types=1);

namespace OffreGen\Tests\Http;

use OffreGen\Http\Conteneur;
use PHPUnit\Framework\TestCase;

final class ConteneurTest extends TestCase
{
    protected function tearDown(): void
    {
        putenv('OFFREGEN_SONDE');
        unset($_SERVER['OFFREGEN_SONDE_SERVEUR'], $_SERVER['HTTP_DB_NOM']);
    }

    public function testUneVariableDuSystemeEstLueMemeHorsDeEnv(): void
    {
        // Sous php -S, une variable du système n'est visible que par getenv() (constaté le 2026-11-07).
        putenv('OFFREGEN_SONDE=vu');
        self::assertSame('vu', Conteneur::lireEnvironnement(dirname(__DIR__, 2))['OFFREGEN_SONDE'] ?? null);
    }

    public function testUneVariableDuServeurWebEstLue(): void
    {
        $_SERVER['OFFREGEN_SONDE_SERVEUR'] = 'vu';
        self::assertSame('vu', Conteneur::lireEnvironnement(dirname(__DIR__, 2))['OFFREGEN_SONDE_SERVEUR'] ?? null);
    }

    public function testUneVariableDuSystemeLEmporteSurLeFichierEnv(): void
    {
        // Constaté le 2026-11-12 : sous php -S, TRANSCRIPTION=factice posé au lancement était ignoré,
        // la valeur du .env l'emportait, contrairement à ce que promet lireEnvironnement().
        $racine = sys_get_temp_dir() . '/offregen-env-' . bin2hex(random_bytes(4));
        mkdir($racine);
        file_put_contents($racine . '/.env', "OFFREGEN_SONDE_PRIO=fichier\n");
        putenv('OFFREGEN_SONDE_PRIO=systeme');
        try {
            self::assertSame('systeme', Conteneur::lireEnvironnement($racine)['OFFREGEN_SONDE_PRIO'] ?? null);
        } finally {
            putenv('OFFREGEN_SONDE_PRIO');
            unset($_SERVER['OFFREGEN_SONDE_PRIO'], $_ENV['OFFREGEN_SONDE_PRIO'], $_SERVER['SYMFONY_DOTENV_VARS'], $_ENV['SYMFONY_DOTENV_VARS']);
            unlink($racine . '/.env');
            rmdir($racine);
        }
    }

    public function testUnEnTeteDuClientNEstJamaisLu(): void
    {
        $_SERVER['HTTP_DB_NOM'] = 'base_du_pirate';
        self::assertArrayNotHasKey('HTTP_DB_NOM', Conteneur::lireEnvironnement(dirname(__DIR__, 2)));
    }

    /** Revue du 2026-11-18 (H3) : « auto » était ignoré par Symfony hors framework ; le cookie partait sans Secure. */
    public function testLeCookieDeSessionEstSecureDesQueLAdresseEstEnHttps(): void
    {
        $racine = dirname(__DIR__, 2);
        self::assertTrue((new Conteneur($racine, ['APP_URL' => 'https://easy-offres.xtradev.ch']))->optionsSession()['cookie_secure']);
        self::assertFalse((new Conteneur($racine, ['APP_URL' => 'http://localhost:8000']))->optionsSession()['cookie_secure']);
        // Sans adresse : la production est en https.
        self::assertTrue((new Conteneur($racine, []))->optionsSession()['cookie_secure']);
        self::assertTrue((new Conteneur($racine, []))->optionsSession()['cookie_httponly']);
    }

    /** Revue du 2026-11-18 (B4) : en production, une adresse http:// enverrait le jeton de l'IA en clair. */
    public function testEnProductionLIAExigeHttps(): void
    {
        $racine = dirname(__DIR__, 2);
        $env = ['APP_ENV' => 'prod', 'EXTRACTION' => 'api', 'EXTRACTION_URL' => 'http://api.infomaniak.com/2/ai/1/openai/v1', 'EXTRACTION_MODELE' => 'm'];
        try {
            (new Conteneur($racine, $env))->extracteur();
            self::fail('Adresse http:// acceptée en production.');
        } catch (\RuntimeException $e) {
            self::assertStringContainsString('https', $e->getMessage());
        }
        $env['EXTRACTION_URL'] = 'https://api.infomaniak.com/2/ai/1/openai/v1';
        self::assertInstanceOf(\OffreGen\Offre\ExtracteurApi::class, (new Conteneur($racine, $env))->extracteur());
        // En développement, le Whisper et l'Ollama locaux restent en http.
        $dev = ['APP_ENV' => 'dev', 'TRANSCRIPTION' => 'api', 'TRANSCRIPTION_URL' => 'http://127.0.0.1:8090'];
        self::assertInstanceOf(\OffreGen\Audio\TranscripteurApi::class, (new Conteneur($racine, $dev))->transcripteur());
    }

    /**
     * Revue du 2026-11-18 (M12) : le mot de passe de l'utilisateur de structure était dans le .env, que le site web lit
     * à chaque requête (contraire à docs/SECURITE.md). Sur les serveurs, il vit dans .env.structure, lu par les
     * migrations seules.
     */
    public function testLeMotDePasseDeStructureNEstLuQueParLesMigrations(): void
    {
        $racine = sys_get_temp_dir() . '/offregen-env-' . bin2hex(random_bytes(4));
        mkdir($racine);
        // Les tests voisins lisent le .env du dépôt, qui remplit $_ENV et $_SERVER pour tout le processus.
        $cles = ['DB_NOM', 'DB_STRUCTURE_UTILISATEUR', 'DB_STRUCTURE_MOT_DE_PASSE', 'SYMFONY_DOTENV_VARS'];
        $avant = [];
        foreach ($cles as $cle) {
            $avant[$cle] = [$_ENV[$cle] ?? null, $_SERVER[$cle] ?? null];
            unset($_ENV[$cle], $_SERVER[$cle]);
        }
        try {
            file_put_contents("$racine/.env", "APP_ENV=prod\nDB_NOM=base_essai\nDB_STRUCTURE_UTILISATEUR=struct\n");
            file_put_contents("$racine/.env.structure", "DB_STRUCTURE_MOT_DE_PASSE=secret-structure\n");
            self::assertArrayNotHasKey('DB_STRUCTURE_MOT_DE_PASSE', Conteneur::lireEnvironnement($racine));
            $migrations = Conteneur::lireEnvironnementMigrations($racine);
            self::assertSame('secret-structure', $migrations['DB_STRUCTURE_MOT_DE_PASSE']);
            self::assertSame('base_essai', $migrations['DB_NOM']);
        } finally {
            @unlink("$racine/.env");
            @unlink("$racine/.env.structure");
            @rmdir($racine);
            foreach ($avant as $cle => [$env, $serveur]) {
                unset($_ENV[$cle], $_SERVER[$cle]);
                if ($env !== null) {
                    $_ENV[$cle] = $env;
                }
                if ($serveur !== null) {
                    $_SERVER[$cle] = $serveur;
                }
            }
        }
    }
}
