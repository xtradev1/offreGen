<?php

// Essai chez Infomaniak : sortie en lignes de texte « n | désignation | unité | prix », sans appel d'outil.
// Usage : php variante-texte.php <texte.txt> [essais]
declare(strict_types=1);

use OffreGen\Http\Conteneur;
use Symfony\Component\HttpClient\HttpClient;

require dirname(__DIR__, 4) . '/vendor/autoload.php';
$env = Conteneur::lireEnvironnement(dirname(__DIR__, 4));
$consigne = <<<'TXT'
    Tu lis une ancienne offre (un devis) d'un artisan, pour bâtir son catalogue de prix. Les lignes du texte sont numérotées. Une barre « | » sépare les cellules d'un tableau.
    Relève toutes les lignes de travail ou de fourniture qui portent un prix unitaire, une par une, du début à la fin de l'offre.
    Réponds seulement par ces lignes, une par ligne relevée, sans titre ni commentaire :
    n | désignation | unité | prix
    - n : le numéro de la ligne du texte où se lit le prix unitaire.
    - désignation : le libellé du travail tel qu'il est écrit, sans le numéro de position, sans la quantité, sans le nom du client. Si le libellé continue sur la ligne suivante, réunis-le. Ne recopie pas un descriptif technique de plusieurs lignes : garde le libellé. Jamais de barre « | » dans la désignation.
    - unité : l'unité telle qu'elle est écrite (m2, ml, pce, h, forfait). Sans unité écrite : forfait.
    - prix : le prix unitaire, jamais le montant de la ligne (quantité fois prix unitaire). Copie le nombre écrit, ne calcule rien.
    - Ne relève ni les totaux, ni les sous-totaux, ni les rabais, ni la TVA, ni les acomptes, ni les titres de chapitre, ni les conditions.
    - N'invente rien : une ligne sans prix n'est pas relevée.
    TXT;
$texte = explode("\n", (string) file_get_contents($argv[1]));
$num = [];
foreach ($texte as $i => $l) {
    if (trim($l) !== '') {
        $num[] = ($i + 1) . ': ' . trim($l);
    }
}
$http = HttpClient::create();
for ($e = 1; $e <= (int) ($argv[2] ?? 4); $e++) {
    $t = microtime(true);
    $r = $http->request('POST', rtrim($env['EXTRACTION_URL'], '/') . '/chat/completions', [
        'headers' => ['Authorization' => 'Bearer ' . $env['EXTRACTION_CLE']], 'timeout' => 120,
        'json' => ['model' => $env['EXTRACTION_MODELE'], 'temperature' => 0, 'reasoning_effort' => 'none', 'max_tokens' => 16000,
            'messages' => [['role' => 'system', 'content' => $consigne], ['role' => 'user', 'content' => "Offre :\n" . implode("\n", $num)]]],
    ]);
    $j = json_decode($r->getContent(false), true);
    $sortie = (string) ($j['choices'][0]['message']['content'] ?? '');
    $lignes = array_filter(explode("\n", $sortie), static fn (string $l): bool => preg_match('/^\s*\d+\s*\|.+\|.+\|\s*[\d\'’ .,]+\s*$/u', $l) === 1);
    printf("texte essai %d : %d lignes, %d + %d jetons, %.1f s, fin %s\n", $e, count($lignes), $j['usage']['prompt_tokens'] ?? 0, $j['usage']['completion_tokens'] ?? 0, microtime(true) - $t, $j['choices'][0]['finish_reason'] ?? '?');
    if ($e === 1) {
        echo '   ', str_replace("\n", "\n   ", mb_substr($sortie, 0, 700)), "\n";
    }
}
