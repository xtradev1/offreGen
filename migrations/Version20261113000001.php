<?php

declare(strict_types=1);

namespace OffreGen\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Factures (sous-chantier 18, réponses de Xavier du 2026-11-13). Une facture naît d'une offre : ses lignes y sont
 * copiées (catégorie comprise, pour qu'un produit supprimé ou rangé ailleurs ne la change pas), puis corrigées en
 * brouillon. À l'émission, tout ce qui fait le montant est figé dans `facture` et le PDF est gardé dans
 * `facture_pdf` : on le sert tel quel ensuite. Un numéro est unique chez l'artisan, même annulé.
 * L'IBAN de l'artisan sert à la section de paiement : 21 caractères, IBAN suisse ou liechtensteinois.
 */
final class Version20261113000001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Factures, leurs lignes, leur PDF émis ; IBAN de l\'artisan';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE artisan ADD iban VARCHAR(21) NULL AFTER ide');
        $this->addSql(<<<'SQL'
            CREATE TABLE facture (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                artisan_id INT UNSIGNED NOT NULL,
                offre_id INT UNSIGNED NOT NULL,
                numero VARCHAR(20) NOT NULL,
                genre VARCHAR(10) NOT NULL,
                acompte_rang TINYINT UNSIGNED NULL,
                acompte_nombre TINYINT UNSIGNED NULL,
                acompte_pourcent TINYINT UNSIGNED NULL,
                statut VARCHAR(10) NOT NULL DEFAULT 'brouillon',
                date_facture DATE NOT NULL,
                echeance DATE NOT NULL,
                titre VARCHAR(190) NOT NULL,
                client_nom VARCHAR(190) NOT NULL,
                client_adresse VARCHAR(255) NULL,
                tva_pour_dix_mille INT NULL,
                arrondi_cinq TINYINT(1) NULL,
                iban VARCHAR(21) NULL,
                reference VARCHAR(27) NULL,
                ht INT NULL,
                tva INT NULL,
                arrondi INT NULL,
                ttc INT NULL,
                deja_facture INT NULL,
                a_payer INT NULL,
                cree_le DATETIME NOT NULL,
                modifiee_le DATETIME NULL,
                emise_le DATETIME NULL,
                annulee_le DATETIME NULL,
                refaite_depuis INT UNSIGNED NULL,
                UNIQUE KEY ux_facture_numero (artisan_id, numero),
                KEY ix_facture_offre (artisan_id, offre_id),
                CONSTRAINT fk_facture_artisan FOREIGN KEY (artisan_id) REFERENCES artisan (id),
                CONSTRAINT fk_facture_offre FOREIGN KEY (offre_id) REFERENCES offre (id),
                CONSTRAINT fk_facture_refaite FOREIGN KEY (refaite_depuis) REFERENCES facture (id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE facture_ligne (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                artisan_id INT UNSIGNED NOT NULL,
                facture_id INT UNSIGNED NOT NULL,
                rang INT UNSIGNED NOT NULL,
                designation VARCHAR(255) NOT NULL,
                quantite_milliemes INT NOT NULL,
                unite VARCHAR(20) NOT NULL,
                prix_centimes INT NOT NULL,
                categorie VARCHAR(100) NULL,
                produit_id INT UNSIGNED NULL,
                supprimee_le DATETIME NULL,
                KEY ix_facture_ligne (facture_id, rang),
                CONSTRAINT fk_facture_ligne_artisan FOREIGN KEY (artisan_id) REFERENCES artisan (id),
                CONSTRAINT fk_facture_ligne_facture FOREIGN KEY (facture_id) REFERENCES facture (id) ON DELETE CASCADE,
                CONSTRAINT fk_facture_ligne_produit FOREIGN KEY (produit_id) REFERENCES produit (id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE facture_pdf (
                facture_id INT UNSIGNED PRIMARY KEY,
                artisan_id INT UNSIGNED NOT NULL,
                contenu MEDIUMBLOB NOT NULL,
                cree_le DATETIME NOT NULL,
                CONSTRAINT fk_facture_pdf_artisan FOREIGN KEY (artisan_id) REFERENCES artisan (id),
                CONSTRAINT fk_facture_pdf_facture FOREIGN KEY (facture_id) REFERENCES facture (id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE facture_pdf');
        $this->addSql('DROP TABLE facture_ligne');
        $this->addSql('DROP TABLE facture');
        $this->addSql('ALTER TABLE artisan DROP iban');
    }
}
