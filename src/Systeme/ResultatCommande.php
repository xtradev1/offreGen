<?php

declare(strict_types=1);

namespace OffreGen\Systeme;

/** Ce qu'une commande a rendu : son code (Commande::IMPOSSIBLE si rien n'a été lancé), sa sortie et ses erreurs. */
final readonly class ResultatCommande
{
    public function __construct(
        public int $code,
        public string $sortie,
        public string $erreur,
    ) {
    }
}
