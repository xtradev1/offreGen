<?php

declare(strict_types=1);

namespace OffreGen\Audio;

/**
 * Le lot de transcription est perdu (échoué, annulé, introuvable, trop long) : il faut renvoyer l'audio.
 * Le traiteur remet l'enregistrement en « reçu » et compte une tentative.
 */
final class TranscriptionEchouee extends \RuntimeException
{
}
