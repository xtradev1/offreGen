<?php

declare(strict_types=1);

namespace OffreGen\Tests\Offre;

use OffreGen\Offre\ExtracteurApi;
use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger;
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

    public function testLesJetonsConsommesVontAuJournalSansLeTexte(): void
    {
        $journal = new class extends AbstractLogger {
            /** @var list<string> */
            public array $lignes = [];

            public function log($level, string|\Stringable $message, array $context = []): void
            {
                $this->lignes[] = $level . ' ' . $message;
            }

            public function vider(): void
            {
                $this->lignes = [];
            }
        };
        $donnees = ['choices' => [['message' => ['role' => 'assistant', 'content' => '', 'tool_calls' => [
            ['id' => 'appel-1', 'type' => 'function', 'function' => ['name' => 'proposer_offre', 'arguments' => self::ARGUMENTS_REELS]],
        ]]]], 'usage' => ['prompt_tokens' => 2140, 'completion_tokens' => 310, 'total_tokens' => 2450]];
        $http = new MockHttpClient(new MockResponse((string) json_encode($donnees), ['http_code' => 200]));
        (new ExtracteurApi($http, 'https://api.exemple.test/v1', 'jeton', 'qwen-test', $journal))->extraire('Madame Secret, rue Privée 3.', self::CATALOGUE);

        self::assertContains('info offreGen, extraction : 2140 jetons envoyés, 310 reçus, modèle qwen-test.', $journal->lignes);
        self::assertStringNotContainsString('Secret', implode("\n", $journal->lignes));

        // Sans compte de jetons dans la réponse : rien d'inventé.
        $journal->vider();
        (new ExtracteurApi(new MockHttpClient(self::reponseOutil(self::ARGUMENTS_REELS)), 'https://api.exemple.test/v1', 'jeton', 'qwen-test', $journal))
            ->extraire('Dictée.', self::CATALOGUE);
        self::assertStringNotContainsString('jetons', implode("\n", $journal->lignes));
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

    public function testLePrixDuModeleNeRemplaceJamaisLeCatalogue(): void
    {
        // Le catalogue gagne toujours (nom, unité, prix) ; depuis le 2026-11-17, un autre prix dit est gardé à côté et
        // la ligne passe à vérifier, pour que l'artisan le voie (décision de Xavier).
        $r = (new ExtracteurApi(new MockHttpClient(), 'x', '', 'm', new NullLogger()))->valider(['lignes' => [
            ['produit_id' => 4, 'quantite' => 10, 'prix_unitaire' => 1, 'designation' => 'Autre nom', 'unite' => 'pce', 'certitude' => 'sure'],
        ]], self::CATALOGUE);
        self::assertSame(['Peinture des murs, 2 couches', 'm²', 2800, true, 100], [$r->lignes[0]->designation, $r->lignes[0]->unite, $r->lignes[0]->prixCentimes, $r->lignes[0]->aVerifier, $r->lignes[0]->prixDicteCentimes]);
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

    /**
     * Un poste dicté ne se perd jamais pour une quantité manquante ou fausse (essai de Xavier du 2026-11-16 : « des frais
     * de nettoyage », rendus par Qwen 27B avec une quantité null, avaient disparu de l'offre) : 1, à vérifier. Seule une
     * ligne sans désignation ni produit connu est écartée, faute de rien à montrer.
     */
    public function testUnPosteSansQuantiteValableResteAUnAVerifier(): void
    {
        $r = (new ExtracteurApi(new MockHttpClient(), 'x', '', 'm', new NullLogger()))->valider(['lignes' => [
            ['produit_id' => 4, 'quantite' => 0, 'certitude' => 'sure'],
            ['produit_id' => 4, 'quantite' => -3, 'certitude' => 'sure'],
            ['produit_id' => 4, 'quantite' => 'beaucoup', 'certitude' => 'sure'],
            ['produit_id' => null, 'quantite' => 2, 'certitude' => 'sure'],
            'pas une ligne',
            ['produit_id' => 4, 'quantite' => 12.3456, 'certitude' => 'sure'],
            ['produit_id' => null, 'designation' => 'Frais de nettoyage', 'quantite' => null, 'unite' => 'forfait', 'certitude' => 'sure', 'extrait' => 'et des frais de nettoyage'],
        ]], self::CATALOGUE);
        self::assertSame([1000, 1000, 1000, 12346, 1000], array_map(static fn ($l): int => $l->quantiteMilliemes, $r->lignes));
        self::assertSame([true, true, true, false, true], array_map(static fn ($l): bool => $l->aVerifier, $r->lignes), 'Une quantité devinée est toujours à vérifier.');
        self::assertSame(['Frais de nettoyage', 'forfait', 0, null], [$r->lignes[4]->designation, $r->lignes[4]->unite, $r->lignes[4]->prixCentimes, $r->lignes[4]->produitId]);
    }

    public function testSansAppelDOutilUneOffreVide(): void
    {
        // Constaté le 2026-11-12 : une dictée sans rien d'utile, le modèle répond une phrase au lieu de
        // l'outil, cinq fois de suite (température 0), et l'enregistrement finissait en échec. Règle de
        // Xavier : rien d'utile dit, offre vide.
        $journal = new class extends AbstractLogger {
            /** @var list<string> */
            public array $lignes = [];

            public function log($level, string|\Stringable $message, array $context = []): void
            {
                $this->lignes[] = $level . ' ' . $message;
            }
        };
        $reponse = new MockResponse((string) json_encode(['choices' => [['message' => ['role' => 'assistant', 'content' => 'Je n\'ai rien à proposer.']]]]));
        $r = (new ExtracteurApi(new MockHttpClient($reponse), 'https://api.exemple.test/v1', 'jeton', 'qwen-test', $journal))->extraire('Euh… bon.', self::CATALOGUE);
        self::assertSame([null, []], [$r->titre, $r->lignes]);
        self::assertContains('warning offreGen, extraction : le modèle n\'a proposé aucune ligne, offre vide.', $journal->lignes);
    }

    public function testReponseSansChoixResteUneErreur(): void
    {
        $this->expectExceptionMessage('Extraction : réponse sans message du modèle.');
        $this->extracteur(new MockResponse((string) json_encode(['erreur' => 'surcharge'])))->extraire('Dictée.', self::CATALOGUE);
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

    public function testPrixDicteDUnProduitDuCatalogueGardeACoteEtAVerifier(): void
    {
        // Dictée de Luigi (2026-11-17) : « deux couches de peinture sur les murs, 45 m² à 15 francs », catalogue à 28.
        $r = (new ExtracteurApi(new MockHttpClient(), 'x', '', 'm', new NullLogger()))->valider(['lignes' => [
            ['produit_id' => 4, 'quantite' => 45, 'prix_unitaire' => 15, 'certitude' => 'sure', 'extrait' => '45 m² à 15 francs'],
            ['produit_id' => 4, 'quantite' => 10, 'prix_unitaire' => 28, 'certitude' => 'sure'],
            ['produit_id' => 4, 'quantite' => 12, 'certitude' => 'sure'],
        ]], self::CATALOGUE);
        self::assertSame([2800, 1500, true], [$r->lignes[0]->prixCentimes, $r->lignes[0]->prixDicteCentimes, $r->lignes[0]->aVerifier]);
        // Prix dicté égal au catalogue, ou pas de prix dit : rien à signaler.
        self::assertSame([null, false], [$r->lignes[1]->prixDicteCentimes, $r->lignes[1]->aVerifier]);
        self::assertSame([null, false], [$r->lignes[2]->prixDicteCentimes, $r->lignes[2]->aVerifier]);
    }

    public function testLaPieceDicteeEstGardeeEtNettoyee(): void
    {
        // Sous-chantier 28 (Xavier, 2026-11-17) : les lignes se rangent par pièce ; « général » vaut pour tout le chantier.
        $r = (new ExtracteurApi(new MockHttpClient(), 'x', '', 'm', new NullLogger()))->valider(['lignes' => [
            ['produit_id' => 4, 'quantite' => 38, 'certitude' => 'sure', 'piece' => '  salon du deuxième   étage '],
            ['produit_id' => 1, 'quantite' => 1, 'certitude' => 'sure', 'piece' => 'Général'],
            ['produit_id' => 4, 'quantite' => 12, 'certitude' => 'sure', 'piece' => 'tout le chantier'],
            ['produit_id' => 4, 'quantite' => 12, 'certitude' => 'sure'],
            ['produit_id' => null, 'designation' => 'Toile de verre', 'quantite' => 22, 'unite' => 'm²', 'certitude' => 'sure', 'piece' => str_repeat('é', 120)],
        ]], self::CATALOGUE);
        self::assertSame(['Salon du deuxième étage', null, null, null], array_map(fn ($l) => $l->piece, array_slice($r->lignes, 0, 4)));
        self::assertSame(80, mb_strlen((string) $r->lignes[4]->piece));
    }

    public function testLesPiecesViennentDUnSecondAppelSansCatalogue(): void
    {
        // Sous-chantier 28 : l'appel principal ne demande pas la pièce (mesuré le 2026-11-17, il perdait des prix) ;
        // un second appel range les lignes trouvées.
        $pieces = new MockResponse((string) json_encode(['choices' => [['message' => ['role' => 'assistant', 'content' => '', 'tool_calls' => [
            ['id' => 'appel-2', 'type' => 'function', 'function' => ['name' => 'ranger_par_piece', 'arguments' => '{"pieces":[{"ligne":1,"piece":"général"},{"ligne":2,"piece":"salon"}]}']],
        ]]]]]), ['http_code' => 200]);
        $reponses = [self::reponseOutil(self::ARGUMENTS_REELS), $pieces];
        $http = new MockHttpClient(function (string $methode, string $url, array $options) use (&$reponses): MockResponse {
            $this->requetes[] = ['url' => $url, 'options' => $options];
            return array_shift($reponses) ?? throw new \LogicException('Appel en trop.');
        });
        $r = (new ExtracteurApi($http, 'https://api.exemple.test/v1', 'jeton', 'qwen-test', new NullLogger()))->extraire('Dictée.', self::CATALOGUE);
        self::assertSame([null, 'Salon'], [$r->lignes[0]->piece, $r->lignes[1]->piece]);
        self::assertSame([42000, 2800], [$r->lignes[1]->quantiteMilliemes, $r->lignes[1]->prixCentimes], 'le reste de la ligne ne bouge pas');
        self::assertCount(2, $this->requetes);
        $premier = json_decode((string) $this->requetes[0]['options']['body'], true);
        self::assertArrayNotHasKey('piece', $premier['tools'][0]['function']['parameters']['properties']['lignes']['items']['properties']);
        $second = json_decode((string) $this->requetes[1]['options']['body'], true);
        self::assertSame('ranger_par_piece', $second['tool_choice']['function']['name']);
        self::assertStringNotContainsString('Catalogue', $second['messages'][1]['content'], 'le catalogue ne repart pas');
        self::assertStringContainsString('2. Peinture des murs, 2 couches (« Les murs du salon', $second['messages'][1]['content']);
    }

    public function testLeSecondAppelPeutPrendreUnAutreModele(): void
    {
        // Question de Xavier (2026-11-17) : un modèle moins cher pour ranger les pièces ?
        $reponses = [self::reponseOutil(self::ARGUMENTS_REELS), new MockResponse('{}', ['http_code' => 200])];
        $http = new MockHttpClient(function (string $methode, string $url, array $options) use (&$reponses): MockResponse {
            $this->requetes[] = ['url' => $url, 'options' => $options];
            return array_shift($reponses) ?? throw new \LogicException('Appel en trop.');
        });
        (new ExtracteurApi($http, 'https://api.exemple.test/v1', 'jeton', 'qwen-test', new NullLogger(), true, 'gemma-test'))->extraire('Dictée.', self::CATALOGUE);
        $modeles = array_map(fn (array $r) => json_decode((string) $r['options']['body'], true)['model'], $this->requetes);
        self::assertSame(['qwen-test', 'gemma-test'], $modeles);
    }

    public function testSansSecondAppelLOffreSortEntiereSansPieces(): void
    {
        $reponses = [self::reponseOutil(self::ARGUMENTS_REELS), new MockResponse('{"erreur":"surcharge"}', ['http_code' => 503])];
        $http = new MockHttpClient(function () use (&$reponses): MockResponse {
            return array_shift($reponses) ?? throw new \LogicException('Appel en trop.');
        });
        $journal = new class extends AbstractLogger {
            /** @var list<string> */
            public array $lignes = [];

            public function log($level, string|\Stringable $message, array $context = []): void
            {
                $this->lignes[] = $level . ' ' . $message;
            }
        };
        $r = (new ExtracteurApi($http, 'https://api.exemple.test/v1', 'jeton', 'qwen-test', $journal))->extraire('Dictée.', self::CATALOGUE);
        self::assertCount(2, $r->lignes);
        self::assertSame([null, null], [$r->lignes[0]->piece, $r->lignes[1]->piece]);
        self::assertStringContainsString('warning offreGen, pièces : second appel en échec', implode("\n", $journal->lignes));
    }

    public function testLaConsigneDemandeLePrixDitPourToutesLesLignes(): void
    {
        $this->extracteur(self::reponseOutil(self::ARGUMENTS_REELS))->extraire('Dictée.', self::CATALOGUE);
        $corps = json_decode((string) $this->requetes[0]['options']['body'], true);
        // Règles mesurées sur la dictée de Luigi le 2026-11-17 (docs/plans/2026-11-07_voix-offre/29-qualite-extraction).
        self::assertStringContainsString('catalogue compris', $corps['messages'][0]['content']);
        self::assertStringContainsString('en bloc', $corps['messages'][0]['content']);
        self::assertStringContainsString('toute ligne', $corps['tools'][0]['function']['parameters']['properties']['lignes']['items']['properties']['prix_unitaire']['description']);
    }

    public function testTroisCentsLignesGardeesPasUneDePlus(): void
    {
        // Banc du 2026-11-17 : 300 postes dictés en 30 minutes, 299 rendus par le 27B. Le plafond ne doit pas les couper.
        $lignes = array_map(fn (int $i) => ['produit_id' => null, 'designation' => "Poste $i", 'quantite' => 1, 'certitude' => 'sure'], range(1, 301));
        $r = (new ExtracteurApi(new MockHttpClient(), 'x', '', 'm', new NullLogger()))->valider(['lignes' => $lignes], self::CATALOGUE);
        self::assertCount(300, $r->lignes);
        self::assertSame('Poste 300', $r->lignes[299]->designation);
        self::assertSame(1, $r->postesCoupes);
    }

    public function testSousLePlafondAucunPosteCoupe(): void
    {
        $r = $this->extracteur(self::reponseOutil(self::ARGUMENTS_REELS))->extraire('Dictée.', self::CATALOGUE);
        self::assertSame(0, $r->postesCoupes);
    }

    public function testLaSortieDuModeleEstBornee(): void
    {
        // Revue du 2026-11-18 : sans max_tokens, la limite par défaut du service coupait l'offre sans prévenir.
        $this->extracteur(self::reponseOutil(self::ARGUMENTS_REELS))->extraire('Dictée.', self::CATALOGUE);
        $corps = json_decode((string) $this->requetes[0]['options']['body'], true);
        self::assertIsArray($corps);
        self::assertSame(ExtracteurApi::JETONS_SORTIE_MAX, $corps['max_tokens']);
        // Revue du 2026-11-18 (B2) : une durée totale bornée, sous le verrou de 900 s du traitement.
        self::assertLessThan(900, $this->requetes[0]['options']['max_duration']);
        self::assertGreaterThan(0, $this->requetes[0]['options']['max_duration']);
        // 300 lignes d'environ 57 jetons (banc du 2026-11-17) doivent tenir.
        self::assertGreaterThan(300 * 57, ExtracteurApi::JETONS_SORTIE_MAX);
    }

    public function testUneReponseCoupeeParLaLimiteEstUnEchecPasUneOffreVide(): void
    {
        $reponse = new MockResponse((string) json_encode(['choices' => [['finish_reason' => 'length', 'message' => ['role' => 'assistant', 'content' => '', 'tool_calls' => [
            ['id' => 'appel-1', 'type' => 'function', 'function' => ['name' => 'proposer_offre', 'arguments' => '{"titre":"Offre","lignes":[{"produit_id":4,"quan']],
        ]]]]]), ['http_code' => 200]);
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('coupée');
        $this->extracteur($reponse)->extraire('Dictée.', self::CATALOGUE);
    }

    public function testDesArgumentsIllisiblesSontUnEchecPasUneOffreVide(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->extracteur(self::reponseOutil('{"titre":"Offre","lignes":[{"produit_id":4,'))->extraire('Dictée.', self::CATALOGUE);
    }

    public function testQuantiteEtPrixDictesHorsBornesNeCassentPasLOffre(): void
    {
        // Revue du 2026-11-18 : 3 millions de m² donnaient 3 000 000 000 millièmes, refusés par la base après l'appel payé.
        $r = (new ExtracteurApi(new MockHttpClient(), 'x', '', 'm', new NullLogger()))->valider(['lignes' => [
            ['produit_id' => 4, 'quantite' => 3e6, 'certitude' => 'sure', 'prix_unitaire' => 5e6],
            ['produit_id' => null, 'designation' => 'Échafaudage', 'quantite' => 1, 'certitude' => 'sure', 'prix_unitaire' => 9e9],
        ]], self::CATALOGUE);
        self::assertCount(2, $r->lignes);
        self::assertSame(1000, $r->lignes[0]->quantiteMilliemes);
        self::assertTrue($r->lignes[0]->aVerifier);
        self::assertNull($r->lignes[0]->prixDicteCentimes);
        self::assertSame(2800, $r->lignes[0]->prixCentimes);
        self::assertSame(0, $r->lignes[1]->prixCentimes);
        self::assertTrue($r->lignes[1]->aVerifier);
    }

    public function testUnIdentifiantDeProduitEnTexteResteLeProduit(): void
    {
        $r = (new ExtracteurApi(new MockHttpClient(), 'x', '', 'm', new NullLogger()))->valider(['lignes' => [
            ['produit_id' => '4', 'quantite' => 12, 'certitude' => 'sure'],
        ]], self::CATALOGUE);
        self::assertSame(4, $r->lignes[0]->produitId);
        self::assertSame('Peinture des murs, 2 couches', $r->lignes[0]->designation);
    }
}
