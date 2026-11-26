<?php

declare(strict_types=1);

namespace OffreGen\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Rabais sous le sous-total (chantier 2026-11-21_lignes-conditions, décisions D4 B et D15 A de Xavier) : une liste en
 * JSON sur l'offre (Offre\Rabais), copiée sur la facture finale.
 *
 * Additive (docs/DEPLOIEMENT.md, Migrations) : NULL par défaut, l'ancienne version l'ignore.
 */
final class Version20261122000002 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Rabais sur l\'offre et sur la facture';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE offre ADD rabais TEXT NULL');
        $this->addSql('ALTER TABLE facture ADD rabais TEXT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE facture DROP COLUMN rabais');
        $this->addSql('ALTER TABLE offre DROP COLUMN rabais');
    }
}
