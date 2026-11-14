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
     * @param list<array{quantite_milliemes: int, prix_centimes: int}> $lignes
     * @param bool $arrondiCinq arrondir le total aux 5 centimes (réglage du compte)
     * @return array{lignes: list<int>, ht: int, tva: int, arrondi: int, ttc: int}
     */
    public static function calculer(array $lignes, int $tvaPourDixMille = self::TVA_NORMALE, bool $arrondiCinq = true): array
    {
        $parLigne = [];
        foreach ($lignes as $l) {
            $parLigne[] = self::arrondir($l['quantite_milliemes'] * $l['prix_centimes'], 1000);
        }
        $ht = array_sum($parLigne);
        $tva = self::arrondir($ht * $tvaPourDixMille, 10000);
        $brut = $ht + $tva;
        $ttc = $arrondiCinq ? self::arrondir($brut, 5) * 5 : $brut;
        return ['lignes' => $parLigne, 'ht' => $ht, 'tva' => $tva, 'arrondi' => $ttc - $brut, 'ttc' => $ttc];
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
