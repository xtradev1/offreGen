<?php

declare(strict_types=1);

namespace OffreGen\Tests\Http;

use OffreGen\Adresse\RegistreFactice;
use OffreGen\Adresse\RegistreSwisstopo;
use OffreGen\Http\Controleur\AdresseControleur;
use OffreGen\Tests\Outils\Jeu;
use OffreGen\Tests\Outils\Monde;
use OffreGen\Tests\Outils\Navigateur;
use PHPUnit\Framework\TestCase;

/**
 * Aide à la saisie d'une adresse (Xavier, 2026-11-13 : « Tu tapes ton adresse et puis ça te dit oui, tu es ici ou
 * pas »). Ici avec le registre factice : aucun appel ne sort pendant les tests.
 */
final class AdressesEcranTest extends TestCase
{
    private Navigateur $a;

    protected function setUp(): void
    {
        new Jeu();
        $this->a = new Navigateur(Monde::conteneur());
        $this->a->connecter('a@exemple.ch');
    }

    /** @return array<string, mixed> */
    private function json(string $chemin): array
    {
        $r = $this->a->get($chemin);
        self::assertSame(200, $r->getStatusCode(), $chemin);
        self::assertStringContainsString('application/json', (string) $r->headers->get('Content-Type'));
        return (array) json_decode((string) $r->getContent(), true);
    }

    public function testLeNpaDonneSaLocalite(): void
    {
        self::assertSame(['localites' => ['Delémont'], 'seule' => 'Delémont'], $this->json('/adresses/localites?npa=2800'));
        self::assertSame(['localites' => [], 'seule' => null], $this->json('/adresses/localites?npa=0001'));
        self::assertSame(['localites' => [], 'seule' => null], $this->json('/adresses/localites?npa=28'));
        self::assertSame('Lausanne', $this->json('/adresses/localites?npa=1000')['seule']);
    }

    public function testLesSuggestionsViennentDuRegistre(): void
    {
        self::assertSame(['suggestions' => [
            ['rue' => 'Rue de la Préfecture', 'numero' => '1', 'npa' => '2800', 'localite' => 'Delémont', 'texte' => 'Rue de la Préfecture 1, 2800 Delémont'],
            ['rue' => 'Rue de la Préfecture', 'numero' => '2', 'npa' => '2800', 'localite' => 'Delémont', 'texte' => 'Rue de la Préfecture 2, 2800 Delémont'],
        ]], $this->json('/adresses/suggestions?q=' . rawurlencode('rue de la pref')));
        self::assertSame(['suggestions' => []], $this->json('/adresses/suggestions?q=ru'), 'trois lettres au moins');
        self::assertSame(['suggestions' => [], 'indisponible' => true], $this->json('/adresses/suggestions?q=panne'));
    }

    public function testLaVerificationDitTrouveeOuPas(): void
    {
        $verifier = fn (string $rue, string $numero, string $npa): mixed => $this->json('/adresses/verification?' . http_build_query(['rue' => $rue, 'numero' => $numero, 'npa' => $npa, 'localite' => 'Delémont']))['trouvee'];
        self::assertTrue($verifier('rue de la prefecture', '2', '2800'));
        self::assertFalse($verifier('Rue de la Préfecture', '99', '2800'));
        self::assertFalse($verifier('Rue de la Préfecture', '2', '2900'));
        self::assertNull($verifier('Rue en panne', '2', '2800'), 'registre muet : on ne sait pas');
        self::assertNull($verifier('', '2', '2800'), 'sans rue, rien à vérifier');
    }

    public function testLeRegistreNestPasSollicitePlusDeVingtFoisParMinute(): void
    {
        for ($i = 0; $i < AdresseControleur::APPELS_PAR_MINUTE; $i++) {
            self::assertArrayNotHasKey('indisponible', $this->json('/adresses/suggestions?q=rue+de+la+gare'));
        }
        self::assertSame(['suggestions' => [], 'indisponible' => true], $this->json('/adresses/suggestions?q=rue+de+la+gare'));
    }

    public function testLesTestsNAppellentJamaisSwisstopo(): void
    {
        self::assertInstanceOf(RegistreFactice::class, Monde::conteneur()->registreAdresses(), 'même si le .env dit swisstopo');
        self::assertInstanceOf(RegistreSwisstopo::class, Monde::conteneur(env: ['ADRESSES' => 'swisstopo'])->registreAdresses());
    }

    public function testSansSessionRienNeRepond(): void
    {
        $inconnu = new Navigateur(Monde::conteneur());
        foreach (['/adresses/localites?npa=2800', '/adresses/suggestions?q=rue+de+la+gare', '/adresses/verification?rue=x&npa=2800'] as $chemin) {
            self::assertNotSame(200, $inconnu->get($chemin)->getStatusCode(), $chemin);
        }
    }
}
