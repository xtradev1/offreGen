<?php

declare(strict_types=1);

namespace OffreGen\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Revue du 2026-11-18, point A13 de la mise en production : les postes dictés au-delà des 300 lignes d'une offre
 * étaient écartés sans rien dire. Leur nombre est gardé sur l'offre, qui en avertit l'artisan.
 *
 * Additive (docs/DEPLOIEMENT.md, Migrations) : nouvelle colonne avec une valeur par défaut ; l'ancienne version l'ignore.
 */
final class Version20261118000001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Postes coupés au-delà du plafond de lignes d\'une offre';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE offre ADD postes_coupes SMALLINT UNSIGNED NOT NULL DEFAULT 0');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE offre DROP COLUMN postes_coupes');
    }
}
