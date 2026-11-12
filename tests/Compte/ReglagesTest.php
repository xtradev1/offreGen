<?php

declare(strict_types=1);

namespace OffreGen\Tests\Compte;

use OffreGen\Compte\Reglages;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ReglagesTest extends TestCase
{
    /** @return iterable<string, array{string, string|null}> */
    public static function ides(): iterable
    {
        yield 'forme officielle' => ['CHE-123.456.789', 'CHE-123.456.789'];
        yield 'avec TVA' => ['CHE-123.456.789 TVA', 'CHE-123.456.789'];
        yield 'minuscules et espaces' => ['che 123 456 789 tva', 'CHE-123.456.789'];
        yield 'collé' => ['CHE123456789', 'CHE-123.456.789'];
        yield 'MWST' => ['CHE-123.456.789 MWST', 'CHE-123.456.789'];
        yield 'vide permis' => ['  ', ''];
        yield 'chiffres manquants' => ['CHE-123.456', null];
        yield 'sans CHE' => ['123.456.789', null];
    }

    #[DataProvider('ides')]
    public function testIde(string $saisie, ?string $attendu): void
    {
        self::assertSame($attendu, Reglages::ide($saisie));
    }

    public function testLocaliteSansNumeroPostal(): void
    {
        self::assertSame('Delémont', (new Reglages('X', 810, true, "Rue 1\n2800 Delémont"))->localite());
        self::assertSame('Porrentruy', (new Reglages('X', 810, true, "CH-2900 Porrentruy"))->localite());
        self::assertSame('', (new Reglages('X', 810, true))->localite());
    }
}
