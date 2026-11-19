<?php

declare(strict_types=1);

namespace OffreGen\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Écran Équipe refait (maquette v7, réponses de Xavier du 2026-11-19) :
 * - apporte_par : qui a amené l'artisan. Ne change jamais, même quand le suivi (cree_par) passe à quelqu'un
 *   d'autre au départ d'un commercial : « il faut juste regarder qui a amené les artisans car en fonction
 *   il y a une commission » ;
 * - acces_en_attente : 1 tant qu'un membre invité n'a pas choisi son mot de passe (L2 = A).
 *
 * Additive (docs/DEPLOIEMENT.md, Migrations) : colonnes avec une valeur par défaut, l'ancienne version les ignore.
 */
final class Version20261119100002 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Qui a amené un artisan, et accès d\'un membre encore à ouvrir';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            ALTER TABLE artisan
                ADD apporte_par INT UNSIGNED NULL DEFAULT NULL,
                ADD acces_en_attente TINYINT(1) NOT NULL DEFAULT 0,
                ADD KEY ix_artisan_apporte_par (apporte_par),
                ADD CONSTRAINT fk_artisan_apporte_par FOREIGN KEY (apporte_par) REFERENCES artisan (id) ON DELETE SET NULL
            SQL);
        // Les artisans déjà suivis par quelqu'un : c'est lui qui les a amenés.
        $this->addSql('UPDATE artisan SET apporte_par = cree_par WHERE cree_par IS NOT NULL AND administrateur = 0 AND role IS NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE artisan DROP FOREIGN KEY fk_artisan_apporte_par, DROP KEY ix_artisan_apporte_par, DROP apporte_par, DROP acces_en_attente');
    }
}
