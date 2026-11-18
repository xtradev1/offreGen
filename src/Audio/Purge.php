<?php

declare(strict_types=1);

namespace OffreGen\Audio;

use OffreGen\Donnees\FileTraitement;
use Psr\Clock\ClockInterface;
use Psr\Log\LoggerInterface;

/**
 * Supprime l'audio des enregistrements dont la date de suppression est passée (30 jours après l'offre
 * prête ou l'échec). L'offre et la transcription restent.
 *
 * Éteinte tant que PURGE_AUDIO ne vaut pas 1 : décision Q1 de Xavier (2026-11-18), « audio effacé 30 jours après
 * l'offre, texte gardé, mais pour l'instant désactivé, sera activé en production quand je déciderai ».
 */
final class Purge
{
    public function __construct(
        private readonly FileTraitement $file,
        private readonly StockageAudio $stockage,
        private readonly ClockInterface $horloge,
        private readonly LoggerInterface $journal,
        private readonly bool $active,
    ) {
    }

    /** @return array{supprimes: int, erreurs: int}|null null : purge éteinte, rien n'a été lu ni supprimé */
    public function purger(): ?array
    {
        if (!$this->active) {
            return null;
        }
        $supprimes = 0;
        $erreurs = 0;
        foreach ($this->file->aPurger($this->horloge->now()) as $e) {
            try {
                $this->stockage->supprimer($e['fichier']);
                $this->file->audioSupprime($e['uuid']);
                $supprimes++;
            } catch (\Throwable $ex) {
                // Un fichier récalcitrant ne bloque pas les autres ; il sera retenté au prochain passage.
                $erreurs++;
                $this->journal->error("offreGen, purge de {$e['uuid']} : " . $ex->getMessage());
            }
        }
        return ['supprimes' => $supprimes, 'erreurs' => $erreurs];
    }
}
