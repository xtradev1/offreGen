<?php

declare(strict_types=1);

namespace OffreGen\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Les six tables du sous-chantier 01. Montants en centimes, quantités en millièmes,
 * dates en UTC. Toute table métier porte artisan_id.
 */
final class Version20261107000001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Comptes, journal des connexions, enregistrements, offres, lignes, produits';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE artisan (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                courriel VARCHAR(190) NOT NULL,
                mot_de_passe VARCHAR(255) NOT NULL,
                entreprise VARCHAR(190) NOT NULL,
                demo TINYINT(1) NOT NULL DEFAULT 0,
                plafond_jour INT UNSIGNED NULL,
                cree_le DATETIME NOT NULL,
                UNIQUE KEY uq_artisan_courriel (courriel)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE journal_connexion (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                artisan_id INT UNSIGNED NULL,
                courriel_essaye VARCHAR(190) NOT NULL,
                ip VARCHAR(45) NOT NULL,
                reussi TINYINT(1) NOT NULL,
                quand DATETIME NOT NULL,
                KEY ix_journal_courriel (courriel_essaye, quand),
                KEY ix_journal_ip (ip, quand),
                CONSTRAINT fk_journal_artisan FOREIGN KEY (artisan_id) REFERENCES artisan (id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE enregistrement (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                artisan_id INT UNSIGNED NOT NULL,
                uuid CHAR(36) NOT NULL,
                nom_origine VARCHAR(255) NOT NULL,
                type_mime VARCHAR(100) NOT NULL,
                taille INT UNSIGNED NOT NULL,
                fichier VARCHAR(255) NULL,
                statut VARCHAR(20) NOT NULL,
                transcription_ref VARCHAR(100) NULL,
                transcription MEDIUMTEXT NULL,
                tentatives INT UNSIGNED NOT NULL DEFAULT 0,
                erreur TEXT NULL,
                verrou_jeton CHAR(32) NULL,
                verrou_jusqu_a DATETIME NULL,
                recu_le DATETIME NOT NULL,
                pret_le DATETIME NULL,
                supprimer_apres DATETIME NULL,
                UNIQUE KEY uq_enregistrement_uuid (uuid),
                KEY ix_enregistrement_artisan (artisan_id, recu_le),
                KEY ix_enregistrement_file (statut, verrou_jusqu_a),
                KEY ix_enregistrement_purge (supprimer_apres),
                CONSTRAINT fk_enregistrement_artisan FOREIGN KEY (artisan_id) REFERENCES artisan (id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE offre (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                artisan_id INT UNSIGNED NOT NULL,
                enregistrement_id INT UNSIGNED NULL,
                titre VARCHAR(190) NOT NULL,
                cree_le DATETIME NOT NULL,
                UNIQUE KEY uq_offre_enregistrement (enregistrement_id),
                KEY ix_offre_artisan (artisan_id, cree_le),
                CONSTRAINT fk_offre_artisan FOREIGN KEY (artisan_id) REFERENCES artisan (id),
                CONSTRAINT fk_offre_enregistrement FOREIGN KEY (enregistrement_id) REFERENCES enregistrement (id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE produit (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                artisan_id INT UNSIGNED NOT NULL,
                categorie VARCHAR(100) NOT NULL,
                designation VARCHAR(255) NOT NULL,
                unite VARCHAR(20) NOT NULL,
                prix_centimes INT NOT NULL,
                synonymes TEXT NULL,
                KEY ix_produit_artisan (artisan_id),
                CONSTRAINT fk_produit_artisan FOREIGN KEY (artisan_id) REFERENCES artisan (id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE ligne (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                artisan_id INT UNSIGNED NOT NULL,
                offre_id INT UNSIGNED NOT NULL,
                rang INT UNSIGNED NOT NULL,
                designation VARCHAR(255) NOT NULL,
                quantite_milliemes INT NOT NULL,
                unite VARCHAR(20) NOT NULL,
                prix_centimes INT NOT NULL,
                a_verifier TINYINT(1) NOT NULL DEFAULT 0,
                produit_id INT UNSIGNED NULL,
                KEY ix_ligne_offre (offre_id, rang),
                CONSTRAINT fk_ligne_artisan FOREIGN KEY (artisan_id) REFERENCES artisan (id),
                CONSTRAINT fk_ligne_offre FOREIGN KEY (offre_id) REFERENCES offre (id) ON DELETE CASCADE,
                CONSTRAINT fk_ligne_produit FOREIGN KEY (produit_id) REFERENCES produit (id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            SQL);
    }

    public function down(Schema $schema): void
    {
        foreach (['ligne', 'produit', 'offre', 'enregistrement', 'journal_connexion', 'artisan'] as $table) {
            $this->addSql("DROP TABLE $table");
        }
    }
}
