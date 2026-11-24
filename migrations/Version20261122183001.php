<?php

declare(strict_types=1);

namespace OffreGen\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Dictée mal comprise (docs/plans/2026-11-07_voix-offre/30-dictee-mal-comprise/PLAN.md, lot 2) : le verdict de la
 * dictée et les chiffres qui l'ont donné, gardés pour revoir les seuils ; « Créer l'offre quand même » ; l'offre dont
 * la nouvelle dictée remplace les lignes (décision D5 A de Xavier) ; la date à laquelle l'artisan garde une offre
 * créée sur une dictée douteuse (le bandeau et le point orange disparaissent).
 *
 * Le statut `a_confirmer` tient dans `statut VARCHAR(20)` : pas de changement de type.
 *
 * Additive (docs/DEPLOIEMENT.md, Migrations) : colonnes nullables, `dictee_forcee` à 0 par défaut. L'ancienne version
 * ne lit aucune d'elles.
 */
final class Version20261122183001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Verdict de la dictée, offre remplacée, dictée gardée';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            ALTER TABLE enregistrement
                ADD verdict VARCHAR(20) NULL DEFAULT NULL,
                ADD verdict_cause VARCHAR(30) NULL DEFAULT NULL,
                ADD mots INT UNSIGNED NULL DEFAULT NULL,
                ADD duree_ms INT UNSIGNED NULL DEFAULT NULL,
                ADD voix_db SMALLINT NULL DEFAULT NULL COMMENT 'dixièmes de dB, -412 pour -41,2 dB',
                ADD no_speech_max SMALLINT UNSIGNED NULL DEFAULT NULL COMMENT 'millièmes',
                ADD niveaux TEXT NULL DEFAULT NULL COMMENT 'JSON : dB par seconde, pour l''onde du lecteur',
                ADD dictee_forcee TINYINT(1) NOT NULL DEFAULT 0,
                ADD remplace_offre_id INT UNSIGNED NULL DEFAULT NULL,
                ADD CONSTRAINT fk_enregistrement_remplace FOREIGN KEY (remplace_offre_id) REFERENCES offre (id) ON DELETE SET NULL
            SQL);
        $this->addSql('ALTER TABLE offre ADD dictee_gardee_le DATETIME NULL DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE offre DROP COLUMN dictee_gardee_le');
        $this->addSql('ALTER TABLE enregistrement DROP FOREIGN KEY fk_enregistrement_remplace');
        $this->addSql(<<<'SQL'
            ALTER TABLE enregistrement
                DROP INDEX fk_enregistrement_remplace,
                DROP COLUMN remplace_offre_id,
                DROP COLUMN dictee_forcee,
                DROP COLUMN niveaux,
                DROP COLUMN no_speech_max,
                DROP COLUMN voix_db,
                DROP COLUMN duree_ms,
                DROP COLUMN mots,
                DROP COLUMN verdict_cause,
                DROP COLUMN verdict
            SQL);
    }
}
