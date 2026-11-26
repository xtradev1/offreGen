<?php

declare(strict_types=1);

namespace OffreGen\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Le mois gratuit (chantier onboarding, E1 de Xavier du 2026-11-23 ; lot B de l'administration, T1 et P4) : la fin du
 * mois gratuit d'un compte passé de la démo à l'essai. Les essais d'avant gardent leur nombre d'offres, sans date.
 *
 * Additive (docs/DEPLOIEMENT.md, Migrations) : NULL par défaut ; l'ancienne version l'ignore.
 */
final class Version20261124140001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Fin du mois gratuit';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE artisan ADD essai_fin DATETIME NULL AFTER demo_fin');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE artisan DROP COLUMN essai_fin');
    }
}
