<?php

declare(strict_types=1);

namespace OffreGen\Facture;

/**
 * Le numéro d'une facture : forme 2026-001 (Xavier, 2026-11-13), qui repart à 001 chaque année. L'artisan peut en
 * taper un autre, pour reprendre sa suite ; les suivants partent de lui. Deux factures ne portent jamais le même.
 */
final class Numerotation
{
    /** Lettres, chiffres et - / . _ ; commence par une lettre ou un chiffre ; 20 caractères au plus. */
    private const FORME = '/^[A-Za-z0-9][A-Za-z0-9\/._-]{0,19}$/';

    /** Le numéro tapé, sans les espaces autour ; null s'il n'a pas la forme permise. */
    public static function lire(string $saisie): ?string
    {
        $t = trim($saisie);
        return preg_match(self::FORME, $t) === 1 ? $t : null;
    }

    /**
     * Le numéro qui suit le dernier créé ; on saute ceux que $pris déclare déjà pris.
     *
     * @param callable(string): bool $pris
     */
    public static function suivant(?string $dernier, int $annee, callable $pris): string
    {
        $candidat = self::apres($dernier, $annee);
        while ($pris($candidat)) {
            $candidat = self::apres($candidat, $annee);
        }
        return $candidat;
    }

    private static function apres(?string $numero, int $annee): string
    {
        $debutAnnee = sprintf('%04d-001', $annee);
        if ($numero === null) {
            return $debutAnnee;
        }
        if (preg_match('/^(\d{4})-(\d+)$/', $numero, $m) === 1) {
            return (int) $m[1] === $annee ? $m[1] . '-' . self::plusUn($m[2]) : $debutAnnee;
        }
        if (preg_match('/^(.*?)(\d+)$/', $numero, $m) === 1) {
            return $m[1] . self::plusUn($m[2]);
        }
        return $debutAnnee;
    }

    /** « 0099 » donne « 0100 », « 999 » donne « 1000 » : la largeur des zéros est gardée. */
    private static function plusUn(string $chiffres): string
    {
        return str_pad((string) ((int) $chiffres + 1), strlen($chiffres), '0', STR_PAD_LEFT);
    }
}
