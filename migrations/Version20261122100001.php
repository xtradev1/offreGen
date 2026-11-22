<?php

declare(strict_types=1);

namespace OffreGen\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Activité du compte (chantier 2026-11-22_familles-activite, décision de Xavier du 2026-11-22) : bâtiment, extérieur
 * ou prestations (Compte\Activite). Elle règle les mots des écrans et des documents, et le rangement des lignes.
 *
 * Additive (docs/DEPLOIEMENT.md, Migrations) : « batiment » par défaut, l'application d'avant. Les comptes dont le
 * métier est paysagiste passent en extérieur, comme le ferait leur création aujourd'hui.
 */
final class Version20261122100001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Activité du compte';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE artisan ADD activite VARCHAR(20) NOT NULL DEFAULT 'batiment'");
        $this->addSql("UPDATE artisan SET activite = 'exterieur' WHERE metier = 'paysagiste'");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE artisan DROP COLUMN activite');
    }
}
