<?php

declare(strict_types=1);

namespace OffreGen\Catalogue;

use OffreGen\Offre\Saisie;

/**
 * Ce que les réponses de l'artisan à l'écran « Voici ce qu'on intègre » écrivent au catalogue. Rien n'est écrit tant
 * qu'une réponse manque ou qu'une saisie est fausse : la page revient avec un message sous chaque ligne concernée.
 *
 * Réponses par proposition : « garder » (case cochée), « designation », « unite », « prix » (le crayon) pour un
 * nouveau produit ou un prix trouvé ; « choix » (rang de l'option) pour un prix à choisir.
 *
 * @phpstan-import-type Proposition from Regroupement
 * @phpstan-type Action array{0: 'creer', 1: array{categorie: string, designation: string, unite: string, prix_centimes: int, synonymes: string|null}}|array{0: 'prix', 1: int, 2: int}|array{0: 'synonymes', 1: int, 2: list<string>}
 * @phpstan-type Bilan array{nouveaux: int, completes: int, changes: int, cote: int}
 */
final class Integration
{
    /** Les produits nouveaux arrivent dans cette catégorie ; l'artisan les range ensuite. */
    public const CATEGORIE = 'Importés';

    /**
     * @param list<Proposition> $propositions
     * @param array<int, array<string, string>> $reponses
     * @return array{erreurs: array<int, string>, actions: list<Action>, bilan: Bilan}
     */
    public static function actions(array $propositions, array $reponses): array
    {
        $erreurs = [];
        $actions = [];
        $bilan = ['nouveaux' => 0, 'completes' => 0, 'changes' => 0, 'cote' => 0];
        foreach ($propositions as $i => $p) {
            $r = $reponses[$i] ?? [];
            $id = $p['produit_id'];
            if ($p['type'] === 'identique') {
                if ($id !== null && $p['synonymes'] !== []) {
                    $actions[] = ['synonymes', $id, $p['synonymes']];
                }
                continue;
            }
            if ($p['type'] === 'choix') {
                $rang = $r['choix'] ?? '';
                $option = ctype_digit($rang) ? ($p['options'][(int) $rang] ?? null) : null;
                if ($option === null) {
                    $erreurs[$i] = 'Choisissez un prix.';
                    continue;
                }
                if ($id === null) {
                    $actions[] = ['creer', self::produit($p['designation'], (string) $p['unite'], $option['prix'], $p['synonymes'])];
                    $bilan['nouveaux']++;
                    continue;
                }
                $catalogue = $p['options'][0]['source'] === Regroupement::SOURCE_CATALOGUE ? $p['options'][0]['prix'] : 0;
                if ($option['prix'] !== $catalogue) {
                    $actions[] = ['prix', $id, $option['prix']];
                    $bilan[$catalogue === 0 ? 'completes' : 'changes']++;
                }
                if ($p['synonymes'] !== []) {
                    $actions[] = ['synonymes', $id, $p['synonymes']];
                }
                continue;
            }
            if (($r['garder'] ?? '') !== '1') {
                $bilan['cote']++;
                continue;
            }
            $prix = Saisie::centimes($r['prix'] ?? (string) ($p['prix'] / 100));
            if ($prix === null || $prix === 0) {
                $erreurs[$i] = 'Prix en francs, par exemple 28 ou 28.50.';
                continue;
            }
            if ($p['type'] === 'complete' && $id !== null) {
                $actions[] = ['prix', $id, $prix];
                if ($p['synonymes'] !== []) {
                    $actions[] = ['synonymes', $id, $p['synonymes']];
                }
                $bilan['completes']++;
                continue;
            }
            $designation = Saisie::texte($r['designation'] ?? $p['designation'], 255);
            $unite = Saisie::unite($r['unite'] ?? (string) $p['unite']);
            if ($designation === null || $unite === null) {
                $erreurs[$i] = $designation === null ? 'Indiquez la désignation (255 caractères au plus).' : 'Choisissez une unité.';
                continue;
            }
            $actions[] = ['creer', self::produit($designation, $unite, $prix, $p['synonymes'])];
            $bilan['nouveaux']++;
        }
        return ['erreurs' => $erreurs, 'actions' => $erreurs === [] ? $actions : [], 'bilan' => $bilan];
    }

    /**
     * @param list<string> $synonymes
     * @return array{categorie: string, designation: string, unite: string, prix_centimes: int, synonymes: string|null}
     */
    private static function produit(string $designation, string $unite, int $prix, array $synonymes): array
    {
        $s = self::synonymes(null, $synonymes);
        return ['categorie' => self::CATEGORIE, 'designation' => $designation, 'unite' => $unite, 'prix_centimes' => $prix, 'synonymes' => $s === '' ? null : $s];
    }

    /**
     * Les mots reconnus d'un produit, augmentés des nouveaux, sans doublon, dans la limite de la fiche produit.
     *
     * @param list<string> $nouveaux
     */
    public static function synonymes(?string $existants, array $nouveaux): string
    {
        $resultat = SaisieProduit::synonymes((string) $existants);
        foreach ($nouveaux as $mot) {
            $essai = SaisieProduit::synonymes($resultat === '' ? $mot : $resultat . ', ' . $mot);
            if (mb_strlen($essai) <= SaisieProduit::SYNONYMES_MAX) {
                $resultat = $essai;
            }
        }
        return $resultat;
    }
}
