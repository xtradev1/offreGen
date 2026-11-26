<?php

declare(strict_types=1);

namespace OffreGen\Catalogue;

/**
 * Sans modèle (développement, tests) : lit les lignes de tableau dont une cellule est une unité connue et la suivante
 * un nombre. Le libellé est la plus longue cellule sans chiffre seul ; le prix, le premier nombre après l'unité.
 */
final class LecteurFactice implements Lecteur
{
    public function lire(array $texte): LectureOffre
    {
        $lignes = [];
        foreach ($texte as $i => $ligne) {
            $cellules = array_map('trim', explode('|', $ligne));
            foreach ($cellules as $k => $cellule) {
                $unite = UniteLue::normaliser($cellule);
                $prix = isset($cellules[$k + 1]) ? (LecteurApi::nombres($cellules[$k + 1])[0] ?? 0) : 0;
                if ($unite === null || $prix <= 0) {
                    continue;
                }
                $libelles = array_filter($cellules, static fn (string $c): bool => preg_match('/\p{L}{3}/u', $c) === 1 && UniteLue::normaliser($c) === null);
                usort($libelles, static fn (string $x, string $y): int => mb_strlen($y) <=> mb_strlen($x));
                if ($libelles !== []) {
                    $lignes[] = new LigneLue($libelles[0], $unite, $cellule, $prix, $i + 1);
                }
                break;
            }
        }
        return new LectureOffre($lignes, [], 0, 0);
    }
}
