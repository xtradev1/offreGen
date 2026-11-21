<?php

declare(strict_types=1);

namespace OffreGen\Tests\Outils;

use OffreGen\Http\Conteneur;
use Symfony\Component\Clock\MockClock;

/**
 * Un conteneur de test branché sur la base de test, avec une horloge que le test avance à la main.
 */
final class Monde
{
    /** @param array<string, string> $env */
    public static function conteneur(?MockClock $horloge = null, array $env = []): Conteneur
    {
        return new Conteneur(dirname(__DIR__, 2), array_merge(BaseDeTest::env(), $env), $horloge ?? new MockClock('2026-11-07 10:00:00', 'UTC'), BaseDeTest::pdo());
    }
}
