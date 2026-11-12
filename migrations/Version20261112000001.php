<?php

declare(strict_types=1);

namespace OffreGen\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Transcription et extraction réelles : depuis quand la transcription attend, et ce que le modèle
 * a entendu pour chaque ligne. Migration additive : aucune colonne retirée.
 */
final class Version20261112000001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'enregistrement.transcription_depuis, ligne.entendu';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE enregistrement ADD transcription_depuis DATETIME NULL AFTER transcription_ref');
        $this->addSql('ALTER TABLE ligne ADD entendu VARCHAR(500) NULL AFTER a_verifier');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE ligne DROP COLUMN entendu');
        $this->addSql('ALTER TABLE enregistrement DROP COLUMN transcription_depuis');
    }
}
