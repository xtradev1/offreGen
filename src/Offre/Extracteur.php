<?php

declare(strict_types=1);

namespace OffreGen\Offre;

use OffreGen\Compte\Activite;

/**
 * Lit une transcription et propose des lignes d'offre prises dans le catalogue de l'artisan.
 * Ne calcule aucun total.
 */
interface Extracteur
{
    /**
     * @param list<array{id: int, categorie: string, designation: string, unite: string, prix_centimes: int, synonymes: string|null}> $catalogue
     * @param Activite $activite celle du compte : elle règle deux phrases de la consigne et le rangement des lignes
     */
    public function extraire(string $transcription, array $catalogue, Activite $activite = Activite::Batiment): ResultatExtraction;
}
