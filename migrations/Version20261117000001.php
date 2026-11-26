<?php

declare(strict_types=1);

namespace OffreGen\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Compte d'essai (Xavier, 2026-11-17) : un artisan qui découvre l'application a un nombre d'offres fixe, tous jours
 * confondus (« un plafond absolu, l'idée c'est tester et prendre en main »). NULL : aucun plafond.
 *
 * Additive (docs/DEPLOIEMENT.md, Migrations) : nouvelle colonne nullable ; l'ancienne version l'ignore.
 */
final class Version20261117000001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Plafond total d\'offres d\'un compte d\'essai';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE artisan ADD offres_max INT UNSIGNED NULL DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE artisan DROP COLUMN offres_max');
    }
}
