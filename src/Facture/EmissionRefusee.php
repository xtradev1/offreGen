<?php

declare(strict_types=1);

namespace OffreGen\Facture;

/**
 * L'émission d'une facture est empêchée (ligne sans prix, montant négatif, finale déjà faite) : levée dans la
 * transaction de « Créer la facture », elle l'annule ; rien ne reste en base (revue du sous-chantier 25).
 */
final class EmissionRefusee extends \RuntimeException
{
}
