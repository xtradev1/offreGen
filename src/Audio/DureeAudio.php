<?php

declare(strict_types=1);

namespace OffreGen\Audio;

use OffreGen\Systeme\Commande;

/**
 * Durée d'un audio, lue par ffprobe (livré avec ffmpeg). Sert à refuser un enregistrement trop court.
 */
final class DureeAudio
{
    private readonly Commande $commande;

    public function __construct(private readonly string $ffprobe = 'ffprobe', ?Commande $commande = null)
    {
        $this->commande = $commande ?? new Commande();
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
        // ffprobe absent, ou proc_open désactivée (site web chez Infomaniak) : durée inconnue, l'import continue.
        $r = $this->commande->lancer([$this->ffprobe, '-v', 'error', '-of', 'csv=p=0', ...$arguments]);
        return $r->code === 0 ? trim($r->sortie) : null;
    }
}
