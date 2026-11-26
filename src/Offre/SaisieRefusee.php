<?php

declare(strict_types=1);

namespace OffreGen\Offre;

/**
 * Une saisie de formulaire refusée : un message par champ, montré à côté du champ.
 */
final class SaisieRefusee extends \RuntimeException
{
    /** @param array<string, string> $erreurs nom du champ => message */
    public function __construct(public readonly array $erreurs)
    {
        parent::__construct(implode(' ', $erreurs));
    }
}
