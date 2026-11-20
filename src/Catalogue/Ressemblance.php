<?php

declare(strict_types=1);

namespace OffreGen\Catalogue;

/**
 * Ressemblance de deux libellés par leurs mots utiles : mots communs sur mots distincts, de 0 à 1. Ne décide rien
 * seule : « Dispersion sur murs » et « Peinture murs dispersion » (même travail), « El60 » et « El120 » (deux
 * produits) ont la même note, 0,80 (banc du 2026-11-20). Elle choisit les paires à soumettre au comparateur.
 */
final class Ressemblance
{
    private const VIDES = ['de', 'du', 'des', 'la', 'le', 'les', 'et', 'sur', 'a', 'en', 'y', 'compri', 'd', 'l', 'avec', 'pour', 'un', 'une', 'x', 'au', 'aux'];

    /** @return list<string> */
    public static function mots(string $libelle): array
    {
        $mots = preg_split('/[^\p{L}\p{N}]+/u', Recherche::normaliser($libelle), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        // Pluriel ôté : « murs » et « mur », « travaux » et « travau ».
        $mots = array_map(static fn (string $m): string => strlen($m) > 3 ? rtrim($m, 'sx') : $m, $mots);
        return array_values(array_unique(array_diff($mots, self::VIDES)));
    }

    public static function score(string $a, string $b): float
    {
        [$x, $y] = [self::mots($a), self::mots($b)];
        $tous = count(array_unique(array_merge($x, $y)));
        return $tous === 0 ? 0.0 : count(array_intersect($x, $y)) / $tous;
    }
}
