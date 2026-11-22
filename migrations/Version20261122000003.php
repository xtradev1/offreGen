<?php

declare(strict_types=1);

namespace OffreGen\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Conditions et textes propres à l'offre (chantier 2026-11-21_lignes-conditions, décisions D5 A, D6 A et D16 A de
 * Xavier) : validité, paiement et escompte en JSON sur l'offre (Compte\Conditions), NULL pour suivre le compte ; texte
 * avant et texte après les lignes. L'escompte du compte, qui remplit chaque offre.
 *
 * Additive (docs/DEPLOIEMENT.md, Migrations) : NULL ou 0 par défaut, l'ancienne version l'ignore.
 */
final class Version20261122000003 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Conditions et textes de l\'offre, escompte du compte';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE offre ADD conditions TEXT NULL, ADD texte_avant TEXT NULL, ADD texte_apres TEXT NULL');
        $this->addSql('ALTER TABLE artisan ADD escompte_pour_dix_mille SMALLINT UNSIGNED NOT NULL DEFAULT 0, ADD escompte_jours SMALLINT UNSIGNED NOT NULL DEFAULT 0');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE artisan DROP COLUMN escompte_jours, DROP COLUMN escompte_pour_dix_mille');
        $this->addSql('ALTER TABLE offre DROP COLUMN texte_apres, DROP COLUMN texte_avant, DROP COLUMN conditions');
    }
}
