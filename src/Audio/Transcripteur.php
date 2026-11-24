<?php

declare(strict_types=1);

namespace OffreGen\Audio;

/**
 * Transcription asynchrone : on envoie l'audio, on revient chercher le texte plus tard.
 * Le vrai (Whisper chez Infomaniak) arrive au sous-chantier 04.
 */
interface Transcripteur
{
    /**
     * @param string $vocabulaire mots du métier qui guident la transcription (VocabulaireDictee) ; vide : aucun
     * @return string référence de la transcription chez le prestataire
     */
    public function demarrer(string $cheminAudio, string $vocabulaire = ''): string;

    /** @return string|null le texte, ou null s'il n'est pas encore prêt */
    public function resultat(string $reference): ?string;
}
