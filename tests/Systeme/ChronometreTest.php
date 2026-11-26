<?php

declare(strict_types=1);

namespace OffreGen\Tests\Systeme;

use OffreGen\Audio\PreparationAudio;
use OffreGen\Audio\TranscripteurApi;
use OffreGen\Donnees\Base;
use OffreGen\Donnees\DepotEnregistrements;
use OffreGen\Donnees\DepotEtapes;
use OffreGen\Donnees\FileTraitement;
use OffreGen\Donnees\Portee;
use OffreGen\Ia\ErreurApi;
use OffreGen\Offre\ExtracteurApi;
use OffreGen\Systeme\Chronometre;
use OffreGen\Tests\Outils\BaseDeTest;
use OffreGen\Tests\Outils\Jeu;
use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger;
use Psr\Log\NullLogger;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\HttpClient\CurlHttpClient;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

/** Chronomètre des dictées et pouls du verrou (docs/plans/2026-11-07_voix-offre/31-chronometre-dictees/PLAN.md, lots 2 et 4). */
final class ChronometreTest extends TestCase
{
    private const UUID = '33333333-3333-4333-8333-333333333333';
    private const JETON = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';

    private Jeu $jeu;
    private MockClock $horloge;
    private int $id;

    protected function setUp(): void
    {
        $this->jeu = new Jeu();
        $this->horloge = new MockClock('2026-11-24 11:05:43', 'UTC');
        $this->id = (new DepotEnregistrements($this->jeu->base, new Portee($this->jeu->a)))
            ->creer(self::UUID, 'dictee.webm', 'audio/webm', 672022, "{$this->jeu->a}/" . self::UUID . '.webm', $this->horloge->now());
    }

    private function chrono(?Base $base = null, ?\Psr\Log\LoggerInterface $journal = null): Chronometre
    {
        $base ??= $this->jeu->base;
        return new Chronometre(new DepotEtapes($base), new FileTraitement($base), $this->horloge, $journal ?? new NullLogger());
    }

    /** @return list<array{etape: string, issue: string, tentative: int, processus: string, duree_ms: int|null}> */
    private function etapes(): array
    {
        $r = BaseDeTest::pdo()->query("SELECT etape, issue, tentative, processus, duree_ms FROM dictee_etape WHERE enregistrement_id = {$this->id} ORDER BY id");
        self::assertNotFalse($r);
        /** @var list<array{etape: string, issue: string, tentative: int, processus: string, duree_ms: int|null}> */
        return $r->fetchAll(\PDO::FETCH_ASSOC);
    }

    private function prendreVerrou(string $jeton = self::JETON): void
    {
        self::assertTrue((new FileTraitement($this->jeu->base))->prendreVerrou(self::UUID, $jeton,
            $this->horloge->now()->modify('+' . Chronometre::BAIL_SECONDES . ' seconds'), $this->horloge->now()));
    }

    private function verrouJusqua(): string
    {
        return (string) BaseDeTest::ligne("SELECT verrou_jusqu_a FROM enregistrement WHERE id = {$this->id}")['verrou_jusqu_a'];
    }

    public function testSansPassageOuvertRienNEstEcrit(): void
    {
        $c = $this->chrono();
        self::assertSame(7, $c->mesurer('mesure', static fn (): int => 7));
        $c->commencer('ia_extraction')->finir(['prompt_tokens' => 10, 'completion_tokens' => 2]);
        self::assertSame([], $this->etapes());
    }

    public function testLAttenteCourtDepuisLaReceptionEtChaqueEtapeADureeEtIssue(): void
    {
        $this->horloge->sleep(4.5);
        $c = $this->chrono();
        $c->ouvrirPassage($this->id, self::UUID, null, 1, Chronometre::APRES_REPONSE);
        $this->horloge->sleep(0.25);
        $e = $c->commencer('ia_extraction', 'Qwen/Qwen3.8-27B-FP8');
        $this->horloge->sleep(59.4);
        $e->finir(['prompt_tokens' => 1759, 'completion_tokens' => 793]);
        $c->fermerPassage();

        self::assertSame([
            ['etape' => 'attente', 'issue' => 'ok', 'tentative' => 1, 'processus' => 'apres_reponse', 'duree_ms' => 4500],
            ['etape' => 'ia_extraction', 'issue' => 'ok', 'tentative' => 1, 'processus' => 'apres_reponse', 'duree_ms' => 59400],
        ], $this->etapes());
        self::assertSame(['Qwen/Qwen3.8-27B-FP8', 1759, 793], array_values(BaseDeTest::ligne(
            "SELECT modele, jetons_envoyes, jetons_recus FROM dictee_etape WHERE etape = 'ia_extraction' AND enregistrement_id = {$this->id}")));
    }

    /** L'offre 87 du 24.11 : le premier passage meurt après l'extraction ; le suivant marque l'étape et écrit la reprise. */
    public function testUnPassageMortLaisseSonEtapeQueLeSuivantMarqueInterrompue(): void
    {
        $c = $this->chrono();
        $c->ouvrirPassage($this->id, self::UUID, null, 1, Chronometre::APRES_REPONSE);
        $this->horloge->sleep(10);
        $c->commencer('ecriture');
        // Mort du processus : ni fin d'étape, ni fermeture du passage.
        $this->horloge->sleep(160);
        $suivant = $this->chrono();
        $suivant->ouvrirPassage($this->id, self::UUID, null, 2, Chronometre::CRON);

        self::assertSame([
            ['etape' => 'attente', 'issue' => 'ok', 'tentative' => 1, 'processus' => 'apres_reponse', 'duree_ms' => 0],
            ['etape' => 'ecriture', 'issue' => 'interrompue', 'tentative' => 1, 'processus' => 'apres_reponse', 'duree_ms' => null],
            ['etape' => 'reprise', 'issue' => 'ok', 'tentative' => 2, 'processus' => 'cron', 'duree_ms' => 160000],
        ], $this->etapes());
    }

    public function testApresUneErreurLAttenteCourtDepuisLaFinDeLErreur(): void
    {
        $c = $this->chrono();
        $c->ouvrirPassage($this->id, self::UUID, null, 1, Chronometre::APRES_REPONSE);
        try {
            $c->mesurer('mesure', function (): never {
                $this->horloge->sleep(2);
                throw new \RuntimeException('ffprobe absent');
            });
        } catch (\RuntimeException) {
        }
        $c->fermerPassage();
        $this->horloge->sleep(58);
        $c->ouvrirPassage($this->id, self::UUID, null, 2, Chronometre::CRON);

        self::assertSame(['attente', 'mesure', 'attente'], array_column($this->etapes(), 'etape'));
        self::assertSame([0, 2000, 58000], array_column($this->etapes(), 'duree_ms'));
        self::assertSame('RuntimeException : ffprobe absent', BaseDeTest::ligne("SELECT erreur FROM dictee_etape WHERE etape = 'mesure'")['erreur']);
    }

    /** Une exception hors d'une étape mesurée : les étapes ouvertes du passage passent en erreur, pas en interruption. */
    public function testUnPassageQuiEchoueFermeSesEtapesEnErreur(): void
    {
        $c = $this->chrono();
        $c->ouvrirPassage($this->id, self::UUID, null, 1, Chronometre::APRES_REPONSE);
        $c->commencer('transcription');
        $c->commencer('ecriture');
        $c->echouerOuvertes(new \RuntimeException('MySQL server has gone away'));
        $c->fermerPassage();

        self::assertSame(['ok', 'en_cours', 'erreur'], array_column($this->etapes(), 'issue'), 'l\'attente de la transcription reste ouverte');
    }

    public function testUneEcritureImpossibleNArretePasLaDictee(): void
    {
        $journal = new class extends AbstractLogger {
            /** @var list<string> */
            public array $lignes = [];

            public function log($level, \Stringable|string $message, array $context = []): void
            {
                $this->lignes[] = (string) $message;
            }
        };
        $c = $this->chrono(null, $journal);
        // Dictée inconnue : chaque écriture viole la clé étrangère.
        $c->ouvrirPassage(999999, null, null, 1, Chronometre::CRON);
        self::assertSame('fait', $c->mesurer('mesure', static fn (): string => 'fait'));
        $c->commencer('ia_extraction')->finir();
        $c->fermerPassage();

        self::assertCount(1, $journal->lignes, 'une seule ligne au journal par passage');
        self::assertStringContainsString('chronomètre : écriture impossible', $journal->lignes[0]);
    }

    /** D4 : pendant une réponse lente du modèle, le pouls repousse la fin du verrou. */
    public function testLePoulsProlongeLeVerrouPendantUneReponseLente(): void
    {
        $this->prendreVerrou();
        $c = $this->chrono();
        $c->ouvrirPassage($this->id, self::UUID, self::JETON, 1, Chronometre::APRES_REPONSE);
        $horloge = $this->horloge;
        $corps = (static function () use ($horloge): \Generator {
            // 200 s de calcul chez le service, au-delà du bail de 150 s : la réponse arrive par morceaux espacés.
            foreach (['{"choices":[{"message":', '{"role":"assistant","content":""}', '}],', '"usage":{"prompt_tokens":5,"completion_tokens":1}}'] as $morceau) {
                $horloge->sleep(50);
                yield $morceau;
            }
        })();
        $http = new MockHttpClient([new MockResponse($corps, ['http_code' => 200])]);
        (new ExtracteurApi($http, 'https://api.exemple.test/v1', 'j', 'm', new NullLogger(), chrono: $c))->extraire('Les murs.', []);

        // Dernier pouls au dernier morceau, 200 s après la prise du verrou : verrou jusqu'à 200 + 150 s. Sans pouls, il
        // aurait expiré à 11:08:13, pendant l'appel.
        self::assertSame('2026-11-24 11:11:33', $this->verrouJusqua());
        $ligne = BaseDeTest::ligne("SELECT issue, premier_octet_ms FROM dictee_etape WHERE etape = 'ia_extraction'");
        self::assertSame('ok', $ligne['issue']);
        self::assertNotNull($ligne['premier_octet_ms'], 'premier octet noté par on_progress');
    }

    /** La transcription : préparation de l'audio, puis envoi chez le prestataire, avec le modèle ; un envoi refusé reste en erreur. */
    public function testLaConversionEtLEnvoiALaTranscriptionSontChronometres(): void
    {
        $c = $this->chrono();
        $c->ouvrirPassage($this->id, self::UUID, null, 1, Chronometre::CRON);
        $reponses = [new MockResponse('{"batch_id":"lot-1"}', ['http_code' => 200]), new MockResponse('{"error":{"code":"x"}}', ['http_code' => 500])];
        $api = new TranscripteurApi(new MockHttpClient(static function () use (&$reponses): MockResponse {
            return array_shift($reponses) ?? throw new \LogicException('Appel en trop.');
        }), new PreparationAudio(), 'https://api.exemple.test/', '12345', 'cle', 'whisper', $c);
        $fichier = dirname(__DIR__) . '/fixtures/audio/visite.m4a';

        self::assertSame('lot-1', $api->demarrer($fichier));
        try {
            $api->demarrer($fichier);
            self::fail('Envoi refusé attendu.');
        } catch (ErreurApi) {
        }

        $r = BaseDeTest::pdo()->query("SELECT etape, issue, modele FROM dictee_etape WHERE enregistrement_id = {$this->id} AND etape <> 'attente' ORDER BY id");
        self::assertNotFalse($r);
        self::assertSame([
            ['conversion', 'ok', null], ['transcription_envoi', 'ok', 'whisper'],
            ['conversion', 'ok', null], ['transcription_envoi', 'erreur', 'whisper'],
        ], $r->fetchAll(\PDO::FETCH_NUM));
        self::assertNotNull(BaseDeTest::ligne("SELECT premier_octet_ms FROM dictee_etape WHERE etape = 'transcription_envoi' AND issue = 'ok'")['premier_octet_ms']);
    }

    /**
     * Le premier octet est l'arrivée de la réponse, mesurée par curl réel sur un POST. Recette du 2026-11-24 : le
     * starttransfer_time du curl 7.74 du serveur de test valait 0 ms sur chaque appel à l'IA (début de l'envoi du corps).
     */
    public function testLePremierOctetEstLArriveeDeLaReponse(): void
    {
        $prise = stream_socket_server('tcp://127.0.0.1:0');
        self::assertNotFalse($prise);
        $adresse = (string) stream_socket_get_name($prise, false);
        fclose($prise);
        $serveur = proc_open([PHP_BINARY, '-S', $adresse, dirname(__DIR__) . '/fixtures/http/lente.php'], [1 => ['file', '/dev/null', 'w'], 2 => ['file', '/dev/null', 'w']], $tubes);
        self::assertIsResource($serveur);
        try {
            $limite = microtime(true) + 5;
            while (($s = @fsockopen('tcp://' . $adresse)) === false && microtime(true) < $limite) {
                usleep(50000);
            }
            self::assertNotFalse($s, 'serveur local démarré');
            fclose($s);

            $c = $this->chrono();
            $c->ouvrirPassage($this->id, self::UUID, null, 1, Chronometre::APRES_REPONSE);
            $e = $c->commencer('ia_extraction', 'm');
            $reponse = (new CurlHttpClient())->request('POST', 'http://' . $adresse . '/', [
                'json' => ['corps' => str_repeat('a', 60000)],
                'on_progress' => static function (int $recu, int $taille, array $info) use ($e): void {
                    $e->progres($info);
                },
            ]);
            self::assertSame(['ok' => true], $reponse->toArray());
            $e->finir();
        } finally {
            proc_terminate($serveur);
            proc_close($serveur);
        }
        $premier = BaseDeTest::ligne("SELECT premier_octet_ms FROM dictee_etape WHERE etape = 'ia_extraction'")['premier_octet_ms'];
        self::assertIsInt($premier);
        self::assertGreaterThanOrEqual(290, $premier, 'le serveur répond après 300 ms');
        self::assertLessThan(2000, $premier);
    }

    /** Un autre traitement a repris la dictée : le pouls coupe l'appel en cours, l'exception se reconnaît. */
    public function testLePoulsQuiTrouveLeVerrouReprisCoupeLAppel(): void
    {
        $this->prendreVerrou();
        $c = $this->chrono();
        $c->ouvrirPassage($this->id, self::UUID, self::JETON, 1, Chronometre::APRES_REPONSE);
        $horloge = $this->horloge;
        $id = $this->id;
        $corps = (static function () use ($horloge, $id): \Generator {
            $horloge->sleep(30);
            BaseDeTest::pdo()->exec("UPDATE enregistrement SET verrou_jeton = 'bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb' WHERE id = $id");
            yield '{"choices":[';
            $horloge->sleep(30);
            yield ']}';
        })();
        $http = new MockHttpClient([new MockResponse($corps, ['http_code' => 200])]);
        try {
            (new ExtracteurApi($http, 'https://api.exemple.test/v1', 'j', 'm', new NullLogger(), chrono: $c))->extraire('Les murs.', []);
            self::fail('L\'appel aurait dû être coupé.');
        } catch (\Throwable $e) {
            self::assertTrue(Chronometre::verrouPerdu($e), $e::class . ' : ' . $e->getMessage());
        }
        self::assertSame('erreur', BaseDeTest::ligne("SELECT issue FROM dictee_etape WHERE etape = 'ia_extraction'")['issue']);
    }

    /** Pièces et options lancées ensemble (2026-11-25) : le verrou perdu pendant les pièces coupe aussi les options. */
    public function testLeVerrouPerduPendantLesPiecesCoupeAussiLesOptions(): void
    {
        $this->prendreVerrou();
        $c = $this->chrono();
        $c->ouvrirPassage($this->id, self::UUID, self::JETON, 1, Chronometre::APRES_REPONSE);
        $horloge = $this->horloge;
        $id = $this->id;
        $outil = static fn (string $nom, string $arguments): string => (string) json_encode(['choices' => [['message' => ['role' => 'assistant', 'content' => '',
            'tool_calls' => [['id' => 'a', 'type' => 'function', 'function' => ['name' => $nom, 'arguments' => $arguments]]]]]]]);
        $pieces = (static function () use ($horloge, $id): \Generator {
            $horloge->sleep(30);
            BaseDeTest::pdo()->exec("UPDATE enregistrement SET verrou_jeton = 'bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb' WHERE id = $id");
            yield '{"choices":[';
            $horloge->sleep(30);
            yield ']}';
        })();
        $http = new MockHttpClient([
            new MockResponse($outil('proposer_offre', '{"titre":"T","lignes":[{"produit_id":null,"designation":"Murs","quantite":42,"unite":"m²","certitude":"sure","extrait":"murs"},'
                . '{"produit_id":null,"designation":"Portes","quantite":3,"unite":"pce","certitude":"sure","extrait":"en option les portes"}]}'), ['http_code' => 200]),
            new MockResponse($pieces, ['http_code' => 200]),
            new MockResponse($outil('classer_lignes', '{"lignes":[]}'), ['http_code' => 200]),
        ]);
        try {
            (new ExtracteurApi($http, 'https://api.exemple.test/v1', 'j', 'm', new NullLogger(), chrono: $c))->extraire('Les murs. En option, les portes.', []);
            self::fail('L\'appel aurait dû être coupé.');
        } catch (\Throwable $e) {
            self::assertTrue(Chronometre::verrouPerdu($e), $e::class . ' : ' . $e->getMessage());
        }
        $r = BaseDeTest::pdo()->query("SELECT etape, issue FROM dictee_etape WHERE enregistrement_id = {$this->id} AND etape LIKE 'ia_%' ORDER BY id");
        self::assertNotFalse($r);
        self::assertSame([['ia_extraction', 'ok'], ['ia_pieces', 'erreur'], ['ia_options', 'erreur']], $r->fetchAll(\PDO::FETCH_NUM));
    }
}
