<?php

declare(strict_types=1);

namespace OffreGen\Http;

/**
 * Levée quand la ressource existe pour cet artisan mais refuse l'écriture demandée (une facture émise ne se
 * corrige plus) : le noyau répond 409 avec ce message.
 */
final class Conflit extends \RuntimeException
{
}
