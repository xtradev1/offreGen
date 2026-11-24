<?php

declare(strict_types=1);

namespace OffreGen\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Chronomètre des dictées (docs/plans/2026-11-07_voix-offre/31-chronometre-dictees/PLAN.md, lot 1) : une ligne par
 * étape et par passage du traitement, écrite au début de l'étape et complétée à la fin ; les rejeux demandés depuis
 * l'administration ; sur la dictée, le navigateur et l'origine (micro ou fichier). La durée de l'audio va dans
 * `enregistrement.duree_ms`, posée par le sous-chantier 30.
 *
 * Additive (docs/DEPLOIEMENT.md, Migrations) : deux tables neuves et deux colonnes NULL par défaut, que l'ancienne
 * version ignore.
 */
final class Version20261124230001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Chronomètre des dictées : étapes, rejeux, navigateur et origine';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE dictee_rejeu (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                enregistrement_id INT UNSIGNED NOT NULL,
                demande_par INT UNSIGNED NULL,
                demande_le DATETIME NOT NULL,
                fini_le DATETIME NULL,
                statut VARCHAR(12) NOT NULL,
                transcription MEDIUMTEXT NULL,
                titre VARCHAR(190) NULL,
                lignes SMALLINT UNSIGNED NULL,
                erreur VARCHAR(190) NULL,
                KEY ix_dictee_rejeu_enregistrement (enregistrement_id, demande_le),
                CONSTRAINT fk_dictee_rejeu_enregistrement FOREIGN KEY (enregistrement_id) REFERENCES enregistrement (id) ON DELETE CASCADE,
                CONSTRAINT fk_dictee_rejeu_demande_par FOREIGN KEY (demande_par) REFERENCES artisan (id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE dictee_etape (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                enregistrement_id INT UNSIGNED NOT NULL,
                rejeu_id INT UNSIGNED NULL,
                tentative TINYINT UNSIGNED NOT NULL,
                etape VARCHAR(24) NOT NULL,
                processus VARCHAR(16) NOT NULL,
                debut DATETIME(3) NOT NULL,
                fin DATETIME(3) NULL,
                duree_ms INT UNSIGNED NULL,
                issue VARCHAR(12) NOT NULL,
                erreur VARCHAR(190) NULL,
                modele VARCHAR(100) NULL,
                jetons_envoyes INT UNSIGNED NULL,
                jetons_recus INT UNSIGNED NULL,
                premier_octet_ms INT UNSIGNED NULL,
                KEY ix_dictee_etape_dictee (enregistrement_id, rejeu_id, debut),
                KEY ix_dictee_etape_debut (debut),
                CONSTRAINT fk_dictee_etape_enregistrement FOREIGN KEY (enregistrement_id) REFERENCES enregistrement (id) ON DELETE CASCADE,
                CONSTRAINT fk_dictee_etape_rejeu FOREIGN KEY (rejeu_id) REFERENCES dictee_rejeu (id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            SQL);
        $this->addSql('ALTER TABLE enregistrement ADD navigateur VARCHAR(255) NULL DEFAULT NULL, ADD origine VARCHAR(10) NULL DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE enregistrement DROP COLUMN origine, DROP COLUMN navigateur');
        $this->addSql('DROP TABLE dictee_etape');
        $this->addSql('DROP TABLE dictee_rejeu');
    }
}
