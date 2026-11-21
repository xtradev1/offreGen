<?php

declare(strict_types=1);

namespace OffreGen\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Import du catalogue depuis d'anciennes offres (chantier 2026-11-20_import-catalogue).
 *
 * import_catalogue : un import par lot de PDF (cinq au plus) ; il compte dans la limite de trois par mois dès sa
 * création. Il garde ses propositions jusqu'à l'intégration, puis seulement le bilan et de quoi l'annuler.
 * import_ligne : les lignes chiffrées lues dans les offres (libellé, unité, prix), effacées à l'intégration. Le texte
 * des PDF, avec les noms et adresses des clients de l'artisan, n'est jamais gardé (décision D9 de Xavier).
 *
 * Additive (docs/DEPLOIEMENT.md, Migrations) : deux tables neuves, l'ancienne version les ignore.
 */
final class Version20261120210001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Import du catalogue depuis d\'anciennes offres en PDF';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE import_catalogue (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                artisan_id INT UNSIGNED NOT NULL,
                etat VARCHAR(12) NOT NULL DEFAULT 'lecture',
                offres SMALLINT UNSIGNED NOT NULL DEFAULT 0,
                lignes SMALLINT UNSIGNED NOT NULL DEFAULT 0,
                jetons_envoyes INT UNSIGNED NOT NULL DEFAULT 0,
                jetons_recus INT UNSIGNED NOT NULL DEFAULT 0,
                propositions MEDIUMTEXT NULL,
                bilan VARCHAR(255) NULL,
                annulation MEDIUMTEXT NULL,
                cree_le DATETIME NOT NULL,
                integre_le DATETIME NULL,
                KEY ix_import_artisan (artisan_id, cree_le),
                CONSTRAINT fk_import_artisan FOREIGN KEY (artisan_id) REFERENCES artisan (id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE import_ligne (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                import_id INT UNSIGNED NOT NULL,
                artisan_id INT UNSIGNED NOT NULL,
                offre TINYINT UNSIGNED NOT NULL,
                designation VARCHAR(255) NOT NULL,
                unite VARCHAR(20) NULL,
                unite_ecrite VARCHAR(20) NOT NULL,
                prix_centimes INT UNSIGNED NOT NULL,
                KEY ix_import_ligne_import (import_id),
                CONSTRAINT fk_import_ligne_import FOREIGN KEY (import_id) REFERENCES import_catalogue (id) ON DELETE CASCADE,
                CONSTRAINT fk_import_ligne_artisan FOREIGN KEY (artisan_id) REFERENCES artisan (id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE import_ligne');
        $this->addSql('DROP TABLE import_catalogue');
    }
}
