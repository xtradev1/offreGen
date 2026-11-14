<?php

declare(strict_types=1);

namespace OffreGen\Tests\Audio;

use OffreGen\Audio\PreparationAudio;
use PHPUnit\Framework\TestCase;

final class PreparationAudioTest extends TestCase
{
    private static function fixture(string $nom): string
    {
        return dirname(__DIR__) . '/fixtures/audio/' . $nom;
    }

    public function testFormatAccepteEtPetitPartTelQuel(): void
    {
        $f = (new PreparationAudio())->preparer(self::fixture('visite.m4a'));
        self::assertSame(self::fixture('visite.m4a'), $f->chemin);
        self::assertFalse($f->temporaire);
        $f->nettoyer();
        self::assertFileExists(self::fixture('visite.m4a'), 'nettoyer() ne doit jamais effacer l\'original');
    }

    /** @return iterable<string, array{string}> */
    public static function formatsRefusesParInfomaniak(): iterable
    {
        yield 'amr' => ['visite.amr'];
        yield '3gp' => ['visite.3gp'];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('formatsRefusesParInfomaniak')]
    public function testFormatRefuseConvertiEnOpus(string $nom): void
    {
        $f = (new PreparationAudio())->preparer(self::fixture($nom));
        self::assertTrue($f->temporaire);
        self::assertStringEndsWith('.ogg', $f->chemin);
        self::assertSame('audio/ogg', (new \finfo(FILEINFO_MIME_TYPE))->file($f->chemin));
        $f->nettoyer();
        self::assertFileDoesNotExist($f->chemin);
        self::assertFileExists(self::fixture($nom));
    }

    public function testFichierTropGrosConvertiPourPasserSousLaLimite(): void
    {
        // visite.wav : 16 078 octets ; limite du test : 10 000. Une seconde d'Opus pèse quelques Ko.
        $f = (new PreparationAudio('ffmpeg', 10000))->preparer(self::fixture('visite.wav'));
        self::assertTrue($f->temporaire);
        self::assertLessThan(10000, filesize($f->chemin));
        $f->nettoyer();
    }

    public function testTropLongMemeCompresseRefuse(): void
    {
        $this->expectExceptionMessage('trop long');
        (new PreparationAudio('ffmpeg', 100))->preparer(self::fixture('visite.wav'));
    }

    public function testSansFfmpegLeMessageLeDit(): void
    {
        $this->expectExceptionMessage('ffmpeg est absent');
        (new PreparationAudio('/chemin/sans/ffmpeg'))->preparer(self::fixture('visite.amr'));
    }
}
