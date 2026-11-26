<?php

declare(strict_types=1);

namespace OffreGen\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Escompte sur chaque facture, acomptes compris (chantier 2026-11-21_lignes-conditions, décisions D13 A et D14 A de
 * Xavier) : copié des conditions de l'offre à la création, il reste celui de la facture si l'offre change ensuite.
 *
 * Additive (docs/DEPLOIEMENT.md, Migrations) : 0 par défaut, pas d'escompte.
 */
final class Version20261122000006 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Escompte de la facture';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE facture ADD escompte_pour_dix_mille SMALLINT UNSIGNED NOT NULL DEFAULT 0, ADD escompte_jours SMALLINT UNSIGNED NOT NULL DEFAULT 0');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE facture DROP COLUMN escompte_jours, DROP COLUMN escompte_pour_dix_mille');
    }
}
