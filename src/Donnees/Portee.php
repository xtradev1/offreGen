<?php

declare(strict_types=1);

namespace OffreGen\Donnees;

/**
 * L'artisan au nom duquel un dépôt lit et écrit. Un dépôt métier ne se construit pas sans elle :
 * chaque requête filtre sur artisan_id, et la donnée d'un autre artisan n'existe pas pour lui.
 */
final readonly class Portee
{
    public function __construct(public int $artisanId)
    {
        if ($artisanId <= 0) {
            throw new \InvalidArgumentException('Portée sans artisan.');
        }
    }
}
