<?php

// Essai d'une variante de consigne chez Infomaniak, appel direct, sans passer par LecteurApi.
// Usage : php variante.php <sans-ponctuel|actuelle> <texte.txt> [essais]
declare(strict_types=1);

use OffreGen\Http\Conteneur;
use Symfony\Component\HttpClient\HttpClient;

require dirname(__DIR__, 4) . '/vendor/autoload.php';
$env = Conteneur::lireEnvironnement(dirname(__DIR__, 4));
$variante = $argv[1];
$consigne = (new ReflectionClassConstant(OffreGen\Catalogue\LecteurApi::class, 'CONSIGNE'))->getValue();
$proprietes = ['n' => ['type' => 'integer'], 'designation' => ['type' => 'string'], 'unite' => ['type' => 'string'], 'prix' => ['type' => 'number'], 'ponctuel' => ['type' => 'boolean']];
if ($variante === 'sans-ponctuel') {
    $consigne = implode("\n", array_filter(explode("\n", $consigne), static fn (string $l): bool => !str_starts_with(trim($l), '- ponctuel')));
    unset($proprietes['ponctuel']);
}
$texte = explode("\n", (string) file_get_contents($argv[2]));
$num = [];
foreach ($texte as $i => $l) {
    if (trim($l) !== '') {
        $num[] = ($i + 1) . ': ' . trim($l);
    }
}
$http = HttpClient::create();
for ($e = 1; $e <= (int) ($argv[3] ?? 4); $e++) {
    $t = microtime(true);
    $r = $http->request('POST', rtrim($env['EXTRACTION_URL'], '/') . '/chat/completions', [
        'headers' => ['Authorization' => 'Bearer ' . $env['EXTRACTION_CLE']], 'timeout' => 120,
        'json' => ['model' => $env['EXTRACTION_MODELE'], 'temperature' => 0, 'reasoning_effort' => 'none', 'max_tokens' => 16000,
            'messages' => [['role' => 'system', 'content' => $consigne], ['role' => 'user', 'content' => "Offre :\n" . implode("\n", $num)]],
            'tools' => [['type' => 'function', 'function' => ['name' => 'relever_lignes', 'description' => 'Relève les lignes chiffrées de l\'offre.',
                'parameters' => ['type' => 'object', 'required' => ['lignes'], 'properties' => ['lignes' => ['type' => 'array', 'items' => [
                    'type' => 'object', 'required' => array_keys($proprietes), 'properties' => $proprietes]]]]]]],
            'tool_choice' => ['type' => 'function', 'function' => ['name' => 'relever_lignes']]],
    ]);
    $j = json_decode($r->getContent(false), true);
    $args = json_decode((string) ($j['choices'][0]['message']['tool_calls'][0]['function']['arguments'] ?? '{}'), true);
    printf("%s essai %d : %d lignes, %d + %d jetons, %.1f s\n", $variante, $e, count($args['lignes'] ?? []), $j['usage']['prompt_tokens'] ?? 0, $j['usage']['completion_tokens'] ?? 0, microtime(true) - $t);
}
