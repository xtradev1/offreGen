<?php

declare(strict_types=1);

namespace OffreGen\Catalogue;

/**
 * Recherche dans le catalogue, sans tenir compte des majuscules ni des accents : « facade » trouve
 * « Peinture de façade ». public/js/app.js filtre la même liste, à la frappe, avec la même règle.
 *
 * @phpstan-import-type Produit from \OffreGen\Donnees\DepotProduits
 */
final class Recherche
{
    /**
     * Le texte cherché d'un produit : désignation, catégorie et mots reconnus, normalisés.
     *
     * @param Produit $p
     */
    public static function texte(array $p): string
    {
        return self::normaliser($p['designation'] . ' ' . $p['categorie'] . ' ' . ($p['synonymes'] ?? ''));
    }

    public static function normaliser(string $s): string
    {
        $d = \Normalizer::normalize(mb_strtolower($s), \Normalizer::FORM_D);
        return trim((string) preg_replace(['/\p{Mn}+/u', '/\s+/u'], ['', ' '], $d === false ? mb_strtolower($s) : $d));
    }

    /**
     * @param list<Produit> $produits
     * @return list<Produit>
     */
    public static function filtrer(array $produits, string $q, string $categorie = ''): array
    {
        $mots = array_filter(explode(' ', self::normaliser($q)));
        return array_values(array_filter($produits, static function (array $p) use ($mots, $categorie): bool {
            if ($categorie !== '' && $p['categorie'] !== $categorie) {
                return false;
            }
            $texte = self::texte($p);
            foreach ($mots as $mot) {
                if (!str_contains($texte, $mot)) {
                    return false;
                }
            }
            return true;
        }));
    }
}
