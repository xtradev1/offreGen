<?php

declare(strict_types=1);

namespace OffreGen\Donnees;

/**
 * Ce qu'un membre de l'équipe a le droit d'atteindre parmi les comptes des artisans. Exigée par toute lecture et
 * toute écriture de l'administration sur un artisan, comme Portee l'est pour les données d'un artisan.
 * Un commercial n'atteint que les artisans qu'il a créés (Xavier, 2026-11-19) ; le modérateur et l'administrateur,
 * tous.
 */
final readonly class PorteeEquipe
{
    public function __construct(public int $acteurId, public bool $toutVoir)
    {
    }
}
