<?php

declare(strict_types=1);

namespace OffreGen\Tests\Http;

use OffreGen\Http\Noyau;
use OffreGen\Http\Route;
use OffreGen\Tests\Outils\BaseDeTest;
use OffreGen\Tests\Outils\Jeu;
use OffreGen\Tests\Outils\Monde;
use OffreGen\Tests\Outils\Navigateur;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Décision A1 de Xavier (2026-11-18) : les pages d'administration répondent 404 à tout autre compte, et sans session :
 * personne ne doit pouvoir savoir qu'elles existent.
 */
final class DroitAdministrateurTest extends TestCase
{
    private Jeu $jeu;

    protected function setUp(): void
    {
        $this->jeu = new Jeu();
    }

    private function demander(Navigateur $n, string $verbe = 'GET'): Response
    {
        $noyau = new Noyau([
            new Route('admin_essai', '/administration/essai', ['GET', 'POST'], static fn (Request $r): Response => new Response('admin ' . $r->attributes->get('_artisan')), Route::ADMIN),
        ], $n->c->twig(), $n->c->garde());
        $requete = Request::create('/administration/essai', $verbe, server: ['REMOTE_ADDR' => '127.0.0.1']);
        $requete->setSession($n->session);
        return $noyau->traiter($requete);
    }

    public function testSansSessionLaPageNExistePas(): void
    {
        self::assertSame(404, $this->demander(new Navigateur(Monde::conteneur()))->getStatusCode());
    }

    public function testUnArtisanSansLeDroitNeLaVoitPas(): void
    {
        $n = new Navigateur(Monde::conteneur());
        $n->connecter('a@exemple.ch');
        self::assertSame(404, $this->demander($n)->getStatusCode());
    }

    public function testLAdministrateurLaVoitAvecSonIdentifiant(): void
    {
        BaseDeTest::pdo()->exec("UPDATE artisan SET administrateur = 1 WHERE id = {$this->jeu->a}");
        $n = new Navigateur(Monde::conteneur());
        $n->connecter('a@exemple.ch');
        $reponse = $this->demander($n);
        self::assertSame(200, $reponse->getStatusCode());
        self::assertSame("admin {$this->jeu->a}", $reponse->getContent());
    }

    public function testLeDroitRetireFermeLaPageAuProchainClic(): void
    {
        BaseDeTest::pdo()->exec("UPDATE artisan SET administrateur = 1 WHERE id = {$this->jeu->a}");
        $n = new Navigateur(Monde::conteneur());
        $n->connecter('a@exemple.ch');
        BaseDeTest::pdo()->exec("UPDATE artisan SET administrateur = 0 WHERE id = {$this->jeu->a}");
        self::assertSame(404, $this->demander($n)->getStatusCode());
    }

    public function testUnPostAdministrateurExigeLeJeton(): void
    {
        BaseDeTest::pdo()->exec("UPDATE artisan SET administrateur = 1 WHERE id = {$this->jeu->a}");
        $n = new Navigateur(Monde::conteneur());
        $n->connecter('a@exemple.ch');
        self::assertSame(403, $this->demander($n, 'POST')->getStatusCode());
    }

    /** Revue du 2026-11-18 (connexion, défaut 4) : un 405 sur une méthode non déclarée trahissait la page. */
    public function testUneMethodeNonDeclareeRend404EtNon405(): void
    {
        $n = new Navigateur(Monde::conteneur());
        $noyau = new Noyau([
            new Route('admin_lecture', '/administration/lecture', ['GET'], static fn (): Response => new Response('admin'), Route::ADMIN),
            new Route('publique', '/publique', ['GET'], static fn (): Response => new Response('ok'), Route::PUBLIC),
        ], $n->c->twig(), $n->c->garde());
        self::assertSame(404, $noyau->traiter(Request::create('/administration/lecture', 'DELETE'))->getStatusCode());
        self::assertSame(405, $noyau->traiter(Request::create('/publique', 'DELETE'))->getStatusCode());
    }

    public function testLaCommandeDonneEtRetireLeDroit(): void
    {
        $c = Monde::conteneur();
        $c->comptes()->changerAdministrateur('A@exemple.ch', true);
        self::assertSame(1, BaseDeTest::compter('artisan', "id = {$this->jeu->a} AND administrateur = 1"));
        $c->comptes()->changerAdministrateur('a@exemple.ch', false);
        self::assertSame(0, BaseDeTest::compter('artisan', 'administrateur = 1'));
        $this->expectException(\InvalidArgumentException::class);
        $c->comptes()->changerAdministrateur('personne@exemple.ch', true);
    }
}
