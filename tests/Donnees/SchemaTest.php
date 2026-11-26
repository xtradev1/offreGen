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
     * Chronomètre des dictées (sous-chantier 31, PLAN lot 1) : une ligne par étape et par passage, les rejeux, et sur la
     * dictée le navigateur et l'origine. Les étapes et les rejeux partent avec leur dictée.
     */
    public function testLeChronometreASesTables(): void
    {
        self::assertSame([
            'debut' => ['datetime(3)', 'NO', null],
            'duree_ms' => ['int(10) unsigned', 'YES', 'NULL'],
            'enregistrement_id' => ['int(10) unsigned', 'NO', null],
            'erreur' => ['varchar(190)', 'YES', 'NULL'],
            'etape' => ['varchar(24)', 'NO', null],
            'fin' => ['datetime(3)', 'YES', 'NULL'],
            'issue' => ['varchar(12)', 'NO', null],
            'jetons_envoyes' => ['int(10) unsigned', 'YES', 'NULL'],
            'jetons_recus' => ['int(10) unsigned', 'YES', 'NULL'],
            'modele' => ['varchar(100)', 'YES', 'NULL'],
            'premier_octet_ms' => ['int(10) unsigned', 'YES', 'NULL'],
            'processus' => ['varchar(16)', 'NO', null],
            'rejeu_id' => ['int(10) unsigned', 'YES', 'NULL'],
            'tentative' => ['tinyint(3) unsigned', 'NO', null],
        ], self::colonnes('dictee_etape', [
            'enregistrement_id', 'rejeu_id', 'tentative', 'etape', 'processus', 'debut', 'fin', 'duree_ms', 'issue', 'erreur',
            'modele', 'jetons_envoyes', 'jetons_recus', 'premier_octet_ms',
        ]));
        self::assertSame([
            'demande_le' => ['datetime', 'NO', null],
            'demande_par' => ['int(10) unsigned', 'YES', 'NULL'],
            'enregistrement_id' => ['int(10) unsigned', 'NO', null],
            'erreur' => ['varchar(190)', 'YES', 'NULL'],
            'fini_le' => ['datetime', 'YES', 'NULL'],
            'lignes' => ['smallint(5) unsigned', 'YES', 'NULL'],
            'statut' => ['varchar(12)', 'NO', null],
            'titre' => ['varchar(190)', 'YES', 'NULL'],
            'transcription' => ['mediumtext', 'YES', 'NULL'],
        ], self::colonnes('dictee_rejeu', [
            'enregistrement_id', 'demande_par', 'demande_le', 'fini_le', 'statut', 'transcription', 'titre', 'lignes', 'erreur',
        ]));
        self::assertSame([
            'navigateur' => ['varchar(255)', 'YES', 'NULL'],
            'origine' => ['varchar(10)', 'YES', 'NULL'],
        ], self::colonnes('enregistrement', ['navigateur', 'origine']));

        $requete = BaseDeTest::pdo()->query(
            "SELECT k.TABLE_NAME, k.COLUMN_NAME, r.REFERENCED_TABLE_NAME, r.DELETE_RULE FROM information_schema.REFERENTIAL_CONSTRAINTS r
             JOIN information_schema.KEY_COLUMN_USAGE k
               ON k.CONSTRAINT_SCHEMA = r.CONSTRAINT_SCHEMA AND k.CONSTRAINT_NAME = r.CONSTRAINT_NAME
             WHERE r.CONSTRAINT_SCHEMA = DATABASE() AND k.TABLE_NAME IN ('dictee_etape', 'dictee_rejeu')
             ORDER BY k.TABLE_NAME, k.COLUMN_NAME"
        );
        self::assertNotFalse($requete);
        self::assertSame([
            ['dictee_etape', 'enregistrement_id', 'enregistrement', 'CASCADE'],
            ['dictee_etape', 'rejeu_id', 'dictee_rejeu', 'CASCADE'],
            ['dictee_rejeu', 'demande_par', 'artisan', 'SET NULL'],
            ['dictee_rejeu', 'enregistrement_id', 'enregistrement', 'CASCADE'],
        ], $requete->fetchAll(\PDO::FETCH_NUM));
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
