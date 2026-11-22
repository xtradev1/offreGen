<?php

declare(strict_types=1);

namespace OffreGen\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Ligne offerte (chantier 2026-11-21_lignes-conditions, décisions D1 et D2 de Xavier) : `offerte` à 1, la ligne compte
 * 0 ; son prix reste, barré sur le document, ou 0 pour « Offert » seul. Un prix 0 sans `offerte` reste « manquant ».
 * La facture finale copie la marque, d'où la colonne sur `facture_ligne`.
 *
 * Additive (docs/DEPLOIEMENT.md, Migrations) : 0 par défaut, l'ancienne version l'ignore.
 */
final class Version20261122000001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ligne offerte, sur l\'offre et sur la facture';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE ligne ADD offerte TINYINT(1) NOT NULL DEFAULT 0 AFTER prix_centimes');
        $this->addSql('ALTER TABLE facture_ligne ADD offerte TINYINT(1) NOT NULL DEFAULT 0 AFTER prix_centimes');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE facture_ligne DROP COLUMN offerte');
        $this->addSql('ALTER TABLE ligne DROP COLUMN offerte');
    }
}
