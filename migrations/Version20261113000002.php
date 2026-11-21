<?php

declare(strict_types=1);

namespace OffreGen\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use OffreGen\Adresse\Adresse;

/**
 * Adresses en champs séparés (sous-chantier 18, Xavier le 2026-11-13 : « il faut absolument que ça corresponde au
 * standard ») : rue, numéro, NPA, localité, pays, aux longueurs de la QR-facture (type S).
 *
 * Additive (docs/DEPLOIEMENT.md, Migrations) : rien n'est renommé ni supprimé, l'ancienne version tourne sur cette
 * structure. Les colonnes `artisan.adresse` et `client.adresse` gardent le texte de l'adresse, rendu depuis les champs
 * par l'application ; quand le découpage échoue, elles gardent le texte d'avant, que l'écran montre à reporter.
 * La facture reçoit l'adresse du client en champs, copiée à sa création, pour la section de paiement.
 *
 * Le découpage est celui de l'application (Adresse::depuisTexte) au jour de la migration.
 */
final class Version20261113000002 extends AbstractMigration
{
    private const COLONNES = 'rue VARCHAR(70) NULL, numero VARCHAR(16) NULL, npa VARCHAR(16) NULL, localite VARCHAR(35) NULL, pays CHAR(2) NULL';

    public function getDescription(): string
    {
        return 'Adresses structurées : artisan, client, facture';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE artisan ' . self::ajouts('adresse_', 'adresse'));
        $this->addSql('ALTER TABLE client ' . self::ajouts('', 'adresse'));
        $this->addSql('ALTER TABLE facture ' . self::ajouts('client_', 'client_adresse'));

        // Lu maintenant, écrit après les ALTER (addSql s'exécute dans l'ordre, après up()).
        foreach (['artisan' => ['adresse_', 'adresse'], 'client' => ['', 'adresse'], 'facture' => ['client_', 'client_adresse']] as $table => [$prefixe, $texte]) {
            foreach ($this->connection->fetchAllAssociative("SELECT id, $texte AS texte FROM $table WHERE $texte IS NOT NULL") as $l) {
                $a = Adresse::depuisTexte((string) $l['texte']);
                if ($a === null) {
                    continue;
                }
                $sets = array_map(static fn (string $col): string => "$col = :$col", array_keys($a->versBase($prefixe)));
                // Le texte d'une facture reste celui de sa création ; celui d'un artisan ou d'un client prend sa forme rendue.
                $plus = $table === 'facture' ? [] : ["$texte = :rendu"];
                $this->addSql("UPDATE $table SET " . implode(', ', [...$sets, ...$plus]) . ' WHERE id = :id',
                    $a->versBase($prefixe) + ['id' => (int) $l['id']] + ($plus === [] ? [] : ['rendu' => $a->lignes()]));
            }
        }
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE artisan ' . self::retraits('adresse_'));
        $this->addSql('ALTER TABLE client ' . self::retraits(''));
        $this->addSql('ALTER TABLE facture ' . self::retraits('client_'));
    }

    private static function ajouts(string $prefixe, string $apres): string
    {
        $morceaux = [];
        foreach (explode(', ', self::COLONNES) as $col) {
            $morceaux[] = 'ADD ' . $prefixe . $col . ' AFTER ' . $apres;
            $apres = $prefixe . explode(' ', $col)[0];
        }
        return implode(', ', $morceaux);
    }

    private static function retraits(string $prefixe): string
    {
        return implode(', ', array_map(static fn (string $c): string => 'DROP ' . $prefixe . $c, ['rue', 'numero', 'npa', 'localite', 'pays']));
    }
}
