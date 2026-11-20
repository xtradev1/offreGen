<?php

declare(strict_types=1);

namespace OffreGen\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Facture annulée (décision 1 C de Xavier, 2026-11-19, docs/plans/2026-11-19_responsive) :
 * - facture.envoyee_le : posée quand le partage du PDF aboutit, comme pour les offres ; une facture annulée déjà
 *   envoyée propose d'envoyer sa version annulée ;
 * - facture_pdf.contenu_annule : le PDF marqué « Annulée », sans section de paiement, fabriqué à l'annulation.
 *   Le PDF de l'émission reste dans « contenu ».
 *
 * Additive (docs/DEPLOIEMENT.md, Migrations) : colonnes nulles par défaut, l'ancienne version les ignore.
 */
final class Version20261119180001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return "Date d'envoi d'une facture, PDF de la facture annulée";
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE facture ADD envoyee_le DATETIME NULL DEFAULT NULL');
        $this->addSql('ALTER TABLE facture_pdf ADD contenu_annule MEDIUMBLOB NULL DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE facture_pdf DROP contenu_annule');
        $this->addSql('ALTER TABLE facture DROP envoyee_le');
    }
}
