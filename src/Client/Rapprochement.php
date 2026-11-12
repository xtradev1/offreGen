<?php

declare(strict_types=1);

namespace OffreGen\Client;

/**
 * Retrouve la fiche d'un client à partir du nom dicté. Règle de Xavier (2026-11-12) : « à plus de 80 % on
 * valide, sinon on crée un nouveau », et la fiche nouvelle ne naît que lorsque l'artisan la confirme.
 *
 * Les noms se comparent sans majuscules, sans accents et sans civilité (« Madame Rossi » = « Mme Rossi »).
 * Deux civilités contraires (madame, monsieur) désignent deux personnes : la ressemblance plafonne à 0,5.
 * Le seuil est strict, et deux fiches à égalité ne rattachent rien : mieux vaut laisser choisir l'artisan
 * que ranger une offre chez le mauvais client.
 */
final class Rapprochement
{
    public const SEUIL = 0.8;

    /** Civilités en tête du nom : f, h, ou neutre (famille, liaison « et »). */
    private const CIVILITES = [
        'madame' => 'f', 'mme' => 'f', 'mademoiselle' => 'f', 'mlle' => 'f',
        'monsieur' => 'h', 'm' => 'h', 'mr' => 'h',
        'famille' => '', 'fam' => '', 'et' => '',
    ];

    /** Le nom sans sa civilité de tête, pour ranger la liste : « Mme Rossi » se range à R. */
    public static function cleDeTri(string $nom): string
    {
        return (string) preg_replace('/^(?:(?:madame|mme|mademoiselle|mlle|monsieur|mr?|famille|fam|et)\.?\s+)+/iu', '', trim($nom));
    }

    /** Ressemblance de deux noms, de 0 (rien à voir) à 1 (le même nom). Symétrique. */
    public static function ressemblance(string $a, string $b): float
    {
        [$genreA, $motsA] = self::decomposer($a);
        [$genreB, $motsB] = self::decomposer($b);
        if ($motsA === [] || $motsB === []) {
            return 0.0;
        }
        $score = max(self::proximite(implode(' ', $motsA), implode(' ', $motsB)), self::proximite(self::trie($motsA), self::trie($motsB)));
        // Un nom contenu tout entier dans l'autre : « Rossi » pour « Maria Rossi ».
        [$court, $long] = count($motsA) <= count($motsB) ? [$motsA, $motsB] : [$motsB, $motsA];
        if (array_diff($court, $long) === []) {
            $score = max($score, 0.9);
        }
        if ($genreA !== '' && $genreB !== '' && $genreA !== $genreB) {
            $score = min($score, 0.5);
        }
        return round($score, 4);
    }

    /**
     * La fiche à rattacher d'office : plus de 80 % de ressemblance, et seule en tête.
     *
     * @param list<array{id: int, nom: string}> $clients
     */
    public static function meilleur(string $nom, array $clients): ?int
    {
        $meilleur = null;
        $score = 0.0;
        $egalite = false;
        foreach ($clients as $c) {
            $s = self::ressemblance($nom, $c['nom']);
            if ($s > $score) {
                [$meilleur, $score, $egalite] = [$c['id'], $s, false];
            } elseif ($s === $score && $s > 0.0) {
                $egalite = true;
            }
        }
        return $meilleur !== null && $score > self::SEUIL && !$egalite ? $meilleur : null;
    }

    /**
     * Minuscules sans accents, mots séparés, civilités de tête retirées.
     *
     * @return array{string, list<string>} genre (f, h ou vide) et mots du nom
     */
    private static function decomposer(string $nom): array
    {
        static $ascii = null;
        $ascii ??= \Transliterator::create('Any-Latin; Latin-ASCII; Lower()');
        $plat = $ascii instanceof \Transliterator ? (string) $ascii->transliterate($nom) : mb_strtolower($nom);
        $mots = preg_split('/[^a-z0-9]+/', $plat, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $genres = [];
        while ($mots !== [] && array_key_exists($mots[0], self::CIVILITES)) {
            $genres[self::CIVILITES[array_shift($mots)]] = true;
        }
        unset($genres['']);
        // « Monsieur et Madame Rossi » : un couple, aucune civilité ne s'oppose.
        $genre = count($genres) === 1 ? (string) array_key_first($genres) : '';
        return [$genre, $mots];
    }

    private static function proximite(string $a, string $b): float
    {
        $longueur = max(strlen($a), strlen($b));
        return $longueur === 0 ? 0.0 : 1 - levenshtein($a, $b) / $longueur;
    }

    /** @param list<string> $mots */
    private static function trie(array $mots): string
    {
        sort($mots);
        return implode(' ', $mots);
    }
}
