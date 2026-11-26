<?php

declare(strict_types=1);

namespace OffreGen\Compte;

/**
 * L'IBAN de l'artisan, pour la section de paiement de ses factures. La QR-facture n'accepte qu'un IBAN suisse ou
 * liechtensteinois : 21 caractères, clé modulo 97 juste. Le calcul se fait par morceaux, sans bcmath.
 */
final class Iban
{
    /** Le QR-IBAN se reconnaît au numéro de sa banque (IID, caractères 5 à 9) : 30000 à 31999. */
    private const IID_QR_MIN = 30000;
    private const IID_QR_MAX = 31999;

    /** Forme compacte en majuscules ; vide reste vide ; null si ce n'est pas un IBAN CH ou LI juste. */
    public static function lire(string $saisie): ?string
    {
        $compact = strtoupper((string) preg_replace('/\s+/', '', $saisie));
        if ($compact === '') {
            return '';
        }
        if (preg_match('/^(CH|LI)\d{2}[0-9A-Z]{17}$/', $compact) !== 1 || self::reste97(substr($compact, 4) . substr($compact, 0, 4)) !== 1) {
            return null;
        }
        return $compact;
    }

    public static function estQrIban(string $iban): bool
    {
        if (strlen($iban) !== 21) {
            return false;
        }
        $iid = (int) substr($iban, 4, 5);
        return $iid >= self::IID_QR_MIN && $iid <= self::IID_QR_MAX;
    }

    /** Par groupes de quatre, comme sur un relevé : CH93 0076 2011 6238 5295 7. */
    public static function lisible(string $iban): string
    {
        return implode(' ', str_split($iban, 4));
    }

    /** Reste de la division par 97 du nombre que forment les caractères (A = 10, …, Z = 35). */
    private static function reste97(string $caracteres): int
    {
        $reste = 0;
        foreach (str_split($caracteres) as $c) {
            $valeur = ctype_digit($c) ? $c : (string) (ord($c) - ord('A') + 10);
            foreach (str_split($valeur) as $chiffre) {
                $reste = ($reste * 10 + (int) $chiffre) % 97;
            }
        }
        return $reste;
    }
}
