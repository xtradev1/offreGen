<?php

declare(strict_types=1);

namespace OffreGen\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Liste des offres (sous-chantier 21, réponses de Xavier du 2026-11-12) : une offre s'archive ou se
 * supprime sans quitter le compte du mois ; un échec se retire de la liste. Additive : colonnes nulles.
 */
final class Version20261112000005 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Offre archivée ou supprimée, échec retiré';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE offre ADD archivee_le DATETIME NULL AFTER modifiee_le, ADD supprimee_le DATETIME NULL AFTER archivee_le');
        $this->addSql('ALTER TABLE enregistrement ADD retire_le DATETIME NULL AFTER pret_le');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE offre DROP COLUMN archivee_le, DROP COLUMN supprimee_le');
        $this->addSql('ALTER TABLE enregistrement DROP COLUMN retire_le');
    }
}
