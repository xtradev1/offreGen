<?php

declare(strict_types=1);

namespace OffreGen\Donnees;

/**
 * Statuts d'un enregistrement, dans l'ordre du traitement.
 */
final class Statut
{
    public const RECU = 'recu';
    public const TRANSCRIPTION = 'transcription';
    public const TRANSCRIT = 'transcrit';
    public const PRET = 'pret';
    public const ECHEC = 'echec';

    public static function estFinal(string $statut): bool
    {
        return $statut === self::PRET || $statut === self::ECHEC;
    }
}
