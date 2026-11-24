<?php

declare(strict_types=1);

namespace OffreGen\Compte;

/**
 * La saisie ressemble à un prospect ou à un compte déjà connu. La personne tranche, sauf quand le numéro IDE est le
 * même : la fiche existe, on l'ouvre.
 */
final class DoublonProspect extends \RuntimeException
{
    /**
     * @param list<array{id: int, motif: string}> $fiches motif : ide, telephone, courriel ou nom
     * @param list<array{id: int, entreprise: string, demo: int, offres_max: int|null, apporteur: string|null}> $comptes
     * @param bool $ferme même numéro IDE : confirmer ne suffit pas
     */
    public function __construct(public readonly array $fiches, public readonly array $comptes, public readonly bool $ferme)
    {
        parent::__construct('Ce prospect ressemble à une fiche ou à un compte déjà connu.');
    }
}
