<?php

declare(strict_types=1);

namespace OffreGen\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Les phrases du document réglées par l'artisan (chantier des modèles, réponses de Xavier du 2026-11-23 à
 * MAQUETTE-lettre-phrases-auto.html) :
 * - artisan.phrases_document : les phrases tirées de l'offre que l'artisan a réécrites, en JSON par clé
 *   (Compte\PhrasesLettre::DOCUMENT) ; vide, celles de l'application ;
 * - artisan.lettre_partout : 1 pour la formule d'appel, le début et la fin dans les douze modèles, 0 pour Lettre seul.
 *
 * Additive (docs/DEPLOIEMENT.md, Migrations) : deux colonnes, facultative et à valeur par défaut ; l'ancienne version
 * les ignore.
 */
final class Version20261123210001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Phrases du document sur le compte';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            ALTER TABLE artisan
                ADD phrases_document TEXT NULL DEFAULT NULL,
                ADD lettre_partout TINYINT(1) NOT NULL DEFAULT 0
            SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE artisan DROP COLUMN lettre_partout, DROP COLUMN phrases_document');
    }
}
