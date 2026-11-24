<?php

declare(strict_types=1);

namespace OffreGen\Audio;

/**
 * Range les audios hors de public/, un dossier par artisan : <racine>/<artisan_id>/<uuid>.<ext>, et les suites d'une
 * dictée continuée à côté : <uuid>-2.<ext>, <uuid>-3.<ext>…
 * La base ne garde que le chemin relatif.
 */
final class StockageAudio
{
    public function __construct(private readonly string $racine)
    {
    }

    /**
     * @param int $partie 1 pour le début de la dictée, 2 et plus pour ses suites (<uuid>-2.<ext>, …)
     * @return string chemin relatif à la racine
     */
    public function ranger(int $artisanId, string $uuid, AudioValide $audio, int $partie = 1): string
    {
        if (!Uuid::valide($uuid)) {
            throw new \InvalidArgumentException("Identifiant invalide : $uuid");
        }
        if ($partie < 1 || $partie > 9) {
            throw new \InvalidArgumentException("Partie invalide : $partie");
        }
        $dossier = $this->racine . '/' . $artisanId;
        if (!is_dir($dossier) && !mkdir($dossier, 0700, true) && !is_dir($dossier)) {
            throw new \RuntimeException("Dossier audio impossible à créer : $dossier");
        }
        $nom = $uuid . ($partie > 1 ? '-' . $partie : '') . '.' . $audio->extension;
        $audio->fichier->move($dossier, $nom);
        chmod($dossier . '/' . $nom, 0600);
        return $artisanId . '/' . $nom;
    }

    public function chemin(string $relatif): string
    {
        if (preg_match('#^\d+/[0-9a-f-]{36}(-[2-9])?\.[a-z0-9]{2,4}$#', $relatif) !== 1) {
            throw new \InvalidArgumentException("Chemin audio refusé : $relatif");
        }
        return $this->racine . '/' . $relatif;
    }

    public function supprimer(string $relatif): void
    {
        $chemin = $this->chemin($relatif);
        if (is_file($chemin) && !unlink($chemin)) {
            throw new \RuntimeException("Suppression impossible : $relatif");
        }
    }
}
