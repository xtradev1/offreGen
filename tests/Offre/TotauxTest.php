<?php

declare(strict_types=1);

namespace OffreGen\Tests\Offre;

use OffreGen\Offre\Rabais;
use OffreGen\Offre\Totaux;
use PHPUnit\Framework\TestCase;

/**
 * Chaque attendu est calculé à la main dans le commentaire qui le précède.
 */
final class TotauxTest extends TestCase
{
    /** TVA par ligne (Xavier, 2026-11-15) : le taux de l'entreprise par défaut, une ligne peut en avoir un autre. */
    public function testDeuxTauxSurUneOffre(): void
    {
        // 1 × 100.00 au taux de l'entreprise (8,1 %) : TVA 8.10 ; 1 × 50.00 à 2,6 % : TVA 1.30.
        // HT 150.00 ; TVA 9.40 ; 159.40, déjà aux 5 centimes.
        $lignes = [['quantite_milliemes' => 1000, 'prix_centimes' => 10000, 'tva_pour_dix_mille' => null],
                   ['quantite_milliemes' => 1000, 'prix_centimes' => 5000, 'tva_pour_dix_mille' => 260]];
        self::assertSame(['lignes' => [10000, 5000], 'ht' => 15000, 'tva' => 940, 'arrondi' => 0, 'ttc' => 15940], self::sansDetail(Totaux::calculer($lignes, 810)));
        self::assertSame([['taux' => 810, 'ht' => 10000, 'tva' => 810], ['taux' => 260, 'ht' => 5000, 'tva' => 130]], Totaux::parTaux($lignes, 810));
    }

    /** Ligne offerte (D1, 2026-11-21) : elle garde son prix pour le document, elle compte 0 partout. */
    public function testUneLigneOfferteCompteZero(): void
    {
        // 1 × 100.00 facturée, 1 × 50.00 offerte à 2,6 % : HT 100.00, TVA 8.10, aucun groupe à 2,6 %.
        $lignes = [['quantite_milliemes' => 1000, 'prix_centimes' => 10000],
                   ['quantite_milliemes' => 1000, 'prix_centimes' => 5000, 'tva_pour_dix_mille' => 260, 'offerte' => 1]];
        self::assertSame(['lignes' => [10000, 0], 'ht' => 10000, 'tva' => 810, 'arrondi' => 0, 'ttc' => 10810], self::sansDetail(Totaux::calculer($lignes, 810)));
        self::assertSame([['taux' => 810, 'ht' => 10000, 'tva' => 810]], Totaux::parTaux($lignes, 810), 'pas de groupe « TVA 2,6 % : 0.00 » sur le document');
    }

    public function testChaqueTauxSArrondiAuCentime(): void
    {
        // 3.33 à 8,1 % : 0.26973 → 0.27 ; 3.33 à 2,6 % : 0.08658 → 0.09 ; TVA 0.36.
        // Calculée sur le total (6.66 à un seul taux), elle serait fausse : on additionne les TVA arrondies par taux.
        $lignes = [['quantite_milliemes' => 1000, 'prix_centimes' => 333], ['quantite_milliemes' => 1000, 'prix_centimes' => 333, 'tva_pour_dix_mille' => 260]];
        self::assertSame(36, Totaux::calculer($lignes, 810, false)['tva']);
    }

    public function testUnTauxEgalACeluiDeLEntrepriseNeFaitQuUnGroupe(): void
    {
        $lignes = [['quantite_milliemes' => 1000, 'prix_centimes' => 1000, 'tva_pour_dix_mille' => 810], ['quantite_milliemes' => 1000, 'prix_centimes' => 2000]];
        self::assertSame([['taux' => 810, 'ht' => 3000, 'tva' => 243]], Totaux::parTaux($lignes, 810));
    }

    public function testUneLigneSansTvaDansUneEntrepriseAssujettie(): void
    {
        // 100.00 à 8,1 % : 8.10 ; 40.00 à 0 % : 0. TVA 8.10 ; 148.10 → 148.10.
        $lignes = [['quantite_milliemes' => 1000, 'prix_centimes' => 10000], ['quantite_milliemes' => 1000, 'prix_centimes' => 4000, 'tva_pour_dix_mille' => 0]];
        self::assertSame(['lignes' => [10000, 4000], 'ht' => 14000, 'tva' => 810, 'arrondi' => 0, 'ttc' => 14810], self::sansDetail(Totaux::calculer($lignes, 810)));
        self::assertSame([['taux' => 810, 'ht' => 10000, 'tva' => 810], ['taux' => 0, 'ht' => 4000, 'tva' => 0]], Totaux::parTaux($lignes, 810));
    }

    public function testUneLigneTvaEtArrondiAuxCinqCentimes(): void
    {
        // 12,5 m² × 18.50 = 231.25 ; TVA 8,1 % = 18.73125 → 18.73 ; 231.25 + 18.73 = 249.98 → 250.00 (+0.02).
        $t = Totaux::calculer([['quantite_milliemes' => 12500, 'prix_centimes' => 1850]]);
        self::assertSame(['lignes' => [23125], 'ht' => 23125, 'tva' => 1873, 'arrondi' => 2, 'ttc' => 25000], self::sansDetail($t));
    }

    public function testDemiCentimeDeLigneArrondiVersLeHaut(): void
    {
        // 3 × 7.99 = 23.97 ; 0,333 h × 95.00 = 31.635 → 31.64 ; HT 55.61.
        // TVA 55.61 × 0,081 = 4.50441 → 4.50 ; 60.11 → 60.10 (-0.01).
        $t = Totaux::calculer([
            ['quantite_milliemes' => 3000, 'prix_centimes' => 799],
            ['quantite_milliemes' => 333, 'prix_centimes' => 9500],
        ]);
        self::assertSame(['lignes' => [2397, 3164], 'ht' => 5561, 'tva' => 450, 'arrondi' => -1, 'ttc' => 6010], self::sansDetail($t));
    }

    public function testDemiCentimeDeTvaArrondiVersLeHaut(): void
    {
        // 5.00 ; TVA 0.405 → 0.41 ; 5.41 → 5.40 (-0.01).
        $t = Totaux::calculer([['quantite_milliemes' => 1000, 'prix_centimes' => 500]]);
        self::assertSame(['lignes' => [500], 'ht' => 500, 'tva' => 41, 'arrondi' => -1, 'ttc' => 540], self::sansDetail($t));
    }

    public function testOffreVide(): void
    {
        self::assertSame(['lignes' => [], 'ht' => 0, 'tva' => 0, 'arrondi' => 0, 'ttc' => 0], self::sansDetail(Totaux::calculer([])));
    }

    public function testMontantNegatifArrondiEnSEloignantDeZero(): void
    {
        // Rabais : 1,5 × -0.01 = -0.015 → -0.02 ; 1 × 10.00 ; HT 9.98 ; TVA 0.80838 → 0.81 ; 10.79 → 10.80 (+0.01).
        $t = Totaux::calculer([
            ['quantite_milliemes' => 1500, 'prix_centimes' => -1],
            ['quantite_milliemes' => 1000, 'prix_centimes' => 1000],
        ]);
        self::assertSame(['lignes' => [-2, 1000], 'ht' => 998, 'tva' => 81, 'arrondi' => 1, 'ttc' => 1080], self::sansDetail($t));
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
        self::assertSame(['lignes' => [23125], 'ht' => 23125, 'tva' => 1873, 'arrondi' => 0, 'ttc' => 24998], self::sansDetail($t));
    }

    public function testNonAssujettiSansTva(): void
    {
        // 231.25, TVA 0 ; arrondi aux 5 centimes : 231.25 reste 231.25.
        $t = Totaux::calculer([['quantite_milliemes' => 12500, 'prix_centimes' => 1850]], 0);
        self::assertSame(['lignes' => [23125], 'ht' => 23125, 'tva' => 0, 'arrondi' => 0, 'ttc' => 23125], self::sansDetail($t));
    }

    public function testArrondirRefuseUnDenominateurNul(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Totaux::arrondir(1, 0);
    }

    /** Rabais sous le sous-total, avant la TVA (D4 B, D15 A, 2026-11-21). */
    public function testUnRabaisEnPourCentBaisseLaBaseDeLaTva(): void
    {
        // 10 × 100.00 = 1000.00 ; rabais 5 % : 50.00 ; HT 950.00 ; TVA 8,1 % : 76.95 ; 1026.95 → 1026.95 (déjà aux 5 ct).
        $t = Totaux::calculer([['quantite_milliemes' => 10000, 'prix_centimes' => 10000]], 810, true, [Rabais::pourcent('Rabais fidélité', 500)]);
        self::assertSame(100000, $t['sous_total']);
        self::assertSame([['libelle' => 'Rabais fidélité', 'pour_dix_mille' => 500, 'montant' => 5000]], $t['rabais']);
        self::assertSame(['lignes' => [100000], 'ht' => 95000, 'tva' => 7695, 'arrondi' => 0, 'ttc' => 102695], self::sansDetail($t));
        self::assertSame([['taux' => 810, 'ht' => 95000, 'tva' => 7695]], $t['par_taux']);
    }

    public function testDeuxRabaisSeComptentSurLeMemeSousTotal(): void
    {
        // 1000.00 ; 10 % : 100.00 ; 150.00 en francs ; HT 750.00 ; TVA 60.75 ; 810.75 → 810.75.
        $t = Totaux::calculer([['quantite_milliemes' => 1000, 'prix_centimes' => 100000]], 810, true,
            [Rabais::pourcent('Rabais', 1000), Rabais::montant('Geste commercial', 15000)]);
        self::assertSame([10000, 15000], array_column($t['rabais'], 'montant'));
        self::assertSame(75000, $t['ht']);
        self::assertSame(81075, $t['ttc']);
    }

    public function testLesRabaisNeDepassentJamaisLeSousTotal(): void
    {
        // 100.00 ; 80.00 en francs, puis 50.00 : le second est ramené à 20.00 ; HT 0.
        $t = Totaux::calculer([['quantite_milliemes' => 1000, 'prix_centimes' => 10000]], 810, true,
            [Rabais::montant('A', 8000), Rabais::montant('B', 5000)]);
        self::assertSame([8000, 2000], array_column($t['rabais'], 'montant'));
        self::assertSame(0, $t['ht']);
        self::assertSame(0, $t['ttc']);
    }

    public function testUnRabaisSeRepartitEntreLesTauxAuProrata(): void
    {
        // 300.00 à 8,1 % et 100.00 à 2,6 % ; 40.00 de rabais : 30.00 et 10.00.
        // HT 270.00 (TVA 21.87) et 90.00 (TVA 2.34) ; HT 360.00, TVA 24.21, 384.21 → 384.20.
        $lignes = [['quantite_milliemes' => 1000, 'prix_centimes' => 30000], ['quantite_milliemes' => 1000, 'prix_centimes' => 10000, 'tva_pour_dix_mille' => 260]];
        $t = Totaux::calculer($lignes, 810, true, [Rabais::montant('Rabais', 4000)]);
        self::assertSame([['taux' => 810, 'ht' => 27000, 'tva' => 2187], ['taux' => 260, 'ht' => 9000, 'tva' => 234]], $t['par_taux']);
        self::assertSame(36000, $t['ht']);
        self::assertSame(38420, $t['ttc']);
    }

    public function testLeResteDArrondiDuProrataVaAuPlusGrosTaux(): void
    {
        // 1.00, 1.00, 1.00 à trois taux ; 0.10 de rabais : 0.0333 → 0.03 trois fois, 0.01 de reste au plus gros (le premier rencontré).
        $t = Totaux::parTaux([['quantite_milliemes' => 1000, 'prix_centimes' => 100], ['quantite_milliemes' => 1000, 'prix_centimes' => 100, 'tva_pour_dix_mille' => 260],
            ['quantite_milliemes' => 1000, 'prix_centimes' => 100, 'tva_pour_dix_mille' => 380]], 810, [Rabais::montant('R', 10)]);
        self::assertSame(290, array_sum(array_column($t, 'ht')), 'la somme des HT par taux est le HT après rabais');
        self::assertSame([96, 97, 97], array_column($t, 'ht'));
    }

    public function testUnRabaisSansLigneNeCreeRien(): void
    {
        $t = Totaux::calculer([], 810, true, [Rabais::pourcent('Rabais', 500)]);
        self::assertSame(0, $t['ttc']);
        self::assertSame(0, $t['rabais'][0]['montant']);
    }

    /** Une ligne offerte compte 0 : le rabais en pour cent ne la compte pas. */
    public function testLeRabaisEnPourCentIgnoreLesLignesOffertes(): void
    {
        $t = Totaux::calculer([['quantite_milliemes' => 1000, 'prix_centimes' => 10000], ['quantite_milliemes' => 1000, 'prix_centimes' => 10000, 'offerte' => 1]],
            810, true, [Rabais::pourcent('Rabais', 1000)]);
        self::assertSame(1000, $t['rabais'][0]['montant']);
    }

    /**
     * @param array{lignes: list<int>, sous_total: int, rabais: list<array{libelle: string, pour_dix_mille: int|null, montant: int}>, ht: int, tva: int, arrondi: int, ttc: int, par_taux: list<array{taux: int, ht: int, tva: int}>, avec_options: array{rabais: int, ht: int, tva: int, arrondi: int, ttc: int}|null} $t
     * @return array{lignes: list<int>, ht: int, tva: int, arrondi: int, ttc: int}
     */
    private static function sansDetail(array $t): array
    {
        unset($t['par_taux'], $t['sous_total'], $t['rabais'], $t['avec_options']);
        return $t;
    }
}
