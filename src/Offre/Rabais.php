<?php

declare(strict_types=1);

namespace OffreGen\Offre;

/**
 * Un rabais sous le sous-total, avant la TVA (décisions D4 B et D15 A de Xavier, 2026-11-21) : en pour cent du
 * sous-total des lignes, ou en francs hors TVA. Une offre en porte plusieurs ; chacun se compte sur le même sous-total,
 * et leur somme ne le dépasse jamais. La facture finale copie ceux de l'offre.
 *
 * Gardés en JSON sur la ligne de l'offre (colonne rabais) : ils se lisent et s'écrivent toujours ensemble, avec leur
 * offre, et la portée de l'artisan est celle de l'offre.
 */
final readonly class Rabais
{
    public const MAX = 5;
    public const LIBELLE_MAX = 120;
    public const LIBELLE_PAR_DEFAUT = 'Rabais';
    /** Les puces de la fenêtre : 5 % et 10 % en un geste, sinon un pourcentage ou un montant tapé. */
    public const CHOIX = ['5', '10', 'pct', 'chf'];

    private function __construct(public string $libelle, public ?int $pourDixMille, public ?int $centimes)
    {
    }

    public static function pourcent(string $libelle, int $pourDixMille): self
    {
        return new self($libelle, $pourDixMille, null);
    }

    public static function montant(string $libelle, int $centimes): self
    {
        return new self($libelle, null, $centimes);
    }

    /** Le rabais sur ce sous-total, avant le plafond (Totaux l'applique). */
    public function sur(int $sousTotal): int
    {
        return $this->pourDixMille !== null ? Totaux::arrondir($sousTotal * $this->pourDixMille, 10000) : (int) $this->centimes;
    }

    /** La puce de la fenêtre qui le montre. */
    public function choix(): string
    {
        return match (true) {
            $this->pourDixMille === 500 => '5',
            $this->pourDixMille === 1000 => '10',
            $this->pourDixMille !== null => 'pct',
            default => 'chf',
        };
    }

    /**
     * La saisie de la fenêtre. Libellé vide : « Rabais ». Pourcentage : plus de 0 et moins de 100, deux décimales au
     * plus ; montant : plus de 0.
     *
     * @return array{self|null, array<string, string>}
     */
    public static function depuisSaisie(string $libelle, string $choix, string $valeur): array
    {
        $erreurs = [];
        $lib = trim((string) preg_replace('/\s+/u', ' ', $libelle));
        $lib = $lib === '' ? self::LIBELLE_PAR_DEFAUT : $lib;
        if (mb_strlen($lib) > self::LIBELLE_MAX) {
            $erreurs['libelle'] = 'Libellé de ' . self::LIBELLE_MAX . ' caractères au plus.';
        }
        $rabais = null;
        if ($choix === '5' || $choix === '10') {
            $rabais = self::pourcent($lib, (int) $choix * 100);
        } elseif ($choix === 'pct') {
            // Deux décimales, comme un prix : 5.5 % se lit 550 dix-millièmes.
            $p = trim($valeur) === '' ? null : Saisie::centimes($valeur);
            if ($p === null || $p <= 0 || $p >= 10000) {
                $erreurs['valeur'] = 'Pourcentage de plus de 0 et de moins de 100, par exemple 7.5.';
            } else {
                $rabais = self::pourcent($lib, $p);
            }
        } elseif ($choix === 'chf') {
            $m = trim($valeur) === '' ? null : Saisie::centimes($valeur);
            if ($m === null || $m <= 0) {
                $erreurs['valeur'] = 'Montant hors TVA, par exemple 150.';
            } else {
                $rabais = self::montant($lib, $m);
            }
        } else {
            $erreurs['choix'] = 'Choisissez 5 %, 10 %, un autre pourcentage ou un montant.';
        }
        return [$erreurs === [] ? $rabais : null, $erreurs];
    }

    /**
     * Les rabais gardés sur une offre ou une facture. Une entrée illisible est ignorée : un rabais abîmé ne doit
     * jamais empêcher d'ouvrir l'offre.
     *
     * @return list<self>
     */
    public static function liste(?string $json): array
    {
        if ($json === null || $json === '') {
            return [];
        }
        $brut = json_decode($json, true);
        if (!is_array($brut)) {
            return [];
        }
        $liste = [];
        foreach ($brut as $r) {
            if (!is_array($r) || !is_string($r['libelle'] ?? null)) {
                continue;
            }
            if (is_int($r['pour_dix_mille'] ?? null) && $r['pour_dix_mille'] > 0 && $r['pour_dix_mille'] < 10000) {
                $liste[] = self::pourcent($r['libelle'], $r['pour_dix_mille']);
            } elseif (is_int($r['centimes'] ?? null) && $r['centimes'] > 0) {
                $liste[] = self::montant($r['libelle'], $r['centimes']);
            }
        }
        return array_slice($liste, 0, self::MAX);
    }

    /** @param list<self> $liste */
    public static function versJson(array $liste): ?string
    {
        if ($liste === []) {
            return null;
        }
        return json_encode(array_map(static fn (self $r): array => ['libelle' => $r->libelle, 'pour_dix_mille' => $r->pourDixMille, 'centimes' => $r->centimes], $liste),
            JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }
}
