<?php

declare(strict_types=1);

namespace OffreGen\Offre;

/**
 * Seul endroit où l'on calcule un montant. Entiers seulement : quantités en millièmes,
 * prix et montants en centimes, taux en dix-millièmes (8,1 % = 810).
 *
 * Arrondis : chaque ligne et la TVA au centime, pour que les montants affichés s'additionnent ;
 * le total TTC aux 5 centimes (plus petite pièce suisse) si le compte le demande, l'écart affiché à part.
 * Un demi se compte en s'éloignant de zéro.
 */
final class Totaux
{
    public const TVA_NORMALE = 810;

    /**
     * Une ligne peut porter son taux (tva_pour_dix_mille) ; sans taux, ou à null, elle prend celui de l'entreprise
     * (Xavier, 2026-11-15). La TVA se calcule taux par taux, chacune arrondie au centime, puis s'additionne.
     * Une ligne offerte (offerte à 1, D1 du 2026-11-21) compte 0 et ne crée pas de groupe de TVA.
     *
     * @param list<array{quantite_milliemes: int, prix_centimes: int, tva_pour_dix_mille?: int|null, offerte?: int|bool}> $lignes
     * @param int $tvaPourDixMille le taux de l'entreprise
     * @param bool $arrondiCinq arrondir le total aux 5 centimes (réglage du compte)
     * @return array{lignes: list<int>, ht: int, tva: int, arrondi: int, ttc: int, par_taux: list<array{taux: int, ht: int, tva: int}>}
     */
    public static function calculer(array $lignes, int $tvaPourDixMille = self::TVA_NORMALE, bool $arrondiCinq = true): array
    {
        $parLigne = [];
        foreach ($lignes as $l) {
            $parLigne[] = self::montant($l);
        }
        $ht = array_sum($parLigne);
        $parTaux = self::parTaux($lignes, $tvaPourDixMille);
        $tva = array_sum(array_column($parTaux, 'tva'));
        $brut = $ht + $tva;
        $ttc = $arrondiCinq ? self::arrondir($brut, 5) * 5 : $brut;
        return ['lignes' => $parLigne, 'ht' => $ht, 'tva' => $tva, 'arrondi' => $ttc - $brut, 'ttc' => $ttc, 'par_taux' => $parTaux];
    }

    /**
     * La TVA détaillée par taux, du plus haut au plus bas : ce que le document montre dès qu'il y a plusieurs taux.
     *
     * @param list<array{quantite_milliemes: int, prix_centimes: int, tva_pour_dix_mille?: int|null, offerte?: int|bool}> $lignes
     * @return list<array{taux: int, ht: int, tva: int}>
     */
    public static function parTaux(array $lignes, int $tvaPourDixMille = self::TVA_NORMALE): array
    {
        $ht = [];
        foreach ($lignes as $l) {
            if (Offerte::est($l)) {
                continue;
            }
            $taux = $l['tva_pour_dix_mille'] ?? $tvaPourDixMille;
            $ht[$taux] = ($ht[$taux] ?? 0) + self::montant($l);
        }
        if ($ht === []) {
            $ht[$tvaPourDixMille] = 0;
        }
        krsort($ht);
        $groupes = [];
        foreach ($ht as $taux => $montant) {
            $groupes[] = ['taux' => $taux, 'ht' => $montant, 'tva' => self::arrondir($montant * $taux, 10000)];
        }
        return $groupes;
    }

    /** @param array{quantite_milliemes: int, prix_centimes: int, offerte?: int|bool} $l */
    private static function montant(array $l): int
    {
        return Offerte::est($l) ? 0 : self::brut($l);
    }

    /**
     * Le montant de la ligne comme si elle était facturée : celui qu'une ligne offerte montre barré (D1).
     *
     * @param array{quantite_milliemes: int, prix_centimes: int} $l
     */
    public static function brut(array $l): int
    {
        return self::arrondir($l['quantite_milliemes'] * $l['prix_centimes'], 1000);
    }

    /** $numerateur / $denominateur arrondi à l'entier, demi éloigné de zéro. */
    public static function arrondir(int $numerateur, int $denominateur): int
    {
        if ($denominateur <= 0) {
            throw new \InvalidArgumentException('Dénominateur nul ou négatif.');
        }
        $signe = $numerateur < 0 ? -1 : 1;
        return $signe * intdiv(2 * abs($numerateur) + $denominateur, 2 * $denominateur);
    }
}
