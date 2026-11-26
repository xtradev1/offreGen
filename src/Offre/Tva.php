<?php

declare(strict_types=1);

namespace OffreGen\Offre;

/**
 * Les taux de TVA suisses (depuis le 1er janvier 2024), en dix-millièmes. L'entreprise en choisit un ; une ligne ou un
 * produit peut en prendre un autre (Xavier, 2026-11-15 ; décision D11).
 */
final class Tva
{
    /** Du plus courant au plus rare, avec ce que chacun couvre. */
    public const TAUX = [810 => 'normal', 260 => 'réduit', 380 => 'hébergement', 0 => 'sans TVA'];

    /**
     * Le choix d'un formulaire : vide donne null (le taux habituel), un taux permis donne ce taux, le reste false.
     */
    public static function lire(string $saisie): int|null|false
    {
        $saisie = trim($saisie);
        if ($saisie === '') {
            return null;
        }
        if (preg_match('/^\d{1,4}$/', $saisie) !== 1 || !array_key_exists((int) $saisie, self::TAUX)) {
            return false;
        }
        return (int) $saisie;
    }
}
