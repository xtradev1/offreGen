<?php

declare(strict_types=1);

namespace OffreGen\Tests\Http;

use OffreGen\Http\Route;
use OffreGen\Tests\Outils\BaseDeTest;
use OffreGen\Tests\Outils\Jeu;
use OffreGen\Tests\Outils\Monde;
use OffreGen\Tests\Outils\Navigateur;
use PHPUnit\Framework\TestCase;

/**
 * Trois rôles d'équipe (décision R1 de Xavier, 2026-11-19) : administrateur, modérateur, commercial.
 * Une page de l'administration répond 404 à qui n'a pas le rôle voulu, avec ou sans session (A1, 2026-11-18).
 */
final class DroitsEquipeTest extends TestCase
{
    private Jeu $jeu;

    protected function setUp(): void
    {
        $this->jeu = new Jeu();
    }

    /** Le compte A, connecté, avec le rôle demandé ; « artisan » : aucun rôle ; « personne » : sans session. */
    private function navigateur(string $role): Navigateur
    {
        $n = new Navigateur(Monde::conteneur());
        match ($role) {
            'administrateur' => BaseDeTest::pdo()->exec("UPDATE artisan SET administrateur = 1, role = NULL WHERE id = {$this->jeu->a}"),
            'moderateur', 'commercial' => BaseDeTest::pdo()->exec("UPDATE artisan SET administrateur = 0, role = '$role' WHERE id = {$this->jeu->a}"),
            default => BaseDeTest::pdo()->exec("UPDATE artisan SET administrateur = 0, role = NULL WHERE id = {$this->jeu->a}"),
        };
        if ($role !== 'personne') {
            $n->connecter('a@exemple.ch');
        }
        return $n;
    }

    public function testChaquePageDAdministrationSuitLaMatriceDesRoles(): void
    {
        $admis = [
            Route::EQUIPE => ['administrateur', 'moderateur', 'commercial'],
            Route::MODERATION => ['administrateur', 'moderateur'],
            Route::ADMIN => ['administrateur'],
        ];
        $pages = 0;
        foreach (['administrateur', 'moderateur', 'commercial', 'artisan', 'personne'] as $role) {
            $n = $this->navigateur($role);
            foreach ($n->c->noyau()->routes() as $route) {
                $droit = (string) $route->droit;
                if (!isset($admis[$droit]) || !in_array('GET', $route->verbes, true) || str_contains($route->chemin, '{')) {
                    continue;
                }
                $pages++;
                $code = $n->get($route->chemin)->getStatusCode();
                if (in_array($role, $admis[$droit], true)) {
                    self::assertContains($code, [200, 303], "$role sur {$route->chemin}");
                } else {
                    self::assertSame(404, $code, "$role sur {$route->chemin}");
                }
            }
        }
        // Le contrôle lit bien des pages : au moins l'accueil, la liste, le suivi, « à aider », le tableau, l'équipe.
        self::assertGreaterThanOrEqual(5 * 6, $pages);
    }

    public function testToutCheminSousAdminEstReserve(): void
    {
        foreach (Monde::conteneur()->noyau()->routes() as $route) {
            if (str_starts_with($route->chemin, '/admin')) {
                self::assertContains($route->droit, Route::RESERVES, "Route d'administration ouverte : {$route->nom}");
            }
        }
    }

    public function testLAccueilRenvoieChaqueRoleASaPage(): void
    {
        foreach (['administrateur' => '/admin/tableau', 'moderateur' => '/admin/a-aider', 'commercial' => '/admin/suivi'] as $role => $page) {
            $reponse = $this->navigateur($role)->get('/admin');
            self::assertSame(303, $reponse->getStatusCode());
            self::assertSame($page, $reponse->headers->get('Location'));
        }
    }

    public function testLaPageDitLeRoleEtNeSeGardePasEnCache(): void
    {
        foreach (['administrateur' => 'Admin', 'moderateur' => 'Modérateur', 'commercial' => 'Commercial'] as $role => $libelle) {
            $reponse = $this->navigateur($role)->get('/admin/artisans');
            self::assertSame(200, $reponse->getStatusCode());
            self::assertStringContainsString('<span class="role-puce">' . $libelle . '</span>', (string) $reponse->getContent());
            self::assertStringContainsString('no-store', (string) $reponse->headers->get('Cache-Control'));
        }
    }

    /** Le menu ne propose à un rôle que des pages qui lui répondent : aucun lien vers un refus. */
    public function testLeMenuNeMeneJamaisAUnRefus(): void
    {
        foreach (['administrateur', 'moderateur', 'commercial'] as $role) {
            $n = $this->navigateur($role);
            $page = (string) $n->get('/admin/artisans')->getContent();
            preg_match_all('#<nav class="tabs".*?</nav>#s', $page, $menus);
            self::assertCount(1, $menus[0], $role);
            preg_match_all('#href="(/[^"]*)"#', $menus[0][0], $liens);
            self::assertNotEmpty($liens[1]);
            foreach ($liens[1] as $lien) {
                self::assertContains($n->get($lien)->getStatusCode(), [200, 303], "$role vers $lien");
            }
        }
    }

    public function testLEntreeNApparaitQuePourLEquipe(): void
    {
        foreach (['administrateur' => 'Administration', 'moderateur' => 'Espace modérateur', 'commercial' => 'Espace commercial'] as $role => $entree) {
            $page = (string) $this->navigateur($role)->get('/plus')->getContent();
            self::assertStringContainsString('href="/admin"', $page);
            self::assertStringContainsString($entree, $page);
        }
        self::assertStringNotContainsString('href="/admin"', (string) $this->navigateur('artisan')->get('/plus')->getContent());
        self::assertStringNotContainsString('href="/admin"', (string) $this->navigateur('artisan')->get('/')->getContent());
    }

    public function testUnRoleRetireFermeLesPagesAuClicSuivant(): void
    {
        $n = $this->navigateur('commercial');
        self::assertSame(200, $n->get('/admin/suivi')->getStatusCode());
        BaseDeTest::pdo()->exec("UPDATE artisan SET role = NULL WHERE id = {$this->jeu->a}");
        self::assertSame(404, $n->get('/admin/suivi')->getStatusCode());
    }

    /** Le droit administrateur ne se donne que par sa colonne (bin/administrateur), jamais par le rôle. */
    public function testLaColonneDuRoleNeDonneJamaisLeDroitAdministrateur(): void
    {
        $n = $this->navigateur('artisan');
        BaseDeTest::pdo()->exec("UPDATE artisan SET role = 'administrateur' WHERE id = {$this->jeu->a}");
        self::assertSame(404, $n->get('/admin')->getStatusCode());
        self::assertSame(404, $n->get('/admin/tableau')->getStatusCode());
    }

    public function testUnPostDAdministrationInconnuRend404EtNon405(): void
    {
        $n = $this->navigateur('artisan');
        $n->get('/');
        self::assertSame(404, $n->post('/admin/tableau', [])->getStatusCode());
    }
}
