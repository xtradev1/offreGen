<?php

declare(strict_types=1);

namespace OffreGen\Audio;

use Symfony\Component\HttpFoundation\File\UploadedFile;

final readonly class AudioValide
{
    public function __construct(
        public string $mime,
        public string $extension,
        public int $taille,
        public UploadedFile $fichier,
    ) {
    }
}
