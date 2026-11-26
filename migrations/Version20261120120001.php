<?php

declare(strict_types=1);

namespace OffreGen\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Modèles d'offre du bâtiment (décisions de Xavier des 19 et 20 novembre 2026, docs/plans/2026-11-19_modeles-offre) :
 * - offre.chantier_adresse, chantier_contact, chantier_acces : où se font les travaux, qui est sur place, comment on entre ;
 * - offre.debut_travaux, duree_travaux : texte libre (« semaine 3, 2027 », « 6 jours ouvrables ») ;
 * - offre.non_compris : ce que l'offre ne comprend pas, une entrée par ligne ;
 * - offre.visite_le : date de la visite, citée par le modèle Lettre ;
 * - artisan.contact_nom : la personne de contact, sur le document et sous la signature de l'entreprise.
 *
 * Additive (docs/DEPLOIEMENT.md, Migrations) : huit colonnes facultatives ; l'ancienne version les ignore.
 */
final class Version20261120120001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Chantier, début et durée des travaux, non compris, visite sur l\'offre ; contact sur le compte';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            ALTER TABLE offre
                ADD chantier_adresse VARCHAR(255) NULL DEFAULT NULL,
                ADD chantier_contact VARCHAR(190) NULL DEFAULT NULL,
                ADD chantier_acces VARCHAR(255) NULL DEFAULT NULL,
                ADD debut_travaux VARCHAR(80) NULL DEFAULT NULL,
                ADD duree_travaux VARCHAR(80) NULL DEFAULT NULL,
                ADD non_compris VARCHAR(1000) NULL DEFAULT NULL,
                ADD visite_le DATE NULL DEFAULT NULL
            SQL);
        $this->addSql('ALTER TABLE artisan ADD contact_nom VARCHAR(120) NULL DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE artisan DROP COLUMN contact_nom');
        $this->addSql('ALTER TABLE offre DROP COLUMN visite_le, DROP COLUMN non_compris, DROP COLUMN duree_travaux, DROP COLUMN debut_travaux, DROP COLUMN chantier_acces, DROP COLUMN chantier_contact, DROP COLUMN chantier_adresse');
    }
}
