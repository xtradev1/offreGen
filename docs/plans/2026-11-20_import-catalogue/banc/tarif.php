<?php

// Tarif de Qwen lu chez Infomaniak (GET /1/ai/models?with=pricing), pour chiffrer le banc au prix du jour.
// Le jeton vient du .env de l'application et n'est jamais affiché.
declare(strict_types=1);

use OffreGen\Http\Conteneur;
use Symfony\Component\HttpClient\HttpClient;

require dirname(__DIR__, 4) . '/vendor/autoload.php';
$env = Conteneur::lireEnvironnement(dirname(__DIR__, 4));
$r = HttpClient::create()->request('GET', 'https://api.infomaniak.com/1/ai/models?with=pricing', [
    'headers' => ['Authorization' => 'Bearer ' . $env['EXTRACTION_CLE']], 'timeout' => 30,
]);
echo 'HTTP ', $r->getStatusCode(), "\n";
$json = json_decode($r->getContent(false), true);
foreach ($json['data'] ?? [] as $m) {
    $nom = (string) ($m['name'] ?? $m['model'] ?? '');
    if (stripos($nom, 'qwen3.8') !== false || stripos($nom, 'Qwen3.8') !== false) {
        echo $nom, ' : ', json_encode($m['pricing'] ?? $m['prices'] ?? array_diff_key($m, ['description' => 0]), JSON_UNESCAPED_UNICODE), "\n";
    }
}
