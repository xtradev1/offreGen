<?php

declare(strict_types=1);

namespace OffreGen\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Courriels du 2026-11-24 (demande de Xavier) :
 * - artisan.courriel_dictee_echec : l'artisan reçoit ou non le courriel « dictée en échec », oui par défaut ;
 * - artisan.demo_rappel_2j_le, artisan.demo_rappel_12h_le : rappels de fin de démo déjà envoyés, pour n'en envoyer
 *   chacun qu'une fois.
 *
 * Additive (docs/DEPLOIEMENT.md, Migrations) : colonnes avec une valeur par défaut, l'ancienne version les ignore.
 */
final class Version20261124100001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Courriels : réglage « dictée en échec », rappels de fin de démo';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE artisan ADD courriel_dictee_echec TINYINT(1) NOT NULL DEFAULT 1');
        $this->addSql('ALTER TABLE artisan ADD demo_rappel_2j_le DATETIME NULL DEFAULT NULL');
        $this->addSql('ALTER TABLE artisan ADD demo_rappel_12h_le DATETIME NULL DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE artisan DROP COLUMN demo_rappel_12h_le');
        $this->addSql('ALTER TABLE artisan DROP COLUMN demo_rappel_2j_le');
        $this->addSql('ALTER TABLE artisan DROP COLUMN courriel_dictee_echec');
    }
}
