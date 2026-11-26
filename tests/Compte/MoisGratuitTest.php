<?php

declare(strict_types=1);

namespace OffreGen\Tests\Compte;

use OffreGen\Compte\MoisGratuit;
use OffreGen\Compte\PlafondEssai;
use PHPUnit\Framework\TestCase;

/** Le mois gratuit : un mois de calendrier à Zurich, et sa jauge (chantier onboarding, E1). */
final class MoisGratuitTest extends TestCase
{
    private static function utc(string $quand): \DateTimeImmutable
    {
        return new \DateTimeImmutable($quand, new \DateTimeZone('UTC'));
    }

    public function testUnMoisDeCalendrierAZurich(): void
    {
        self::assertSame('2026-12-24 22:59:59', MoisGratuit::fin(self::utc('2026-11-24 09:30:00'))->format('Y-m-d H:i:s'));
        // Le 31 janvier finit le dernier jour de février.
        self::assertSame('2027-02-28 22:59:59', MoisGratuit::fin(self::utc('2027-01-31 10:00:00'))->format('Y-m-d H:i:s'));
        // 23:30 à Zurich le 31 octobre (heure d'hiver) : le 30 novembre à minuit, à Zurich.
        self::assertSame('2026-11-30 22:59:59', MoisGratuit::fin(self::utc('2026-10-31 22:30:00'))->format('Y-m-d H:i:s'));
        // Passage à l'heure d'été : minuit à Zurich, deux heures de moins en UTC.
        self::assertSame('2027-04-15 21:59:59', MoisGratuit::fin(self::utc('2027-03-15 09:00:00'))->format('Y-m-d H:i:s'));
    }

    public function testTermine(): void
    {
        self::assertFalse(MoisGratuit::termine(null, self::utc('2030-01-01 00:00:00')));
        self::assertFalse(MoisGratuit::termine('2026-12-24 09:30:00', self::utc('2026-12-24 09:29:59')));
        self::assertTrue(MoisGratuit::termine('2026-12-24 09:30:00', self::utc('2026-12-24 09:30:00')));
    }

    public function testLaJaugeDitLesOffresDuMoisSurCinquante(): void
    {
        // 50 offres plus 2 dictées pendant la démo ; 14 reçues en tout, donc 12 ce mois.
        $plafond = PlafondEssai::de(52, 14, true);
        self::assertNotNull($plafond);
        self::assertSame('12 offres ce mois sur 50', $plafond->reste());
        self::assertSame('1 offre ce mois sur 50', PlafondEssai::de(52, 3, true)?->reste());
        self::assertStringContainsString('les 50 offres de votre mois gratuit', (string) PlafondEssai::de(52, 52, true)?->refus());
        self::assertSame("Compte d'essai\u{00A0}: 8 offres restantes", PlafondEssai::de(10, 2)?->reste());
    }
}
