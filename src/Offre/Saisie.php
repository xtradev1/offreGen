<?php

declare(strict_types=1);

namespace OffreGen\Offre;

/**
 * Lecture des nombres et des textes tapés par l'artisan, sans passer par un nombre à virgule flottante.
 * Acceptés : « 12.5 », « 12,5 », « 1'234.50 », « 1’234,50 », « 28.- ».
 */
final class Saisie
{
    /** Unités proposées à l'écran (maquette v4). */
    public const UNITES = ['pce', 'm²', 'm', 'm³', 'h', 'forfait'];

    public const QUANTITE_MAX_MILLIEMES = 1_000_000_000;
    public const PRIX_MAX_CENTIMES = 100_000_000;

    /** Quantité en millièmes, strictement positive ; null si la saisie n'en est pas une. */
    public static function milliemes(string $saisie): ?int
    {
        $n = self::decimal($saisie, 3);
        return $n === null || $n <= 0 || $n > self::QUANTITE_MAX_MILLIEMES ? null : $n;
    }

    /** Prix en centimes ; un champ vide vaut 0 (« Prix ? ») ; null si la saisie n'en est pas un. */
    public static function centimes(string $saisie): ?int
    {
        $propre = self::nettoyer($saisie);
        if ($propre === '') {
            return 0;
        }
        $n = self::decimal($propre, 2);
        return $n === null || $n > self::PRIX_MAX_CENTIMES ? null : $n;
    }

    /** Texte d'une ligne, sur une ligne, espaces réduits ; null s'il est vide ou trop long. */
    public static function texte(string $saisie, int $max): ?string
    {
        $t = trim((string) preg_replace('/\s+/u', ' ', $saisie));
        return $t === '' || mb_strlen($t) > $max ? null : $t;
    }

    /**
     * Texte de quelques lignes (une adresse) : chaque ligne nettoyée, lignes vides retirées.
     * Vide permis ; null s'il y a trop de lignes ou de caractères.
     */
    public static function lignes(string $saisie, int $maxLignes, int $max): ?string
    {
        $lignes = [];
        foreach (preg_split('/\R/u', $saisie) ?: [] as $ligne) {
            $ligne = trim((string) preg_replace('/\s+/u', ' ', $ligne));
            if ($ligne !== '') {
                $lignes[] = $ligne;
            }
        }
        $texte = implode("\n", $lignes);
        return count($lignes) > $maxLignes || mb_strlen($texte) > $max ? null : $texte;
    }

    /** L'unité choisie, si elle est dans la liste ou si c'est celle que la ligne ou le produit avait déjà. */
    public static function unite(string $saisie, ?string $actuelle = null): ?string
    {
        $u = trim($saisie);
        return in_array($u, self::UNITES, true) || ($actuelle !== null && $u === $actuelle) ? $u : null;
    }

    private static function nettoyer(string $saisie): string
    {
        $t = str_replace(["'", '’', ' ', "\u{00A0}", "\u{202F}"], '', trim($saisie));
        return (string) preg_replace('/\.-$/', '', str_replace(',', '.', $t));
    }

    /** « 12.5 » avec 3 décimales donne 12500 ; au plus $decimales chiffres après le point. */
    private static function decimal(string $saisie, int $decimales): ?int
    {
        $propre = self::nettoyer($saisie);
        if (preg_match('/^(\d{1,9})(?:\.(\d{1,' . $decimales . '}))?$/', $propre, $m) !== 1) {
            return null;
        }
        $fraction = str_pad($m[2] ?? '', $decimales, '0');
        return (int) $m[1] * 10 ** $decimales + (int) $fraction;
    }
}
