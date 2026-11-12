<?php

declare(strict_types=1);

namespace OffreGen\Tests\Outils;

use OffreGen\Donnees\FabriqueConnexion;
use OffreGen\Http\Conteneur;

/**
 * Accès à la base de test. Refuse toute base dont le nom ne finit pas par _test :
 * les tests vident les tables, ils ne doivent jamais toucher la base de développement.
 */
final class BaseDeTest
{
    private static ?\PDO $pdo = null;

    /** @return array<string, string> */
    public static function env(): array
    {
        $env = Conteneur::lireEnvironnement(dirname(__DIR__, 2));
        $env['APP_ENV'] = 'test';
        // Une variable du shell ne doit pas changer le comportement d'un test.
        unset($env['TRAITEMENT_IMMEDIAT'], $env['AUDIO_DOSSIER'], $env['COURRIELS_DOSSIER'], $env['TRANSCRIPTION'], $env['EXTRACTION']);
        return $env;
    }

    public static function fabrique(): FabriqueConnexion
    {
        $fabrique = new FabriqueConnexion(self::env());
        if (!str_ends_with($fabrique->nomBase(), '_test')) {
            throw new \LogicException('Les tests refusent la base ' . $fabrique->nomBase() . ' : il faut une base *_test.');
        }
        return $fabrique;
    }

    public static function pdo(): \PDO
    {
        return self::$pdo ??= self::fabrique()->pdo();
    }

    public static function compter(string $table, string $condition = '1'): int
    {
        $requete = self::pdo()->query("SELECT COUNT(*) FROM $table WHERE $condition");
        if ($requete === false) {
            throw new \RuntimeException("Comptage impossible sur $table");
        }
        return (int) $requete->fetchColumn();
    }

    /** @return array<string, mixed> la première ligne ; échoue s'il n'y en a pas */
    public static function ligne(string $sql): array
    {
        $requete = self::pdo()->query($sql);
        $ligne = $requete === false ? false : $requete->fetch(\PDO::FETCH_ASSOC);
        if (!is_array($ligne)) {
            throw new \RuntimeException("Aucune ligne : $sql");
        }
        /** @var array<string, mixed> $ligne */
        return $ligne;
    }

    /** Vide les tables métier, dans l'ordre des clés étrangères. */
    public static function vider(): void
    {
        $pdo = self::pdo();
        foreach (['ligne', 'offre', 'client', 'produit', 'enregistrement', 'journal_connexion', 'artisan'] as $table) {
            $pdo->exec("DELETE FROM $table");
        }
    }
}
