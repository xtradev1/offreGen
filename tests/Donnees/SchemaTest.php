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
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'artisan' AND COLUMN_NAME IN ('role', 'cree_par', 'demo_fin', 'offres_offertes', 'apporte_par', 'acces_en_attente', 'courriel_saisi', 'nom_affiche')"
        );
        self::assertNotFalse($requete);
        $colonnes = [];
        foreach ($requete->fetchAll(\PDO::FETCH_ASSOC) as $c) {
            $colonnes[$c['COLUMN_NAME']] = [$c['IS_NULLABLE'], $c['COLUMN_DEFAULT']];
        }
        ksort($colonnes);
        // Un compte existant reste un artisan sans rôle, sans créateur, sans fin, sans offre offerte.
        self::assertSame([
            'acces_en_attente' => ['NO', '0'],
            'apporte_par' => ['YES', 'NULL'],
            'courriel_saisi' => ['YES', 'NULL'],
            'cree_par' => ['YES', 'NULL'],
            'demo_fin' => ['YES', 'NULL'],
            'nom_affiche' => ['YES', 'NULL'],
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

    /**
     * Dictée mal comprise (docs/plans/2026-11-07_voix-offre/30-dictee-mal-comprise/PLAN.md, lot 2) : verdict et chiffres
     * de la mesure sur l'enregistrement, offre que la nouvelle dictée remplace, date à laquelle l'artisan garde l'offre.
     * Toutes nullables ou avec une valeur par défaut : l'ancienne version tourne sur la base migrée.
     */
    public function testLaDicteeDouteuseASesColonnes(): void
    {
        self::assertSame([
            'dictee_forcee' => ['tinyint(1)', 'NO', '0'],
            'duree_ms' => ['int(10) unsigned', 'YES', 'NULL'],
            'mots' => ['int(10) unsigned', 'YES', 'NULL'],
            'niveaux' => ['text', 'YES', 'NULL'],
            'no_speech_max' => ['smallint(5) unsigned', 'YES', 'NULL'],
            'remplace_offre_id' => ['int(10) unsigned', 'YES', 'NULL'],
            'verdict' => ['varchar(20)', 'YES', 'NULL'],
            'verdict_cause' => ['varchar(30)', 'YES', 'NULL'],
            'voix_db' => ['smallint(6)', 'YES', 'NULL'],
        ], self::colonnes('enregistrement', [
            'verdict', 'verdict_cause', 'mots', 'duree_ms', 'voix_db', 'no_speech_max', 'niveaux', 'dictee_forcee',
            'remplace_offre_id',
        ]));
        self::assertSame(['dictee_gardee_le' => ['datetime', 'YES', 'NULL']], self::colonnes('offre', ['dictee_gardee_le']));

        // L'offre remplacée qui disparaît ne bloque ni n'emporte l'enregistrement.
        $requete = BaseDeTest::pdo()->query(
            "SELECT r.REFERENCED_TABLE_NAME, r.DELETE_RULE FROM information_schema.REFERENTIAL_CONSTRAINTS r
             JOIN information_schema.KEY_COLUMN_USAGE k
               ON k.CONSTRAINT_SCHEMA = r.CONSTRAINT_SCHEMA AND k.CONSTRAINT_NAME = r.CONSTRAINT_NAME
             WHERE r.CONSTRAINT_SCHEMA = DATABASE() AND k.TABLE_NAME = 'enregistrement' AND k.COLUMN_NAME = 'remplace_offre_id'"
        );
        self::assertNotFalse($requete);
        self::assertSame([['offre', 'SET NULL']], $requete->fetchAll(\PDO::FETCH_NUM));
    }

    /**
     * @param list<string> $noms
     * @return array<string, array{string, string, ?string}>
     */
    private static function colonnes(string $table, array $noms): array
    {
        $requete = BaseDeTest::pdo()->prepare(
            'SELECT COLUMN_NAME, COLUMN_TYPE, IS_NULLABLE, COLUMN_DEFAULT FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME IN (' . implode(', ', array_fill(0, count($noms), '?')) . ')'
        );
        $requete->execute([$table, ...$noms]);
        $colonnes = [];
        foreach ($requete->fetchAll(\PDO::FETCH_ASSOC) as $c) {
            $colonnes[$c['COLUMN_NAME']] = [$c['COLUMN_TYPE'], $c['IS_NULLABLE'], $c['COLUMN_DEFAULT']];
        }
        ksort($colonnes);

        return $colonnes;
    }
}
