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
}
