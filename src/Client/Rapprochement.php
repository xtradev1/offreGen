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
     * La fiche à rattacher d'office : plus de 80 % de ressemblance, seule en tête, et dans la localité dictée
     * quand la dictée en nomme une.
     *
     * @param list<array{id: int, nom: string, npa?: string|null, localite?: string|null}> $clients
     */
    public static function meilleur(string $nom, array $clients, ?string $lieu = null): ?int
    {
        $meilleur = null;
        $score = 0.0;
        $egalite = false;
        foreach ($clients as $c) {
            if (!self::memeLocalite($lieu, $c['npa'] ?? null, $c['localite'] ?? null)) {
                continue;
            }
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
     * Le lieu dicté désigne-t-il la localité de la fiche ? Règle de Xavier (2026-11-14) : « si c'est pas la
     * même localité ça ne doit pas marcher ». Oui quand le lieu contient le NPA de la fiche, ou sa localité
     * en mots entiers (sans accents, article de tête ignoré : « au Locle » pour « Le Locle », « St » pour
     * « Saint »). Sans lieu dicté, ou sans localité ni NPA sur la fiche, rien ne se contredit : oui.
     * Conséquence voulue : un chantier dicté ailleurs que chez le client n'est pas rattaché d'office,
     * l'artisan choisit la fiche.
     */
    public static function memeLocalite(?string $lieu, ?string $npa, ?string $localite): bool
    {
        $dicte = self::mots((string) $lieu);
        $mots = self::mots((string) $localite);
        if (count($mots) > 1 && in_array($mots[0], ['le', 'la', 'les', 'l'], true)) {
            array_shift($mots);
        }
        $npa = trim((string) $npa);
        if ($dicte === [] || ($mots === [] && $npa === '')) {
            return true;
        }
        if ($npa !== '' && in_array($npa, $dicte, true)) {
            return true;
        }
        $n = count($mots);
        for ($i = 0; $n > 0 && $i + $n <= count($dicte); $i++) {
            if (array_slice($dicte, $i, $n) === $mots) {
                return true;
            }
        }
        return false;
    }

    /** @return list<string> mots en minuscules sans accents, « st » et « ste » écrits en entier */
    private static function mots(string $texte): array
    {
        $mots = preg_split('/[^a-z0-9]+/', self::plat($texte), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        return array_map(static fn (string $m): string => ['st' => 'saint', 'ste' => 'sainte'][$m] ?? $m, $mots);
    }

    private static function plat(string $texte): string
    {
        static $ascii = null;
        $ascii ??= \Transliterator::create('Any-Latin; Latin-ASCII; Lower()');
        return $ascii instanceof \Transliterator ? (string) $ascii->transliterate($texte) : mb_strtolower($texte);
    }

    /**
     * Minuscules sans accents, mots séparés, civilités de tête retirées.
     *
     * @return array{string, list<string>} genre (f, h ou vide) et mots du nom
     */
    private static function decomposer(string $nom): array
    {
        $mots = preg_split('/[^a-z0-9]+/', self::plat($nom), -1, PREG_SPLIT_NO_EMPTY) ?: [];
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
