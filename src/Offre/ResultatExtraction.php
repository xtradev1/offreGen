<?php

declare(strict_types=1);

namespace OffreGen\Offre;

/**
 * Ce que l'extraction propose : un titre, le client et son lieu s'ils sont dictés, et les lignes validées.
 * postesCoupes : postes proposés au-delà du plafond de lignes d'une offre, écartés ; l'artisan en est averti.
 */
final readonly class ResultatExtraction
{
    /** @param list<LigneExtraite> $lignes */
    public function __construct(
        public ?string $titre,
        public array $lignes,
        public ?string $client = null,
        public ?string $lieu = null,
        public int $postesCoupes = 0,
    ) {
    }
}
