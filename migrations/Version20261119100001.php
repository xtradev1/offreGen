<?php

declare(strict_types=1);

namespace OffreGen\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Administration des comptes et des démos (décisions de Xavier du 2026-11-19, docs/plans/2026-11-19_admin-comptes) :
 * - role : « commercial » ou « moderateur » ; le droit administrateur reste dans sa colonne, donné par commande ;
 * - cree_par : qui a créé la démo ou le compte ; un commercial ne voit que les siens ;
 * - demo_fin : fin de la période active d'une démo ;
 * - offres_offertes : total des offres ajoutées depuis une fiche, pour la limite de chaque rôle ;
 * - demande_appel : « Demander un appel », côté artisan ;
 * - journal_admin : qui a fait quoi sur quel compte. Sans clé étrangère : le journal survit au compte.
 *
 * Additive (docs/DEPLOIEMENT.md, Migrations) : colonnes avec une valeur par défaut, tables neuves ; l'ancienne
 * version les ignore.
 */
final class Version20261119100001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Rôles de l\'équipe, créateur et fin de démo, demandes d\'appel, journal des gestes';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            ALTER TABLE artisan
                ADD role VARCHAR(16) NULL DEFAULT NULL,
                ADD cree_par INT UNSIGNED NULL DEFAULT NULL,
                ADD demo_fin DATETIME NULL DEFAULT NULL,
                ADD offres_offertes SMALLINT UNSIGNED NOT NULL DEFAULT 0,
                ADD KEY ix_artisan_cree_par (cree_par),
                ADD CONSTRAINT fk_artisan_cree_par FOREIGN KEY (cree_par) REFERENCES artisan (id) ON DELETE SET NULL
            SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE demande_appel (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                artisan_id INT UNSIGNED NOT NULL,
                telephone VARCHAR(32) NOT NULL,
                moment VARCHAR(8) NOT NULL,
                question VARCHAR(500) NULL,
                cree_le DATETIME NOT NULL,
                traite_le DATETIME NULL,
                traite_par INT UNSIGNED NULL,
                KEY ix_appel_attente (traite_le, cree_le),
                CONSTRAINT fk_appel_artisan FOREIGN KEY (artisan_id) REFERENCES artisan (id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE journal_admin (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                acteur_id INT UNSIGNED NOT NULL,
                artisan_id INT UNSIGNED NULL,
                geste VARCHAR(32) NOT NULL,
                detail VARCHAR(190) NULL,
                quand DATETIME NOT NULL,
                KEY ix_journal_artisan (artisan_id, quand),
                KEY ix_journal_acteur (acteur_id, quand)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE journal_admin');
        $this->addSql('DROP TABLE demande_appel');
        $this->addSql('ALTER TABLE artisan DROP FOREIGN KEY fk_artisan_cree_par, DROP KEY ix_artisan_cree_par, DROP COLUMN offres_offertes, DROP COLUMN demo_fin, DROP COLUMN cree_par, DROP COLUMN role');
    }
}
