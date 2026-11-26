<?php

declare(strict_types=1);

namespace OffreGen\Audio;

/** Enregistrement plus long que la durée permise : échec définitif, sans aucun appel payant (revue du 2026-11-18, H1). */
final class AudioTropLong extends \RuntimeException
{
}
