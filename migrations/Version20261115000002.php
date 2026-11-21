<?php

declare(strict_types=1);

namespace OffreGen\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * TVA par ligne (Xavier, 2026-11-15 ; décision D11) : un taux pour l'entreprise (réglages), un taux d'exception sur une
 * ligne d'offre, une ligne de facture ou un produit du catalogue. NULL : le taux de l'entreprise (ou, pour une ligne
 * d'offre, celui de son produit). En dix-millièmes, comme le reste (8,1 % = 810).
 *
 * Additive (docs/DEPLOIEMENT.md, Migrations) : colonnes nullables, l'ancienne version les ignore et calcule comme avant.
 */
final class Version20261115000002 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Taux de TVA par ligne et par produit';
    }

    public function up(Schema $schema): void
    {
        foreach (['ligne', 'facture_ligne', 'produit'] as $table) {
            $this->addSql("ALTER TABLE $table ADD tva_pour_dix_mille SMALLINT UNSIGNED NULL AFTER prix_centimes");
        }
    }

    public function down(Schema $schema): void
    {
        foreach (['ligne', 'facture_ligne', 'produit'] as $table) {
            $this->addSql("ALTER TABLE $table DROP tva_pour_dix_mille");
        }
    }
}
