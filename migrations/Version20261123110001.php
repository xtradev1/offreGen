<?php

declare(strict_types=1);

namespace OffreGen\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Courriel « offre prête » (M2 de Xavier, 2026-11-23 : « pas de spam sinon les gens ne lisent plus ») :
 * - artisan.courriel_offre_prete : l'artisan le reçoit ou non, oui par défaut ;
 * - enregistrement.suivi_le : dernier passage de l'écran d'attente. S'il est tout récent quand l'offre sort, l'artisan
 *   est devant son écran et l'application lui ouvre l'offre : aucun courriel ne part.
 *
 * Additive (docs/DEPLOIEMENT.md, Migrations) : colonnes avec une valeur par défaut, l'ancienne version les ignore.
 */
final class Version20261123110001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Courriel « offre prête » : réglage de l\'artisan, suivi de l\'écran d\'attente';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE artisan ADD courriel_offre_prete TINYINT(1) NOT NULL DEFAULT 1');
        $this->addSql('ALTER TABLE enregistrement ADD suivi_le DATETIME NULL DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE enregistrement DROP COLUMN suivi_le');
        $this->addSql('ALTER TABLE artisan DROP COLUMN courriel_offre_prete');
    }
}
