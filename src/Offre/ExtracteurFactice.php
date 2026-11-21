<?php

declare(strict_types=1);

namespace OffreGen\Offre;

/**
 * Extracteur de développement : ne lit pas la transcription. Les trois premiers produits du catalogue,
 * quantités fixes, le troisième marqué « à vérifier ». Sans catalogue, deux lignes libres sans prix.
 */
final class ExtracteurFactice implements Extracteur
{
    private const QUANTITES = [12500, 3000, 1000];

    public function extraire(string $transcription, array $catalogue): ResultatExtraction
    {
        if ($catalogue === []) {
            return new ResultatExtraction(null, [
                new LigneExtraite('Travaux décrits dans la visite', 1000, 'forfait', 0, true, null),
                new LigneExtraite('Déplacement', 1000, 'forfait', 0, true, null),
            ]);
        }
        $lignes = [];
        foreach (array_slice($catalogue, 0, 3) as $i => $p) {
            $lignes[] = new LigneExtraite($p['designation'], self::QUANTITES[$i], $p['unite'], $p['prix_centimes'], $i === 2, $p['id']);
        }
        return new ResultatExtraction(null, $lignes);
    }
}
