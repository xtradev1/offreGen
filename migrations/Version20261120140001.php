<?php

declare(strict_types=1);

namespace OffreGen\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Démo créée par l'équipe (lot 5 de docs/plans/2026-11-19_admin-comptes, demande de Xavier du 2026-11-20) :
 * courriel_saisi garde le courriel tapé par le prospect à l'ouverture de sa démo, ou par le commercial à la création.
 * Il n'est pas confirmé : il ne devient l'adresse du compte qu'au passage à l'essai. Jusque-là, le compte garde son
 * adresse fabriquée en @demo.invalid.
 *
 * Additive (docs/DEPLOIEMENT.md, Migrations) : une colonne avec une valeur par défaut, l'ancienne version l'ignore.
 */
final class Version20261120140001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Courriel tapé par le prospect d\'une démo, non confirmé';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE artisan ADD courriel_saisi VARCHAR(190) NULL DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE artisan DROP COLUMN courriel_saisi');
    }
}
