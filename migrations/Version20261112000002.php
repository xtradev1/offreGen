<?php

declare(strict_types=1);

namespace OffreGen\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Sous-chantier 11 : réglages de calcul du compte, et ligne supprimée que « Annuler » peut remettre.
 * Colonnes ajoutées avec une valeur par défaut : l'ancienne version du code continue de tourner.
 */
final class Version20261112000002 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Réglages TVA et arrondi du compte, suppression annulable des lignes';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE artisan ADD tva_pour_dix_mille INT UNSIGNED NOT NULL DEFAULT 810 AFTER plafond_jour, ADD arrondi_cinq_centimes TINYINT(1) NOT NULL DEFAULT 1 AFTER tva_pour_dix_mille');
        $this->addSql('ALTER TABLE ligne ADD supprimee_le DATETIME NULL AFTER produit_id');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE ligne DROP supprimee_le');
        $this->addSql('ALTER TABLE artisan DROP arrondi_cinq_centimes, DROP tva_pour_dix_mille');
    }
}
