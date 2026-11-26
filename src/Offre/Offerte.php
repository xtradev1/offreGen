<?php

declare(strict_types=1);

namespace OffreGen\Offre;

/**
 * Ligne offerte (décisions D1, D2 et D17 de Xavier, 2026-11-21). Une ligne offerte compte 0. Son prix reste : barré
 * sur le document, ou 0 pour « Offert » seul. Taper 0 offre la ligne ; un prix vide la laisse « Prix manquant ».
 */
final class Offerte
{
    public const CHOIX_OFFERTE = 'offerte';
    public const CHOIX_FACTUREE = 'facturee';

    /** @param array{prix_centimes: int, offerte?: int|bool} $ligne */
    public static function prixManquant(array $ligne): bool
    {
        return $ligne['prix_centimes'] === 0 && !self::est($ligne);
    }

    /** @param array{offerte?: int|bool} $ligne */
    public static function est(array $ligne): bool
    {
        return (bool) ($ligne['offerte'] ?? false);
    }

    /**
     * L'état après une saisie.
     *
     * @param bool $avant la ligne était offerte
     * @param int $prixAvant son prix avant la saisie
     * @param string|null $prixTape le champ prix envoyé, null s'il n'est pas parti (quantité seule)
     * @param int $prixApres le prix lu dans $prixTape (0 pour un champ vide)
     * @param string|null $choix « Facturée / Offerte » de la fenêtre de la ligne ; null depuis la liste des lignes
     */
    public static function apresSaisie(bool $avant, int $prixAvant, ?string $prixTape, int $prixApres, ?string $choix): bool
    {
        $vide = $prixTape !== null && trim($prixTape) === '';
        // Un 0 tapé à l'instant offre la ligne (D2). Celui d'une ligne déjà offerte à 0 n'est pas une nouvelle saisie.
        $zeroTape = $prixTape !== null && !$vide && $prixApres === 0 && !($avant && $prixAvant === 0);
        if ($choix !== null) {
            return $choix === self::CHOIX_OFFERTE || $zeroTape;
        }
        if ($prixTape === null) {
            return $avant;
        }
        if ($vide) {
            return false;
        }
        if ($prixApres === 0) {
            return true;
        }
        // Un vrai prix sur une ligne offerte à 0 la refacture ; sur une ligne offerte barrée, il change le prix barré.
        return $avant && $prixAvant !== 0;
    }
}
