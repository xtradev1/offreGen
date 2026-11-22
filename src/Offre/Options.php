<?php

declare(strict_types=1);

namespace OffreGen\Offre;

/**
 * Options et variantes d'une offre (décisions O1 à O5 de Xavier, 2026-11-19 et 20 ; points 2 à 4 du 2026-11-22).
 *
 * Une ligne est dans le total si c'est une ligne qu'aucune variante retenue ne remplace, une option retenue, ou une
 * variante retenue. Une variante dont la ligne de base n'est pas (ou plus) une ligne de l'offre se lit comme une option
 * (O3) : rien n'est réécrit, et « Annuler » sur la base la remet à sa place. Si deux variantes d'une même ligne sont
 * retenues, la première compte seule (l'écriture l'évite, DepotOffres::retenir).
 *
 * Une ligne sans nature (ligne de facture, tableau de test) est une ligne du total.
 *
 * @phpstan-type Choix array{id?: int, nature?: string, remplace_id?: int|null, retenue?: int|bool}
 */
final class Options
{
    public const LIGNE = 'ligne';
    public const OPTION = 'option';
    public const VARIANTE = 'variante';
    public const NATURES = [self::LIGNE, self::OPTION, self::VARIANTE];

    /**
     * @param Choix $l
     * @param list<Choix> $lignes toutes les lignes actives de l'offre
     * @return self::LIGNE|self::OPTION|self::VARIANTE
     */
    public static function natureEffective(array $l, array $lignes): string
    {
        return self::nature($l, self::bases($lignes));
    }

    /**
     * @param Choix $l
     * @param array<int, true> $bases
     * @return self::LIGNE|self::OPTION|self::VARIANTE
     */
    private static function nature(array $l, array $bases): string
    {
        $nature = $l['nature'] ?? self::LIGNE;
        if ($nature === self::OPTION) {
            return self::OPTION;
        }
        if ($nature !== self::VARIANTE) {
            return self::LIGNE;
        }
        return isset($bases[$l['remplace_id'] ?? 0]) ? self::VARIANTE : self::OPTION;
    }

    /**
     * Dans le total, ligne par ligne, dans l'ordre reçu.
     *
     * @param list<Choix> $lignes
     * @return list<bool>
     */
    public static function masque(array $lignes): array
    {
        $bases = self::bases($lignes);
        $choisies = self::variantesChoisies($lignes);
        $masque = [];
        foreach ($lignes as $i => $l) {
            $masque[] = match (self::nature($l, $bases)) {
                self::LIGNE => !isset($choisies[$l['id'] ?? 0]),
                self::OPTION => (bool) ($l['retenue'] ?? false),
                self::VARIANTE => in_array($i, $choisies, true),
            };
        }
        return $masque;
    }

    /**
     * Le total « avec toutes les options » : en plus, les options que le client n'a pas encore retenues ; pas les
     * variantes, qui s'excluent entre elles (point 4).
     *
     * @param list<Choix> $lignes
     * @return list<bool>
     */
    public static function masqueAvecOptions(array $lignes): array
    {
        $bases = self::bases($lignes);
        $masque = self::masque($lignes);
        $avec = [];
        foreach ($lignes as $i => $l) {
            $avec[] = $masque[$i] || self::nature($l, $bases) === self::OPTION;
        }
        return $avec;
    }

    /** @param list<Choix> $lignes */
    public static function aDesOptionsLibres(array $lignes): bool
    {
        $bases = self::bases($lignes);
        foreach ($lignes as $l) {
            if (self::nature($l, $bases) === self::OPTION && !($l['retenue'] ?? false)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Les lignes du total, dans l'ordre de l'offre ; une variante retenue prend le rang de sa ligne de base (point 2).
     * Ce que copie une facture finale.
     *
     * @template T of Choix
     * @param list<T> $lignes
     * @return list<T>
     */
    public static function lignesDuTotal(array $lignes): array
    {
        $bases = self::bases($lignes);
        $choisies = self::variantesChoisies($lignes);
        $masque = self::masque($lignes);
        $rangs = [];
        foreach ($lignes as $i => $l) {
            $nature = self::nature($l, $bases);
            if ($nature === self::LIGNE && isset($choisies[$l['id'] ?? 0])) {
                $rangs[] = $choisies[$l['id'] ?? 0];
            } elseif ($nature !== self::VARIANTE && $masque[$i]) {
                $rangs[] = $i;
            }
        }
        $total = [];
        foreach ($rangs as $i) {
            $total[] = $lignes[$i];
        }
        return $total;
    }

    /**
     * Les ids des lignes du total (nature « ligne ») qu'une variante peut remplacer.
     *
     * @param list<Choix> $lignes
     * @return array<int, true>
     */
    private static function bases(array $lignes): array
    {
        $bases = [];
        foreach ($lignes as $l) {
            if (isset($l['id']) && ($l['nature'] ?? self::LIGNE) !== self::OPTION && ($l['nature'] ?? self::LIGNE) !== self::VARIANTE) {
                $bases[$l['id']] = true;
            }
        }
        return $bases;
    }

    /**
     * Pour chaque ligne de base, l'indice de la première variante retenue qui la remplace.
     *
     * @param list<Choix> $lignes
     * @return array<int, int>
     */
    private static function variantesChoisies(array $lignes): array
    {
        $bases = self::bases($lignes);
        $choisies = [];
        foreach ($lignes as $i => $l) {
            $base = $l['remplace_id'] ?? null;
            if (($l['nature'] ?? null) === self::VARIANTE && ($l['retenue'] ?? false) && $base !== null && isset($bases[$base]) && !isset($choisies[$base])) {
                $choisies[$base] = $i;
            }
        }
        return $choisies;
    }
}
