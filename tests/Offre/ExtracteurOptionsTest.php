<?php

declare(strict_types=1);

namespace OffreGen\Tests\Offre;

use OffreGen\Donnees\DepotOffres;
use OffreGen\Donnees\Portee;
use OffreGen\Offre\ExtracteurApi;
use OffreGen\Offre\LigneExtraite;
use OffreGen\Tests\Outils\BaseDeTest;
use OffreGen\Tests\Outils\Jeu;
use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger;
use Psr\Log\NullLogger;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

/**
 * Options et variantes dans l'extraction (docs/plans/2026-11-22_options-variantes/PLAN.md, lot 8) : un troisième appel,
 * seulement quand la dictée a un mot déclencheur ; sa sortie est traitée comme non fiable ; l'offre créée relie la
 * variante à sa ligne.
 */
final class ExtracteurOptionsTest extends TestCase
{
    /** @var list<array{url: string, body: array<string, mixed>}> */
    private array $requetes = [];

    private const PRINCIPAL = '{"titre":"Séjour","lignes":[
        {"produit_id":null,"designation":"Peinture des murs","quantite":42,"unite":"m²","prix_unitaire":28,"certitude":"sure","extrait":"murs 42"},
        {"produit_id":null,"designation":"Peinture lessivable","quantite":1,"unite":"m²","prix_unitaire":34,"certitude":"a_verifier","extrait":"variante lessivable à 34 le mètre"},
        {"produit_id":null,"designation":"Laquage des portes","quantite":3,"unite":"pce","prix_unitaire":140,"certitude":"sure","extrait":"en option les portes"}]}';

    private static function outil(string $nom, string $arguments): MockResponse
    {
        return new MockResponse((string) json_encode(['choices' => [['message' => ['role' => 'assistant', 'content' => '', 'tool_calls' => [
            ['id' => 'a', 'type' => 'function', 'function' => ['name' => $nom, 'arguments' => $arguments]],
        ]]]]]), ['http_code' => 200]);
    }

    /** @param list<MockResponse> $reponses */
    private function extracteur(array $reponses, bool $classer = true, ?\Psr\Log\LoggerInterface $journal = null): ExtracteurApi
    {
        $http = new MockHttpClient(function (string $methode, string $url, array $options) use (&$reponses): MockResponse {
            $this->requetes[] = ['url' => $url, 'body' => json_decode((string) $options['body'], true)];
            return array_shift($reponses) ?? throw new \LogicException('Appel en trop.');
        });
        return new ExtracteurApi($http, 'https://api.exemple.test/v1', 'jeton', 'qwen-test', $journal ?? new NullLogger(), true, null, $classer);
    }

    public function testSansMotDeclencheurPasDeTroisiemeAppel(): void
    {
        $r = $this->extracteur([self::outil('proposer_offre', self::PRINCIPAL), self::outil('ranger_par_piece', '{"pieces":[]}')])
            ->extraire('Les murs du séjour, 42 mètres carrés, les portes.', []);
        self::assertCount(2, $this->requetes, 'appel principal et pièces, rien de plus');
        self::assertSame(['ligne', 'ligne', 'ligne'], array_map(static fn (LigneExtraite $l): string => $l->nature, $r->lignes));
    }

    public function testLeTroisiemeAppelDitLesOptionsEtLesVariantes(): void
    {
        $r = $this->extracteur([self::outil('proposer_offre', self::PRINCIPAL), self::outil('ranger_par_piece', '{"pieces":[{"ligne":1,"piece":"séjour"}]}'),
            self::outil('classer_lignes', '{"lignes":[{"ligne":2,"nature":"variante","remplace":1},{"ligne":3,"nature":"option"}]}')])
            ->extraire('Les murs 42 mètres carrés. Variante, peinture lessivable à 34. En option les portes.', []);
        self::assertCount(3, $this->requetes);
        $troisieme = $this->requetes[2]['body'];
        self::assertSame('classer_lignes', $troisieme['tool_choice']['function']['name']);
        self::assertStringNotContainsString('Catalogue', $troisieme['messages'][1]['content'], 'le catalogue ne repart pas');
        self::assertSame(['ligne', 'variante', 'option'], array_map(static fn (LigneExtraite $l): string => $l->nature, $r->lignes));
        self::assertSame([null, 0, null], array_map(static fn (LigneExtraite $l): ?int => $l->remplace, $r->lignes));
        // La variante comptée 1 dans l'unité de sa ligne prend sa quantité et sa pièce (O2) ; au forfait, elle garderait la sienne (revue, C2).
        self::assertSame([42000, 'm²', 'Séjour'], [$r->lignes[1]->quantiteMilliemes, $r->lignes[1]->unite, $r->lignes[1]->piece]);
        self::assertSame(3400, $r->lignes[1]->prixCentimes);
    }

    public function testLaSortieDuTroisiemeAppelEstTraiteeCommeNonFiable(): void
    {
        $lignes = [new LigneExtraite('Murs', 42000, 'm²', 2800, false, null), new LigneExtraite('Lessivable', 42000, 'm²', 3400, false, null),
            new LigneExtraite('Portes', 3000, 'pce', 14000, false, null), new LigneExtraite('Minérale', 42000, 'm²', 2600, false, null)];
        $sortie = $this->extracteur([])->validerOptions(['lignes' => [
            ['ligne' => 9, 'nature' => 'option'],                 // hors de la liste : ignorée
            ['ligne' => 1, 'nature' => 'bidon'],                  // nature inconnue : ignorée
            ['ligne' => 2, 'nature' => 'variante', 'remplace' => 2], // se remplace elle-même : option à vérifier
            ['ligne' => 3, 'nature' => 'option'],
            ['ligne' => 4, 'nature' => 'variante', 'remplace' => 3], // remplace une option : option à vérifier
            ['ligne' => 3, 'nature' => 'variante', 'remplace' => 1], // deuxième avis sur la ligne 3 : ignoré
        ]], $lignes);
        self::assertSame(['ligne', 'option', 'option', 'option'], array_map(static fn (LigneExtraite $l): string => $l->nature, $sortie));
        // Toute ligne sortie du total passe à vérifier (revue, R1).
        self::assertSame([false, true, true, true], array_map(static fn (LigneExtraite $l): bool => $l->aVerifier, $sortie));
        self::assertSame([null, null, null, null], array_map(static fn (LigneExtraite $l): ?int => $l->remplace, $sortie));
    }

    public function testUnTroisiemeAppelEnEchecLaisseToutDansLeTotal(): void
    {
        $journal = new class () extends AbstractLogger {
            /** @var list<string> */
            public array $lignes = [];

            public function log($level, string|\Stringable $message, array $context = []): void
            {
                $this->lignes[] = $level . ' ' . $message;
            }
        };
        $r = $this->extracteur([self::outil('proposer_offre', self::PRINCIPAL), self::outil('ranger_par_piece', '{"pieces":[]}'),
            new MockResponse('{"erreur":"panne"}', ['http_code' => 500])], true, $journal)->extraire('En option, les portes.', []);
        self::assertSame(['ligne', 'ligne', 'ligne'], array_map(static fn (LigneExtraite $l): string => $l->nature, $r->lignes));
        self::assertStringContainsString('warning offreGen, options : troisième appel en échec', implode("\n", $journal->lignes));
    }

    public function testLeBancPeutSePasserDuTroisiemeAppel(): void
    {
        $this->extracteur([self::outil('proposer_offre', self::PRINCIPAL), self::outil('ranger_par_piece', '{"pieces":[]}')], false)
            ->extraire('En option, les portes.', []);
        self::assertCount(2, $this->requetes);
    }

    public function testLOffreCreeeRelieLaVarianteASaLigne(): void
    {
        $jeu = new Jeu();
        $depot = new DepotOffres($jeu->base, new Portee($jeu->a));
        $id = $depot->creerExemple('Séjour', [new LigneExtraite('Murs', 42000, 'm²', 2800, false, null),
            new LigneExtraite('Lessivable', 42000, 'm²', 3400, false, null, null, null, null, 'variante', 0),
            new LigneExtraite('Portes', 3000, 'pce', 14000, false, null, null, null, null, 'option'),
            new LigneExtraite('Argile', 42000, 'm²', 3900, false, null, null, null, null, 'variante', 2)], $jeu->maintenant, $jeu->client['a']);
        $r = BaseDeTest::pdo()->query("SELECT designation, nature, remplace_id, a_verifier, id FROM ligne WHERE offre_id = $id ORDER BY rang");
        self::assertNotFalse($r);
        $lignes = $r->fetchAll(\PDO::FETCH_ASSOC);
        self::assertSame(['ligne', 'variante', 'option', 'option'], array_column($lignes, 'nature'), 'une variante d\'une option devient une option');
        self::assertSame((int) $lignes[0]['id'], (int) $lignes[1]['remplace_id']);
        self::assertSame([0, 0, 0, 1], array_map('intval', array_column($lignes, 'a_verifier')));
    }
}
