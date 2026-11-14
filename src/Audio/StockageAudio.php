<?php

declare(strict_types=1);

namespace OffreGen\Audio;

/**
 * Range les audios hors de public/, un dossier par artisan : <racine>/<artisan_id>/<uuid>.<ext>.
 * La base ne garde que le chemin relatif.
 */
final class StockageAudio
{
    public function __construct(private readonly string $racine)
    {
    }

    /** @return string chemin relatif à la racine */
    public function ranger(int $artisanId, string $uuid, AudioValide $audio): string
    {
        if (!Uuid::valide($uuid)) {
            throw new \InvalidArgumentException("Identifiant invalide : $uuid");
        }
        $dossier = $this->racine . '/' . $artisanId;
        if (!is_dir($dossier) && !mkdir($dossier, 0700, true) && !is_dir($dossier)) {
            throw new \RuntimeException("Dossier audio impossible à créer : $dossier");
        }
        $nom = $uuid . '.' . $audio->extension;
        $audio->fichier->move($dossier, $nom);
        chmod($dossier . '/' . $nom, 0600);
        return $artisanId . '/' . $nom;
    }

    public function chemin(string $relatif): string
    {
        if (preg_match('#^\d+/[0-9a-f-]{36}\.[a-z0-9]{2,4}$#', $relatif) !== 1) {
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
