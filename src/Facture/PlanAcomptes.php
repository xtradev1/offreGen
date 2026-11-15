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

    /** Le pourcentage proposé pour un acompte hors du plan des conditions. */
    public const POURCENT_PAR_DEFAUT = 30;

    /** Les puces de la feuille Facturer (E4, Xavier, 2026-11-15). */
    private const POURCENTAGES = [20, 30, 40, 50];

    /**
     * Les factures possibles, la proposée en premier ; vide si la finale existe déjà. Tant qu'il reste une tranche
     * d'acompte au plan des conditions, l'acompte suivant est proposé ; sinon la finale, et un acompte de plus reste
     * possible (E4 : un acompte se facture aussi sans plan, au pourcentage ou au montant choisi).
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
            return [$finale, ['genre' => 'acompte', 'rang' => $acomptesFaits + 1, 'nombre' => null, 'pourcent' => self::POURCENT_PAR_DEFAUT]];
        }
        return [['genre' => 'acompte', 'rang' => $acomptesFaits + 1, 'nombre' => $nombre, 'pourcent' => $tranches[$acomptesFaits]], $finale];
    }

    /**
     * Les pourcentages proposés en puces, croissants ; celui des conditions s'y ajoute s'il n'y est pas.
     *
     * @return list<int>
     */
    public static function pourcentages(?int $deConditions): array
    {
        $p = self::POURCENTAGES;
        if ($deConditions !== null && !in_array($deConditions, $p, true)) {
            $p[] = $deConditions;
            sort($p);
        }
        return $p;
    }

    /**
     * Un acompte d'un montant tapé TVA comprise (E4) : le HT de chaque taux de l'offre, dans la même proportion pour
     * tous, ajusté au centime pour que le TTC calculé (Totaux, arrondi compris) tombe sur le montant voulu, ou au plus
     * près quand l'arrondi aux 5 centimes l'empêche. Un seul taux, celui de l'entreprise, garde un taux nul.
     *
     * @param list<array{taux: int, ht: int, tva: int}> $parTaux Totaux::parTaux des lignes de l'offre
     * @return list<array{taux: int|null, ht: int}>
     */
    public static function partsPourMontant(array $parTaux, int $ttcVoulu, int $tauxEntreprise, bool $arrondiCinq): array
    {
        $groupes = array_values(array_filter($parTaux, static fn (array $g): bool => $g['ht'] > 0));
        if ($groupes === []) {
            $groupes = [['taux' => $tauxEntreprise, 'ht' => 1, 'tva' => 0]];
        }
        $ttcOffre = array_sum(array_map(static fn (array $g): int => $g['ht'] + Totaux::arrondir($g['ht'] * $g['taux'], 10000), $groupes));
        $seul = count($groupes) === 1 && $groupes[0]['taux'] === $tauxEntreprise;
        $taux = array_map(static fn (array $g): ?int => $seul ? null : $g['taux'], $groupes);
        $hts = array_map(static fn (array $g): int => Totaux::arrondir($g['ht'] * $ttcVoulu, $ttcOffre), $groupes);
        // Un taux minuscule dans l'offre donnerait une ligne à 0.00, refusée ensuite comme « sans prix » (revue du 25).
        $gardes = array_keys(array_filter($hts, static fn (int $ht): bool => $ht > 0));
        if ($gardes !== [] && count($gardes) < count($hts)) {
            $groupes = array_values(array_intersect_key($groupes, array_flip($gardes)));
            $taux = array_values(array_intersect_key($taux, array_flip($gardes)));
            $hts = array_values(array_intersect_key($hts, array_flip($gardes)));
        }
        $ecart = static function (array $hts) use ($taux, $ttcVoulu, $tauxEntreprise, $arrondiCinq): int {
            $lignes = [];
            foreach ($hts as $i => $ht) {
                $lignes[] = ['quantite_milliemes' => 1000, 'prix_centimes' => (int) $ht, 'tva_pour_dix_mille' => $taux[$i]];
            }
            return $ttcVoulu - Totaux::calculer($lignes, $tauxEntreprise, $arrondiCinq)['ttc'];
        };
        // La plus grande part absorbe l'essentiel de l'écart d'arrondi...
        $grande = (int) array_search(max([0, ...$hts]), $hts, true);
        $tauxGrande = $groupes[$grande]['taux'];
        for ($i = 0; $i < 20 && ($e = $ecart($hts)) !== 0; $i++) {
            $pas = Totaux::arrondir($e * 10000, 10000 + $tauxGrande);
            $hts[$grande] += $pas === 0 ? ($e > 0 ? 1 : -1) : $pas;
        }
        // ... puis une recherche de deux centimes au plus sur chaque part, car la TVA arrondie de chaque taux peut
        // faire sauter le total d'un centime.
        $meilleurs = $hts;
        $ecartMin = abs($ecart($hts));
        $n = count($hts);
        for ($k = 0; $k < 5 ** $n && $ecartMin > 0 && $n <= 4; $k++) {
            $essai = $hts;
            for ($i = 0, $reste = $k; $i < $n; $i++, $reste = intdiv($reste, 5)) {
                $essai[$i] += $reste % 5 - 2;
            }
            $e = abs($ecart($essai));
            if ($e < $ecartMin) {
                [$ecartMin, $meilleurs] = [$e, $essai];
            }
        }
        $parts = [];
        foreach ($meilleurs as $i => $ht) {
            $parts[] = ['taux' => $taux[$i], 'ht' => $ht];
        }
        return $parts;
    }

    /** Le montant HT d'un acompte, en centimes : le pourcentage du HT de l'offre, au centime. */
    public static function montant(int $htOffre, int $pourcent): int
    {
        return Totaux::arrondir($htOffre * $pourcent, 100);
    }

    /**
     * L'acompte réparti par taux de TVA (D11, 2026-11-15) : le pourcentage du HT de chaque taux, au centime, pour que
     * l'acompte porte la TVA de ce qu'il paie. Un seul taux, celui de l'entreprise, garde un taux nul, comme avant.
     *
     * @param list<array{taux: int, ht: int, tva: int}> $parTaux Totaux::parTaux des lignes de l'offre
     * @return list<array{taux: int|null, ht: int}>
     */
    public static function parts(array $parTaux, int $pourcent, int $tauxEntreprise): array
    {
        if (count($parTaux) === 1 && $parTaux[0]['taux'] === $tauxEntreprise) {
            return [['taux' => null, 'ht' => self::montant($parTaux[0]['ht'], $pourcent)]];
        }
        return array_map(static fn (array $g): array => ['taux' => $g['taux'], 'ht' => self::montant($g['ht'], $pourcent)], $parTaux);
    }

    /**
     * La ligne d'une facture d'acompte : « sur N » quand l'acompte suit le plan des conditions, le pourcentage quand
     * il y en a un (un montant tapé n'en a pas).
     */
    public static function designation(int $rang, ?int $nombre, ?int $pourcent, int $numeroOffre): string
    {
        $sur = $nombre === null ? '' : " sur $nombre";
        $part = $pourcent === null ? '' : " ($pourcent\u{00A0}%)";
        return "Acompte $rang$sur$part, selon offre n°\u{00A0}$numeroOffre";
    }

    /** Date de la facture plus le délai des conditions (« net à N jours », « à N jours ») ; 30 jours sinon. */
    public static function echeance(Reglages $r, string $genre, \DateTimeImmutable $date): \DateTimeImmutable
    {
        $jours = $genre === 'finale' && $r->paiementType !== 'acomptes' ? $r->paiementJours : self::JOURS_PAR_DEFAUT;
        return $date->modify("+$jours days");
    }
}
