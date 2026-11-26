<?php

declare(strict_types=1);

namespace OffreGen\Tests\Audio;

use OffreGen\Audio\AudioRefuse;
use OffreGen\Audio\ValidateurAudio;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * Fichiers de tests/fixtures/audio/ : une seconde de son, produits par ffmpeg 6.1
 * (l'AMR écrit à la main, ffmpeg d'Ubuntu n'ayant pas d'encodeur AMR ; ffprobe le lit, 1,03 s).
 */
final class ValidateurAudioTest extends TestCase
{
    private static function fichier(string $nom, int $erreur = UPLOAD_ERR_OK): UploadedFile
    {
        return new UploadedFile(dirname(__DIR__) . '/fixtures/audio/' . $nom, $nom, null, $erreur, true);
    }

    /** @return iterable<string, array{string, string}> */
    public static function audiosAcceptes(): iterable
    {
        yield 'm4a iPhone' => ['visite.m4a', 'm4a'];
        yield 'm4a Android (isom)' => ['android.m4a', 'm4a'];
        yield 'mp3' => ['visite.mp3', 'mp3'];
        yield 'ogg vorbis' => ['visite.ogg', 'ogg'];
        yield 'opus' => ['visite.opus', 'ogg'];
        yield 'wav' => ['visite.wav', 'wav'];
        yield 'amr' => ['visite.amr', 'amr'];
        yield '3gp' => ['visite.3gp', '3gp'];
        yield 'webm (enregistrement du navigateur)' => ['visite.webm', 'webm'];
    }

    #[DataProvider('audiosAcceptes')]
    public function testAudioAccepte(string $nom, string $extension): void
    {
        $audio = (new ValidateurAudio())->valider(self::fichier($nom));
        self::assertSame($extension, $audio->extension);
        self::assertSame(filesize(dirname(__DIR__) . '/fixtures/audio/' . $nom), $audio->taille);
    }

    public function testTexteRenommeEnM4aRefuse(): void
    {
        $this->expectException(AudioRefuse::class);
        $this->expectExceptionMessage("pas un enregistrement audio reconnu");
        (new ValidateurAudio())->valider(self::fichier('texte.m4a'));
    }

    public function testAuDelaDeLaTailleRefuse(): void
    {
        // visite.wav fait 16 078 octets ; la limite du test est 16 000.
        $this->expectException(AudioRefuse::class);
        $this->expectExceptionMessage('dépasse');
        (new ValidateurAudio(16000))->valider(self::fichier('visite.wav'));
    }

    public function testFichierCoupeParPhpRefuseCommeTropGros(): void
    {
        $this->expectException(AudioRefuse::class);
        $this->expectExceptionMessage('dépasse 200 Mo');
        (new ValidateurAudio())->valider(self::fichier('visite.wav', UPLOAD_ERR_INI_SIZE));
    }

    public function testFichierVideRefuse(): void
    {
        $vide = tempnam(sys_get_temp_dir(), 'vide');
        self::assertNotFalse($vide);
        try {
            $this->expectException(AudioRefuse::class);
            $this->expectExceptionMessage('vide');
            (new ValidateurAudio())->valider(new UploadedFile($vide, 'vide.m4a', null, UPLOAD_ERR_OK, true));
        } finally {
            unlink($vide);
        }
    }
}
