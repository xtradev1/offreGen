<?php

declare(strict_types=1);

namespace OffreGen\Http;

/**
 * Levée par un contrôleur quand la ressource n'existe pas pour cet artisan, qu'elle soit absente
 * ou à un autre : le noyau répond 404 dans les deux cas, sans révéler laquelle.
 */
final class PageIntrouvable extends \RuntimeException
{
}
