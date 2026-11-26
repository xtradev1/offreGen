<?php

declare(strict_types=1);

namespace OffreGen\Audio;

use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * Accepte un fichier audio d'après son contenu (finfo), jamais d'après son nom ni le type que
 * le navigateur annonce. Types relevés le 2026-11-07 sur des fichiers produits par ffmpeg 6.1
 * (voir tests/fixtures/audio/).
 */
final class ValidateurAudio
{
    public const TAILLE_MAX = 200 * 1024 * 1024;
    public const FORMATS = 'm4a, mp3, ogg, opus, wav, amr, 3gp, webm';

    /** Type détecté (en minuscules) => extension de rangement. */
    private const TYPES = [
        'audio/x-m4a' => 'm4a',
        'audio/mp4' => 'm4a',
        'audio/m4a' => 'm4a',
        // Les enregistreurs Android écrivent souvent un MP4 de marque « isom », que finfo voit en vidéo.
        'video/mp4' => 'm4a',
        'audio/mpeg' => 'mp3',
        'audio/ogg' => 'ogg',
        'audio/opus' => 'opus',
        'audio/x-wav' => 'wav',
        'audio/wav' => 'wav',
        'audio/vnd.wave' => 'wav',
        'audio/amr' => 'amr',
        'video/3gpp' => '3gp',
        'audio/3gpp' => '3gp',
        // Enregistrement dans Chrome et Firefox (MediaRecorder) : Opus dans un WebM, que finfo voit en vidéo.
        'video/webm' => 'webm',
        'audio/webm' => 'webm',
    ];

    public function __construct(private readonly int $tailleMax = self::TAILLE_MAX)
    {
    }

    public function valider(UploadedFile $fichier): AudioValide
    {
        $erreur = $fichier->getError();
        if ($erreur === UPLOAD_ERR_INI_SIZE || $erreur === UPLOAD_ERR_FORM_SIZE) {
            throw new AudioRefuse($this->messageTaille());
        }
        if ($erreur === UPLOAD_ERR_NO_FILE) {
            throw new AudioRefuse('Choisissez un fichier audio.');
        }
        if ($erreur !== UPLOAD_ERR_OK || !$fichier->isValid()) {
            throw new AudioRefuse("L'envoi a été interrompu. Réessayez.");
        }
        $taille = (int) $fichier->getSize();
        if ($taille === 0) {
            throw new AudioRefuse('Ce fichier est vide.');
        }
        if ($taille > $this->tailleMax) {
            throw new AudioRefuse($this->messageTaille());
        }
        $mime = strtolower((string) (new \finfo(FILEINFO_MIME_TYPE))->file($fichier->getPathname()));
        $extension = self::TYPES[$mime] ?? null;
        if ($extension === null) {
            throw new AudioRefuse("Ce fichier n'est pas un enregistrement audio reconnu. Formats acceptés : " . self::FORMATS . '.');
        }
        return new AudioValide($mime, $extension, $taille, $fichier);
    }

    private function messageTaille(): string
    {
        return 'Ce fichier dépasse ' . intdiv($this->tailleMax, 1024 * 1024) . ' Mo.';
    }
}
