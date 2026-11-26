<?php

declare(strict_types=1);

namespace OffreGen\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Onboarding du commercial et de l'artisan (chantier 2026-11-21_onboarding, PLAN.md, lot 1) :
 * - guide_artisan_le : le guide à anneau de l'artisan, fini ou quitté ; null, il se montre ;
 * - guide_equipe_le : la bienvenue d'un membre de l'équipe, vue ; null, elle se montre à son entrée dans /admin ;
 * - entrainement : la démo d'entraînement d'un membre, hors limite de dix démos par jour et sans « amené par ».
 *
 * Additive (docs/DEPLOIEMENT.md, Migrations) : colonnes nulles ou à 0 ; l'ancienne version les ignore. Les comptes
 * déjà ouverts ont fini le guide : seuls les comptes neufs le voient.
 */
final class Version20261122300001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return "Guides de l'artisan et de l'équipe, démo d'entraînement";
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE artisan ADD guide_artisan_le DATETIME NULL, ADD guide_equipe_le DATETIME NULL, ADD entrainement TINYINT(1) NOT NULL DEFAULT 0');
        $this->addSql('UPDATE artisan SET guide_artisan_le = NOW(), guide_equipe_le = NOW()');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE artisan DROP COLUMN entrainement, DROP COLUMN guide_equipe_le, DROP COLUMN guide_artisan_le');
    }
}
