<?php

declare(strict_types=1);

namespace OffreGen\Offre;

/**
 * Une ligne d'offre telle que l'extraction la propose. Quantité en millièmes, prix unitaire en centimes :
 * le total de la ligne se calcule dans Totaux, jamais ailleurs. « entendu » : les mots de la dictée
 * d'où vient la ligne, gardés pour le banc de mesure ; l'écran ne les montre pas (CODING_RULES, Ergonomie).
 */
final readonly class LigneExtraite
{
    public function __construct(
        public string $designation,
        public int $quantiteMilliemes,
        public string $unite,
        public int $prixCentimes,
        public bool $aVerifier,
        public ?int $produitId,
        public ?string $entendu = null,
    ) {
        if (trim($designation) === '') {
            throw new \InvalidArgumentException('Ligne sans désignation.');
        }
        if ($quantiteMilliemes <= 0) {
            throw new \InvalidArgumentException("Quantité nulle ou négative : $designation");
        }
    }
}
