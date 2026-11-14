<?php

declare(strict_types=1);

namespace OffreGen\Adresse;

/**
 * Le répertoire officiel des localités de swisstopo (Suisse et Liechtenstein), copié dans localites.php par
 * outils/localites.php : aucune donnée ne sort de l'application pour contrôler un NPA.
 */
final class Localites
{
    /** @var array<string, list<string>>|null */
    private static ?array $parNpa = null;

    /** @return list<string> les localités officielles du NPA, vide s'il n'existe pas */
    public static function de(string $npa): array
    {
        return self::donnees()[$npa] ?? [];
    }

    /**
     * La localité à proposer d'office : la seule du NPA, ou leur nom commun quand elles ne diffèrent que par un
     * numéro (1000 : « Lausanne 25 », « Lausanne 26 »… donne « Lausanne ») ; null s'il faut choisir.
     */
    public static function seule(string $npa): ?string
    {
        $noms = self::de($npa);
        if (count($noms) === 1) {
            return $noms[0];
        }
        $bases = array_values(array_unique(array_map(self::base(...), $noms)));
        return count($bases) === 1 ? $bases[0] : null;
    }

    /**
     * Le nom officiel qui correspond à la saisie, sans tenir compte de la casse, des accents, des traits d'union
     * ni de « Saint » écrit en entier (« delemont » donne « Delémont », « Saint-Imier » donne « St-Imier »).
     * Le nom sans son numéro est accepté (« Lausanne » pour 1000). null si rien ne correspond.
     */
    public static function officielle(string $npa, string $saisie): ?string
    {
        $cle = self::cle($saisie);
        if ($cle === '') {
            return null;
        }
        foreach (self::de($npa) as $nom) {
            if (self::cle($nom) === $cle) {
                return $nom;
            }
        }
        foreach (self::de($npa) as $nom) {
            if (self::cle(self::base($nom)) === $cle) {
                return self::base($nom);
            }
        }
        return null;
    }

    /** Le NPA d'une localité dictée seule (« Delémont » donne 2800), s'il est unique. */
    public static function npaDe(string $nom): ?string
    {
        $cle = self::cle($nom);
        if ($cle === '') {
            return null;
        }
        $trouves = [];
        foreach (self::donnees() as $npa => $noms) {
            foreach ($noms as $n) {
                if (self::cle($n) === $cle) {
                    $trouves[] = (string) $npa;
                }
            }
        }
        return count($trouves) === 1 ? $trouves[0] : null;
    }

    /** Clé de comparaison : minuscules sans accents, ponctuation réduite à une espace, « saint » abrégé. */
    public static function cle(string $s): string
    {
        $ascii = \Transliterator::create('Any-Latin; Latin-ASCII; Lower()');
        $t = $ascii instanceof \Transliterator ? (string) $ascii->transliterate($s) : mb_strtolower($s);
        $t = trim((string) preg_replace('/[^a-z0-9]+/', ' ', $t));
        return (string) preg_replace(['/\b(saint|sankt)\b/', '/\bsainte\b/'], ['st', 'ste'], $t);
    }

    /** « Lausanne 25 » donne « Lausanne ». */
    private static function base(string $nom): string
    {
        return (string) preg_replace('/\s+\d+$/', '', $nom);
    }

    /** @return array<string, list<string>> */
    private static function donnees(): array
    {
        if (self::$parNpa === null) {
            /** @var array<string, list<string>> $l */
            $l = require __DIR__ . '/localites.php';
            self::$parNpa = $l;
        }
        return self::$parNpa;
    }
}
