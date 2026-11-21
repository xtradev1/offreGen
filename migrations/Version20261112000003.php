<?php

declare(strict_types=1);

namespace OffreGen\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Sous-chantier 12 : de quoi générer le document de l'offre. Coordonnées de l'entreprise et conditions
 * (réglages), client de l'offre, numéro d'offre propre à chaque artisan, date de la dernière modification.
 * Additive : colonnes nulles ou avec valeur par défaut, l'ancienne version du code tourne encore.
 */
final class Version20261112000003 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Coordonnées et conditions de l\'artisan, client, numéro et date de modification de l\'offre';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            ALTER TABLE artisan
                ADD adresse VARCHAR(255) NULL AFTER entreprise,
                ADD telephone VARCHAR(40) NULL AFTER adresse,
                ADD courriel_contact VARCHAR(190) NULL AFTER telephone,
                ADD ide VARCHAR(40) NULL AFTER courriel_contact,
                ADD validite_jours INT UNSIGNED NOT NULL DEFAULT 30 AFTER arrondi_cinq_centimes,
                ADD conditions_paiement VARCHAR(255) NOT NULL DEFAULT 'À 30 jours dès la fin des travaux.' AFTER validite_jours
            SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE offre
                ADD numero INT UNSIGNED NULL AFTER artisan_id,
                ADD client_nom VARCHAR(190) NULL AFTER titre,
                ADD client_adresse VARCHAR(255) NULL AFTER client_nom,
                ADD modifiee_le DATETIME NULL AFTER cree_le
            SQL);
        // Les offres existantes reçoivent leur numéro dans l'ordre de création, artisan par artisan.
        $this->addSql(<<<'SQL'
            UPDATE offre o JOIN (
                SELECT id, ROW_NUMBER() OVER (PARTITION BY artisan_id ORDER BY cree_le, id) AS n FROM offre
            ) x ON x.id = o.id SET o.numero = x.n
            SQL);
        $this->addSql('ALTER TABLE offre ADD UNIQUE KEY uq_offre_numero (artisan_id, numero)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE offre DROP INDEX uq_offre_numero, DROP numero, DROP client_nom, DROP client_adresse, DROP modifiee_le');
        $this->addSql('ALTER TABLE artisan DROP adresse, DROP telephone, DROP courriel_contact, DROP ide, DROP validite_jours, DROP conditions_paiement');
    }
}
