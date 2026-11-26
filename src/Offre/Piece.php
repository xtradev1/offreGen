<?php

declare(strict_types=1);

namespace OffreGen\Offre;

/**
 * La pièce d'une ligne d'offre (sous-chantier 28). Une seule règle pour ce qui vient du modèle et ce que l'artisan
 * tape : espaces resserrés, première lettre en capitale, 80 caractères au plus. Ce qui vaut pour tout le chantier
 * (« général », « tout le chantier », rien) devient null : le groupe « Général ».
 */
final class Piece
{
    public const LONGUEUR_MAX = 80;
    public const GENERAL = 'Général';

    private const GENERALES = ['général', 'general', 'généralités', 'tout le chantier', 'chantier', 'divers'];

    public static function lire(mixed $saisie): ?string
    {
        if (!is_string($saisie)) {
            return null;
        }
        $texte = trim((string) preg_replace('/\s+/u', ' ', str_replace(["\u{2014}", "\u{2013}"], ',', $saisie)));
        if ($texte === '' || in_array(mb_strtolower($texte), self::GENERALES, true)) {
            return null;
        }
        $texte = mb_substr($texte, 0, self::LONGUEUR_MAX);
        return mb_strtoupper(mb_substr($texte, 0, 1)) . mb_substr($texte, 1);
    }

    /** Deux pièces sont la même sans tenir compte de la casse : « chambre » rejoint « Chambre ». */
    public static function cle(?string $piece): string
    {
        return $piece === null ? '' : mb_strtolower($piece);
    }
}
