<?php

declare(strict_types=1);

namespace OffreGen\Audio;

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

    public function __construct(
        private readonly string $ffmpeg = 'ffmpeg',
        private readonly int $tailleMax = self::TAILLE_MAX,
    ) {
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
        $erreur = '';
        $code = $this->executer($commande, $erreur);
        if ($code !== 0 || !is_file($sortie)) {
            @unlink($sortie);
            throw new \RuntimeException($code === 127
                ? 'Conversion impossible : ffmpeg est absent de ce serveur.'
                : 'Conversion de l\'audio échouée (code ' . $code . ') : ' . mb_substr(trim($erreur), 0, 200));
        }
        if ((int) filesize($sortie) > $this->tailleMax) {
            unlink($sortie);
            throw new \RuntimeException('Audio trop long pour la transcription, même compressé.');
        }
        return new FichierPrepare($sortie, true);
    }

    /** @param list<string> $commande sans passer par un shell : aucun nom de fichier n'est interprété */
    private function executer(array $commande, string &$erreur): int
    {
        $tubes = [];
        $processus = @proc_open($commande, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $tubes);
        if (!is_resource($processus)) {
            return 127;
        }
        stream_get_contents($tubes[1]);
        $erreur = (string) stream_get_contents($tubes[2]);
        fclose($tubes[1]);
        fclose($tubes[2]);
        return proc_close($processus);
    }
}
