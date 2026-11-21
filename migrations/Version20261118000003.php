<?php

declare(strict_types=1);

namespace OffreGen\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Mise en production, liens d'accès (décisions P1, P3, P4 et A3 de Xavier, 2026-11-18) : « mot de passe oublié »
 * et courriel d'accès d'un nouveau compte. Seule l'empreinte SHA-256 du jeton est gardée.
 * version_session : changer le mot de passe ferme les sessions des autres appareils (revue du 2026-11-18, M6).
 *
 * Additive (docs/DEPLOIEMENT.md, Migrations) : une table neuve, une colonne avec une valeur par défaut ; l'ancienne
 * version les ignore.
 */
final class Version20261118000003 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Liens d\'accès par courriel, numéro de session des comptes';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE jeton_acces (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                artisan_id INT UNSIGNED NOT NULL,
                empreinte CHAR(64) NOT NULL,
                motif VARCHAR(10) NOT NULL,
                cree_le DATETIME NOT NULL,
                expire_le DATETIME NOT NULL,
                utilise_le DATETIME NULL,
                UNIQUE KEY uq_jeton_empreinte (empreinte),
                KEY ix_jeton_artisan (artisan_id, motif, cree_le),
                CONSTRAINT fk_jeton_artisan FOREIGN KEY (artisan_id) REFERENCES artisan (id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            SQL);
        $this->addSql('ALTER TABLE artisan ADD version_session INT UNSIGNED NOT NULL DEFAULT 0');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE artisan DROP COLUMN version_session');
        $this->addSql('DROP TABLE jeton_acces');
    }
}
