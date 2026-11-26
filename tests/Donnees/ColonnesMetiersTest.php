<?php

declare(strict_types=1);

namespace OffreGen\Tests\Donnees;

use OffreGen\Tests\Outils\BaseDeTest;
use PHPUnit\Framework\TestCase;

/**
 * Métiers de la démo et de l'essai (docs/plans/2026-11-19_metiers-demo/PLAN.md, lot 2) : le métier sur le compte,
 * le prix d'exemple sur le produit, la marque « exemple » sur l'offre et le client.
 */
final class ColonnesMetiersTest extends TestCase
{
    public function testLesCinqColonnesExistentAvecLeurTypeEtLeurDefaut(): void
    {
        $requete = BaseDeTest::pdo()->query(
            "SELECT TABLE_NAME, COLUMN_NAME, COLUMN_TYPE, IS_NULLABLE, COLUMN_DEFAULT FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND (
                 (TABLE_NAME = 'artisan' AND COLUMN_NAME IN ('metier', 'metier_saisi'))
                 OR (TABLE_NAME = 'produit' AND COLUMN_NAME = 'prix_exemple_centimes')
                 OR (TABLE_NAME IN ('offre', 'client') AND COLUMN_NAME = 'exemple'))"
        );
        self::assertNotFalse($requete);
        $colonnes = [];
        foreach ($requete->fetchAll(\PDO::FETCH_ASSOC) as $c) {
            $colonnes[$c['TABLE_NAME'] . '.' . $c['COLUMN_NAME']] = [$c['COLUMN_TYPE'], $c['IS_NULLABLE'], $c['COLUMN_DEFAULT']];
        }
        ksort($colonnes);
        // Un compte, un produit, une offre et un client d'avant la migration : sans métier, sans marque.
        self::assertSame([
            'artisan.metier' => ['varchar(20)', 'YES', 'NULL'],
            'artisan.metier_saisi' => ['varchar(60)', 'YES', 'NULL'],
            'client.exemple' => ['tinyint(1)', 'NO', '0'],
            'offre.exemple' => ['tinyint(1)', 'NO', '0'],
            'produit.prix_exemple_centimes' => ['int(10) unsigned', 'YES', 'NULL'],
        ], $colonnes);
    }
}
