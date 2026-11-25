<?php

declare(strict_types=1);

namespace OffreGen\Tests\Offre;

use OffreGen\Offre\ExtracteurApi;
use OffreGen\Offre\Options;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

/**
 * Pièces et options partent ensemble (Xavier, 2026-11-25) : les deux requêtes sont envoyées avant qu'on lise la
 * première réponse ; le client curl les fait alors avancer en même temps (mesure du 2026-11-25 sur un serveur local à
 * 600 ms par appel : 1,21 s au lieu de 1,81 s, ETAT du sous-chantier 31). Un test chronométré sur `php -S` serait
 * instable : un de ses processus peut accepter les deux connexions et les servir l'une après l'autre.
 */
final class PiecesOptionsEnsembleTest extends TestCase
{
    /** @param list<string> $journal */
    private static function outil(string $nom, string $arguments, array &$journal): MockResponse
    {
        $corps = (string) json_encode(['choices' => [['message' => ['role' => 'assistant', 'content' => '', 'tool_calls' => [
            ['id' => 'a', 'type' => 'function', 'function' => ['name' => $nom, 'arguments' => $arguments]],
        ]]]]]);
        $morceaux = (static function () use ($nom, $corps, &$journal): \Generator {
            $journal[] = 'lu ' . $nom;
            yield $corps;
        })();
        return new MockResponse($morceaux, ['http_code' => 200]);
    }

    public function testLesDeuxRequetesPartentAvantLaPremiereLectureEtSAppliquentDansLOrdre(): void
    {
        $journal = [];
        $reponses = [
            'proposer_offre' => '{"titre":"Salon","lignes":[{"produit_id":null,"designation":"Peinture des murs","quantite":42,"unite":"m²","certitude":"sure","extrait":"les murs du salon"},'
                . '{"produit_id":null,"designation":"Peinture silicate","quantite":42,"unite":"m²","certitude":"sure","extrait":"en variante, peinture silicate"}]}',
            'ranger_par_piece' => '{"pieces":[{"ligne":1,"piece":"Salon"},{"ligne":2,"piece":"Cuisine"}]}',
            'classer_lignes' => '{"lignes":[{"ligne":2,"nature":"variante","remplace":1}]}',
        ];
        $http = new MockHttpClient(function (string $m, string $u, array $options) use ($reponses, &$journal): MockResponse {
            $nom = (string) (json_decode((string) $options['body'], true)['tool_choice']['function']['name'] ?? '');
            $journal[] = 'envoi ' . $nom;
            return self::outil($nom, $reponses[$nom] ?? throw new \LogicException('Outil inattendu : ' . $nom), $journal);
        });

        $resultat = (new ExtracteurApi($http, 'https://api.exemple.test/v1', 'j', 'm', new NullLogger()))
            ->extraire('Les murs du salon, 42 mètres carrés. En variante, peinture silicate.', []);

        self::assertSame(['envoi proposer_offre', 'lu proposer_offre', 'envoi ranger_par_piece', 'envoi classer_lignes', 'lu ranger_par_piece', 'lu classer_lignes'], $journal);
        self::assertSame('Salon', $resultat->lignes[0]->piece);
        self::assertSame(Options::VARIANTE, $resultat->lignes[1]->nature);
        self::assertSame(0, $resultat->lignes[1]->remplace);
        // L'appel des pièces met la variante en cuisine ; elle prend la pièce de sa ligne de base, appliquée après.
        self::assertSame('Salon', $resultat->lignes[1]->piece);
    }
}
