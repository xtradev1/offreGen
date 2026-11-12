<?php

declare(strict_types=1);

namespace OffreGen\Tests\Offre;

use OffreGen\Offre\ExtracteurApi;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class ExtracteurApiTest extends TestCase
{
    private const CATALOGUE = [
        ['id' => 1, 'categorie' => 'Préparation', 'designation' => 'Protection des sols et des meubles', 'unite' => 'forfait', 'prix_centimes' => 18000, 'synonymes' => 'bâcher, protéger'],
        ['id' => 4, 'categorie' => 'Peinture intérieure', 'designation' => 'Peinture des murs, 2 couches', 'unite' => 'm²', 'prix_centimes' => 2800, 'synonymes' => 'murs'],
        ['id' => 8, 'categorie' => 'Peinture intérieure', 'designation' => 'Peinture anti-moisissure', 'unite' => 'm²', 'prix_centimes' => 0, 'synonymes' => null],
    ];

    /**
     * Arguments rendus par offregen-qwen3.8 (Ollama) le 2026-11-12 sur la dictée fictive du peintre, abrégés.
     */
    private const ARGUMENTS_REELS = '{"titre":"Offre \u2013 Madame Exemple, Bulle","lignes":[{"certitude":"sure","extrait":"Dans le salon, il faut d\'abord protéger les sols et les meubles.","produit_id":1,"quantite":1,"unite":"forfait"},{"certitude":"a_verifier","extrait":"Les murs du salon, environ quarante-deux mètres carrés, deux couches.","produit_id":4,"quantite":42,"unite":"m²"}]}';

    /** @var list<array{url: string, options: array<string, mixed>}> */
    private array $requetes = [];

    private function extracteur(MockResponse $reponse, string $cle = 'jeton'): ExtracteurApi
    {
        $http = new MockHttpClient(function (string $methode, string $url, array $options) use ($reponse): MockResponse {
            $this->requetes[] = ['url' => $url, 'options' => $options];
            return $reponse;
        });
        return new ExtracteurApi($http, 'https://api.exemple.test/2/ai/12345/openai/v1/', $cle, 'qwen-test', new NullLogger());
    }

    private static function reponseOutil(string $arguments): MockResponse
    {
        return new MockResponse((string) json_encode(['choices' => [['message' => ['role' => 'assistant', 'content' => '', 'tool_calls' => [
            ['id' => 'appel-1', 'type' => 'function', 'function' => ['name' => 'proposer_offre', 'arguments' => $arguments]],
        ]]]]]), ['http_code' => 200]);
    }

    public function testRequeteAuFormatOpenAiAvecOutilImpose(): void
    {
        $this->extracteur(self::reponseOutil(self::ARGUMENTS_REELS))->extraire('Dictée.', self::CATALOGUE);
        $r = $this->requetes[0];
        self::assertSame('https://api.exemple.test/2/ai/12345/openai/v1/chat/completions', $r['url']);
        /** @var list<string> $entetes */
        $entetes = $r['options']['headers'];
        self::assertContains('Authorization: Bearer jeton', $entetes);
        $corps = json_decode((string) $r['options']['body'], true);
        self::assertIsArray($corps);
        self::assertSame('qwen-test', $corps['model']);
        self::assertSame(0, $corps['temperature']);
        self::assertSame('none', $corps['reasoning_effort']);
        self::assertSame(['type' => 'function', 'function' => ['name' => 'proposer_offre']], $corps['tool_choice']);
        self::assertStringContainsString("4|Peinture intérieure|Peinture des murs, 2 couches|m²|28.00|murs", $corps['messages'][1]['content']);
        self::assertStringContainsString("8|Peinture intérieure|Peinture anti-moisissure|m²|?|", $corps['messages'][1]['content']);
    }

    public function testReponseReelleDevientDesLignesDuCatalogue(): void
    {
        $r = $this->extracteur(self::reponseOutil(self::ARGUMENTS_REELS))->extraire('Dictée.', self::CATALOGUE);
        // Le tiret demi-cadratin que Qwen a écrit est remplacé par une virgule.
        self::assertSame('Offre, Madame Exemple, Bulle', $r->titre);
        self::assertCount(2, $r->lignes);
        [$protection, $murs] = $r->lignes;
        self::assertSame(['Protection des sols et des meubles', 1000, 'forfait', 18000, false, 1],
            [$protection->designation, $protection->quantiteMilliemes, $protection->unite, $protection->prixCentimes, $protection->aVerifier, $protection->produitId]);
        self::assertSame([42000, 2800, true, 4], [$murs->quantiteMilliemes, $murs->prixCentimes, $murs->aVerifier, $murs->produitId]);
        self::assertSame('Les murs du salon, environ quarante-deux mètres carrés, deux couches.', $murs->entendu);
    }

    public function testLePrixDuModeleEstIgnorePourUnProduitDuCatalogue(): void
    {
        $r = (new ExtracteurApi(new MockHttpClient(), 'x', '', 'm', new NullLogger()))->valider(['lignes' => [
            ['produit_id' => 4, 'quantite' => 10, 'prix_unitaire' => 1, 'designation' => 'Autre nom', 'unite' => 'pce', 'certitude' => 'sure'],
        ]], self::CATALOGUE);
        self::assertSame(['Peinture des murs, 2 couches', 'm²', 2800, false], [$r->lignes[0]->designation, $r->lignes[0]->unite, $r->lignes[0]->prixCentimes, $r->lignes[0]->aVerifier]);
    }

    public function testProduitSansPrixToujoursAVerifier(): void
    {
        $r = (new ExtracteurApi(new MockHttpClient(), 'x', '', 'm', new NullLogger()))->valider(['lignes' => [
            ['produit_id' => 8, 'quantite' => 12, 'certitude' => 'sure'],
        ]], self::CATALOGUE);
        self::assertTrue($r->lignes[0]->aVerifier);
        self::assertSame(0, $r->lignes[0]->prixCentimes);
    }

    public function testProduitInconnuOuLigneLibreDevientLigneLibreAVerifier(): void
    {
        $r = (new ExtracteurApi(new MockHttpClient(), 'x', '', 'm', new NullLogger()))->valider(['lignes' => [
            ['produit_id' => 999, 'designation' => 'Location de nacelle', 'unite' => 'jour', 'quantite' => 1, 'prix_unitaire' => 380.5, 'certitude' => 'sure'],
            ['produit_id' => null, 'designation' => 'Évacuation de la baignoire', 'quantite' => 1, 'certitude' => 'sure'],
        ]], self::CATALOGUE);
        self::assertSame(['Location de nacelle', 'jour', 38050, true, null], [$r->lignes[0]->designation, $r->lignes[0]->unite, $r->lignes[0]->prixCentimes, $r->lignes[0]->aVerifier, $r->lignes[0]->produitId]);
        self::assertSame(['forfait', 0, true], [$r->lignes[1]->unite, $r->lignes[1]->prixCentimes, $r->lignes[1]->aVerifier]);
    }

    public function testLignesInvalidesEcartees(): void
    {
        $r = (new ExtracteurApi(new MockHttpClient(), 'x', '', 'm', new NullLogger()))->valider(['lignes' => [
            ['produit_id' => 4, 'quantite' => 0, 'certitude' => 'sure'],
            ['produit_id' => 4, 'quantite' => -3, 'certitude' => 'sure'],
            ['produit_id' => 4, 'quantite' => 'beaucoup', 'certitude' => 'sure'],
            ['produit_id' => null, 'quantite' => 2, 'certitude' => 'sure'],
            'pas une ligne',
            ['produit_id' => 4, 'quantite' => 12.3456, 'certitude' => 'sure'],
        ]], self::CATALOGUE);
        self::assertCount(1, $r->lignes);
        self::assertSame(12346, $r->lignes[0]->quantiteMilliemes);
    }

    public function testSansAppelDOutilErreur(): void
    {
        $this->expectExceptionMessage("n'a pas appelé proposer_offre");
        $reponse = new MockResponse((string) json_encode(['choices' => [['message' => ['content' => 'Voici votre offre…']]]]));
        $this->extracteur($reponse)->extraire('Dictée.', self::CATALOGUE);
    }

    public function testDicteeVideSansAppel(): void
    {
        $r = $this->extracteur(self::reponseOutil('{}'))->extraire('   ', self::CATALOGUE);
        self::assertSame([], $r->lignes);
        self::assertSame([], $this->requetes);
    }

    public function testAucunTiretLongNeSortDuModele(): void
    {
        // Titre réellement produit par offregen-qwen3.8 le 2026-11-12.
        $r = (new ExtracteurApi(new MockHttpClient(), 'x', '', 'm', new NullLogger()))->valider([
            'titre' => "Madame Exemple, Bulles \u{2013} Travaux de peinture",
            'lignes' => [['produit_id' => null, 'designation' => "Nacelle\u{2014}1 jour", 'quantite' => 1, 'certitude' => 'sure', 'extrait' => "la nacelle \u{2013} un jour"]],
        ], self::CATALOGUE);
        self::assertSame('Madame Exemple, Bulles, Travaux de peinture', $r->titre);
        self::assertSame('Nacelle, 1 jour', $r->lignes[0]->designation);
        self::assertSame('la nacelle, un jour', $r->lignes[0]->entendu);
    }

    public function testClientEtLieuDictesSontRepris(): void
    {
        $e = new ExtracteurApi(new MockHttpClient(), 'x', '', 'm', new NullLogger());
        $r = $e->valider(['titre' => 'Peinture', 'client' => ' Madame Exemple ', 'lieu' => 'Bulles', 'lignes' => []], self::CATALOGUE);
        self::assertSame(['Madame Exemple', 'Bulles'], [$r->client, $r->lieu]);
        $vide = $e->valider(['titre' => 'Peinture', 'client' => '', 'lignes' => []], self::CATALOGUE);
        self::assertSame([null, null], [$vide->client, $vide->lieu]);
    }

    public function testTitreTronqueEtTitreVideRemplace(): void
    {
        $e = new ExtracteurApi(new MockHttpClient(), 'x', '', 'm', new NullLogger());
        self::assertSame(90, mb_strlen((string) $e->valider(['titre' => str_repeat('é', 200), 'lignes' => []], self::CATALOGUE)->titre));
        self::assertNull($e->valider(['titre' => '  ', 'lignes' => []], self::CATALOGUE)->titre);
    }
}
