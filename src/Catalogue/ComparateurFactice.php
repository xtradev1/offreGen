<?php

declare(strict_types=1);

namespace OffreGen\Catalogue;

/** Sans modèle (développement, tests) : réunit seulement les libellés qui ont presque tous leurs mots en commun. */
final class ComparateurFactice implements Comparateur
{
    private const SEUIL = 0.75;

    public function memes(array $paires): array
    {
        return array_map(static fn (array $p): bool => Ressemblance::score($p[0], $p[1]) >= self::SEUIL, $paires);
    }

    public function jetonsConsommes(): array
    {
        return [0, 0];
    }
}
