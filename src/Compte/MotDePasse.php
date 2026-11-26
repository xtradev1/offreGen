<?php

declare(strict_types=1);

namespace OffreGen\Compte;

/**
 * Règle d'un mot de passe choisi par l'artisan : 10 caractères au moins, sans autre contrainte de composition
 * (une longue phrase vaut mieux qu'un mot court mêlé de chiffres). 128 au plus : le hachage reste rapide.
 */
final class MotDePasse
{
    public const LONGUEUR_MIN = 10;
    public const LONGUEUR_MAX = 128;

    /** @return string|null le message à montrer, ou null si le mot de passe convient */
    public static function erreur(string $motDePasse, string $confirmation): ?string
    {
        $longueur = mb_strlen($motDePasse);
        if ($longueur < self::LONGUEUR_MIN) {
            return 'Le mot de passe doit compter ' . self::LONGUEUR_MIN . ' caractères au moins.';
        }
        if ($longueur > self::LONGUEUR_MAX) {
            return 'Le mot de passe doit compter ' . self::LONGUEUR_MAX . ' caractères au plus.';
        }
        if ($motDePasse !== $confirmation) {
            return 'Les deux mots de passe ne sont pas les mêmes.';
        }
        return null;
    }
}
