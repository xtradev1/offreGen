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

    /**
     * Administration des comptes (docs/plans/2026-11-19_admin-comptes/PLAN-lot-A.md, lot 1) : rôle d'équipe, créateur,
     * fin de démo et offres offertes sur le compte ; demandes d'appel ; journal des gestes de l'équipe.
     */
    public function testLEquipeASesColonnesEtSesTables(): void
    {
        $requete = BaseDeTest::pdo()->query(
            "SELECT COLUMN_NAME, IS_NULLABLE, COLUMN_DEFAULT FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'artisan' AND COLUMN_NAME IN ('role', 'cree_par', 'demo_fin', 'offres_offertes')"
        );
        self::assertNotFalse($requete);
        $colonnes = [];
        foreach ($requete->fetchAll(\PDO::FETCH_ASSOC) as $c) {
            $colonnes[$c['COLUMN_NAME']] = [$c['IS_NULLABLE'], $c['COLUMN_DEFAULT']];
        }
        ksort($colonnes);
        // Un compte existant reste un artisan sans rôle, sans créateur, sans fin, sans offre offerte.
        self::assertSame([
            'cree_par' => ['YES', 'NULL'],
            'demo_fin' => ['YES', 'NULL'],
            'offres_offertes' => ['NO', '0'],
            'role' => ['YES', 'NULL'],
        ], $colonnes);

        $requete = BaseDeTest::pdo()
            ->query('SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE()');
        self::assertNotFalse($requete);
        $tables = $requete->fetchAll(\PDO::FETCH_COLUMN);
        self::assertContains('demande_appel', $tables);
        self::assertContains('journal_admin', $tables);
    }
}
