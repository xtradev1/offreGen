<?php

declare(strict_types=1);

namespace OffreGen\Tests\Offre;

use OffreGen\Offre\Options;
use PHPUnit\Framework\TestCase;

/**
 * Ce qui est dans le total d'une offre (docs/plans/2026-11-22_options-variantes/PLAN.md, « Règle unique du total »).
 */
final class OptionsTest extends TestCase
{
    /** @return array{id: int, nature: string, remplace_id: int|null, retenue: int} */
    private static function l(int $id, string $nature = 'ligne', ?int $remplace = null, int $retenue = 0): array
    {
        return ['id' => $id, 'nature' => $nature, 'remplace_id' => $remplace, 'retenue' => $retenue];
    }

    public function testUneLigneSansNatureEstDansLeTotal(): void
    {
        // Ligne de facture, ou ligne d'avant la migration : pas de nature.
        self::assertSame([true, true], Options::masque([['id' => 1], []]));
    }

    public function testOptionEtVarianteHorsDuTotalTantQueRienNEstRetenu(): void
    {
        $lignes = [self::l(1), self::l(2, 'variante', 1), self::l(3, 'option')];
        self::assertSame([true, false, false], Options::masque($lignes));
        self::assertSame([true, false, true], Options::masqueAvecOptions($lignes), 'les options, pas les variantes (point 4)');
        self::assertTrue(Options::aDesOptionsLibres($lignes));
    }

    public function testUneVarianteRetenuePrendLaPlaceDeSaLigne(): void
    {
        $lignes = [self::l(1), self::l(2, 'variante', 1, 1), self::l(3, 'option', null, 1)];
        self::assertSame([false, true, true], Options::masque($lignes));
        self::assertFalse(Options::aDesOptionsLibres($lignes));
    }

    public function testUneSeuleVarianteRetenueParLigneLaPremiere(): void
    {
        // L'écriture décoche les autres (DepotOffres::retenir) ; si deux le sont quand même, la première compte seule.
        $lignes = [self::l(1), self::l(2, 'variante', 1, 1), self::l(3, 'variante', 1, 1)];
        self::assertSame([false, true, false], Options::masque($lignes));
    }

    public function testUneVarianteOrphelineSeLitCommeUneOption(): void
    {
        // Sa ligne de base est supprimée (absente de la liste) ou n'est pas une ligne du total.
        $lignes = [self::l(2, 'variante', 1), self::l(3, 'option'), self::l(4, 'variante', 3)];
        self::assertSame('option', Options::natureEffective($lignes[0], $lignes));
        self::assertSame('option', Options::natureEffective($lignes[2], $lignes));
        self::assertSame([false, false, false], Options::masque($lignes));
        self::assertSame([true, true, true], Options::masqueAvecOptions($lignes));
        $retenue = [self::l(2, 'variante', 1, 1)];
        self::assertSame([true], Options::masque($retenue));
    }

    public function testUneNatureInconnueEstUneLigne(): void
    {
        self::assertSame('ligne', Options::natureEffective(self::l(1, 'bidon'), [self::l(1, 'bidon')]));
        self::assertSame([true], Options::masque([self::l(1, 'bidon')]));
    }

    public function testLesLignesDuTotalMettentLaVarianteAuRangDeSaBase(): void
    {
        $lignes = [self::l(1), self::l(5), self::l(2, 'option', null, 1), self::l(3, 'option'), self::l(4, 'variante', 1, 1)];
        self::assertSame([4, 5, 2], array_column(Options::lignesDuTotal($lignes), 'id'));
    }
}
