<?php

declare(strict_types=1);

namespace OffreGen\Systeme;

/**
 * L'en-tête User-Agent gardé tel quel sur la dictée, lu en clair pour la page des dictées : « Safari, iPhone ».
 * Les règles suivent l'ordre des jetons : Edge et Chrome sur iPhone se présentent aussi comme Safari.
 */
final class Navigateur
{
    public static function lisible(?string $userAgent): string
    {
        if ($userAgent === null || trim($userAgent) === '') {
            return 'inconnu';
        }
        $navigateur = match (true) {
            str_contains($userAgent, 'Edg') => 'Edge',
            str_contains($userAgent, 'SamsungBrowser') => 'Samsung Internet',
            str_contains($userAgent, 'Firefox') || str_contains($userAgent, 'FxiOS') => 'Firefox',
            str_contains($userAgent, 'CriOS') || str_contains($userAgent, 'Chrome') => 'Chrome',
            str_contains($userAgent, 'Safari') => 'Safari',
            default => 'autre navigateur',
        };
        $appareil = match (true) {
            str_contains($userAgent, 'iPhone') => 'iPhone',
            str_contains($userAgent, 'iPad') => 'iPad',
            str_contains($userAgent, 'Android') => 'Android',
            str_contains($userAgent, 'Windows') => 'Windows',
            str_contains($userAgent, 'Mac OS X') || str_contains($userAgent, 'Macintosh') => 'Mac',
            str_contains($userAgent, 'Linux') => 'Linux',
            default => 'autre appareil',
        };
        return $navigateur . ', ' . $appareil;
    }
}
