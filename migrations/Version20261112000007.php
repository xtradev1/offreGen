<?php

declare(strict_types=1);

namespace OffreGen\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Réglages mieux rangés (sous-chantier 22, choix de Xavier du 2026-11-12) : un assistant s'ouvre au premier
 * lancement ; reglages_faits_le dit qu'il a été fait ou passé. Additive : colonne nulle, chacun le verra une fois.
 */
final class Version20261112000007 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Date de fin de l\'assistant des réglages';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE artisan ADD reglages_faits_le DATETIME NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE artisan DROP COLUMN reglages_faits_le');
    }
}
