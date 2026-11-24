<?php

declare(strict_types=1);

namespace OffreGen\Audio;

/**
 * Le prompt de Whisper : les mots du catalogue de l'artisan, pour qu'une dictée de métier ne devienne pas
 * « travers teint » ou « plaintes » (sous-chantier 30, lot 8, banc du 2026-11-24 dans son ETAT.md).
 */
final class VocabulaireDictee
{
    /**
     * Whisper ne garde que 224 jetons de prompt, et tronque par le début : un vocabulaire trop long perdrait les
     * produits les plus utilisés, rangés en tête. 500 caractères restent sous cette limite pour du français accentué.
     */
    public const LONGUEUR_MAX = 500;

    private const DEBUT = 'Offre. ';

    /**
     * @param list<string> $designations dans l'ordre de priorité (les plus utilisées d'abord)
     * @return string vide si aucune désignation : la dictée part alors sans prompt
     */
    public static function depuis(array $designations): string
    {
        $gardees = [];
        $vues = [];
        $longueur = mb_strlen(self::DEBUT) + 1;
        foreach ($designations as $designation) {
            $designation = trim(preg_replace('/\s+/u', ' ', $designation) ?? '');
            $cle = mb_strtolower($designation);
            if ($designation === '' || isset($vues[$cle])) {
                continue;
            }
            $ajout = mb_strlen($designation) + ($gardees === [] ? 0 : 2);
            if ($longueur + $ajout > self::LONGUEUR_MAX) {
                continue;
            }
            $vues[$cle] = true;
            $gardees[] = $designation;
            $longueur += $ajout;
        }
        return $gardees === [] ? '' : self::DEBUT . implode(', ', $gardees) . '.';
    }
}
