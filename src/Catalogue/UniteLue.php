<?php

declare(strict_types=1);

namespace OffreGen\Catalogue;

/**
 * L'unité écrite dans une ancienne offre (« m2 », « m' », « fft », « pièce ») ramenée à une unité du catalogue
 * (`Saisie::UNITES`). Une unité inconnue rend null : l'artisan la choisit à la relecture.
 */
final class UniteLue
{
    private const FORMES = [
        'pce' => ['pce', 'pces', 'pc', 'pcs', 'piece', 'pieces', 'u', 'un', 'unite', 'unites', 'st', 'stk'],
        'm²' => ['m²', 'm2', 'mq'],
        'm' => ['m', 'metre', 'metres'],
        'ml' => ['ml', "m'", 'm’', 'mlin', 'mct', 'metrelineaire', 'metreslineaires'],
        'm³' => ['m³', 'm3'],
        'kg' => ['kg', 'kilo', 'kilos', 'kilogramme', 'kilogrammes'],
        'h' => ['h', 'hr', 'hrs', 'heure', 'heures', 'std'],
        'jour' => ['jour', 'jours', 'j', 'jr', 'jrs'],
        'forfait' => ['forfait', 'forfaits', 'fft', 'ft', 'forf', 'fo', 'gl', 'global', 'ens', 'ensemble', 'bloc'],
    ];

    public static function normaliser(string $ecrite): ?string
    {
        $forme = str_replace(['.', ' '], '', Recherche::normaliser($ecrite));
        foreach (self::FORMES as $unite => $formes) {
            if (in_array($forme, $formes, true)) {
                return $unite;
            }
        }
        return null;
    }
}
