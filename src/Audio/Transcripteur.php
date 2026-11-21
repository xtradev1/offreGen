<?php

declare(strict_types=1);

namespace OffreGen\Audio;

/**
 * Transcription asynchrone : on envoie l'audio, on revient chercher le texte plus tard.
 * Le vrai (Whisper chez Infomaniak) arrive au sous-chantier 04.
 */
interface Transcripteur
{
    /** @return string référence de la transcription chez le prestataire */
    public function demarrer(string $cheminAudio): string;

    /** @return string|null le texte, ou null s'il n'est pas encore prêt */
    public function resultat(string $reference): ?string;
}
