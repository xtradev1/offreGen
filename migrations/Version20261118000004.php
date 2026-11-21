<?php

declare(strict_types=1);

namespace OffreGen\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Dictée à l'abri (sous-chantier 27), revue du 2026-11-18 : la clé que le téléphone donne à sa dictée. Une dictée
 * renvoyée parce que la réponse du premier envoi s'est perdue retrouve son enregistrement au lieu d'en créer un second.
 *
 * Additive (docs/DEPLOIEMENT.md, Migrations) : colonne nullable, index unique qui admet plusieurs NULL ; l'ancienne
 * version n'écrit jamais la colonne.
 */
final class Version20261118000004 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Clé de la dictée donnée par le téléphone';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE enregistrement ADD cle_client VARCHAR(40) NULL DEFAULT NULL, ADD UNIQUE KEY uq_enregistrement_cle (artisan_id, cle_client)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE enregistrement DROP INDEX uq_enregistrement_cle, DROP COLUMN cle_client');
    }
}
