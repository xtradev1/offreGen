<?php

declare(strict_types=1);

namespace OffreGen\Audio;

/** Un audio prêt à envoyer ; s'il est une conversion temporaire, nettoyer() l'efface. */
final readonly class FichierPrepare
{
    public function __construct(public string $chemin, public bool $temporaire)
    {
    }

    public function nettoyer(): void
    {
        if ($this->temporaire && is_file($this->chemin)) {
            unlink($this->chemin);
        }
    }
}
