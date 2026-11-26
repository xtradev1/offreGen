<?php

declare(strict_types=1);

namespace OffreGen\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Prospects (docs/plans/2026-11-24_prospects/PLAN.md, lot 1) : le catalogue des artisans rencontrés, en amont de la
 * démo, et leurs notes datées.
 *
 * Additive (docs/DEPLOIEMENT.md, Migrations) : deux tables neuves, que l'ancienne version ignore. Aucune colonne ne
 * s'appelle artisan_id : la suppression d'une démo finie (DepotDemosFinies) détache la fiche par la clé étrangère
 * (SET NULL), elle ne l'efface pas.
 */
final class Version20261124210001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Prospects et leurs notes';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE prospect (
                id INT UNSIGNED AUTO_INCREMENT NOT NULL,
                entreprise VARCHAR(120) NOT NULL,
                nom_cle VARCHAR(140) NOT NULL,
                npa CHAR(4) NULL,
                localite VARCHAR(80) NOT NULL,
                metier VARCHAR(20) NULL,
                metier_saisi VARCHAR(60) NULL,
                telephone VARCHAR(32) NULL,
                telephone_cle VARCHAR(16) NULL,
                courriel VARCHAR(190) NULL,
                contact VARCHAR(120) NULL,
                origine VARCHAR(16) NULL,
                ide CHAR(15) NULL,
                etat VARCHAR(16) NOT NULL DEFAULT 'a_contacter',
                suivi_par INT UNSIGNED NULL,
                suivi_depuis DATETIME NULL,
                suivi_avant INT UNSIGNED NULL,
                libre_depuis DATETIME NULL,
                pas_texte VARCHAR(120) NULL,
                pas_le DATE NULL,
                derniere_action_le DATETIME NOT NULL,
                compte_id INT UNSIGNED NULL,
                cree_par INT UNSIGNED NULL,
                cree_le DATETIME NOT NULL,
                PRIMARY KEY (id),
                UNIQUE KEY uq_prospect_ide (ide),
                UNIQUE KEY uq_prospect_compte (compte_id),
                KEY ix_prospect_suivi (suivi_par, pas_le),
                KEY ix_prospect_nom (nom_cle),
                KEY ix_prospect_telephone (telephone_cle),
                KEY ix_prospect_courriel (courriel),
                CONSTRAINT fk_prospect_suivi_par FOREIGN KEY (suivi_par) REFERENCES artisan (id) ON DELETE SET NULL,
                CONSTRAINT fk_prospect_suivi_avant FOREIGN KEY (suivi_avant) REFERENCES artisan (id) ON DELETE SET NULL,
                CONSTRAINT fk_prospect_compte FOREIGN KEY (compte_id) REFERENCES artisan (id) ON DELETE SET NULL,
                CONSTRAINT fk_prospect_cree_par FOREIGN KEY (cree_par) REFERENCES artisan (id) ON DELETE SET NULL
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB
            SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE prospect_note (
                id INT UNSIGNED AUTO_INCREMENT NOT NULL,
                prospect_id INT UNSIGNED NOT NULL,
                auteur_id INT UNSIGNED NULL,
                texte VARCHAR(1000) NOT NULL,
                cree_le DATETIME NOT NULL,
                PRIMARY KEY (id),
                KEY ix_prospect_note_fiche (prospect_id, cree_le),
                CONSTRAINT fk_prospect_note_fiche FOREIGN KEY (prospect_id) REFERENCES prospect (id) ON DELETE CASCADE,
                CONSTRAINT fk_prospect_note_auteur FOREIGN KEY (auteur_id) REFERENCES artisan (id) ON DELETE SET NULL
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB
            SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE prospect_note');
        $this->addSql('DROP TABLE prospect');
    }
}
