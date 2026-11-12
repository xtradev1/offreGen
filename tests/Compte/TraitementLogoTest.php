<?php

declare(strict_types=1);

namespace OffreGen\Tests\Compte;

use OffreGen\Compte\LogoRefuse;
use OffreGen\Compte\TraitementLogo;
use PHPUnit\Framework\TestCase;

final class TraitementLogoTest extends TestCase
{
    private string $sortie;

    protected function setUp(): void
    {
        $this->sortie = sys_get_temp_dir() . '/offregen-logo-' . bin2hex(random_bytes(4)) . '.jpg';
    }

    protected function tearDown(): void
    {
        if (is_file($this->sortie)) {
            unlink($this->sortie);
        }
    }

    private static function fixture(string $nom): string
    {
        return dirname(__DIR__) . '/fixtures/images/' . $nom;
    }

    /** La compression JPEG décale un peu les couleurs : à quelques unités près par canal. */
    private static function assertCouleurProche(string $attendue, ?string $obtenue): void
    {
        self::assertNotNull($obtenue);
        for ($i = 1; $i <= 5; $i += 2) {
            self::assertEqualsWithDelta(hexdec(substr($attendue, $i, 2)), hexdec(substr($obtenue, $i, 2)), 6, "$attendue contre $obtenue");
        }
    }

    public function testUnPngTransparentDevientUnJpegReduitSurFondBlanc(): void
    {
        (new TraitementLogo())->preparer(self::fixture('logo-transparent.png'), $this->sortie);
        $taille = getimagesize($this->sortie);
        self::assertNotFalse($taille);
        self::assertSame(IMAGETYPE_JPEG, $taille[2]);
        // 800 x 300 réduit dans 600 x 240, proportions gardées.
        self::assertSame([600, 225], [$taille[0], $taille[1]]);
        // Le fond transparent est devenu blanc, le rectangle reste bleu : la couleur du logo est le bleu.
        self::assertCouleurProche('#1D4E89', (new TraitementLogo())->couleurDominante($this->sortie));
        // Blanc exact : un blanc teinté (255, 253, 255, constaté le 2026-11-12) dessinait un cadre sur le papier.
        $coin = (string) shell_exec('ffmpeg -v error -i ' . escapeshellarg($this->sortie) . ' -vf crop=1:1:3:3 -f rawvideo -pix_fmt rgb24 -');
        self::assertSame([255, 255, 255], array_map('ord', str_split($coin)));
    }

    public function testUnJpegPasse(): void
    {
        (new TraitementLogo())->preparer(self::fixture('logo-rouge.jpg'), $this->sortie);
        self::assertCouleurProche('#B23A2E', (new TraitementLogo())->couleurDominante($this->sortie));
    }

    /** @return iterable<string, array{string}> */
    public static function refuses(): iterable
    {
        yield 'texte nommé .png' => ['pas-une-image.png'];
        // Un SVG peut porter du script : jamais accepté.
        yield 'svg' => ['logo.svg'];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('refuses')]
    public function testCeQuiNEstPasUnPngNiUnJpegEstRefuse(string $nom): void
    {
        try {
            (new TraitementLogo())->preparer(self::fixture($nom), $this->sortie);
            self::fail("$nom accepté");
        } catch (LogoRefuse $e) {
            self::assertSame('Le logo doit être une image PNG ou JPEG.', $e->getMessage());
        }
        self::assertFileDoesNotExist($this->sortie);
    }

    public function testTropLourdRefuse(): void
    {
        $this->expectExceptionMessage('Le logo pèse plus de 5 Mo.');
        (new TraitementLogo('ffmpeg', 1000))->preparer(self::fixture('logo-transparent.png'), $this->sortie);
    }
}
