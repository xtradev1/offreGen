<?php

declare(strict_types=1);

namespace OffreGen\Facture;

use OffreGen\Compte\Reglages;
use OffreGen\Offre\Totaux;

/**
 * Ce qu'une offre peut encore facturer (Xavier, 2026-11-13, choix A2) : un acompte par tranche des conditions de
 * paiement, sauf la dernière, qui est la facture finale ; la finale reprend toutes les lignes et déduit les acomptes.
 * Une seule finale par offre. L'artisan peut aussi facturer le tout d'un coup.
 *
 * @phpstan-type Genre array{genre: 'acompte'|'finale', rang: int|null, nombre: int|null, pourcent: int|null}
 */
final class PlanAcomptes
{
    /** Échéance d'un acompte, et d'une finale quand les conditions ne donnent pas de délai. */
    public const JOURS_PAR_DEFAUT = 30;

    /**
     * Les factures possibles, la proposée en premier ; vide si la finale existe déjà.
     *
     * @param list<int> $tranches les acomptes des conditions, en pour-cent ; vide sans acomptes
     * @param int $acomptesFaits acomptes non annulés de l'offre, brouillons compris
     * @return list<Genre>
     */
    public static function possibles(array $tranches, int $acomptesFaits, bool $finaleFaite): array
    {
        if ($finaleFaite) {
            return [];
        }
        $finale = ['genre' => 'finale', 'rang' => null, 'nombre' => null, 'pourcent' => null];
        $nombre = count($tranches);
        if ($nombre < 2 || $acomptesFaits >= $nombre - 1) {
            return [$finale];
        }
        return [['genre' => 'acompte', 'rang' => $acomptesFaits + 1, 'nombre' => $nombre, 'pourcent' => $tranches[$acomptesFaits]], $finale];
    }

    /** Le montant HT d'un acompte, en centimes : le pourcentage du HT de l'offre, au centime. */
    public static function montant(int $htOffre, int $pourcent): int
    {
        return Totaux::arrondir($htOffre * $pourcent, 100);
    }

    /** La ligne unique d'une facture d'acompte. */
    public static function designation(int $rang, int $nombre, int $pourcent, int $numeroOffre): string
    {
        return "Acompte $rang sur $nombre ($pourcent\u{00A0}%), selon offre n°\u{00A0}$numeroOffre";
    }

    /** Date de la facture plus le délai des conditions (« net à N jours », « à N jours ») ; 30 jours sinon. */
    public static function echeance(Reglages $r, string $genre, \DateTimeImmutable $date): \DateTimeImmutable
    {
        $jours = $genre === 'finale' && $r->paiementType !== 'acomptes' ? $r->paiementJours : self::JOURS_PAR_DEFAUT;
        return $date->modify("+$jours days");
    }
}
