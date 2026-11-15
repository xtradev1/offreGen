<?php

declare(strict_types=1);

namespace OffreGen\Tests\Systeme;

use OffreGen\Audio\DureeAudio;
use OffreGen\Audio\PreparationAudio;
use OffreGen\Compte\LogoRefuse;
use OffreGen\Compte\TraitementLogo;
use OffreGen\Systeme\Commande;
use PHPUnit\Framework\TestCase;

/**
 * Chez Infomaniak, proc_open est désactivée pour le site web et permise en ligne de commande
 * (relevé du 2026-11-15 : « Call to undefined function proc_open() », erreur 500 à l'envoi d'un logo).
 * Sans elle : aucune erreur 500, chaque usage se replie.
 */
final class CommandeTest extends TestCase
{
    private static function fixture(string $chemin): string
    {
        return dirname(__DIR__) . '/fixtures/' . $chemin;
    }

    public function testUneCommandeTourneEtRendSaSortie(): void
    {
        $r = (new Commande())->lancer(['printf', 'bonjour']);
        self::assertSame(0, $r->code);
        self::assertSame('bonjour', $r->sortie);
    }

    public function testUnProgrammeAbsentRend127(): void
    {
        self::assertSame(127, (new Commande())->lancer(['/chemin/absent/programme'])->code);
    }

    public function testSansProcOpenRienNEstLanceEtLeCodeLeDit(): void
    {
        $r = (new Commande(false))->lancer(['printf', 'bonjour']);
        self::assertSame(Commande::IMPOSSIBLE, $r->code);
        self::assertSame('', $r->sortie);
    }

    /** La durée sert au minimum de 5 secondes : sans elle, l'import continue (règle déjà écrite pour ffprobe absent). */
    public function testSansProcOpenLaDureeEstInconnueSansErreur(): void
    {
        self::assertNull((new DureeAudio('ffprobe', new Commande(false)))->secondes(self::fixture('audio/visite.m4a')));
    }

    public function testSansProcOpenLaConversionLeDitEtLeTraitementDeFondReprendra(): void
    {
        $this->expectExceptionMessage('le serveur web ne peut pas lancer ffmpeg');
        (new PreparationAudio('ffmpeg', PreparationAudio::TAILLE_MAX, new Commande(false)))->preparer(self::fixture('audio/visite.amr'));
    }

    public function testSansProcOpenLeLogoEstRefuseAvecUnMessageClair(): void
    {
        $sortie = sys_get_temp_dir() . '/offregen-logo-' . bin2hex(random_bytes(4)) . '.jpg';
        try {
            (new TraitementLogo('ffmpeg', TraitementLogo::TAILLE_MAX, new Commande(false)))->preparer(self::fixture('images/logo-rouge.jpg'), $sortie);
            self::fail('Logo accepté sans traitement.');
        } catch (LogoRefuse $e) {
            self::assertStringContainsString('ce serveur', $e->getMessage());
            self::assertFileDoesNotExist($sortie);
        }
    }
}
