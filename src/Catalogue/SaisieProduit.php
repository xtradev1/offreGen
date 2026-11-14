<?php

declare(strict_types=1);

namespace OffreGen\Catalogue;

use OffreGen\Offre\Saisie;
use OffreGen\Offre\SaisieRefusee;

/**
 * Le formulaire d'un produit du catalogue, contrôlé champ par champ.
 *
 * @phpstan-import-type ProduitNeuf from \OffreGen\Donnees\DepotProduits
 */
final class SaisieProduit
{
    public const SYNONYMES_MAX = 1000;

    /**
     * @param array{designation: string, unite: string, prix: string, categorie: string, nouvelle_categorie: string, synonymes: string} $champs
     * @return ProduitNeuf
     */
    public static function lire(array $champs, ?string $uniteActuelle = null): array
    {
        $erreurs = [];
        $designation = Saisie::texte($champs['designation'], 255);
        if ($designation === null) {
            $erreurs['designation'] = 'Indiquez la désignation (255 caractères au plus).';
        }
        $unite = Saisie::unite($champs['unite'], $uniteActuelle);
        if ($unite === null) {
            $erreurs['unite'] = 'Choisissez une unité.';
        }
        $prix = Saisie::centimes($champs['prix']);
        if ($prix === null) {
            $erreurs['prix'] = "Prix en francs, par exemple 28 ou 28.50\u{00A0}; vide si vous ne le connaissez pas encore.";
        }
        $brute = trim($champs['nouvelle_categorie']) !== '' ? $champs['nouvelle_categorie'] : $champs['categorie'];
        $categorie = Saisie::texte($brute, 100);
        if ($categorie === null) {
            $erreurs['categorie'] = 'Choisissez une catégorie ou créez-en une (100 caractères au plus).';
        }
        $synonymes = self::synonymes($champs['synonymes']);
        if (mb_strlen($synonymes) > self::SYNONYMES_MAX) {
            $erreurs['synonymes'] = 'Trop de mots (' . self::SYNONYMES_MAX . ' caractères au plus).';
        }
        if ($designation === null || $unite === null || $prix === null || $categorie === null || $erreurs !== []) {
            throw new SaisieRefusee($erreurs);
        }
        return ['categorie' => $categorie, 'designation' => $designation, 'unite' => $unite, 'prix_centimes' => $prix,
                'synonymes' => $synonymes === '' ? null : $synonymes];
    }

    /** « murs,  Dispersion, murs » donne « murs, Dispersion » : séparés par une virgule, sans doublon. */
    public static function synonymes(string $saisie): string
    {
        $vus = [];
        foreach (explode(',', $saisie) as $mot) {
            $mot = trim((string) preg_replace('/\s+/u', ' ', $mot));
            if ($mot !== '' && !isset($vus[mb_strtolower($mot)])) {
                $vus[mb_strtolower($mot)] = $mot;
            }
        }
        return implode(', ', $vus);
    }
}
