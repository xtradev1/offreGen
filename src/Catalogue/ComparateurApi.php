<?php

declare(strict_types=1);

namespace OffreGen\Catalogue;

use OffreGen\Ia\ReponseApi;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Le modèle décide si deux libellés tirés des offres de l'artisan désignent le même travail. Un seul appel pour toutes
 * les paires, sans raisonnement, réponse en lignes de texte « n | même » ou « n | autre » (même choix que LecteurApi).
 * Banc du 2026-11-20 sur douze paires : 11 justes, la faute sépare deux libellés identiques, aucune réunion à tort.
 */
final class ComparateurApi implements Comparateur
{
    private const CONSIGNE = <<<'TXT'
        Tu compares des libellés tirés des offres d'un même artisan, pour ranger son catalogue de prix.
        Pour chaque paire, dis si les deux libellés désignent le même travail, que l'artisan vendrait au même prix au même client.
        - même : seule la formulation change (ordre des mots, synonyme, précision sans effet sur le travail).
        - autre : dès que le support, la matière, la dimension, l'épaisseur, la performance ou l'objet diffère (murs et plafonds, 1,5 mm et 2 mm, El60 et El120).
        - Dans le doute : autre.
        Réponds seulement par une ligne par paire, sans commentaire : n | même   ou   n | autre
        TXT;

    /** @var array{int, int} */
    private array $jetons = [0, 0];

    public function __construct(
        private readonly HttpClientInterface $http,
        private readonly string $url,
        private readonly string $cle,
        private readonly string $modele,
        private readonly LoggerInterface $journal,
        private readonly bool $sansRaisonnement = true,
    ) {
    }

    public function memes(array $paires): array
    {
        if ($paires === []) {
            return [];
        }
        $liste = [];
        foreach ($paires as $i => [$a, $b]) {
            $liste[] = ($i + 1) . '. « ' . $a . ' » / « ' . $b . ' »';
        }
        $corps = [
            'model' => $this->modele,
            'temperature' => 0,
            'messages' => [['role' => 'system', 'content' => self::CONSIGNE], ['role' => 'user', 'content' => "Paires :\n" . implode("\n", $liste)]],
            'max_tokens' => 20 + 12 * count($paires),
        ];
        if ($this->sansRaisonnement) {
            $corps['reasoning_effort'] = 'none';
        }
        $donnees = ReponseApi::lire($this->http->request('POST', rtrim($this->url, '/') . '/chat/completions', [
            'headers' => $this->cle === '' ? [] : ['Authorization' => 'Bearer ' . $this->cle],
            'json' => $corps,
            'timeout' => 60,
            'max_duration' => 90,
        ]), 'Comparaison de libellés');
        $usage = is_array($donnees['usage'] ?? null) ? $donnees['usage'] : [];
        if (is_int($usage['prompt_tokens'] ?? null) && is_int($usage['completion_tokens'] ?? null)) {
            $this->jetons = [$this->jetons[0] + $usage['prompt_tokens'], $this->jetons[1] + $usage['completion_tokens']];
            $this->journal->info(sprintf('offreGen, comparaison de libellés : %d paires, %d jetons envoyés, %d reçus, modèle %s.',
                count($paires), $usage['prompt_tokens'], $usage['completion_tokens'], $this->modele));
        }
        $choix = is_array($donnees['choices'] ?? null) && is_array($donnees['choices'][0] ?? null) ? $donnees['choices'][0] : [];
        $message = is_array($choix['message'] ?? null) ? $choix['message'] : [];
        $reponses = [];
        foreach (preg_split('/\R/u', is_string($message['content'] ?? null) ? $message['content'] : '') ?: [] as $ligne) {
            if (preg_match('/^\s*(\d+)\s*[|.:)]\s*(\S+)/u', $ligne, $m) === 1) {
                $reponses[(int) $m[1]] = Recherche::normaliser($m[2]) === 'meme';
            }
        }
        $resultat = [];
        foreach (array_keys($paires) as $i) {
            $resultat[] = $reponses[$i + 1] ?? false;
        }
        return $resultat;
    }

    public function jetonsConsommes(): array
    {
        return $this->jetons;
    }
}
