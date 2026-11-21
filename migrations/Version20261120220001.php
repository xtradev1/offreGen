<?php

declare(strict_types=1);

namespace OffreGen\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Les phrases du modèle Lettre, réglées par l'artisan (chantier des modèles, réponses de Xavier du 2026-11-20 à
 * MAQUETTE-lettre-phrases.html) :
 * - artisan.lettre_appel : « client » pour la formule d'appel selon le nom du client, vide pour « Madame, Monsieur, » ;
 * - artisan.lettre_offre_debut, lettre_offre_fin, lettre_facture_debut, lettre_facture_fin : les phrases, vides pour
 *   garder celles d'avant (Compte\PhrasesLettre::ACTUELLES).
 *
 * Additive (docs/DEPLOIEMENT.md, Migrations) : cinq colonnes facultatives ; l'ancienne version les ignore.
 */
final class Version20261120220001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Phrases du modèle Lettre sur le compte';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            ALTER TABLE artisan
                ADD lettre_appel VARCHAR(8) NULL DEFAULT NULL,
                ADD lettre_offre_debut VARCHAR(500) NULL DEFAULT NULL,
                ADD lettre_offre_fin VARCHAR(500) NULL DEFAULT NULL,
                ADD lettre_facture_debut VARCHAR(500) NULL DEFAULT NULL,
                ADD lettre_facture_fin VARCHAR(500) NULL DEFAULT NULL
            SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE artisan DROP COLUMN lettre_facture_fin, DROP COLUMN lettre_facture_debut, DROP COLUMN lettre_offre_fin, DROP COLUMN lettre_offre_debut, DROP COLUMN lettre_appel');
    }
}
