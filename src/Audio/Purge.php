<?php

declare(strict_types=1);

namespace OffreGen\Audio;

use OffreGen\Donnees\FileTraitement;
use Psr\Clock\ClockInterface;
use Psr\Log\LoggerInterface;

/**
 * Supprime l'audio des enregistrements dont la date de suppression est passée (30 jours après l'offre
 * prête ou l'échec). L'offre et la transcription restent.
 */
final class Purge
{
    public function __construct(
        private readonly FileTraitement $file,
        private readonly StockageAudio $stockage,
        private readonly ClockInterface $horloge,
        private readonly LoggerInterface $journal,
    ) {
    }

    /** @return array{supprimes: int, erreurs: int} */
    public function purger(): array
    {
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
