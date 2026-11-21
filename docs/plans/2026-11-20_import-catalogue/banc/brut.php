<?php

// Diagnostic : rejoue une offre chez Infomaniak jusqu'à une réponse courte, et montre la fin de la réponse brute
// (raison de l'arrêt, nombre d'appels d'outil, longueur des arguments). Le jeton n'est jamais affiché.
// Usage : php brut.php <texte.txt> [essais]
declare(strict_types=1);

use OffreGen\Catalogue\LecteurApi;
use OffreGen\Http\Conteneur;
use Psr\Log\NullLogger;
use Symfony\Component\HttpClient\DecoratorTrait;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

require dirname(__DIR__, 4) . '/vendor/autoload.php';
$env = Conteneur::lireEnvironnement(dirname(__DIR__, 4));

final class Espion implements HttpClientInterface
{
    use DecoratorTrait;

    public ?ResponseInterface $derniere = null;

    public function request(string $method, string $url, array $options = []): ResponseInterface
    {
        return $this->derniere = $this->client->request($method, $url, $options);
    }
}

$espion = new Espion(HttpClient::create());
$lecteur = new LecteurApi($espion, $env['EXTRACTION_URL'], $env['EXTRACTION_CLE'], $env['EXTRACTION_MODELE'], new NullLogger());
$texte = explode("\n", (string) file_get_contents($argv[1]));
for ($i = 1; $i <= (int) ($argv[2] ?? 6); $i++) {
    $debut = microtime(true);
    $r = $lecteur->lire($texte);
    $json = json_decode($espion->derniere?->getContent(false) ?? '', true);
    $choix = $json['choices'][0] ?? [];
    $appels = $choix['message']['tool_calls'] ?? [];
    printf("essai %d : %d lignes, %.1f s, finish_reason=%s, %d appel(s) d'outil, contenu texte %d car., usage %s\n", $i, count($r->lignes),
        microtime(true) - $debut, json_encode($choix['finish_reason'] ?? null), count($appels), mb_strlen((string) ($choix['message']['content'] ?? '')),
        json_encode($json['usage'] ?? null));
    foreach ($appels as $a) {
        echo '   arguments : ', mb_substr((string) $a['function']['arguments'], 0, 400), "\n";
    }
    if (count($r->lignes) < 3) {
        echo '   contenu : ', mb_substr((string) ($choix['message']['content'] ?? ''), 0, 400), "\n";
        break;
    }
}
