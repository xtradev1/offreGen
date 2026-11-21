<?php

declare(strict_types=1);

namespace OffreGen\Audio;

/**
 * Transcripteur de développement : n'envoie rien nulle part, rend toujours la même visite inventée.
 */
final class TranscripteurFactice implements Transcripteur
{
    public const TEXTE = "Visite chez Madame Exemple, appartement de trois pièces. Dans le salon, murs à repeindre, "
        . "environ douze mètres carrés et demi, deux couches. Il y a aussi trois portes à laquer. "
        . "Pour la chambre, je dois encore vérifier la surface du plafond.";

    public function demarrer(string $cheminAudio): string
    {
        if (!is_file($cheminAudio)) {
            throw new \RuntimeException('Audio introuvable : ' . basename($cheminAudio));
        }
        return 'factice-' . substr(hash('sha256', $cheminAudio), 0, 16);
    }

    public function resultat(string $reference): string
    {
        return self::TEXTE;
    }
}
