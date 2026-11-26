<?php

declare(strict_types=1);

namespace OffreGen\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Mise en production, droit administrateur (décision A1 de Xavier, 2026-11-18) : un compte qui voit les pages
 * d'administration. Aucun compte ne l'a au départ ; bin/administrateur le donne.
 *
 * Additive (docs/DEPLOIEMENT.md, Migrations) : nouvelle colonne avec une valeur par défaut ; l'ancienne version l'ignore.
 */
final class Version20261118000002 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Droit administrateur d\'un compte';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE artisan ADD administrateur TINYINT(1) NOT NULL DEFAULT 0');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE artisan DROP COLUMN administrateur');
    }
}
