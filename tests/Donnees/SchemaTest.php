<?php

declare(strict_types=1);

namespace OffreGen\Tests\Donnees;

use OffreGen\Tests\Outils\BaseDeTest;
use PHPUnit\Framework\TestCase;

final class SchemaTest extends TestCase
{
    public function testLesSixTablesExistent(): void
    {
        $requete = BaseDeTest::pdo()
            ->query('SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() ORDER BY TABLE_NAME');
        self::assertNotFalse($requete);
        $tables = $requete->fetchAll(\PDO::FETCH_COLUMN);
        foreach (['artisan', 'enregistrement', 'journal_connexion', 'ligne', 'offre', 'produit'] as $table) {
            self::assertContains($table, $tables);
        }
    }

    public function testLUtilisateurDApplicationNePeutPasChangerLaStructure(): void
    {
        try {
            BaseDeTest::pdo()->exec('CREATE TABLE intrus (id INT)');
            self::fail("L'utilisateur d'application a pu créer une table.");
        } catch (\PDOException $e) {
            // 1142 : commande refusée à cet utilisateur.
            self::assertSame(1142, $e->errorInfo[1] ?? null, $e->getMessage());
        }
    }

    public function testLUtilisateurDApplicationNePeutPasSupprimerUneTable(): void
    {
        $this->expectException(\PDOException::class);
        BaseDeTest::pdo()->exec('DROP TABLE ligne');
    }
}
