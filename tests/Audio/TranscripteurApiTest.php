<?php

declare(strict_types=1);

namespace OffreGen\Tests\Audio;

use OffreGen\Audio\PreparationAudio;
use OffreGen\Audio\TranscripteurApi;
use OffreGen\Audio\TranscriptionEchouee;
use OffreGen\Ia\ErreurApi;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

/**
 * Le protocole d'Infomaniak tel que documenté le 2026-11-12, joué contre un faux serveur.
 */
final class TranscripteurApiTest extends TestCase
{
    /** @var list<array{methode: string, url: string, entetes: list<string>, corps: string}> */
    private array $requetes = [];

    /** @param list<MockResponse> $reponses */
    private function transcripteur(array $reponses, string $cle = 'jeton-secret'): TranscripteurApi
    {
        $http = new MockHttpClient(function (string $methode, string $url, array $options) use (&$reponses): MockResponse {
            $corps = '';
            if (isset($options['body'])) {
                $corps = is_string($options['body']) ? $options['body'] : '';
                if (is_callable($options['body'])) {
                    while (($morceau = ($options['body'])(16384)) !== '') {
                        $corps .= $morceau;
                    }
                }
            }
            /** @var list<string> $entetes */
            $entetes = $options['headers'] ?? [];
            $this->requetes[] = ['methode' => $methode, 'url' => $url, 'entetes' => $entetes, 'corps' => $corps];
            return array_shift($reponses) ?? new MockResponse('', ['http_code' => 500]);
        });
        return new TranscripteurApi($http, new PreparationAudio(), 'https://api.exemple.test/', '12345', $cle, 'whisper');
    }

    private static function json(mixed $donnees, int $code = 200): MockResponse
    {
        return new MockResponse((string) json_encode($donnees), ['http_code' => $code, 'response_headers' => ['content-type: application/json']]);
    }

    private static function fixture(string $nom): string
    {
        return dirname(__DIR__) . '/fixtures/audio/' . $nom;
    }

    public function testEnvoiAuFormatOpenAiAvecLeJeton(): void
    {
        $lot = $this->transcripteur([self::json(['batch_id' => '9b9fec49-cc95-44d5-8d3a-be56a6e05970'])])
            ->demarrer(self::fixture('visite.m4a'));

        self::assertSame('9b9fec49-cc95-44d5-8d3a-be56a6e05970', $lot);
        $r = $this->requetes[0];
        self::assertSame('POST', $r['methode']);
        self::assertSame('https://api.exemple.test/1/ai/12345/openai/audio/transcriptions', $r['url']);
        self::assertContains('Authorization: Bearer jeton-secret', $r['entetes']);
        self::assertMatchesRegularExpression('/^Content-Type: multipart\/form-data; boundary=/m', implode("\n", $r['entetes']));
        // Longueur exacte annoncée : pas d'envoi par morceaux (défaut trouvé le 2026-11-12 contre le Whisper local).
        self::assertContains('Content-Length: ' . strlen($r['corps']), $r['entetes']);
        foreach (['name="model"' => 'whisper', 'name="language"' => 'fr', 'name="response_format"' => 'text'] as $champ => $valeur) {
            self::assertMatchesRegularExpression('/' . preg_quote($champ, '/') . '\r\n(?:[^\r\n]+\r\n)*\r\n' . $valeur . '\r\n/', $r['corps']);
        }
        self::assertStringContainsString('name="file"; filename="audio.m4a"', $r['corps']);
        self::assertStringContainsString((string) file_get_contents(self::fixture('visite.m4a')), $r['corps']);
    }

    public function testSansCleAucunEnteteAuthorization(): void
    {
        $this->transcripteur([self::json(['batch_id' => 'abc'])], '')->demarrer(self::fixture('visite.m4a'));
        self::assertDoesNotMatchRegularExpression('/^Authorization/mi', implode("\n", $this->requetes[0]['entetes']));
    }

    public function testReponseEnveloppeeAcceptee(): void
    {
        $lot = $this->transcripteur([self::json(['result' => 'success', 'data' => ['batch_id' => 'lot-1']])])->demarrer(self::fixture('visite.m4a'));
        self::assertSame('lot-1', $lot);
    }

    public function testAmrConvertiAvantEnvoiEtCopieEffacee(): void
    {
        $avant = glob(sys_get_temp_dir() . '/offregen-conv-*') ?: [];
        $this->transcripteur([self::json(['batch_id' => 'lot-amr'])])->demarrer(self::fixture('visite.amr'));
        self::assertStringContainsString('filename="audio.ogg"', $this->requetes[0]['corps']);
        self::assertSame($avant, glob(sys_get_temp_dir() . '/offregen-conv-*') ?: []);
    }

    public function testRefusDuServiceRemonteSansLeCorps(): void
    {
        try {
            $this->transcripteur([self::json(['result' => 'error', 'error' => ['code' => 'validation_failed', 'description' => 'secret du service']], 422)])
                ->demarrer(self::fixture('visite.m4a'));
            self::fail('Refus non remonté.');
        } catch (ErreurApi $e) {
            self::assertSame(422, $e->getCode());
            self::assertStringContainsString('validation_failed', $e->getMessage());
            self::assertStringNotContainsString('secret du service', $e->getMessage());
        }
    }

    public function testReponseSansNumeroDeLotRefusee(): void
    {
        $this->expectExceptionMessage('aucun numéro de lot');
        $this->transcripteur([self::json(['autre' => 1])])->demarrer(self::fixture('visite.m4a'));
    }

    public function testLotEnCoursRendNull(): void
    {
        $t = $this->transcripteur([self::json(['status' => 'pending']), self::json(['status' => 'processing'])]);
        self::assertNull($t->resultat('lot-1'));
        self::assertNull($t->resultat('lot-1'));
        self::assertSame('https://api.exemple.test/1/ai/12345/results/lot-1', $this->requetes[0]['url']);
        self::assertSame('GET', $this->requetes[0]['methode']);
    }

    public function testLotReussiRendLeTexteBrutOuJson(): void
    {
        $t = $this->transcripteur([
            self::json(['status' => 'success', 'url' => 'x', 'file_name' => 'x', 'file_size' => 3, 'data' => " Bonjour. \n"]),
            self::json(['status' => 'success', 'data' => '{"text": "Salut"}']),
            self::json(['result' => 'success', 'data' => ['status' => 'success', 'data' => 'Enveloppé']]),
        ]);
        self::assertSame('Bonjour.', $t->resultat('a'));
        self::assertSame('Salut', $t->resultat('b'));
        self::assertSame('Enveloppé', $t->resultat('c'));
    }

    public function testLotEchoueAnnuleOuIntrouvableDemandeUnRenvoi(): void
    {
        foreach ([self::json(['status' => 'failed']), self::json(['status' => 'cancelled']), self::json(['result' => 'error'], 404)] as $reponse) {
            try {
                $this->transcripteur([$reponse])->resultat('lot-1');
                self::fail('Pas de TranscriptionEchouee');
            } catch (TranscriptionEchouee) {
                $this->addToAssertionCount(1);
            }
        }
    }

    /** Infomaniak, relevé le 2026-11-14 : un lot inconnu rend 403 access_result_forbidden, pas 404. */
    public function testLotInconnuChezInfomaniakDemandeUnRenvoi(): void
    {
        $this->expectException(TranscriptionEchouee::class);
        $this->transcripteur([self::json(['result' => 'error', 'error' => ['code' => 'access_result_forbidden', 'description' => 'access_result_forbidden']], 403)])
            ->resultat('lot-1');
    }

    /** Un autre 403 (jeton sans le droit) n'est pas un lot perdu : renvoyer l'audio n'y changerait rien. */
    public function testAutreRefusNEstPasUnLotPerdu(): void
    {
        try {
            $this->transcripteur([self::json(['result' => 'error', 'error' => ['code' => 'not_authorized']], 403)])->resultat('lot-1');
            self::fail('Refus non remonté.');
        } catch (ErreurApi $e) {
            self::assertSame(403, $e->getCode());
            self::assertSame('not_authorized', $e->codeService);
        }
    }

    public function testPanneDuServiceNEstPasUnLotPerdu(): void
    {
        $this->expectException(ErreurApi::class);
        $this->transcripteur([self::json(['result' => 'error'], 503)])->resultat('lot-1');
    }

    public function testReferenceInjecteeRefusee(): void
    {
        $this->expectException(TranscriptionEchouee::class);
        $this->transcripteur([])->resultat('../../autre');
    }
}
