<?php

declare(strict_types=1);

namespace OffreGen\Tests\Adresse;

use OffreGen\Adresse\Adresse;
use OffreGen\Adresse\RegistreFactice;
use OffreGen\Adresse\RegistreIndisponible;
use OffreGen\Adresse\RegistreSwisstopo;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class RegistreSwisstopoTest extends TestCase
{
    /** Réponse relevée le 2026-11-13 sur api3.geo.admin.ch (« rue de la prefecture 2800 »), réduite aux champs lus. */
    private const REPONSE = '{"results":[{"attrs":{"detail":"rue de la prefecture 1 2800 delemont 6711 delemont ch ju","label":"Rue de la Préfecture 1 <b>2800 Delémont</b>","num":1,"origin":"address"},"id":61989,"weight":4},'
        . '{"attrs":{"label":"Rue de la Préfecture 2 <b>2800 Delémont</b>","num":2,"origin":"address"}},'
        . '{"attrs":{"label":"Chemin des Acácias 12a <b>6900 Lugano</b>"}},'
        . '{"attrs":{"label":"sans NPA"}},{"pas":"d\'attrs"}]}';

    public function testLesAdressesSeLisentDuLibelle(): void
    {
        $demandes = [];
        $http = new MockHttpClient(static function (string $methode, string $url, array $options) use (&$demandes): MockResponse {
            $demandes[] = [$methode, $url, $options['timeout'] ?? null];
            return new MockResponse(self::REPONSE, ['response_headers' => ['content-type' => 'application/json']]);
        });
        $adresses = (new RegistreSwisstopo($http))->chercher('rue de la prefecture 2800', 5);
        self::assertEquals([
            new Adresse('Rue de la Préfecture', '1', '2800', 'Delémont'),
            new Adresse('Rue de la Préfecture', '2', '2800', 'Delémont'),
            new Adresse('Chemin des Acácias', '12a', '6900', 'Lugano'),
        ], $adresses);
        self::assertCount(1, $demandes);
        [$methode, $url, $delai] = $demandes[0];
        self::assertSame('GET', $methode);
        self::assertStringStartsWith(RegistreSwisstopo::URL . '?', $url);
        parse_str((string) parse_url($url, PHP_URL_QUERY), $q);
        self::assertSame(['searchText' => 'rue de la prefecture 2800', 'type' => 'locations', 'origins' => 'address', 'limit' => '5'], $q);
        self::assertSame(3.0, (float) $delai, 'une réponse lente ne bloque pas la saisie');
    }

    public function testUnServiceEnPanneEstIndisponible(): void
    {
        foreach ([new MockResponse('', ['http_code' => 503]), new MockResponse('pas du json'), new MockResponse('', ['error' => 'délai dépassé'])] as $reponse) {
            try {
                (new RegistreSwisstopo(new MockHttpClient($reponse)))->chercher('rue', 5);
                self::fail('panne non signalée');
            } catch (RegistreIndisponible) {
                self::addToAssertionCount(1);
            }
        }
    }

    public function testLibelles(): void
    {
        self::assertEquals(new Adresse('Bundesplatz', '3', '3011', 'Bern'), RegistreSwisstopo::lireLibelle('Bundesplatz 3 <b>3011 Bern</b>'));
        self::assertEquals(new Adresse('Rue de l\'Église', '', '2900', 'Porrentruy'), RegistreSwisstopo::lireLibelle('Rue de l&#39;Église <b>2900 Porrentruy</b>'));
        self::assertNull(RegistreSwisstopo::lireLibelle('<b>2800 Delémont</b>'));
        self::assertNull(RegistreSwisstopo::lireLibelle('Bundesplatz 3 3011 Bern'));
    }

    public function testRegistreFactice(): void
    {
        $r = new RegistreFactice();
        self::assertEquals([new Adresse('Rue de la Préfecture', '1', '2800', 'Delémont'), new Adresse('Rue de la Préfecture', '2', '2800', 'Delémont')], $r->chercher('rue de la pref', 6));
        self::assertEquals([new Adresse('Rue de la Préfecture', '2', '2800', 'Delémont')], $r->chercher('Rue de la Préfecture 2 2800', 6));
        self::assertSame([], $r->chercher('Rue inconnue', 6));
        $this->expectException(RegistreIndisponible::class);
        $r->chercher('rue en panne', 6);
    }
}
