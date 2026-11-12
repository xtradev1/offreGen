<?php

declare(strict_types=1);

namespace OffreGen\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Marque de l'artisan sur ses documents (sous-chantier 16, choix de Xavier du 2026-11-12) : logo, couleur,
 * modèle. Additive : colonnes nulles ou avec valeur par défaut.
 */
final class Version20261112000006 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Logo, couleur et modèle de document de l\'artisan';
    }

    public function up(Schema $schema): void
    {
        // couleur_logo : la couleur trouvée dans le logo, proposée en première pastille même après un autre choix.
        $this->addSql("ALTER TABLE artisan ADD logo_fichier VARCHAR(255) NULL, ADD couleur_logo CHAR(7) NULL, ADD couleur CHAR(7) NULL, ADD modele VARCHAR(12) NOT NULL DEFAULT 'sobre'");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE artisan DROP COLUMN logo_fichier, DROP COLUMN couleur_logo, DROP COLUMN couleur, DROP COLUMN modele');
    }
}
