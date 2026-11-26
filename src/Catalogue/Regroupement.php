<?php

declare(strict_types=1);

namespace OffreGen\Catalogue;

/**
 * Range les lignes lues dans les anciennes offres en propositions pour le catalogue (chantier 2026-11-20_import-catalogue).
 *
 * 1. Les lignes au même libellé et à la même unité forment une étiquette.
 * 2. Une étiquette au libellé d'un produit du catalogue (ou à l'un de ses mots reconnus), même unité, le rejoint.
 * 3. Les paires restantes de même unité qui ont des mots en commun vont au comparateur, en un appel ; il réunit les
 *    formulations d'un même travail. Deux produits du catalogue ne sont jamais réunis.
 * 4. Chaque groupe qui contient au moins une ligne lue devient une proposition :
 *    - nouveau : aucun produit du catalogue, un seul prix ;
 *    - complete : un produit sans prix, un seul prix lu ;
 *    - identique : un produit au même prix que toutes les lignes ;
 *    - choix : plusieurs prix (entre offres, ou entre le catalogue et une offre). Aucun n'est choisi d'avance (D4, D5).
 *    Les autres libellés du groupe deviennent des mots reconnus (D7).
 *
 * @phpstan-import-type Produit from \OffreGen\Donnees\DepotProduits
 * @phpstan-type LigneImport array{designation: string, unite: string|null, unite_ecrite: string, prix_centimes: int, offre: int}
 * @phpstan-type Option array{prix: int, source: string}
 * @phpstan-type Proposition array{type: string, designation: string, unite: string|null, prix: int|null, produit_id: int|null, synonymes: list<string>, vu: int, options: list<Option>}
 * @phpstan-type Etiquette array{designation: string, unite: string|null, prix: list<array{int, int}>, rang: int}
 */
final class Regroupement
{
    public const SOURCE_CATALOGUE = 'votre catalogue';
    /** Sous ce score, deux libellés ne sont pas soumis au comparateur. */
    private const SEUIL = 0.25;
    /** Au-delà, les paires les moins ressemblantes ne sont pas demandées : 5 offres de 40 lignes en donnent moins. */
    private const PAIRES_MAX = 200;

    /** @var array<int, int> */
    private array $parent = [];
    /** @var array<int, int> racine => indice du produit du catalogue dans son groupe */
    private array $produitDuGroupe = [];

    public function __construct(private readonly Comparateur $comparateur)
    {
    }

    /**
     * @param list<LigneImport> $lignes dans l'ordre de lecture
     * @param list<Produit> $catalogue
     * @return list<Proposition> dans l'ordre de la première ligne lue de chaque groupe
     */
    public function propositions(array $lignes, array $catalogue): array
    {
        $etiquettes = self::etiquettes($lignes);
        $n = count($etiquettes);
        $this->parent = range(0, max(0, $n + count($catalogue) - 1));
        $this->produitDuGroupe = [];
        foreach (array_keys($catalogue) as $j) {
            $this->produitDuGroupe[$n + $j] = $j;
        }
        // 2. Libellé du catalogue, ou l'un de ses mots reconnus, à la même unité.
        foreach ($etiquettes as $i => $e) {
            foreach ($catalogue as $j => $p) {
                if ($e['unite'] === $p['unite'] && in_array(Recherche::normaliser($e['designation']), self::nomsDuProduit($p), true)) {
                    $this->unir($i, $n + $j);
                    break;
                }
            }
        }
        // 3. Paires à soumettre au comparateur, les plus ressemblantes d'abord.
        $paires = [];
        $libelles = array_merge(array_column($etiquettes, 'designation'), array_column($catalogue, 'designation'));
        $unites = array_merge(array_column($etiquettes, 'unite'), array_column($catalogue, 'unite'));
        for ($i = 0; $i < $n; $i++) {
            for ($k = $i + 1; $k < count($libelles); $k++) {
                if ($unites[$i] === null || $unites[$i] !== $unites[$k] || $this->racine($i) === $this->racine($k)) {
                    continue;
                }
                $score = Ressemblance::score($libelles[$i], $libelles[$k]);
                if ($score >= self::SEUIL) {
                    $paires[] = [$score, $i, $k];
                }
            }
        }
        usort($paires, static fn (array $x, array $y): int => $y[0] <=> $x[0]);
        $paires = array_slice($paires, 0, self::PAIRES_MAX);
        $reponses = $this->comparateur->memes(array_map(static fn (array $q): array => [$libelles[$q[1]], $libelles[$q[2]]], $paires));
        foreach ($paires as $q => [, $i, $k]) {
            if (($reponses[$q] ?? false) === true) {
                $this->unir($i, $k);
            }
        }
        // 4. Une proposition par groupe qui contient une ligne lue.
        $groupes = [];
        foreach (array_keys($etiquettes) as $i) {
            $groupes[$this->racine($i)][] = $i;
        }
        $propositions = [];
        foreach ($groupes as $racine => $membres) {
            $j = $this->produitDuGroupe[$racine] ?? null;
            $propositions[] = self::proposition(array_map(static fn (int $i): array => $etiquettes[$i], $membres), $j === null ? null : $catalogue[$j]);
        }
        return $propositions;
    }

    /**
     * @param list<LigneImport> $lignes
     * @return list<Etiquette>
     */
    private static function etiquettes(array $lignes): array
    {
        $etiquettes = [];
        foreach ($lignes as $rang => $l) {
            // Une unité inconnue ne se réunit qu'avec la même unité écrite.
            $cle = Recherche::normaliser($l['designation']) . '|' . ($l['unite'] ?? '?' . Recherche::normaliser($l['unite_ecrite']));
            $etiquettes[$cle] ??= ['designation' => $l['designation'], 'unite' => $l['unite'], 'prix' => [], 'rang' => $rang];
            $etiquettes[$cle]['prix'][] = [$l['prix_centimes'], $l['offre']];
        }
        return array_values($etiquettes);
    }

    /**
     * @param Produit $p
     * @return list<string>
     */
    private static function nomsDuProduit(array $p): array
    {
        $noms = [Recherche::normaliser($p['designation'])];
        foreach (explode(',', (string) $p['synonymes']) as $s) {
            if (trim($s) !== '') {
                $noms[] = Recherche::normaliser($s);
            }
        }
        return $noms;
    }

    /**
     * @param non-empty-list<Etiquette> $membres
     * @param Produit|null $produit
     * @return Proposition
     */
    private static function proposition(array $membres, ?array $produit): array
    {
        $principale = $membres[0];
        foreach ($membres as $e) {
            if (count($e['prix']) > count($principale['prix'])) {
                $principale = $e;
            }
        }
        $designation = $produit['designation'] ?? $principale['designation'];
        $connus = $produit === null ? [Recherche::normaliser($designation)] : self::nomsDuProduit($produit);
        $synonymes = [];
        foreach ($membres as $e) {
            $cle = Recherche::normaliser($e['designation']);
            if (!in_array($cle, $connus, true)) {
                $connus[] = $cle;
                $synonymes[] = $e['designation'];
            }
        }
        // Les prix distincts, dans l'ordre de lecture, avec les offres où chacun se lit.
        $offresParPrix = [];
        foreach ($membres as $e) {
            foreach ($e['prix'] as [$prix, $offre]) {
                $offresParPrix[$prix][$offre] = true;
            }
        }
        $vu = [];
        foreach ($offresParPrix as $offres) {
            $vu += $offres;
        }
        $options = [];
        foreach ($offresParPrix as $prix => $offres) {
            $options[] = ['prix' => $prix, 'source' => self::source(array_keys($offres))];
        }
        $proposition = ['designation' => $designation, 'unite' => $produit['unite'] ?? $principale['unite'], 'produit_id' => $produit['id'] ?? null,
                        'synonymes' => $synonymes, 'vu' => count($vu)];
        if ($produit !== null && $produit['prix_centimes'] > 0) {
            $autres = array_values(array_filter($options, static fn (array $o): bool => $o['prix'] !== $produit['prix_centimes']));
            return $autres === []
                ? ['type' => 'identique', 'prix' => $produit['prix_centimes'], 'options' => []] + $proposition
                : ['type' => 'choix', 'prix' => null, 'options' => [['prix' => $produit['prix_centimes'], 'source' => self::SOURCE_CATALOGUE], ...$autres]] + $proposition;
        }
        if (count($options) > 1) {
            return ['type' => 'choix', 'prix' => null, 'options' => $options] + $proposition;
        }
        return ['type' => $produit === null ? 'nouveau' : 'complete', 'prix' => $options[0]['prix'], 'options' => []] + $proposition;
    }

    /** @param list<int> $offres rangs à partir de 0 */
    private static function source(array $offres): string
    {
        sort($offres);
        $noms = array_map(static fn (int $o): string => (string) ($o + 1), $offres);
        if (count($noms) === 1) {
            return 'offre ' . $noms[0];
        }
        $dernier = array_pop($noms);
        return 'offres ' . implode(', ', $noms) . ' et ' . $dernier;
    }

    private function racine(int $i): int
    {
        while ($this->parent[$i] !== $i) {
            $this->parent[$i] = $this->parent[$this->parent[$i]];
            $i = $this->parent[$i];
        }
        return $i;
    }

    /** Réunit deux groupes, sauf s'ils contiennent chacun un produit du catalogue. */
    private function unir(int $a, int $b): void
    {
        [$ra, $rb] = [$this->racine($a), $this->racine($b)];
        if ($ra === $rb || (isset($this->produitDuGroupe[$ra]) && isset($this->produitDuGroupe[$rb]))) {
            return;
        }
        $this->parent[$rb] = $ra;
        if (isset($this->produitDuGroupe[$rb])) {
            $this->produitDuGroupe[$ra] = $this->produitDuGroupe[$rb];
            unset($this->produitDuGroupe[$rb]);
        }
    }
}
