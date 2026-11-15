<?php

declare(strict_types=1);

namespace OffreGen\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Date d'envoi d'une offre (sous-chantier 24, décision D8 de Xavier, 2026-11-15) : notée quand le partage du téléphone
 * a réussi. Additive (docs/DEPLOIEMENT.md, Migrations) : colonne nullable, l'ancienne version l'ignore.
 */
final class Version20261115000001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return "Date d'envoi d'une offre";
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE offre ADD envoyee_le DATETIME NULL AFTER modifiee_le');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE offre DROP envoyee_le');
    }
}
