<?php

declare(strict_types=1);

namespace OffreGen\Tests\Outils;

use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * Dossiers jetables pour l'audio et les courriels d'un test, et copies des fichiers d'essai
 * (un envoi déplace le fichier : chaque envoi part d'une copie).
 */
final class Dossiers
{
    public readonly string $racine;

    public function __construct()
    {
        $this->racine = sys_get_temp_dir() . '/offregen-test-' . bin2hex(random_bytes(4));
        mkdir($this->racine . '/audio', 0700, true);
        mkdir($this->racine . '/courriels', 0700, true);
    }

    /** @return array<string, string> */
    public function env(): array
    {
        return ['AUDIO_DOSSIER' => $this->racine . '/audio', 'COURRIELS_DOSSIER' => $this->racine . '/courriels'];
    }

    public function envoi(string $fixture, ?string $nomClient = null): UploadedFile
    {
        $copie = $this->racine . '/envoi-' . bin2hex(random_bytes(4));
        copy(dirname(__DIR__) . '/fixtures/audio/' . $fixture, $copie);
        return new UploadedFile($copie, $nomClient ?? $fixture, null, UPLOAD_ERR_OK, true);
    }

    /** @return list<string> */
    public function courriels(): array
    {
        $liste = glob($this->racine . '/courriels/*.eml');
        return $liste === false ? [] : $liste;
    }

    public function effacer(): void
    {
        $it = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($this->racine, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );
        foreach ($it as $f) {
            /** @var \SplFileInfo $f */
            $f->isDir() ? rmdir($f->getPathname()) : unlink($f->getPathname());
        }
        rmdir($this->racine);
    }
}
