<?php

declare(strict_types=1);

namespace OffreGen\Catalogue;

/** Une ligne chiffrée relevée dans une ancienne offre : un candidat pour le catalogue. */
final class LigneLue
{
    public function __construct(
        public readonly string $designation,
        /** Unité du catalogue ; null quand l'unité écrite est inconnue (à choisir à la relecture). */
        public readonly ?string $unite,
        public readonly string $uniteEcrite,
        public readonly int $prixCentimes,
        /** Numéro de la ligne du texte où le prix a été lu. */
        public readonly int $ligneDuTexte,
    ) {
    }
}
