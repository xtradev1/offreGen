<?php

declare(strict_types=1);

namespace OffreGen\Adresse;

/** Le registre des adresses ne répond pas : l'adresse se tape et s'enregistre quand même. */
final class RegistreIndisponible extends \RuntimeException
{
}
