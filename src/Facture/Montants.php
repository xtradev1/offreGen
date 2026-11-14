<?php

declare(strict_types=1);

namespace OffreGen\Facture;

use OffreGen\Compte\Reglages;
use OffreGen\Offre\Totaux;

/**
 * Les montants d'une facture, calculés par Totaux comme ceux de l'offre. Un brouillon suit les réglages du compte et
 * les acomptes émis jusqu'ici ; une facture émise ou annulée garde le taux, l'arrondi et la déduction figés à
 * l'émission : changer de taux ensuite ne la change pas.
 */
final class Montants
{
    /**
     * @param array{statut: string, genre: string, tva_pour_dix_mille: int|null, arrondi_cinq: int|null, deja_facture: int|null} $facture
     * @param list<array{quantite_milliemes: int, prix_centimes: int}> $lignes
     * @param int $dejaFactureVivant le TTC des acomptes émis de l'offre aujourd'hui (DepotFactures::dejaFacture)
     * @return array{lignes: list<int>, ht: int, tva: int, arrondi: int, ttc: int, taux: int, deja: int, a_payer: int}
     */
    public static function de(array $facture, array $lignes, Reglages $r, int $dejaFactureVivant): array
    {
        $figee = $facture['statut'] !== 'brouillon';
        $taux = $figee ? (int) $facture['tva_pour_dix_mille'] : $r->tvaPourDixMille;
        $arrondi = $figee ? $facture['arrondi_cinq'] === 1 : $r->arrondiCinqCentimes;
        $deja = $figee ? (int) $facture['deja_facture'] : ($facture['genre'] === 'finale' ? $dejaFactureVivant : 0);
        $t = Totaux::calculer($lignes, $taux, $arrondi);
        return $t + ['taux' => $taux, 'deja' => $deja, 'a_payer' => $t['ttc'] - $deja];
    }
}
