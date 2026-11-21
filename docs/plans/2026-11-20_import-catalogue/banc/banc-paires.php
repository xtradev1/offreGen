<?php

// Banc du regroupement : deux libellés désignent-ils le même travail ? Compare la ressemblance calculée par le code
// et la réponse de ComparateurApi (le code de l'application), sur des paires dont la réponse attendue est connue.
// Usage : php banc-paires.php [--raisonnement] [--passes=N] [--infomaniak | --url=... --modele=...]
declare(strict_types=1);

use OffreGen\Catalogue\ComparateurApi;
use OffreGen\Catalogue\Ressemblance;
use OffreGen\Http\Conteneur;
use Psr\Log\NullLogger;
use Symfony\Component\HttpClient\HttpClient;

require dirname(__DIR__, 4) . '/vendor/autoload.php';

const PAIRES = [
    ['Rebouchage des fissures et ponçage', 'Rebouchage et ponçage des murs', true],
    ['Dispersion sur murs, 2 couches', 'Dispersion sur plafonds, 2 couches', false],
    ['Peinture de radiateur', 'Peinture des plinthes', false],
    ['Protection des sols et du mobilier', 'Protection des sols et des meubles', true],
    ['Plinthes carrelées assorties', 'Joints silicone sanitaires', false],
    ['Fourniture et pose de carrelage grès cérame 60 x 60, pose droite', 'Faïence murale 30 x 60, zone de douche', false],
    ['Cloisons KM 98/48-35 El60 Ra =45 dB.', 'Cloisons KM 98/48-35 El120 Ra =45 dB.', false],
    ['Lessivage des murs', 'Lavage des murs avant peinture', true],
    ['Peinture émail sur portes, 2 faces', 'Émail sur portes', true],
    ['Crépi de finition 1,5 mm', 'Crépi de finition 2 mm', false],
    ['Dispersion sur murs, 2 couches', 'Peinture murs 2 couches dispersion', true],
    ['Dispersion sur plafonds, 2 couches', 'Plafonds, dispersion 2 couches', true],
];

$raisonnement = in_array('--raisonnement', $argv, true);
$passes = 1;
$url = 'http://172.25.96.1:11434/v1';
$modele = 'offregen-qwen3.8';
$cle = '';
foreach ($argv as $a) {
    if (str_starts_with($a, '--passes=')) {
        $passes = (int) substr($a, 9);
    } elseif (str_starts_with($a, '--url=')) {
        $url = substr($a, 6);
    } elseif (str_starts_with($a, '--modele=')) {
        $modele = substr($a, 9);
    } elseif ($a === '--infomaniak') {
        $env = Conteneur::lireEnvironnement(dirname(__DIR__, 4));
        [$url, $cle, $modele] = [$env['EXTRACTION_URL'], $env['EXTRACTION_CLE'], $env['EXTRACTION_MODELE']];
    }
}

for ($p = 1; $p <= $passes; $p++) {
    $comparateur = new ComparateurApi(HttpClient::create(), $url, $cle, $modele, new NullLogger(), !$raisonnement);
    $debut = microtime(true);
    $reponses = $comparateur->memes(array_map(static fn (array $q): array => [$q[0], $q[1]], PAIRES));
    [$envoyes, $recus] = $comparateur->jetonsConsommes();
    printf("Passe %d, raisonnement %s, %d + %d jetons, %.1f s\n", $p, $raisonnement ? 'oui' : 'non', $envoyes, $recus, microtime(true) - $debut);
    $justes = 0;
    foreach (PAIRES as $i => [$a, $b, $attendu]) {
        $r = $reponses[$i];
        $justes += $r === $attendu ? 1 : 0;
        printf("  %s  code %.2f  modèle %-5s attendu %-5s  %s / %s\n", $r === $attendu ? 'juste' : 'FAUX ', Ressemblance::score($a, $b),
            $r ? 'même' : 'autre', $attendu ? 'même' : 'autre', $a, $b);
    }
    printf("  Modèle : %d justes sur %d\n", $justes, count(PAIRES));
}
