<?php

declare(strict_types=1);

namespace OffreGen\Catalogue;

/** Ce que la lecture d'une ancienne offre a donné : les lignes gardées, celles que le code a refusées, les jetons. */
final class LectureOffre
{
    /**
     * @param list<LigneLue> $lignes
     * @param list<array{string, string}> $refusees désignation proposée par le modèle, raison du refus
     */
    public function __construct(
        public readonly array $lignes,
        public readonly array $refusees = [],
        public readonly ?int $jetonsEnvoyes = null,
        public readonly ?int $jetonsRecus = null,
    ) {
    }
}
