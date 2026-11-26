<?php

declare(strict_types=1);

namespace OffreGen\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Clients (sous-chantier 17, règle de Xavier du 2026-11-12) : une fiche par client de l'artisan, et l'offre la
 * désigne. Le nom et le lieu dictés restent sur l'offre (client_dicte, lieu_dicte) : c'est une proposition, qui
 * ne devient une fiche que lorsque l'artisan la confirme. Aucune fiche n'est donc créée ici. La clé étrangère
 * refuse de supprimer une fiche encore désignée : le code ne supprime que la fiche d'un client sans offre.
 */
final class Version20261112000008 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Table client ; l\'offre désigne son client et garde le nom dicté';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE client (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                artisan_id INT UNSIGNED NOT NULL,
                nom VARCHAR(190) NOT NULL,
                adresse VARCHAR(255) NULL,
                telephone VARCHAR(40) NULL,
                courriel VARCHAR(190) NULL,
                cree_le DATETIME NOT NULL,
                modifie_le DATETIME NULL,
                KEY ix_client_artisan_nom (artisan_id, nom),
                CONSTRAINT fk_client_artisan FOREIGN KEY (artisan_id) REFERENCES artisan (id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE offre
                ADD client_id INT UNSIGNED NULL AFTER titre,
                ADD CONSTRAINT fk_offre_client FOREIGN KEY (client_id) REFERENCES client (id),
                CHANGE client_nom client_dicte VARCHAR(190) NULL,
                CHANGE client_adresse lieu_dicte VARCHAR(255) NULL
            SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('UPDATE offre o JOIN client c ON c.id = o.client_id SET o.client_dicte = c.nom, o.lieu_dicte = c.adresse');
        $this->addSql('ALTER TABLE offre DROP FOREIGN KEY fk_offre_client');
        $this->addSql('ALTER TABLE offre DROP COLUMN client_id, CHANGE client_dicte client_nom VARCHAR(190) NULL, CHANGE lieu_dicte client_adresse VARCHAR(255) NULL');
        $this->addSql('DROP TABLE client');
    }
}
