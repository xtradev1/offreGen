<?php

declare(strict_types=1);

namespace OffreGen\Tests\Offre;

use OffreGen\Offre\Totaux;
use PHPUnit\Framework\TestCase;

/**
 * Chaque attendu est calculé à la main dans le commentaire qui le précède.
 */
final class TotauxTest extends TestCase
{
    public function testUneLigneTvaEtArrondiAuxCinqCentimes(): void
    {
        // 12,5 m² × 18.50 = 231.25 ; TVA 8,1 % = 18.73125 → 18.73 ; 231.25 + 18.73 = 249.98 → 250.00 (+0.02).
        $t = Totaux::calculer([['quantite_milliemes' => 12500, 'prix_centimes' => 1850]]);
        self::assertSame(['lignes' => [23125], 'ht' => 23125, 'tva' => 1873, 'arrondi' => 2, 'ttc' => 25000], $t);
    }

    public function testDemiCentimeDeLigneArrondiVersLeHaut(): void
    {
        // 3 × 7.99 = 23.97 ; 0,333 h × 95.00 = 31.635 → 31.64 ; HT 55.61.
        // TVA 55.61 × 0,081 = 4.50441 → 4.50 ; 60.11 → 60.10 (-0.01).
        $t = Totaux::calculer([
            ['quantite_milliemes' => 3000, 'prix_centimes' => 799],
            ['quantite_milliemes' => 333, 'prix_centimes' => 9500],
        ]);
        self::assertSame(['lignes' => [2397, 3164], 'ht' => 5561, 'tva' => 450, 'arrondi' => -1, 'ttc' => 6010], $t);
    }

    public function testDemiCentimeDeTvaArrondiVersLeHaut(): void
    {
        // 5.00 ; TVA 0.405 → 0.41 ; 5.41 → 5.40 (-0.01).
        $t = Totaux::calculer([['quantite_milliemes' => 1000, 'prix_centimes' => 500]]);
        self::assertSame(['lignes' => [500], 'ht' => 500, 'tva' => 41, 'arrondi' => -1, 'ttc' => 540], $t);
    }

    public function testOffreVide(): void
    {
        self::assertSame(['lignes' => [], 'ht' => 0, 'tva' => 0, 'arrondi' => 0, 'ttc' => 0], Totaux::calculer([]));
    }

    public function testMontantNegatifArrondiEnSEloignantDeZero(): void
    {
        // Rabais : 1,5 × -0.01 = -0.015 → -0.02 ; 1 × 10.00 ; HT 9.98 ; TVA 0.80838 → 0.81 ; 10.79 → 10.80 (+0.01).
        $t = Totaux::calculer([
            ['quantite_milliemes' => 1500, 'prix_centimes' => -1],
            ['quantite_milliemes' => 1000, 'prix_centimes' => 1000],
        ]);
        self::assertSame(['lignes' => [-2, 1000], 'ht' => 998, 'tva' => 81, 'arrondi' => 1, 'ttc' => 1080], $t);
    }

    public function testTauxReduit(): void
    {
        // 100.00 à 2,6 % = 2.60 ; 102.60.
        self::assertSame(10260, Totaux::calculer([['quantite_milliemes' => 1000, 'prix_centimes' => 10000]], 260)['ttc']);
    }

    public function testSansArrondiAuxCinqCentimesLeTotalResteAuCentime(): void
    {
        // Même ligne que le premier test : 231.25 + 18.73 = 249.98, sans écart d'arrondi.
        $t = Totaux::calculer([['quantite_milliemes' => 12500, 'prix_centimes' => 1850]], Totaux::TVA_NORMALE, false);
        self::assertSame(['lignes' => [23125], 'ht' => 23125, 'tva' => 1873, 'arrondi' => 0, 'ttc' => 24998], $t);
    }

    public function testNonAssujettiSansTva(): void
    {
        // 231.25, TVA 0 ; arrondi aux 5 centimes : 231.25 reste 231.25.
        $t = Totaux::calculer([['quantite_milliemes' => 12500, 'prix_centimes' => 1850]], 0);
        self::assertSame(['lignes' => [23125], 'ht' => 23125, 'tva' => 0, 'arrondi' => 0, 'ttc' => 23125], $t);
    }

    public function testArrondirRefuseUnDenominateurNul(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Totaux::arrondir(1, 0);
    }
}
