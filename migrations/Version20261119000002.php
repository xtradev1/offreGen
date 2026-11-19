<?php

declare(strict_types=1);

namespace OffreGen\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Métiers de la démo et de l'essai (décisions de Xavier du 2026-11-19, docs/plans/2026-11-19_metiers-demo) :
 * - artisan.metier : clé du métier choisi (CatalogueFictif) ; artisan.metier_saisi : le texte tapé sous « autre » ;
 * - produit.prix_exemple_centimes : NULL pour un produit de l'artisan ; une valeur pour un produit d'exemple jamais
 *   modifié, son prix de démonstration. Sert de marque « exemple » et de prix proposé par l'écran « Vos prix » ;
 * - offre.exemple, client.exemple : posés par la démonstration, retirés au passage à l'essai.
 *
 * Additive (docs/DEPLOIEMENT.md, Migrations) : chaque colonne a une valeur par défaut, l'ancienne version les ignore.
 */
final class Version20261119000002 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Métier du compte, prix d\'exemple du produit, marque « exemple » des offres et des clients';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE artisan ADD metier VARCHAR(20) NULL DEFAULT NULL, ADD metier_saisi VARCHAR(60) NULL DEFAULT NULL');
        $this->addSql('ALTER TABLE produit ADD prix_exemple_centimes INT UNSIGNED NULL DEFAULT NULL');
        $this->addSql('ALTER TABLE offre ADD exemple TINYINT(1) NOT NULL DEFAULT 0');
        $this->addSql('ALTER TABLE client ADD exemple TINYINT(1) NOT NULL DEFAULT 0');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE client DROP COLUMN exemple');
        $this->addSql('ALTER TABLE offre DROP COLUMN exemple');
        $this->addSql('ALTER TABLE produit DROP COLUMN prix_exemple_centimes');
        $this->addSql('ALTER TABLE artisan DROP COLUMN metier_saisi, DROP COLUMN metier');
    }
}
