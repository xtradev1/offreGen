<?php

declare(strict_types=1);

namespace OffreGen\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Options et variantes (chantier 2026-11-22_options-variantes, décisions O1 à O5 de Xavier) : chaque ligne d'offre a une
 * nature (« ligne » dans le total, « option », « variante ») ; une variante désigne la ligne qu'elle remplace ; une option
 * ou une variante retenue par le client entre dans le total. Une facture ne reçoit que des lignes du total : rien sur
 * `facture_ligne`.
 *
 * Pas de clé étrangère de `remplace_id` vers `ligne` : supprimer une offre efface ses lignes en cascade, et une clé de la
 * table vers elle-même dans cette cascade risque le refus d'InnoDB. DepotOffres::changerNature vérifie que la ligne
 * remplacée est de la même offre et du même artisan ; une variante dont la base a disparu se lit comme une option.
 *
 * Additive (docs/DEPLOIEMENT.md, Migrations) : « ligne », non retenue, par défaut ; l'ancienne version l'ignore.
 */
final class Version20261122200001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Options et variantes des lignes d\'offre';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE ligne ADD nature VARCHAR(10) NOT NULL DEFAULT 'ligne' AFTER offerte,
            ADD remplace_id INT UNSIGNED NULL AFTER nature, ADD retenue TINYINT(1) NOT NULL DEFAULT 0 AFTER remplace_id");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE ligne DROP COLUMN retenue, DROP COLUMN remplace_id, DROP COLUMN nature');
    }
}
