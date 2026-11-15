<?php

declare(strict_types=1);

namespace OffreGen\Tests\Facture;

use OffreGen\Compte\Reglages;
use OffreGen\Facture\PlanAcomptes;
use OffreGen\Offre\Totaux;
use PHPUnit\Framework\TestCase;

/**
 * Une facture par acompte, puis une facture finale qui déduit ce qui a été facturé (Xavier, 2026-11-13, choix A2).
 * La dernière tranche est la finale.
 */
final class PlanAcomptesTest extends TestCase
{
    /** E4 (Xavier, 2026-11-15) : un acompte se facture même sans plan d'acomptes ; la finale est alors proposée d'abord. */
    public function testSansAcomptesLaFinaleDAbordPuisUnAcompteLibre(): void
    {
        $possibles = PlanAcomptes::possibles([], 0, false);
        self::assertSame([
            ['genre' => 'finale', 'rang' => null, 'nombre' => null, 'pourcent' => null],
            ['genre' => 'acompte', 'rang' => 1, 'nombre' => null, 'pourcent' => PlanAcomptes::POURCENT_PAR_DEFAUT],
        ], $possibles);
    }

    public function testLePremierAcompteEstProposeEnPremier(): void
    {
        $possibles = PlanAcomptes::possibles([30, 30, 40], 0, false);
        self::assertSame(['genre' => 'acompte', 'rang' => 1, 'nombre' => 3, 'pourcent' => 30], $possibles[0]);
        self::assertSame('finale', $possibles[1]['genre'], "L'artisan peut facturer le tout d'un coup.");
        self::assertCount(2, $possibles);
    }

    public function testLeDeuxiemeAcompteSuitLePremier(): void
    {
        self::assertSame(['genre' => 'acompte', 'rang' => 2, 'nombre' => 3, 'pourcent' => 30], PlanAcomptes::possibles([30, 30, 40], 1, false)[0]);
    }

    public function testLaDerniereTrancheEstLaFinaleUnAcompteDePlusResteLibre(): void
    {
        self::assertSame(['finale', 'acompte'], array_column(PlanAcomptes::possibles([30, 30, 40], 2, false), 'genre'));
        self::assertSame(['genre' => 'acompte', 'rang' => 2, 'nombre' => null, 'pourcent' => 30], PlanAcomptes::possibles([50, 50], 1, false)[1]);
    }

    /** Les puces de la feuille : 20, 30, 40, 50 %, plus le pourcentage des conditions s'il n'y est pas. */
    public function testLesPourcentagesProposes(): void
    {
        self::assertSame([20, 30, 40, 50], PlanAcomptes::pourcentages(30));
        self::assertSame([20, 25, 30, 40, 50], PlanAcomptes::pourcentages(25));
        self::assertSame([20, 30, 40, 50], PlanAcomptes::pourcentages(null));
    }

    /** E4 : l'artisan tape ce que le client paie, TVA comprise ; la facture tombe sur ce montant. */
    public function testUnMontantTapeTvaComprise(): void
    {
        $offre = [['taux' => 810, 'ht' => 287400, 'tva' => 23280]];
        foreach ([100000, 93205, 150000, 1, 310680] as $ttc) {
            $parts = PlanAcomptes::partsPourMontant($offre, $ttc, 810, false);
            self::assertSame([null], array_column($parts, 'taux'), "Un seul taux, celui de l'entreprise : taux nul, comme parts().");
            self::assertSame($ttc, self::ttc($parts, 810, false), "TTC voulu $ttc");
        }
        self::assertSame(92507, PlanAcomptes::partsPourMontant($offre, 100000, 810, false)[0]['ht']);
    }

    public function testUnMontantTapeAvecArrondiAuxCinqCentimes(): void
    {
        $offre = [['taux' => 810, 'ht' => 287400, 'tva' => 23280]];
        self::assertSame(93205, self::ttc(PlanAcomptes::partsPourMontant($offre, 93205, 810, true), 810, true));
        // 932.03 ne tombe pas sur une pièce : le plus proche possible.
        self::assertSame(93205, self::ttc(PlanAcomptes::partsPourMontant($offre, 93203, 810, true), 810, true));
    }

    /** Deux taux : le montant se répartit au prorata du HT de chaque taux, et tombe juste. */
    public function testUnMontantTapeSurDeuxTaux(): void
    {
        $offre = [['taux' => 810, 'ht' => 200000, 'tva' => 16200], ['taux' => 260, 'ht' => 100000, 'tva' => 2600]];
        $parts = PlanAcomptes::partsPourMontant($offre, 100000, 810, false);
        self::assertSame([810, 260], array_column($parts, 'taux'));
        self::assertSame(100000, self::ttc($parts, 810, false));
        self::assertEqualsWithDelta(2.0, $parts[0]['ht'] / $parts[1]['ht'], 0.001);
    }

    /** @param list<array{taux: int|null, ht: int}> $parts */
    private static function ttc(array $parts, int $taux, bool $cinq): int
    {
        $lignes = array_map(static fn (array $p): array => ['quantite_milliemes' => 1000, 'prix_centimes' => $p['ht'], 'tva_pour_dix_mille' => $p['taux']], $parts);
        return Totaux::calculer($lignes, $taux, $cinq)['ttc'];
    }

    public function testRienApresLaFinale(): void
    {
        self::assertSame([], PlanAcomptes::possibles([30, 30, 40], 0, true));
        self::assertSame([], PlanAcomptes::possibles([], 0, true));
    }

    public function testUnAcompteEstUnPourcentageDuHtArrondiAuCentime(): void
    {
        self::assertSame(86220, PlanAcomptes::montant(287400, 30));
        self::assertSame(2, PlanAcomptes::montant(3, 50), '1,5 centime : le demi monte.');
        self::assertSame(1, PlanAcomptes::montant(3, 40), '1,2 centime descend.');
    }

    public function testLaLigneDUnAcompteDitSonRangEtSonOffre(): void
    {
        self::assertSame("Acompte 1 sur 3 (30\u{00A0}%), selon offre n°\u{00A0}12", PlanAcomptes::designation(1, 3, 30, 12));
        self::assertSame("Acompte 2 (40\u{00A0}%), selon offre n°\u{00A0}12", PlanAcomptes::designation(2, null, 40, 12), 'Hors plan : pas de « sur N ».');
        self::assertSame("Acompte 1, selon offre n°\u{00A0}12", PlanAcomptes::designation(1, null, null, 12), 'Montant tapé : pas de pourcentage.');
    }

    public function testEcheanceTireeDesConditions(): void
    {
        $date = new \DateTimeImmutable('2026-11-13');
        $net = new Reglages('X', 810, true, paiementType: 'net', paiementJours: 10);
        $jours = new Reglages('X', 810, true, paiementType: 'jours', paiementJours: 20);
        $acomptes = new Reglages('X', 810, true, paiementType: 'acomptes', paiementAcomptes: [30, 70]);
        self::assertSame('2026-11-23', PlanAcomptes::echeance($net, 'finale', $date)->format('Y-m-d'));
        self::assertSame('2026-12-03', PlanAcomptes::echeance($jours, 'finale', $date)->format('Y-m-d'));
        self::assertSame('2026-12-13', PlanAcomptes::echeance($acomptes, 'finale', $date)->format('Y-m-d'), 'Aucun délai réglé : 30 jours.');
        self::assertSame('2026-12-13', PlanAcomptes::echeance($net, 'acompte', $date)->format('Y-m-d'), 'Un acompte : 30 jours.');
    }
}
