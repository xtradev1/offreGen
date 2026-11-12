<?php

declare(strict_types=1);

namespace OffreGen\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Conditions de paiement choisies en deux touchers (retour de Xavier du 2026-11-12) : le type (à N jours,
 * net à N jours, acomptes) et son détail. La phrase du document est fabriquée par le code à partir de ces
 * champs ; le texte libre conditions_paiement disparaît. Aucune production n'existe encore : les comptes
 * de développement repartent sur « à 30 jours », la valeur par défaut d'hier.
 */
final class Version20261112000004 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Conditions de paiement structurées : type, nombre de jours, acomptes';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            ALTER TABLE artisan
                ADD paiement_type VARCHAR(10) NOT NULL DEFAULT 'jours' AFTER validite_jours,
                ADD paiement_jours SMALLINT UNSIGNED NOT NULL DEFAULT 30 AFTER paiement_type,
                ADD paiement_acomptes VARCHAR(20) NULL AFTER paiement_jours,
                DROP COLUMN conditions_paiement
            SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            ALTER TABLE artisan
                ADD conditions_paiement VARCHAR(255) NOT NULL DEFAULT 'À 30 jours dès la fin des travaux.' AFTER validite_jours,
                DROP COLUMN paiement_type,
                DROP COLUMN paiement_jours,
                DROP COLUMN paiement_acomptes
            SQL);
    }
}
