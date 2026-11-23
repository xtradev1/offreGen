<?php

declare(strict_types=1);

namespace OffreGen\Tests\Donnees;

use OffreGen\Tests\Outils\BaseDeTest;
use OffreGen\Tests\Outils\Jeu;
use PHPUnit\Framework\TestCase;

/**
 * Onboarding (docs/plans/2026-11-21_onboarding/PLAN.md, lot 1) : les guides et la démo d'entraînement.
 */
final class ColonnesOnboardingTest extends TestCase
{
    public function testLesColonnesExistentAvecLeursValeursParDefaut(): void
    {
        $requete = BaseDeTest::pdo()->query(
            "SELECT COLUMN_NAME, COLUMN_TYPE, IS_NULLABLE, COLUMN_DEFAULT FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'artisan' AND COLUMN_NAME IN ('guide_artisan_le', 'guide_equipe_le', 'entrainement')"
        );
        self::assertNotFalse($requete);
        $colonnes = [];
        foreach ($requete->fetchAll(\PDO::FETCH_ASSOC) as $c) {
            $colonnes[$c['COLUMN_NAME']] = [$c['COLUMN_TYPE'], $c['IS_NULLABLE'], $c['COLUMN_DEFAULT']];
        }
        ksort($colonnes);
        self::assertSame([
            'entrainement' => ['tinyint(1)', 'NO', '0'],
            'guide_artisan_le' => ['datetime', 'YES', 'NULL'],
            'guide_equipe_le' => ['datetime', 'YES', 'NULL'],
        ], $colonnes);
    }

    public function testUnCompteNeufNAPasEncoreVuLesGuides(): void
    {
        $jeu = new Jeu();
        $ligne = BaseDeTest::ligne("SELECT guide_artisan_le, guide_equipe_le, entrainement FROM artisan WHERE id = {$jeu->a}");
        self::assertSame([null, null, 0], [$ligne['guide_artisan_le'], $ligne['guide_equipe_le'], (int) $ligne['entrainement']]);
    }
}
