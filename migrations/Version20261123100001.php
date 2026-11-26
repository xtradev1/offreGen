<?php

declare(strict_types=1);

namespace OffreGen\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Signature des courriels (maquette v8, M3 de Xavier du 2026-11-23) : le courriel qu'un prospect reçoit après une démo
 * est signé de la personne qui la lui a présentée. nom_affiche est ce nom, choisi par le membre de l'équipe ; sans lui,
 * le nom de son compte.
 *
 * Additive (docs/DEPLOIEMENT.md, Migrations) : une colonne avec une valeur par défaut, l'ancienne version l'ignore.
 */
final class Version20261123100001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Nom d\'un membre de l\'équipe dans les courriels';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE artisan ADD nom_affiche VARCHAR(120) NULL DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE artisan DROP COLUMN nom_affiche');
    }
}
