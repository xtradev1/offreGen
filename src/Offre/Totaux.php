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
     * Ordre (D15 A) : lignes, sous-total, rabais, HT, TVA, arrondi. sous_total : la somme des lignes ; ht : ce qui
     * reste après les rabais, la base de la TVA. Sans rabais, les deux sont égaux.
     *
     * Options et variantes (chantier 2026-11-22_options-variantes) : seules les lignes du total comptent (Options::masque),
     * les autres ont un montant 0 dans « lignes », au même indice. avec_options : le total si le client prend toutes les
     * options encore libres, rabais en pour cent compris (réponse de Xavier du 22.11) ; null s'il n'y en a pas.
     *
     * @param list<array{id?: int, quantite_milliemes: int, prix_centimes: int, tva_pour_dix_mille?: int|null, offerte?: int|bool, nature?: string, remplace_id?: int|null, retenue?: int|bool}> $lignes
     * @param int $tvaPourDixMille le taux de l'entreprise
     * @param bool $arrondiCinq arrondir le total aux 5 centimes (réglage du compte)
     * @param list<Rabais> $rabais
     * @return array{lignes: list<int>, sous_total: int, rabais: list<array{libelle: string, pour_dix_mille: int|null, montant: int}>, ht: int, tva: int, arrondi: int, ttc: int, par_taux: list<array{taux: int, ht: int, tva: int}>, avec_options: array{rabais: int, ht: int, tva: int, arrondi: int, ttc: int}|null}
     */
    public static function calculer(array $lignes, int $tvaPourDixMille = self::TVA_NORMALE, bool $arrondiCinq = true, array $rabais = []): array
    {
        $masque = Options::masque($lignes);
        $parLigne = [];
        foreach ($lignes as $i => $l) {
            $parLigne[] = $masque[$i] ? self::montant($l) : 0;
        }
        $sousTotal = array_sum($parLigne);
        $appliques = self::rabais($sousTotal, $rabais);
        $ht = $sousTotal - array_sum(array_column($appliques, 'montant'));
        $parTaux = self::parTauxDans($lignes, $masque, $tvaPourDixMille, $rabais);
        $tva = array_sum(array_column($parTaux, 'tva'));
        $brut = $ht + $tva;
        $ttc = $arrondiCinq ? self::arrondir($brut, 5) * 5 : $brut;
        return ['lignes' => $parLigne, 'sous_total' => $sousTotal, 'rabais' => $appliques, 'ht' => $ht, 'tva' => $tva, 'arrondi' => $ttc - $brut,
            'ttc' => $ttc, 'par_taux' => $parTaux,
            'avec_options' => Options::aDesOptionsLibres($lignes) ? self::avecOptions($lignes, $tvaPourDixMille, $arrondiCinq, $rabais) : null];
    }

    /**
     * @param list<array{id?: int, quantite_milliemes: int, prix_centimes: int, tva_pour_dix_mille?: int|null, offerte?: int|bool, nature?: string, remplace_id?: int|null, retenue?: int|bool}> $lignes
     * @param list<Rabais> $rabais
     * @return array{rabais: int, ht: int, tva: int, arrondi: int, ttc: int}
     */
    private static function avecOptions(array $lignes, int $tvaPourDixMille, bool $arrondiCinq, array $rabais): array
    {
        $masque = Options::masqueAvecOptions($lignes);
        $sousTotal = 0;
        foreach ($lignes as $i => $l) {
            $sousTotal += $masque[$i] ? self::montant($l) : 0;
        }
        $rabaisTotal = array_sum(array_column(self::rabais($sousTotal, $rabais), 'montant'));
        $tva = array_sum(array_column(self::parTauxDans($lignes, $masque, $tvaPourDixMille, $rabais), 'tva'));
        $brut = $sousTotal - $rabaisTotal + $tva;
        $ttc = $arrondiCinq ? self::arrondir($brut, 5) * 5 : $brut;
        return ['rabais' => $rabaisTotal, 'ht' => $sousTotal - $rabaisTotal, 'tva' => $tva, 'arrondi' => $ttc - $brut, 'ttc' => $ttc];
    }

    /**
     * Chaque rabais sur le même sous-total, arrondi au centime ; leur somme ne dépasse jamais le sous-total.
     *
     * @param list<Rabais> $rabais
     * @return list<array{libelle: string, pour_dix_mille: int|null, montant: int}>
     */
    public static function rabais(int $sousTotal, array $rabais): array
    {
        $appliques = [];
        $somme = 0;
        foreach ($rabais as $r) {
            $m = max(0, min($r->sur($sousTotal), $sousTotal - $somme));
            $somme += $m;
            $appliques[] = ['libelle' => $r->libelle, 'pour_dix_mille' => $r->pourDixMille, 'montant' => $m];
        }
        return $appliques;
    }

    /**
     * La TVA détaillée par taux, du plus haut au plus bas : ce que le document montre dès qu'il y a plusieurs taux.
     * Les rabais se répartissent entre les taux au prorata de leur montant (le reste d'arrondi va au plus gros).
     *
     * Les lignes hors du total (option ou variante non retenue, ligne remplacée) n'y comptent pas.
     *
     * @param list<array{id?: int, quantite_milliemes: int, prix_centimes: int, tva_pour_dix_mille?: int|null, offerte?: int|bool, nature?: string, remplace_id?: int|null, retenue?: int|bool}> $lignes
     * @param list<Rabais> $rabais
     * @return list<array{taux: int, ht: int, tva: int}>
     */
    public static function parTaux(array $lignes, int $tvaPourDixMille = self::TVA_NORMALE, array $rabais = []): array
    {
        return self::parTauxDans($lignes, Options::masque($lignes), $tvaPourDixMille, $rabais);
    }

    /**
     * @param list<array{quantite_milliemes: int, prix_centimes: int, tva_pour_dix_mille?: int|null, offerte?: int|bool}> $lignes
     * @param list<bool> $masque les lignes qui comptent, au même indice
     * @param list<Rabais> $rabais
     * @return list<array{taux: int, ht: int, tva: int}>
     */
    private static function parTauxDans(array $lignes, array $masque, int $tvaPourDixMille, array $rabais): array
    {
        $ht = [];
        foreach ($lignes as $i => $l) {
            if (!$masque[$i] || Offerte::est($l)) {
                continue;
            }
            $taux = $l['tva_pour_dix_mille'] ?? $tvaPourDixMille;
            $ht[$taux] = ($ht[$taux] ?? 0) + self::montant($l);
        }
        if ($ht === []) {
            $ht[$tvaPourDixMille] = 0;
        }
        krsort($ht);
        $total = array_sum(array_column(self::rabais(array_sum($ht), $rabais), 'montant'));
        if ($total > 0) {
            $ht = self::repartir($ht, $total);
        }
        $groupes = [];
        foreach ($ht as $taux => $montant) {
            $groupes[] = ['taux' => $taux, 'ht' => $montant, 'tva' => self::arrondir($montant * $taux, 10000)];
        }
        return $groupes;
    }

    /**
     * @param non-empty-array<int, int> $ht montant de chaque taux, de somme positive
     * @return non-empty-array<int, int>
     */
    private static function repartir(array $ht, int $rabais): array
    {
        $sous = array_sum($ht);
        $parts = [];
        foreach ($ht as $taux => $m) {
            $parts[$taux] = self::arrondir($m * $rabais, $sous);
        }
        $plusGros = array_search(max($ht), $ht, true);
        $parts[$plusGros] += $rabais - array_sum($parts);
        foreach ($ht as $taux => $m) {
            $ht[$taux] = $m - $parts[$taux];
        }
        return $ht;
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

    /**
     * Ce que le client paie s'il prend l'escompte (D13 A, D14 A, 2026-11-21) : le montant à payer moins le pourcentage,
     * aux 5 centimes si le compte arrondit ainsi. Le code QR garde le montant plein.
     */
    public static function escompte(int $aPayer, int $pourDixMille, bool $arrondiCinq): int
    {
        $net = $aPayer - self::arrondir($aPayer * $pourDixMille, 10000);
        return $arrondiCinq ? self::arrondir($net, 5) * 5 : $net;
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
