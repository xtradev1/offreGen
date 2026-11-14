<?php

declare(strict_types=1);

namespace OffreGen\Audio;

/**
 * Durée d'un audio, lue par ffprobe (livré avec ffmpeg). Sert à refuser un enregistrement trop court.
 */
final class DureeAudio
{
    public function __construct(private readonly string $ffprobe = 'ffprobe')
    {
    }

    /** En secondes ; null si ffprobe manque ou ne sait pas lire le fichier : l'import ne doit pas en dépendre. */
    public function secondes(string $chemin): ?float
    {
        $entete = $this->sonder(['-show_entries', 'format=duration', $chemin]);
        if ($entete !== null && is_numeric($entete)) {
            return (float) $entete;
        }
        // Le WebM du navigateur (MediaRecorder) n'annonce pas sa durée : l'instant du dernier paquet la donne.
        $paquets = $this->sonder(['-select_streams', 'a:0', '-show_entries', 'packet=pts_time', $chemin]);
        if ($paquets === null) {
            return null;
        }
        $fin = null;
        foreach (explode("\n", $paquets) as $ligne) {
            $instant = explode(',', trim($ligne))[0];
            if (is_numeric($instant)) {
                $fin = max($fin ?? 0.0, (float) $instant);
            }
        }
        return $fin;
    }

    /** @param list<string> $arguments */
    private function sonder(array $arguments): ?string
    {
        $tubes = [];
        // Sans shell : aucun nom de fichier n'est interprété.
        $processus = @proc_open([$this->ffprobe, '-v', 'error', '-of', 'csv=p=0', ...$arguments],
            [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $tubes);
        if (!is_resource($processus)) {
            return null;
        }
        $sortie = trim((string) stream_get_contents($tubes[1]));
        stream_get_contents($tubes[2]);
        fclose($tubes[1]);
        fclose($tubes[2]);
        return proc_close($processus) === 0 ? $sortie : null;
    }
}
