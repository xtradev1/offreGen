<?php

declare(strict_types=1);

namespace OffreGen\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Prix dicté (Xavier, 2026-11-17, dictée de Luigi) : le prix du catalogue reste celui de la ligne ; quand l'artisan a
 * dicté un autre prix pour ce produit, il est gardé ici, montré dans une note avec « Prendre ». NULL : rien à montrer.
 *
 * Additive (docs/DEPLOIEMENT.md, Migrations) : nouvelle colonne nullable ; l'ancienne version l'ignore.
 */
final class Version20261117000002 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Prix dicté d\'une ligne, à côté du prix du catalogue';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE ligne ADD prix_dicte_centimes INT NULL DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE ligne DROP COLUMN prix_dicte_centimes');
    }
}
