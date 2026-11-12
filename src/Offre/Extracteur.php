<?php

declare(strict_types=1);

namespace OffreGen\Offre;

/**
 * Lit une transcription et propose des lignes d'offre prises dans le catalogue de l'artisan.
 * Ne calcule aucun total.
 */
interface Extracteur
{
    /**
     * @param list<array{id: int, categorie: string, designation: string, unite: string, prix_centimes: int, synonymes: string|null}> $catalogue
     */
    public function extraire(string $transcription, array $catalogue): ResultatExtraction;
}
