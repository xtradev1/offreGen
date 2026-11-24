<?php

declare(strict_types=1);

namespace OffreGen\Audio;

/**
 * Les chiffres de la page des dictées (sous-chantier 31) : une ligne par dictée, colonnes regroupant les étapes,
 * statistiques de la période, marque des durées anormales. Ne lit rien : les dictées et leurs étapes arrivent de
 * DepotDictees. Les étapes d'un rejeu n'entrent jamais ici.
 *
 * @phpstan-type Dictee array{id: int, uuid: string, artisan_id: int, recu_le: string, pret_le: string|null, statut: string, tentatives: int, taille: int, type_mime: string, navigateur: string|null, origine: string|null, duree_ms: int|null, offre_id: int|null}
 * @phpstan-type Etape array{enregistrement_id: int, etape: string, tentative: int, processus: string, debut: string, fin: string|null, duree_ms: int|null, issue: string, erreur: string|null, modele: string|null, jetons_envoyes: int|null, jetons_recus: int|null, premier_octet_ms: int|null}
 * @phpstan-type Ligne array{dictee: Dictee, colonnes: array<string, int|null>, detail: array<string, int>, jetons: array<string, array{int, int}>, total: int|null, hors: int|null, essais: int, accroc: bool, modele_ia: string|null, modele_transcription: string|null, lentes: list<string>}
 * @phpstan-type Stat array{n: int, mediane: float|null, centile90: int|null, max: int|null, max_id: int|null}
 */
final class RapportDictees
{
    /** Les colonnes de la liste et les étapes qu'elles additionnent (tentatives comprises). */
    public const COLONNES = [
        // L'envoi du téléphone et sa réception au serveur : la dictée arrive.
        'envoi' => ['envoi', 'reception'],
        'attente' => ['attente', 'reprise'],
        'transcription' => ['mesure', 'conversion', 'transcription_envoi', 'transcription'],
        'extraction' => ['ia_extraction'],
        'pieces' => ['ia_pieces'],
        'options' => ['ia_options'],
        'ecriture' => ['ecriture'],
    ];

    public const LIBELLES = [
        'envoi' => 'Envoi', 'attente' => 'Attente', 'transcription' => 'Transcription', 'extraction' => 'Extraction',
        'pieces' => 'Pièces', 'options' => 'Options', 'ecriture' => 'Écriture', 'total' => 'Total',
    ];

    /** Les appels à l'IA, colonne et étape. */
    public const APPELS = ['extraction' => 'ia_extraction', 'pieces' => 'ia_pieces', 'options' => 'ia_options'];

    /** Les étapes dans l'ordre du CSV. */
    public const ETAPES = ['envoi', 'reception', 'attente', 'reprise', 'mesure', 'conversion', 'transcription_envoi', 'transcription',
        'ia_extraction', 'ia_pieces', 'ia_options', 'ecriture'];

    /** Une durée n'est marquée que si elle dépasse aussi cette valeur : 40 ms contre 15 ms ne dit rien. */
    public const SEUIL_MARQUE_MS = 1000;

    /**
     * @param list<Dictee> $dictees
     * @param list<Etape> $etapes de ces dictées, sans les rejeux
     * @return list<Ligne>
     */
    public static function lignes(array $dictees, array $etapes): array
    {
        $parDictee = [];
        foreach ($etapes as $e) {
            $parDictee[$e['enregistrement_id']][] = $e;
        }
        $lignes = [];
        foreach ($dictees as $d) {
            $lignes[] = self::ligne($d, $parDictee[$d['id']] ?? []);
        }
        return $lignes;
    }

    /**
     * @param Dictee $d
     * @param list<Etape> $etapes
     * @return Ligne
     */
    private static function ligne(array $d, array $etapes): array
    {
        $detail = [];
        $jetons = [];
        $debut = null;
        $fin = null;
        $accroc = false;
        $essais = 0;
        $modeleIa = null;
        $modeleTranscription = null;
        foreach ($etapes as $e) {
            $detail[$e['etape']] = ($detail[$e['etape']] ?? 0) + ($e['duree_ms'] ?? 0);
            $essais = max($essais, $e['tentative']);
            $accroc = $accroc || $e['etape'] === 'reprise' || in_array($e['issue'], ['erreur', 'interrompue'], true);
            $debut = $debut === null || $e['debut'] < $debut ? $e['debut'] : $debut;
            $f = $e['fin'] ?? $e['debut'];
            $fin = $fin === null || $f > $fin ? $f : $fin;
            if (in_array($e['etape'], self::APPELS, true)) {
                [$envoyes, $recus] = $jetons[$e['etape']] ?? [0, 0];
                $jetons[$e['etape']] = [$envoyes + ($e['jetons_envoyes'] ?? 0), $recus + ($e['jetons_recus'] ?? 0)];
                $modeleIa ??= $e['modele'];
            }
            if ($e['etape'] === 'transcription_envoi') {
                $modeleTranscription ??= $e['modele'];
            }
        }
        $colonnes = [];
        foreach (self::COLONNES as $colonne => $noms) {
            $presentes = array_intersect_key($detail, array_flip($noms));
            $colonnes[$colonne] = $presentes === [] ? null : array_sum($presentes);
        }
        $total = $debut === null ? null : self::ecart($debut, (string) $fin);
        // Hors étapes : ce que le total contient sans qu'aucune étape le mesure (lecture du catalogue, temps entre deux passages).
        $hors = $total === null ? null : max(0, $total - array_sum(array_filter($colonnes, static fn (?int $v): bool => $v !== null)));
        // Tentatives : la plus haute chronométrée ; sans chronomètre, déduite du compteur d'échecs (une dictée en échec
        // compte toutes ses tentatives, une dictée prête en a une de plus que ses échecs).
        $essais = $essais > 0 ? $essais : ($d['statut'] === 'echec' ? max(1, $d['tentatives']) : $d['tentatives'] + 1);
        return ['dictee' => $d, 'colonnes' => $colonnes, 'detail' => $detail, 'jetons' => $jetons, 'total' => $total, 'hors' => $hors, 'essais' => $essais,
            'accroc' => $accroc, 'modele_ia' => $modeleIa, 'modele_transcription' => $modeleTranscription, 'lentes' => []];
    }

    /**
     * Pour chaque colonne et le total : nombre, médiane, 90e centile, maximum et la dictée du maximum. Pour chaque appel à
     * l'IA, la durée pour 100 jetons reçus (médiane et maximum) : un appel long avec beaucoup de jetons n'a pas la même
     * cause qu'un appel long avec peu.
     *
     * @param list<Ligne> $lignes
     * @return array{colonnes: array<string, Stat>, par_jetons: array<string, Stat>}
     */
    public static function statistiques(array $lignes): array
    {
        $colonnes = [];
        foreach ([...array_keys(self::COLONNES), 'total'] as $colonne) {
            $valeurs = [];
            foreach ($lignes as $l) {
                $v = $colonne === 'total' ? $l['total'] : $l['colonnes'][$colonne];
                if ($v !== null) {
                    $valeurs[$l['dictee']['id']] = $v;
                }
            }
            $colonnes[$colonne] = self::stat($valeurs);
        }
        $parJetons = [];
        foreach (self::APPELS as $colonne => $etape) {
            $valeurs = [];
            foreach ($lignes as $l) {
                $recus = $l['jetons'][$etape][1] ?? 0;
                if ($recus > 0 && $l['colonnes'][$colonne] !== null) {
                    $valeurs[$l['dictee']['id']] = (int) round($l['colonnes'][$colonne] * 100 / $recus);
                }
            }
            $parJetons[$colonne] = self::stat($valeurs);
        }
        return ['colonnes' => $colonnes, 'par_jetons' => $parJetons];
    }

    /**
     * Une durée plus de deux fois la médiane de sa colonne, et au-delà de SEUIL_MARQUE_MS, est marquée (D2 de Xavier).
     *
     * @param list<Ligne> $lignes
     * @param array{colonnes: array<string, Stat>, par_jetons: array<string, Stat>} $stats
     * @return list<Ligne>
     */
    public static function marquer(array $lignes, array $stats): array
    {
        foreach ($lignes as $i => $l) {
            $lentes = [];
            foreach (array_keys(self::COLONNES) as $colonne) {
                $v = $l['colonnes'][$colonne];
                $m = $stats['colonnes'][$colonne]['mediane'];
                if ($v !== null && $m !== null && $v > 2 * $m && $v > self::SEUIL_MARQUE_MS) {
                    $lentes[] = $colonne;
                }
            }
            $lignes[$i]['lentes'] = $lentes;
        }
        return $lignes;
    }

    /**
     * @param array<int, int> $valeurs durée par dictée
     * @return Stat
     */
    public static function stat(array $valeurs): array
    {
        if ($valeurs === []) {
            return ['n' => 0, 'mediane' => null, 'centile90' => null, 'max' => null, 'max_id' => null];
        }
        $triees = array_values($valeurs);
        sort($triees);
        $n = count($triees);
        $mediane = $n % 2 === 1 ? (float) $triees[intdiv($n, 2)] : ($triees[$n / 2 - 1] + $triees[$n / 2]) / 2.0;
        $max = max($valeurs);
        return ['n' => $n, 'mediane' => $mediane, 'centile90' => $triees[(int) ceil(0.9 * $n) - 1], 'max' => $max,
            'max_id' => (int) array_search($max, $valeurs, true)];
    }

    public const NOMS_ETAPES = [
        'envoi' => 'Envoi', 'reception' => 'Réception', 'attente' => 'Attente', 'reprise' => 'Reprise', 'mesure' => 'Mesure',
        'conversion' => 'Conversion', 'transcription_envoi' => 'Transcription, envoi', 'transcription' => 'Transcription',
        'ia_extraction' => 'IA, extraction', 'ia_pieces' => 'IA, pièces', 'ia_options' => 'IA, options', 'ecriture' => 'Écriture',
    ];

    public const NOMS_PROCESSUS = ['telephone' => 'téléphone', 'web' => 'web', 'apres_reponse' => 'après réponse', 'cron' => 'cron', 'rejeu' => 'rejeu'];

    /** Famille de couleur de chaque étape sur la frise (palette validée, MAQUETTE-chronometre.html). */
    private const FAMILLES = [
        'envoi' => 'f1', 'reception' => 'f1', 'mesure' => 'f2', 'conversion' => 'f2', 'transcription_envoi' => 'f2', 'transcription' => 'f2',
        'ia_extraction' => 'f3', 'ia_pieces' => 'f3', 'ia_options' => 'f3', 'ecriture' => 'f4', 'attente' => 'fa', 'reprise' => 'fa',
    ];

    /**
     * La frise d'un rapport : un bloc par tentative, à sa propre échelle (de 0 à 1000 sur l'axe du SVG). Une reprise
     * (plusieurs minutes de verrou mort) écraserait l'échelle : elle s'affiche avant son bloc, en coupure.
     *
     * @param list<array{etape: string, tentative: int, processus: string, debut: string, fin: string|null, duree_ms: int|null, issue: string}> $etapes
     * @return list<array{tentative: int, processus: string, debut: string, fin: string, duree: int, reprise: array<string, mixed>|null, lignes: list<array<string, mixed>>}>
     */
    public static function frise(array $etapes): array
    {
        $parTentative = [];
        foreach ($etapes as $e) {
            $parTentative[$e['tentative']][] = $e;
        }
        ksort($parTentative);
        $blocs = [];
        foreach ($parTentative as $tentative => $liste) {
            $reprise = null;
            $lignes = [];
            foreach ($liste as $e) {
                if ($e['etape'] === 'reprise') {
                    $reprise = $e;
                } else {
                    $lignes[] = $e;
                }
            }
            if ($lignes === []) {
                continue;
            }
            $debut = min(array_column($lignes, 'debut'));
            $fin = max(array_map(static fn (array $e): string => $e['fin'] ?? $e['debut'], $lignes));
            $duree = max(1, self::ecart($debut, $fin));
            $vues = [];
            foreach ($lignes as $e) {
                $x = (int) round(self::ecart($debut, $e['debut']) * 1000 / $duree);
                $largeur = $e['fin'] === null ? 0 : (int) round(self::ecart($e['debut'], $e['fin']) * 1000 / $duree);
                $vues[] = $e + [
                    'nom' => self::NOMS_ETAPES[$e['etape']] ?? $e['etape'],
                    'nom_processus' => self::NOMS_PROCESSUS[$e['processus']] ?? $e['processus'],
                    'x' => min(997, $x), 'largeur' => max(3, min(1000 - $x, $largeur)),
                    'classe' => in_array($e['issue'], ['erreur', 'interrompue'], true) ? 'fx' : (self::FAMILLES[$e['etape']] ?? 'fa'),
                ];
            }
            // Le processus du traitement, pas celui de l'envoi (téléphone) ni de la réception (web).
            $traitants = array_values(array_filter($lignes, static fn (array $e): bool => !in_array($e['processus'], ['telephone', 'web'], true)));
            $processus = ($traitants[0] ?? $lignes[0])['processus'];
            $blocs[] = ['tentative' => $tentative, 'processus' => self::NOMS_PROCESSUS[$processus] ?? $processus,
                'debut' => $debut, 'fin' => $fin, 'duree' => $duree, 'reprise' => $reprise, 'lignes' => $vues];
        }
        return $blocs;
    }

    /** Écart en millisecondes entre deux dates de la base (UTC, à la milliseconde). */
    public static function ecart(string $debut, string $fin): int
    {
        $utc = new \DateTimeZone('UTC');
        $a = (float) (new \DateTimeImmutable($debut, $utc))->format('U.u');
        $b = (float) (new \DateTimeImmutable($fin, $utc))->format('U.u');
        return max(0, (int) round(($b - $a) * 1000));
    }
}
