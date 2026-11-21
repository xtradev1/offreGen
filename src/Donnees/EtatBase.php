<?php

declare(strict_types=1);

namespace OffreGen\Donnees;

/**
 * Lectures de /sante : la base répond, et quelles migrations y sont jouées. Rien d'un artisan.
 */
final class EtatBase
{
    public function __construct(private readonly Base $base)
    {
    }

    public function joindre(): void
    {
        $this->base->un('SELECT 1 AS un');
    }

    /** @return list<string> */
    public function migrationsJouees(): array
    {
        /** @var list<string> */
        return array_column($this->base->lire('SELECT version FROM doctrine_migration_versions'), 'version');
    }
}
