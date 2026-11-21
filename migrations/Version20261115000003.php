<?php

declare(strict_types=1);

namespace OffreGen\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Numéro de facture donné à la création (sous-chantier 25, décision E5 de Xavier, 2026-11-15) : un brouillon qui sert
 * à corriger des lignes n'a pas encore de numéro ; abandonné, il ne laisse pas de trou dans la suite. L'index unique
 * (artisan_id, numero) laisse passer plusieurs NULL.
 *
 * Additive (docs/DEPLOIEMENT.md, Migrations) : la colonne accepte NULL en plus ; l'ancienne version écrit toujours un
 * numéro et lit les mêmes valeurs.
 */
final class Version20261115000003 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Numéro de facture facultatif tant que le brouillon n\'est pas créé';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE facture MODIFY numero VARCHAR(20) NULL');
    }

    public function down(Schema $schema): void
    {
        // Retour arrière : les brouillons sans numéro n'existaient pas avant ; ils sont effacés, leurs lignes avec eux.
        $this->addSql("DELETE FROM facture_ligne WHERE facture_id IN (SELECT id FROM facture WHERE numero IS NULL AND statut = 'brouillon')");
        $this->addSql("DELETE FROM facture WHERE numero IS NULL AND statut = 'brouillon'");
        $this->addSql('ALTER TABLE facture MODIFY numero VARCHAR(20) NOT NULL');
    }
}
