<?php

declare(strict_types=1);

namespace OffreGen\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Les gestes reçus avec leur clé (chantier sans rechargement, lot 4, 2026-11-23). Un geste fait sans réseau attend dans
 * le téléphone et repart au retour du réseau, parfois deux fois (page quittée pendant l'envoi) : sa clé fait que le
 * serveur ne le fait qu'une fois. adresse : la page où menait l'action, vide tant qu'elle tourne ; un envoi rejoué reçoit
 * les morceaux de cette page. Les clés de plus de 30 jours partent au fil des envois.
 *
 * Additive (docs/DEPLOIEMENT.md, Migrations) : une table neuve ; l'ancienne version l'ignore.
 */
final class Version20261123230001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Clés des gestes reçus (envoi rejoué fait une seule fois)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE envoi (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                artisan_id INT UNSIGNED NOT NULL,
                cle VARCHAR(64) NOT NULL,
                adresse VARCHAR(500) NOT NULL DEFAULT '',
                recu_le DATETIME NOT NULL,
                UNIQUE KEY ux_envoi_cle (artisan_id, cle),
                KEY ix_envoi_recu (artisan_id, recu_le),
                CONSTRAINT fk_envoi_artisan FOREIGN KEY (artisan_id) REFERENCES artisan (id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE envoi');
    }
}
