<?php

declare(strict_types=1);

namespace OffreGen\Audio;

use OffreGen\Systeme\Commande;

/**
 * Rend un audio acceptable par la transcription d'Infomaniak (documentation lue le 2026-11-12) :
 * formats de FORMATS_ACCEPTES, 50 000 Ko au plus. Sinon, conversion en Opus 32 kbit/s mono par ffmpeg :
 * une heure de parole pèse alors environ 14 Mo.
 */
final class PreparationAudio
{
    public const FORMATS_ACCEPTES = ['mp3', 'mp4', 'aac', 'wav', 'flac', 'ogg', 'opus', 'wma', 'm4a', 'webm'];
    /** Un peu sous les 50 000 Ko annoncés, pour la marge de l'envoi multipart. */
    public const TAILLE_MAX = 49 * 1024 * 1024;

    private readonly Commande $commande;

    public function __construct(
        private readonly string $ffmpeg = 'ffmpeg',
        private readonly int $tailleMax = self::TAILLE_MAX,
        ?Commande $commande = null,
    ) {
        $this->commande = $commande ?? new Commande();
    }

    public function preparer(string $chemin): FichierPrepare
    {
        if (!is_file($chemin)) {
            throw new \RuntimeException('Audio introuvable : ' . basename($chemin));
        }
        $extension = strtolower(pathinfo($chemin, PATHINFO_EXTENSION));
        $taille = (int) filesize($chemin);
        if (in_array($extension, self::FORMATS_ACCEPTES, true) && $taille <= $this->tailleMax) {
            return new FichierPrepare($chemin, false);
        }

        $sortie = tempnam(sys_get_temp_dir(), 'offregen-conv-');
        if ($sortie === false) {
            throw new \RuntimeException('Fichier temporaire impossible à créer.');
        }
        unlink($sortie);
        $sortie .= '.ogg';
        $commande = [$this->ffmpeg, '-nostdin', '-v', 'error', '-y', '-i', $chemin, '-vn', '-ac', '1', '-c:a', 'libopus', '-b:a', '32k', $sortie];
        $r = $this->commande->lancer($commande);
        if ($r->code !== 0 || !is_file($sortie)) {
            @unlink($sortie);
            throw new \RuntimeException(match ($r->code) {
                // Site web chez Infomaniak : le traitement de fond (cron, en ligne de commande) la refera.
                Commande::IMPOSSIBLE => 'Conversion remise au traitement de fond : le serveur web ne peut pas lancer ffmpeg.',
                Commande::ABSENT => 'Conversion impossible : ffmpeg est absent de ce serveur.',
                default => 'Conversion de l\'audio échouée (code ' . $r->code . ') : ' . mb_substr(trim($r->erreur), 0, 200),
            });
        }
        if ((int) filesize($sortie) > $this->tailleMax) {
            unlink($sortie);
            throw new \RuntimeException('Audio trop long pour la transcription, même compressé.');
        }
        return new FichierPrepare($sortie, true);
    }
}
