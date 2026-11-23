<?php

declare(strict_types=1);

namespace OffreGen\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * La facture garde les pièces de l'offre (Xavier, 2026-11-23, planche des modèles : « corrige maintenant ») : la pièce de
 * chaque ligne est copiée à la création, comme la catégorie, et la facture se range par pièce comme l'offre. Une ligne
 * ajoutée au brouillon, ou une facture d'avant cette migration, n'a pas de pièce : rangée par catégorie, comme avant.
 *
 * Additive (docs/DEPLOIEMENT.md, Migrations) : NULL par défaut ; l'ancienne version l'ignore.
 */
final class Version20261123200001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Pièce des lignes de facture';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE facture_ligne ADD piece VARCHAR(80) NULL AFTER categorie');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE facture_ligne DROP COLUMN piece');
    }
}
