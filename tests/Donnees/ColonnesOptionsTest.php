<?php

declare(strict_types=1);

namespace OffreGen\Tests\Donnees;

use OffreGen\Tests\Outils\BaseDeTest;
use PHPUnit\Framework\TestCase;

/**
 * Options et variantes (docs/plans/2026-11-22_options-variantes/PLAN.md, lot 1) : la nature de chaque ligne d'offre,
 * la ligne qu'une variante remplace, et le choix du client.
 */
final class ColonnesOptionsTest extends TestCase
{
    public function testLesColonnesDesOptionsExistentAvecLeursDefauts(): void
    {
        $requete = BaseDeTest::pdo()->query(
            "SELECT COLUMN_NAME, COLUMN_TYPE, IS_NULLABLE, COLUMN_DEFAULT FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ligne' AND COLUMN_NAME IN ('nature', 'remplace_id', 'retenue')"
        );
        self::assertNotFalse($requete);
        $colonnes = [];
        foreach ($requete->fetchAll(\PDO::FETCH_ASSOC) as $c) {
            $colonnes[$c['COLUMN_NAME']] = [$c['COLUMN_TYPE'], $c['IS_NULLABLE'], $c['COLUMN_DEFAULT']];
        }
        ksort($colonnes);
        // Une ligne d'avant la migration est une ligne du total, non retenue : l'ancienne version du code n'écrit rien ici.
        self::assertSame([
            'nature' => ['varchar(10)', 'NO', "'ligne'"],
            'remplace_id' => ['int(10) unsigned', 'YES', 'NULL'],
            'retenue' => ['tinyint(1)', 'NO', '0'],
        ], $colonnes);
    }

    public function testUneFactureNePortePasDOptions(): void
    {
        $n = BaseDeTest::pdo()->query(
            "SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'facture_ligne'
             AND COLUMN_NAME IN ('nature', 'remplace_id', 'retenue')"
        );
        self::assertNotFalse($n);
        self::assertSame(0, (int) $n->fetchColumn());
    }
}
