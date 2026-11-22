#!/usr/bin/env php
<?php

declare(strict_types=1);

// Banc du sous-chantier 29 (docs/plans/2026-11-07_voix-offre/29-qualite-extraction/SPEC.md) : passe chaque dictée du
// corpus dans l'extraction, comme l'application (mêmes classes, même validation), et la note contre l'offre attendue
// (Offre\NoteExtraction). Appelle le service réglé dans .env : chaque passe coûte des jetons.
//
// Usage : php outils/banc-corpus.php <dossier> [--passes=3] [--moitie=reglage|controle|toutes] [--seulement=NN,NN]
//         [--reglage=service|sans-options]
// <dossier> contient des sous-dossiers NN-titre avec transcription.txt, catalogue.json et attendu.json. Le rapport
// s'écrit aussi en JSON dans <dossier>/rapports/.

use OffreGen\Compte\Activite;
use OffreGen\Http\Conteneur;
use OffreGen\Offre\ExtracteurApi;
use OffreGen\Offre\NoteExtraction;
use Psr\Log\AbstractLogger;

if (PHP_SAPI !== 'cli') {
    exit(1);
}
require dirname(__DIR__) . '/vendor/autoload.php';

/** CHF HT par million de jetons envoyés et reçus : Qwen/Qwen3.8-27B-FP8, page des prix d'Infomaniak lue le 2026-11-05
 *  (docs/plans/2026-11-05_mesures-ia/cadrage-produit.md, section 4). Non relue depuis. */
const TARIF = [0.40, 3.00];

$options = ['passes' => '3', 'moitie' => 'toutes', 'seulement' => '', 'reglage' => 'service'];
$dossier = null;
foreach (array_slice($argv, 1) as $arg) {
    if (preg_match('/^--(passes|moitie|seulement|reglage)=(.*)$/', $arg, $m) === 1) {
        $options[$m[1]] = $m[2];
    } elseif ($dossier === null) {
        $dossier = rtrim($arg, '/');
    }
}
if ($dossier === null || !is_dir($dossier)) {
    fwrite(STDERR, "Usage : php outils/banc-corpus.php <dossier> [--passes=3] [--moitie=reglage|controle|toutes] [--seulement=NN] [--reglage=service|sans-options]\n");
    exit(2);
}
$c = Conteneur::depuisEnvironnement(dirname(__DIR__));
if ($c->parametre('EXTRACTION') !== 'api') {
    fwrite(STDERR, "EXTRACTION n'est pas « api » dans .env : rien à mesurer.\n");
    exit(2);
}

/** Les jetons que l'extracteur déclare à son journal, appel par appel. */
$journal = new class () extends AbstractLogger {
    /** @var list<array{int, int}> */
    public array $jetons = [];

    public function log($level, \Stringable|string $message, array $context = []): void
    {
        if (preg_match('/(\d+) jetons envoyés, (\d+) reçus/u', (string) $message, $m) === 1) {
            $this->jetons[] = [(int) $m[1], (int) $m[2]];
        }
    }
};
$modelePieces = $c->parametre('EXTRACTION_MODELE_PIECES');
$arguments = [$c->http(), $c->parametre('EXTRACTION_URL'), $c->parametre('EXTRACTION_CLE'), $c->parametre('EXTRACTION_MODELE'), $journal,
    $c->parametre('EXTRACTION_SANS_RAISONNEMENT', '1') === '1', $modelePieces === '' ? null : $modelePieces];
$extracteur = match ($options['reglage']) {
    'service' => new ExtracteurApi(...$arguments),
    // L'extraction d'avant le chantier des options : sans le troisième appel.
    'sans-options' => new ExtracteurApi(...[...$arguments, 'classerOptions' => false]),
    default => throw new \InvalidArgumentException('Réglage inconnu : ' . $options['reglage']),
};

$seulement = array_filter(explode(',', $options['seulement']));
$passes = max(1, (int) $options['passes']);
$rapport = ['date' => date('c'), 'reglage' => $options['reglage'], 'modele' => $c->parametre('EXTRACTION_MODELE'), 'passes' => $passes, 'dictees' => []];
$cumul = ['avec' => ['justes' => 0, 'denominateur' => 0, 'corrections' => 0, 'n' => 0, 'natures' => [0, 0]],
          'sans' => ['justes' => 0, 'denominateur' => 0, 'corrections' => 0, 'n' => 0, 'natures' => [0, 0]]];
$couts = [];
$durees = [];

foreach (glob($dossier . '/[0-9][0-9]-*', GLOB_ONLYDIR) ?: [] as $d) {
    $nom = basename($d);
    if ($seulement !== [] && !in_array(substr($nom, 0, 2), $seulement, true)) {
        continue;
    }
    /** @var array{activite?: string, moitie: string, avec_options?: bool, lignes: list<array<string, mixed>>} $attendu */
    $attendu = json_decode((string) file_get_contents("$d/attendu.json"), true, flags: JSON_THROW_ON_ERROR);
    if ($options['moitie'] !== 'toutes' && $attendu['moitie'] !== $options['moitie']) {
        continue;
    }
    $catalogue = json_decode((string) file_get_contents("$d/catalogue.json"), true, flags: JSON_THROW_ON_ERROR);
    $transcription = (string) file_get_contents("$d/transcription.txt");
    $cle = ($attendu['avec_options'] ?? false) ? 'avec' : 'sans';
    echo "== $nom ({$attendu['moitie']}, " . count($attendu['lignes']) . " lignes)\n";
    for ($k = 1; $k <= $passes; $k++) {
        $journal->jetons = [];
        $debut = microtime(true);
        try {
            $resultat = $extracteur->extraire($transcription, $catalogue, Activite::lire($attendu['activite'] ?? null));
        } catch (\Throwable $e) {
            echo "  passe $k : échec, " . $e->getMessage() . "\n";
            $rapport['dictees'][$nom][] = ['echec' => $e->getMessage()];
            continue;
        }
        $duree = microtime(true) - $debut;
        $proposees = array_map(static fn (object $l): array => get_object_vars($l), $resultat->lignes);
        /** @phpstan-ignore argument.type (le JSON du corpus suit la forme Attendue, vérifiée à l'écriture) */
        $note = NoteExtraction::noter($attendu['lignes'], $proposees);
        $envoyes = array_sum(array_column($journal->jetons, 0));
        $recus = array_sum(array_column($journal->jetons, 1));
        $centimes = ($envoyes * TARIF[0] + $recus * TARIF[1]) / 1e6 * 100;
        $couts[] = $centimes;
        $durees[] = $duree;
        $cumul[$cle]['justes'] += $note['justes'];
        $cumul[$cle]['denominateur'] += $note['attendues'] + count($note['en_trop']);
        $cumul[$cle]['corrections'] += $note['corrections'];
        $cumul[$cle]['n']++;
        $cumul[$cle]['natures'][0] += $note['natures']['justes'];
        $cumul[$cle]['natures'][1] += $note['natures']['total'];
        printf("  passe %d : %d/%d justes, %d en trop, natures %d/%d, %.1f s, %.2f ct (%d + %d jetons)\n", $k, $note['justes'], $note['attendues'],
            count($note['en_trop']), $note['natures']['justes'], $note['natures']['total'], $duree, $centimes, $envoyes, $recus);
        foreach ($note['fausses'] as $f) {
            echo "    fausse {$f['ligne']} {$f['designation']} : " . implode(', ', $f['raisons']) . "\n";
        }
        foreach ($note['en_trop'] as $t) {
            echo "    en trop : $t\n";
        }
        $rapport['dictees'][$nom][] = $note + ['duree' => round($duree, 2), 'centimes' => round($centimes, 3), 'jetons' => [$envoyes, $recus],
            'proposees' => array_map(static fn (array $p): array => array_intersect_key($p, array_flip(['produitId', 'designation', 'quantiteMilliemes', 'unite', 'prixCentimes', 'prixDicteCentimes', 'nature', 'remplace'])), $proposees)];
    }
}

echo "\n";
foreach (['sans' => 'Dictées sans option', 'avec' => 'Dictées avec options'] as $cle => $titre) {
    $x = $cumul[$cle];
    if ($x['n'] === 0) {
        continue;
    }
    printf("%s : taux %.1f %% (%d justes sur %d), %.2f corrections par offre, natures %d/%d\n", $titre, 100 * $x['justes'] / max(1, $x['denominateur']),
        $x['justes'], $x['denominateur'], $x['corrections'] / $x['n'], $x['natures'][0], $x['natures'][1]);
}
if ($couts !== []) {
    printf("Coût moyen %.2f ct par offre, durée moyenne %.1f s (%d passes)\n", array_sum($couts) / count($couts), array_sum($durees) / count($durees), count($couts));
}
$rapport['cumul'] = $cumul;
@mkdir("$dossier/rapports", 0777, true);
$fichier = "$dossier/rapports/" . date('Y-m-d-Hi') . '-' . $options['reglage'] . '-' . $options['moitie'] . '.json';
file_put_contents($fichier, json_encode($rapport, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n");
echo "Rapport : $fichier\n";
