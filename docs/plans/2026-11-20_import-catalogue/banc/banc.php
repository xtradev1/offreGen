<?php

// Banc de l'import du catalogue : lit le texte de chaque offre du corpus avec LecteurApi (le code de l'application)
// et un modèle au format OpenAI, puis note les lignes contre ce qui est attendu.
// Corpus : offres inventées (attendu connu) et devis publics (attendu écrit à la main, ou absent pour les deux longs).
// Les textes et les réponses brutes restent hors du dépôt : les devis publics portent de vrais noms.
// Usage : php banc.php <dossier des textes> <offres.json> <dossier des réponses> [--lot=nom] [--passes=N] [--ids=a,b]
//                      [--raisonnement] [--url=http://172.25.96.1:11434/v1] [--modele=offregen-qwen3.8]
//                      [--infomaniak]  (adresse, jeton et modèle lus dans le .env de l'application ; le jeton n'est jamais affiché)
declare(strict_types=1);

use OffreGen\Catalogue\LecteurApi;
use OffreGen\Catalogue\LigneLue;
use OffreGen\Catalogue\Recherche;
use OffreGen\Http\Conteneur;
use Psr\Log\NullLogger;
use Symfony\Component\HttpClient\HttpClient;

const TARIF = [0.40, 3.00]; // CHF par million de jetons envoyés, reçus (Infomaniak, Qwen3.8 27B, lu le 2026-11-05)

require dirname(__DIR__, 4) . '/vendor/autoload.php';

[$dossierTextes, $fichierOffres, $dossierReponses] = [$argv[1] ?? '', $argv[2] ?? '', $argv[3] ?? ''];
if (!is_dir($dossierTextes) || !is_file($fichierOffres) || $dossierReponses === '') {
    fwrite(STDERR, "Usage : php banc.php <textes> <offres.json> <réponses> [options]\n");
    exit(2);
}
$lot = date('Y-m-d');
$passes = 1;
$ids = null;
$raisonnement = false;
$url = 'http://172.25.96.1:11434/v1';
$modele = 'offregen-qwen3.8';
$cle = '';
foreach (array_slice($argv, 4) as $a) {
    if ($a === '--infomaniak') {
        $env = Conteneur::lireEnvironnement(dirname(__DIR__, 4));
        [$url, $cle, $modele] = [$env['EXTRACTION_URL'], $env['EXTRACTION_CLE'], $env['EXTRACTION_MODELE']];
    }
    if (str_starts_with($a, '--lot=')) {
        $lot = substr($a, 6);
    } elseif (str_starts_with($a, '--passes=')) {
        $passes = (int) substr($a, 9);
    } elseif (str_starts_with($a, '--ids=')) {
        $ids = explode(',', substr($a, 6));
    } elseif ($a === '--raisonnement') {
        $raisonnement = true;
    } elseif (str_starts_with($a, '--url=')) {
        $url = substr($a, 6);
    } elseif (str_starts_with($a, '--modele=')) {
        $modele = substr($a, 9);
    }
}

/** @return list<string> les mots utiles d'une désignation */
function mots(string $s): array
{
    $vides = ['de', 'du', 'des', 'la', 'le', 'les', 'et', 'sur', 'a', 'en', 'y', 'compris', 'd', 'l', 'avec', 'pour', 'un', 'une', 'x'];
    $mots = preg_split('/[^\p{L}\p{N}]+/u', Recherche::normaliser($s), -1, PREG_SPLIT_NO_EMPTY) ?: [];
    return array_values(array_unique(array_diff(array_map(static fn (string $m): string => rtrim($m, 'sx'), $mots), $vides)));
}

function proche(string $a, string $b): float
{
    [$x, $y] = [mots($a), mots($b)];
    $commun = count(array_intersect($x, $y));
    return $commun === 0 ? 0.0 : $commun / count(array_unique(array_merge($x, $y)));
}

$donnees = json_decode((string) file_get_contents($fichierOffres), true);
$corpus = [];
foreach ($donnees['offres'] as $o) {
    $attendu = [];
    foreach ($o['sections'] as $s) {
        foreach ($s['lignes'] as $l) {
            $attendu[] = ['des' => $l['des'], 'unite' => $l['attendu'], 'prix' => (int) round($l['pu'] * 100), 'ponctuel' => $l['ponctuel'] ?? false];
        }
    }
    $corpus[$o['id']] = $attendu;
}
foreach ($donnees['publiques'] as $p) {
    $corpus[$p['id']] = $p['attendu'] === null ? null : array_map(
        static fn (array $l): array => ['des' => $l['des'], 'unite' => $l['attendu'], 'prix' => (int) round($l['pu'] * 100), 'ponctuel' => false],
        $p['attendu'],
    );
}

$lecteur = new LecteurApi(HttpClient::create(), $url, $cle, $modele, new NullLogger(), !$raisonnement);
@mkdir($dossierReponses . '/' . $lot, 0700, true);
$stats = __DIR__ . '/stats/passes.csv';
@mkdir(dirname($stats), 0755, true);
if (!is_file($stats)) {
    file_put_contents($stats, "lot,modele,raisonnement,offre,passe,lignes_texte,attendues,rendues,justes,unite_fausse,manquantes,en_trop,ponctuel_juste,ponctuel_attendu,refusees,jetons_envoyes,jetons_recus,cout_chf,duree_s\n");
}

foreach ($corpus as $id => $attendu) {
    if ($ids !== null && !in_array($id, $ids, true)) {
        continue;
    }
    $texte = explode("\n", (string) file_get_contents("$dossierTextes/$id.txt"));
    for ($passe = 1; $passe <= $passes; $passe++) {
        $debut = microtime(true);
        try {
            $r = $lecteur->lire($texte);
        } catch (\Throwable $e) {
            printf("%-18s passe %d : ÉCHEC %s : %s\n", $id, $passe, $e::class, $e->getMessage());
            continue;
        }
        $duree = microtime(true) - $debut;
        $cout = (($r->jetonsEnvoyes ?? 0) * TARIF[0] + ($r->jetonsRecus ?? 0) * TARIF[1]) / 1e6;
        file_put_contents("$dossierReponses/$lot/$id-$passe.json", json_encode([
            'lignes' => array_map(static fn (LigneLue $l): array => (array) $l, $r->lignes), 'refusees' => $r->refusees,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        $justes = $uniteFausse = $ponctuelJuste = 0;
        $pris = [];
        $fautes = [];
        if ($attendu !== null) {
            foreach ($attendu as $a) {
                $trouve = null;
                foreach ($r->lignes as $i => $l) {
                    if (!isset($pris[$i]) && $l->prixCentimes === $a['prix'] && proche($l->designation, $a['des']) >= 0.4) {
                        $trouve = $i;
                        break;
                    }
                }
                if ($trouve === null) {
                    $fautes[] = 'manque : ' . $a['des'] . ' à ' . number_format($a['prix'] / 100, 2, '.', '');
                    continue;
                }
                $pris[$trouve] = true;
                $l = $r->lignes[$trouve];
                if ($l->unite === $a['unite']) {
                    $justes++;
                } else {
                    $uniteFausse++;
                    $fautes[] = "unité : {$a['des']} : « {$l->uniteEcrite} » rendu " . ($l->unite ?? 'inconnue') . ", attendu {$a['unite']}";
                }
            }
            foreach ($r->lignes as $i => $l) {
                if (!isset($pris[$i])) {
                    $fautes[] = 'en trop : ' . $l->designation . ' à ' . number_format($l->prixCentimes / 100, 2, '.', '');
                }
            }
        }
        foreach ($r->refusees as [$des, $raison]) {
            $fautes[] = "refusée par le code ($raison) : $des";
        }
        $nAtt = $attendu === null ? '' : count($attendu);
        $ponctuelAttendu = $attendu === null ? '' : count(array_filter($attendu, static fn (array $a): bool => $a['ponctuel']));
        $manquantes = $attendu === null ? '' : count($attendu) - $justes - $uniteFausse;
        $enTrop = $attendu === null ? '' : count($r->lignes) - count($pris);
        file_put_contents($stats, implode(',', [$lot, $modele, $raisonnement ? 'oui' : 'non', $id, $passe, count($texte), $nAtt, count($r->lignes),
            $attendu === null ? '' : $justes, $attendu === null ? '' : $uniteFausse, $manquantes, $enTrop, $attendu === null ? '' : $ponctuelJuste, $ponctuelAttendu,
            count($r->refusees), $r->jetonsEnvoyes ?? '', $r->jetonsRecus ?? '', number_format($cout, 5, '.', ''), number_format($duree, 1, '.', '')]) . "\n", FILE_APPEND);
        printf("%-18s passe %d : %s justes sur %s, %d rendues, %d refusées, %s + %s jetons, CHF %.4f, %.1f s\n", $id, $passe,
            $attendu === null ? '?' : (string) $justes, $attendu === null ? '?' : (string) count($attendu), count($r->lignes), count($r->refusees),
            $r->jetonsEnvoyes ?? '?', $r->jetonsRecus ?? '?', $cout, $duree);
        foreach ($fautes as $f) {
            echo "    $f\n";
        }
    }
}
