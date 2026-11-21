<?php

declare(strict_types=1);

namespace OffreGen\Http;

/**
 * Les identifiants pris dans le chemin : un entier positif, sinon la page n'existe pas.
 */
final class Parametres
{
    /** @param array<string, string> $parametres */
    public static function id(array $parametres, string $cle): int
    {
        $valeur = $parametres[$cle] ?? '';
        if (preg_match('/^[1-9]\d{0,9}$/', $valeur) !== 1 || (int) $valeur > 4294967295) {
            throw new PageIntrouvable();
        }
        return (int) $valeur;
    }
}
