<?php

declare(strict_types=1);

namespace OffreGen\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Offre par pièce (sous-chantier 28, décisions de Xavier du 2026-11-17) : la pièce dictée de chaque ligne. NULL : la
 * ligne vaut pour tout le chantier (groupe « Général »), ou l'offre n'est pas rangée par pièce.
 *
 * Additive (docs/DEPLOIEMENT.md, Migrations) : nouvelle colonne nullable ; l'ancienne version l'ignore.
 */
final class Version20261117000003 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Pièce de chaque ligne d\'offre';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE ligne ADD piece VARCHAR(80) NULL DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE ligne DROP COLUMN piece');
    }
}
