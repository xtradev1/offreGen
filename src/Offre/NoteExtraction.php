<?php

declare(strict_types=1);

namespace OffreGen\Offre;

/**
 * La note d'une extraction contre l'offre attendue (sous-chantier 29, SPEC « La note » ; options et variantes,
 * chantier 2026-11-22). Sert le banc du poste de travail (outils/banc-corpus.php), jamais l'application.
 *
 * Chaque ligne attendue est juste ou fausse, avec ses raisons ; toute ligne proposée sans correspondance compte en
 * trop. Taux = justes / (attendues + en trop) ; corrections = fausses + en trop.
 *
 * Une ligne attendue : produit_id (ou null pour une ligne libre), designation, quantite, unite et prix (ligne libre),
 * prix_dicte (produit du catalogue : le prix dit quand il diffère de celui du catalogue, sinon null), nature (ligne,
 * option, variante), remplace (rang 0-based de la ligne attendue qu'une variante remplace).
 * Une ligne proposée : les champs de LigneExtraite (get_object_vars) ; nature et remplace (rang 0-based dans la liste
 * proposée) valent « ligne » et null quand ils manquent.
 *
 * @phpstan-type Attendue array{produit_id: int|null, designation: string, quantite: int|float, unite?: string, prix?: int|float|null, prix_dicte?: int|float|null, nature?: string, remplace?: int|null}
 * @phpstan-type Proposee array{produitId: int|null, designation: string, quantiteMilliemes: int, unite: string, prixCentimes: int, prixDicteCentimes: int|null, nature?: string, remplace?: int|null}
 * @phpstan-type Note array{attendues: int, justes: int, fausses: list<array{ligne: int, designation: string, raisons: list<string>}>, en_trop: list<string>, taux: float, corrections: int, natures: array{justes: int, total: int}}
 */
final class NoteExtraction
{
    /** Ressemblance minimale de deux désignations libres pour les apparier (similar_text, en pour cent). */
    public const RESSEMBLANCE = 55.0;

    /**
     * @param list<Attendue> $attendues
     * @param list<Proposee> $proposees
     * @return Note
     */
    public static function noter(array $attendues, array $proposees): array
    {
        $paires = self::apparier($attendues, $proposees);
        $justes = 0;
        $fausses = [];
        $natures = ['justes' => 0, 'total' => 0];
        foreach ($attendues as $i => $a) {
            $nature = $a['nature'] ?? Options::LIGNE;
            if ($nature !== Options::LIGNE) {
                $natures['total']++;
            }
            if (!isset($paires[$i])) {
                $fausses[] = ['ligne' => $i + 1, 'designation' => $a['designation'], 'raisons' => ['absente']];
                continue;
            }
            $p = $proposees[$paires[$i]];
            $raisons = [];
            if (($p['nature'] ?? Options::LIGNE) !== $nature) {
                $raisons[] = 'nature';
            } elseif ($nature !== Options::LIGNE) {
                $natures['justes']++;
            }
            if ($nature === Options::VARIANTE) {
                $base = $a['remplace'] ?? null;
                if ($base === null || !isset($paires[$base]) || ($p['remplace'] ?? null) !== $paires[$base]) {
                    $raisons[] = 'base';
                }
            }
            if ($p['quantiteMilliemes'] !== (int) round($a['quantite'] * 1000)) {
                $raisons[] = 'quantite';
            }
            if ($a['produit_id'] === null) {
                if (self::unite($p['unite']) !== self::unite($a['unite'] ?? '')) {
                    $raisons[] = 'unite';
                }
                if ($p['prixCentimes'] !== self::centimes($a['prix'] ?? null)) {
                    $raisons[] = 'prix';
                }
            } else {
                $dicte = $a['prix_dicte'] ?? null;
                if ($p['prixDicteCentimes'] !== ($dicte === null ? null : self::centimes($dicte))) {
                    $raisons[] = 'prix dicte';
                }
            }
            if ($raisons === []) {
                $justes++;
            } else {
                $fausses[] = ['ligne' => $i + 1, 'designation' => $a['designation'], 'raisons' => $raisons];
            }
        }
        $enTrop = [];
        $prises = array_flip($paires);
        foreach ($proposees as $j => $p) {
            if (!isset($prises[$j])) {
                $enTrop[] = $p['designation'];
            }
        }
        $denominateur = count($attendues) + count($enTrop);
        return ['attendues' => count($attendues), 'justes' => $justes, 'fausses' => $fausses, 'en_trop' => $enTrop,
            'taux' => $denominateur === 0 ? 1.0 : $justes / (float) $denominateur, 'corrections' => count($fausses) + count($enTrop), 'natures' => $natures];
    }

    /**
     * Attendue i → proposée j. Un produit du catalogue s'apparie au même produit ; une ligne libre à la ligne libre la plus
     * ressemblante, au-dessus du seuil. Chaque proposée ne sert qu'une fois, dans l'ordre des attendues.
     *
     * @param list<Attendue> $attendues
     * @param list<Proposee> $proposees
     * @return array<int, int>
     */
    private static function apparier(array $attendues, array $proposees): array
    {
        $paires = [];
        $libres = array_fill(0, count($proposees), true);
        foreach ($attendues as $i => $a) {
            $meilleure = null;
            $score = self::RESSEMBLANCE;
            foreach ($proposees as $j => $p) {
                if (!$libres[$j]) {
                    continue;
                }
                if ($a['produit_id'] !== null) {
                    if ($p['produitId'] === $a['produit_id']) {
                        $meilleure = $j;
                        break;
                    }
                    continue;
                }
                if ($p['produitId'] !== null) {
                    continue;
                }
                similar_text(self::mots($a['designation']), self::mots($p['designation']), $pourcent);
                if ($pourcent >= $score) {
                    $score = $pourcent;
                    $meilleure = $j;
                }
            }
            if ($meilleure !== null) {
                $paires[$i] = $meilleure;
                $libres[$meilleure] = false;
            }
        }
        return $paires;
    }

    private static function mots(string $texte): string
    {
        $t = mb_strtolower(trim($texte));
        return strtr($t, ['é' => 'e', 'è' => 'e', 'ê' => 'e', 'à' => 'a', 'â' => 'a', 'ç' => 'c', 'ô' => 'o', 'î' => 'i', 'ù' => 'u', 'û' => 'u']);
    }

    /** Les unités qu'un modèle écrit de plusieurs façons. */
    private static function unite(string $u): string
    {
        $u = mb_strtolower(trim($u));
        return match ($u) {
            'm2', 'm²', 'mètre carré', 'mètres carrés' => 'm²',
            'm3', 'm³' => 'm³',
            'pce', 'pc', 'pièce', 'pièces', 'pcs' => 'pce',
            'h', 'heure', 'heures' => 'h',
            'm', 'ml', 'mètre', 'mètres', 'mètre linéaire', 'mètres linéaires' => 'm',
            'forfait', 'fft', 'ff' => 'forfait',
            default => $u,
        };
    }

    private static function centimes(int|float|null $francs): int
    {
        return $francs === null ? 0 : (int) round($francs * 100);
    }
}
