<?php

declare(strict_types=1);

namespace OffreGen\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * « Représenté par » (chantier 2026-11-21_lignes-conditions, décisions D8 A, D9 C et D10 C de Xavier) : la fiche de la
 * régie, de la gérance ou de l'architecte qui représente le client ; à qui l'offre est adressée (le représentant par
 * défaut) ; les factures au client chez lui ou « p.a. » le représentant. La facture garde le nom du représentant.
 *
 * Pas de clé étrangère sur representant_id : la fiche supprimée remet la colonne à NULL (DepotClients::supprimer), et
 * la jointure de lecture ne prend que les fiches de l'artisan.
 *
 * Additive (docs/DEPLOIEMENT.md, Migrations) : NULL ou une valeur par défaut, l'ancienne version l'ignore.
 */
final class Version20261122000004 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Représentant du client sur l\'offre et sur la facture';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE offre ADD representant_id INT UNSIGNED NULL AFTER client_id,
            ADD offre_adressee VARCHAR(12) NOT NULL DEFAULT 'representant', ADD factures_adressees VARCHAR(12) NOT NULL DEFAULT 'pa'");
        $this->addSql('ALTER TABLE facture ADD representant_nom VARCHAR(190) NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE facture DROP COLUMN representant_nom');
        $this->addSql('ALTER TABLE offre DROP COLUMN factures_adressees, DROP COLUMN offre_adressee, DROP COLUMN representant_id');
    }
}
