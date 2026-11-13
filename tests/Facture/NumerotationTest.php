<?php

declare(strict_types=1);

namespace OffreGen\Tests\Facture;

use OffreGen\Facture\Numerotation;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Forme 2026-001 (Xavier, 2026-11-13, choix D1). Un numéro tapé à la main fait partir les suivants de lui.
 */
final class NumerotationTest extends TestCase
{
    /** @return iterable<string, array{string|null, string}> */
    public static function suites(): iterable
    {
        yield 'première facture' => [null, '2026-001'];
        yield 'suite de l\'année' => ['2026-007', '2026-008'];
        yield 'au-delà de 999' => ['2026-999', '2026-1000'];
        yield 'zéros gardés' => ['2026-0099', '2026-0100'];
        yield 'nouvelle année' => ['2025-114', '2026-001'];
        yield 'numéro libre qui finit par des chiffres' => ['F-0099', 'F-0100'];
        yield 'suite simple tapée par l\'artisan' => ['41', '42'];
        yield 'numéro libre sans chiffre final' => ['ABC', '2026-001'];
    }

    #[DataProvider('suites')]
    public function testLeSuivantPartDuDernierCree(?string $dernier, string $attendu): void
    {
        self::assertSame($attendu, Numerotation::suivant($dernier, 2026, static fn (string $n): bool => false));
    }

    public function testUnNumeroDejaPrisEstSaute(): void
    {
        $pris = ['2026-008', '2026-009'];
        self::assertSame('2026-010', Numerotation::suivant('2026-007', 2026, static fn (string $n): bool => in_array($n, $pris, true)));
    }

    public function testRepartirALAnneeSauteAussiUnNumeroPris(): void
    {
        self::assertSame('2026-002', Numerotation::suivant('ABC', 2026, static fn (string $n): bool => $n === '2026-001'));
    }

    /** @return iterable<string, array{string, string|null}> */
    public static function saisies(): iterable
    {
        yield 'forme proposée' => ['2026-001', '2026-001'];
        yield 'espaces autour' => ['  F/12.a_3 ', 'F/12.a_3'];
        yield 'vide' => ['', null];
        yield 'espace dedans' => ['2026 001', null];
        yield 'trop long' => [str_repeat('1', 21), null];
        yield 'vingt caractères' => [str_repeat('1', 20), str_repeat('1', 20)];
        yield 'commence par un tiret' => ['-12', null];
        yield 'accent' => ['é12', null];
        yield 'guillemet' => ['12"', null];
    }

    #[DataProvider('saisies')]
    public function testSaisieDuNumero(string $saisie, ?string $attendu): void
    {
        self::assertSame($attendu, Numerotation::lire($saisie));
    }
}
