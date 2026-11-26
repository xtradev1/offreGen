<?php

declare(strict_types=1);

namespace OffreGen\Offre;

/**
 * Où mène une offre, et ce qui lui reste à relire (Xavier, 2026-11-26, maquette v2 du parcours de la dictée). Une seule
 * règle pour la fin d'une dictée, la fin de la relecture et les comptes des listes : tant qu'une ligne est à relire, la
 * relecture ; tant que le client manque, son choix ; l'offre propre, son PDF.
 */
final class Arrivee
{
    /**
     * À relire : la ligne est marquée « à vérifier », ou son prix manque.
     *
     * @param array{prix_centimes: int, a_verifier: int, offerte?: int|bool} $ligne
     */
    public static function aRelire(array $ligne): bool
    {
        return $ligne['a_verifier'] === 1 || Offerte::prixManquant($ligne);
    }

    /** @param list<array{prix_centimes: int, a_verifier: int, offerte?: int|bool}> $lignes */
    public static function compter(array $lignes): int
    {
        return count(array_filter($lignes, self::aRelire(...)));
    }

    /**
     * L'adresse où aller : la relecture, le choix du client (qui mène ensuite au PDF), ou le PDF. Une offre sans ligne
     * (dictée dont rien n'a été tiré) s'ouvre elle-même : son PDF serait vide.
     *
     * @param array{id: int, client_id: int|null} $offre
     */
    public static function adresse(array $offre, int $aRelire, int $lignes): string
    {
        $base = '/offres/' . $offre['id'];
        if ($lignes === 0) {
            return $base;
        }
        if ($aRelire > 0) {
            return $base . '/relire';
        }
        return $base . ($offre['client_id'] === null ? '/client?suite=apercu' : '/apercu');
    }
}
