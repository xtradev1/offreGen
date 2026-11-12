<?php

declare(strict_types=1);

namespace OffreGen\Offre;

use OffreGen\Ia\ReponseApi;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Extraction par un modèle de langage, route « chat/completions » au format OpenAI : Ollama en local,
 * Infomaniak (POST /2/ai/{produit}/openai/v1/chat/completions) en production. L'outil « proposer_offre »
 * est imposé. Le modèle choisit les produits et les quantités ; le code reprend désignation, unité et prix
 * dans le catalogue, et ne croit jamais un prix du modèle pour un produit du catalogue.
 */
final class ExtracteurApi implements Extracteur
{
    public const LIGNES_MAX = 200;

    private const CONSIGNE = <<<'TXT'
        Tu prépares le brouillon d'une offre pour un artisan suisse, à partir de la dictée de sa visite chez le client.
        Règles :
        - Une ligne par travail dicté. N'invente aucun travail qui n'est pas dicté.
        - Quand le travail correspond à un produit du catalogue (désignation ou synonymes), donne son identifiant et une quantité dans l'unité du catalogue.
        - Quand il n'y est pas, produit_id vaut null : écris la désignation et l'unité ; donne prix_unitaire seulement si un prix en francs est dicté.
        - certitude « a_verifier » dès que la quantité est approximative (environ, à peu près, une douzaine), déduite ou mal comprise ; sinon « sure ».
        - extrait : les mots exacts de la dictée d'où vient la ligne.
        - titre : court, la nature des travaux et le lieu, sans prix.
        - client : le nom du client tel qu'il est dicté (par exemple « Madame Exemple »), sinon une chaîne vide. lieu : l'adresse ou la localité dictée, sinon une chaîne vide. N'invente ni nom ni adresse.
        - Jamais de tiret long : une virgule à la place.
        TXT;

    public function __construct(
        private readonly HttpClientInterface $http,
        private readonly string $url,
        private readonly string $cle,
        private readonly string $modele,
        private readonly LoggerInterface $journal,
        private readonly bool $sansRaisonnement = true,
    ) {
    }

    public function extraire(string $transcription, array $catalogue): ResultatExtraction
    {
        if (trim($transcription) === '') {
            return new ResultatExtraction(null, []);
        }
        $corps = [
            'model' => $this->modele,
            'temperature' => 0,
            'messages' => [
                ['role' => 'system', 'content' => self::CONSIGNE],
                ['role' => 'user', 'content' => "Catalogue :\n" . self::catalogueEnTexte($catalogue) . "\n\nDictée :\n" . $transcription],
            ],
            'tools' => [self::outil()],
            'tool_choice' => ['type' => 'function', 'function' => ['name' => 'proposer_offre']],
        ];
        if ($this->sansRaisonnement) {
            $corps['reasoning_effort'] = 'none';
        }
        $reponse = $this->http->request('POST', rtrim($this->url, '/') . '/chat/completions', [
            'headers' => $this->cle === '' ? [] : ['Authorization' => 'Bearer ' . $this->cle],
            'json' => $corps,
            'timeout' => 300,
        ]);
        $donnees = ReponseApi::lire($reponse, 'Extraction');
        $this->noterJetons($donnees);
        $proposition = self::argumentsDeLOutil($donnees);
        if ($proposition === null) {
            // Règle de Xavier (2026-11-12) : rien d'utile dit, offre vide. Avec une température à 0, réessayer
            // donnait cinq fois la même réponse, puis un échec.
            $this->journal->warning('offreGen, extraction : le modèle n\'a proposé aucune ligne, offre vide.');
            return new ResultatExtraction(null, []);
        }
        return $this->valider($proposition, $catalogue);
    }

    /**
     * Jetons consommés, tels que le service les déclare : de quoi chiffrer le coût d'une offre.
     * Seulement des nombres, jamais le texte de la dictée. Rien si le service ne les donne pas.
     *
     * @param array<string, mixed> $donnees
     */
    private function noterJetons(array $donnees): void
    {
        $usage = $donnees['usage'] ?? null;
        if (!is_array($usage) || !is_int($usage['prompt_tokens'] ?? null) || !is_int($usage['completion_tokens'] ?? null)) {
            return;
        }
        $this->journal->info(sprintf('offreGen, extraction : %d jetons envoyés, %d reçus, modèle %s.', $usage['prompt_tokens'], $usage['completion_tokens'], $this->modele));
    }

    /**
     * @param array<string, mixed> $proposition
     * @param list<array{id: int, categorie: string, designation: string, unite: string, prix_centimes: int, synonymes: string|null}> $catalogue
     */
    public function valider(array $proposition, array $catalogue): ResultatExtraction
    {
        $parId = array_column($catalogue, null, 'id');
        $lignes = [];
        $brutes = is_array($proposition['lignes'] ?? null) ? $proposition['lignes'] : [];
        foreach (array_slice($brutes, 0, self::LIGNES_MAX) as $rang => $brute) {
            if (!is_array($brute)) {
                continue;
            }
            $quantite = $brute['quantite'] ?? null;
            $milliemes = is_int($quantite) || is_float($quantite) ? (int) round($quantite * 1000) : 0;
            if ($milliemes <= 0) {
                $this->journal->warning("offreGen, extraction : ligne $rang sans quantité valable, écartée.");
                continue;
            }
            $entendu = is_string($brute['extrait'] ?? null) ? mb_substr(self::texte($brute['extrait']), 0, 500) : null;
            $doute = ($brute['certitude'] ?? '') !== 'sure';
            $id = $brute['produit_id'] ?? null;
            if (is_int($id) && isset($parId[$id])) {
                $p = $parId[$id];
                $lignes[] = new LigneExtraite($p['designation'], $milliemes, $p['unite'], $p['prix_centimes'], $doute || $p['prix_centimes'] === 0, $p['id'], $entendu);
                continue;
            }
            // Ligne libre, ou produit inconnu du catalogue de cet artisan : toujours à vérifier.
            $designation = is_string($brute['designation'] ?? null) ? mb_substr(self::texte($brute['designation']), 0, 255) : '';
            if ($designation === '') {
                $this->journal->warning("offreGen, extraction : ligne $rang sans désignation ni produit connu, écartée.");
                continue;
            }
            $unite = is_string($brute['unite'] ?? null) && trim($brute['unite']) !== '' ? mb_substr(trim($brute['unite']), 0, 20) : 'forfait';
            $prix = $brute['prix_unitaire'] ?? null;
            $centimes = (is_int($prix) || is_float($prix)) && $prix > 0 ? (int) round($prix * 100) : 0;
            $lignes[] = new LigneExtraite($designation, $milliemes, $unite, $centimes, true, null, $entendu);
        }
        $titre = is_string($proposition['titre'] ?? null) ? mb_substr(self::texte($proposition['titre']), 0, 90) : '';
        $client = is_string($proposition['client'] ?? null) ? mb_substr(self::texte($proposition['client']), 0, 190) : '';
        $lieu = is_string($proposition['lieu'] ?? null) ? mb_substr(self::texte($proposition['lieu']), 0, 255) : '';
        return new ResultatExtraction($titre === '' ? null : $titre, $lignes, $client === '' ? null : $client, $lieu === '' ? null : $lieu);
    }

    /**
     * Texte écrit par le modèle, qui finit sur une offre envoyée au client : sans tiret long
     * (règle de XtraDev ; Qwen en a produit un dans un titre le 2026-11-12).
     */
    private static function texte(string $brut): string
    {
        $t = preg_replace('/\s*[\x{2014}\x{2013}]\s*/u', ', ', trim($brut)) ?? '';
        return trim(preg_replace('/^,\s*|,\s*$/u', '', $t) ?? '');
    }

    /**
     * Les arguments de l'appel à proposer_offre ; null si le modèle a répondu sans l'appeler.
     *
     * @param array<string, mixed> $donnees
     * @return array<string, mixed>|null
     */
    private static function argumentsDeLOutil(array $donnees): ?array
    {
        $choix = is_array($donnees['choices'] ?? null) ? $donnees['choices'] : [];
        $message = is_array($choix[0] ?? null) && is_array($choix[0]['message'] ?? null) ? $choix[0]['message'] : null;
        if ($message === null) {
            throw new \RuntimeException('Extraction : réponse sans message du modèle.');
        }
        $appels = is_array($message['tool_calls'] ?? null) ? $message['tool_calls'] : [];
        foreach ($appels as $appel) {
            $fonction = is_array($appel) && is_array($appel['function'] ?? null) ? $appel['function'] : [];
            if (($fonction['name'] ?? null) !== 'proposer_offre') {
                continue;
            }
            $arguments = $fonction['arguments'] ?? null;
            $decode = is_string($arguments) ? json_decode($arguments, true) : $arguments;
            if (is_array($decode)) {
                /** @var array<string, mixed> $decode */
                return $decode;
            }
        }
        // Le modèle a répondu sans remplir l'offre : la dictée ne contenait rien d'utile.
        return null;
    }

    /** @param list<array{id: int, categorie: string, designation: string, unite: string, prix_centimes: int, synonymes: string|null}> $catalogue */
    private static function catalogueEnTexte(array $catalogue): string
    {
        $lignes = ['id|catégorie|désignation|unité|prix CHF|synonymes'];
        foreach ($catalogue as $p) {
            $prix = $p['prix_centimes'] === 0 ? '?' : number_format($p['prix_centimes'] / 100, 2, '.', '');
            $lignes[] = implode('|', [$p['id'], $p['categorie'], $p['designation'], $p['unite'], $prix, (string) $p['synonymes']]);
        }
        return implode("\n", $lignes);
    }

    /** @return array<string, mixed> */
    private static function outil(): array
    {
        return ['type' => 'function', 'function' => [
            'name' => 'proposer_offre',
            'description' => 'Propose le titre et les lignes de l\'offre tirées de la dictée.',
            'parameters' => [
                'type' => 'object',
                'required' => ['titre', 'lignes'],
                'properties' => [
                    'titre' => ['type' => 'string'],
                    'client' => ['type' => 'string', 'description' => 'Nom du client dicté, ou vide'],
                    'lieu' => ['type' => 'string', 'description' => 'Adresse ou localité dictée, ou vide'],
                    'lignes' => ['type' => 'array', 'items' => [
                        'type' => 'object',
                        'required' => ['produit_id', 'quantite', 'certitude', 'extrait'],
                        'properties' => [
                            'produit_id' => ['type' => ['integer', 'null'], 'description' => 'Identifiant du catalogue, ou null pour une ligne libre'],
                            'designation' => ['type' => 'string', 'description' => 'Seulement pour une ligne libre'],
                            'quantite' => ['type' => 'number'],
                            'unite' => ['type' => 'string', 'description' => 'Seulement pour une ligne libre'],
                            'prix_unitaire' => ['type' => 'number', 'description' => 'CHF, seulement pour une ligne libre dont le prix est dicté'],
                            'certitude' => ['type' => 'string', 'enum' => ['sure', 'a_verifier']],
                            'extrait' => ['type' => 'string'],
                        ],
                    ]],
                ],
            ],
        ]];
    }
}
