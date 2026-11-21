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
        /** Prix dicté pour un produit du catalogue quand il diffère du catalogue (Xavier, 2026-11-17) ; sinon null. */
        public ?int $prixDicteCentimes = null,
        /** Pièce dictée (sous-chantier 28) ; null : tout le chantier, ou pas de pièce dite. Voir Piece. */
        public ?string $piece = null,
    ) {
        if (trim($designation) === '') {
            throw new \InvalidArgumentException('Ligne sans désignation.');
        }
        if ($quantiteMilliemes <= 0) {
            throw new \InvalidArgumentException("Quantité nulle ou négative : $designation");
        }
    }
}
