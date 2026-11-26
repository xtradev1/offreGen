<?php

declare(strict_types=1);

namespace OffreGen\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Libellé d'acompte libre (chantier 2026-11-21_lignes-conditions, décisions D11 A et D12 A de Xavier) : gardé sur la
 * facture d'acompte, il nomme sa ligne et sa place dans la liste des acomptes de la facture finale.
 *
 * Additive (docs/DEPLOIEMENT.md, Migrations) : NULL par défaut ; un acompte d'avant reprend « Acompte N ».
 */
final class Version20261122000005 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Libellé de la facture d\'acompte';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE facture ADD libelle VARCHAR(255) NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE facture DROP COLUMN libelle');
    }
}
