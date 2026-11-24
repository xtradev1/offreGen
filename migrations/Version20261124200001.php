<?php

declare(strict_types=1);

namespace OffreGen\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Dictée continuée « à la suite » (sous-chantier 27, recette AB-1 de Xavier, 2026-11-24) : les fichiers de la suite,
 * en liste JSON de chemins relatifs, et une référence de transcription par partie (cinq au plus, séparées d'un espace ;
 * 5 × 100 caractères permis par TranscripteurApi, plus les espaces : 504).
 *
 * Additive (docs/DEPLOIEMENT.md, Migrations) : colonne NULL par défaut, que l'ancienne version ignore ; la référence
 * est élargie (MODIFY, CODING_RULES), ce que l'ancienne version lit et écrit comme avant.
 */
final class Version20261124200001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Suites d\'une dictée continuée';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE enregistrement ADD fichiers_suite TEXT NULL AFTER fichier, MODIFY transcription_ref VARCHAR(512) NULL');
    }

    public function down(Schema $schema): void
    {
        // La référence reste élargie : des lignes en portent plusieurs, VARCHAR(100) les tronquerait.
        $this->addSql('ALTER TABLE enregistrement DROP COLUMN fichiers_suite');
    }
}
