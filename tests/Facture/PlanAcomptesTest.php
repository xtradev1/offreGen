<?php

declare(strict_types=1);

namespace OffreGen\Tests\Facture;

use OffreGen\Compte\Reglages;
use OffreGen\Facture\PlanAcomptes;
use PHPUnit\Framework\TestCase;

/**
 * Une facture par acompte, puis une facture finale qui déduit ce qui a été facturé (Xavier, 2026-11-13, choix A2).
 * La dernière tranche est la finale.
 */
final class PlanAcomptesTest extends TestCase
{
    public function testSansAcomptesSeuleLaFinale(): void
    {
        $possibles = PlanAcomptes::possibles([], 0, false);
        self::assertSame([['genre' => 'finale', 'rang' => null, 'nombre' => null, 'pourcent' => null]], $possibles);
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

    public function testLaDerniereTrancheEstLaFinale(): void
    {
        self::assertSame(['finale'], array_column(PlanAcomptes::possibles([30, 30, 40], 2, false), 'genre'));
        self::assertSame(['finale'], array_column(PlanAcomptes::possibles([50, 50], 1, false), 'genre'));
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
