<?php

declare(strict_types=1);

namespace OffreGen\Tests\Http;

use OffreGen\Http\Noyau;
use OffreGen\Http\Route;
use OffreGen\Tests\Outils\Monde;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class NoyauTest extends TestCase
{
    /** @var list<string> */
    private array $journal = [];

    private function noyauDeTest(): Noyau
    {
        $c = Monde::conteneur();
        $ok = static fn (): Response => new Response('ok');
        $journal = function (string $message): void {
            $this->journal[] = $message;
        };
        return new Noyau([
            new Route('publique', '/publique', ['GET'], $ok, Route::PUBLIC),
            new Route('envoi', '/envoi', ['POST'], $ok, Route::PUBLIC),
            new Route('sans_droit', '/sans-droit', ['GET'], $ok, null),
            new Route('droit_inconnu', '/droit-inconnu', ['GET'], $ok, 'admin'),
            new Route('casse', '/casse', ['GET'], static fn (): Response => throw new \RuntimeException('boum'), Route::PUBLIC),
        ], $c->twig(), $c->garde(), $journal);
    }

    public function testRouteInconnueRend404(): void
    {
        self::assertSame(404, $this->noyauDeTest()->traiter(Request::create('/nulle-part'))->getStatusCode());
    }

    public function testRouteSansDroitRend403(): void
    {
        self::assertSame(403, $this->noyauDeTest()->traiter(Request::create('/sans-droit'))->getStatusCode());
    }

    public function testDroitInconnuRend403(): void
    {
        self::assertSame(403, $this->noyauDeTest()->traiter(Request::create('/droit-inconnu'))->getStatusCode());
    }

    public function testVerbeNonDeclareRend405(): void
    {
        self::assertSame(405, $this->noyauDeTest()->traiter(Request::create('/publique', 'POST'))->getStatusCode());
    }

    public function testExceptionRend500SansDetail(): void
    {
        $reponse = $this->noyauDeTest()->traiter(Request::create('/casse'));
        self::assertSame(500, $reponse->getStatusCode());
        self::assertStringNotContainsString('boum', (string) $reponse->getContent());
        self::assertSame(['offreGen, erreur sur casse : boum'], $this->journal);
    }

    public function testCorpsJeteParPhpRend413PlutotQue403(): void
    {
        $requete = Request::create('/envoi', 'POST', server: ['CONTENT_LENGTH' => '300000000']);
        self::assertSame(413, $this->noyauDeTest()->traiter($requete)->getStatusCode());
    }

    public function testRouteDeclareeDeuxFoisEstRefusee(): void
    {
        $c = Monde::conteneur();
        $ok = static fn (): Response => new Response('ok');
        $this->expectException(\LogicException::class);
        new Noyau([
            new Route('double', '/a', ['GET'], $ok, Route::PUBLIC),
            new Route('double', '/b', ['GET'], $ok, Route::PUBLIC),
        ], $c->twig(), $c->garde());
    }

    public function testPageDeConnexionRend200AvecJeton(): void
    {
        $reponse = Monde::conteneur()->noyau()->traiter(Request::create('/connexion'));
        self::assertSame(200, $reponse->getStatusCode());
        self::assertStringContainsString('name="courriel"', (string) $reponse->getContent());
        self::assertMatchesRegularExpression('/name="_csrf" value="[^"]{20,}"/', (string) $reponse->getContent());
    }

    public function testChaqueRouteDeclareeAUnDroitConnu(): void
    {
        $routes = Monde::conteneur()->noyau()->routes();
        self::assertNotEmpty($routes);
        foreach ($routes as $route) {
            self::assertContains($route->droit, [Route::PUBLIC, Route::ARTISAN], "Route sans droit : {$route->nom}");
        }
    }
}
