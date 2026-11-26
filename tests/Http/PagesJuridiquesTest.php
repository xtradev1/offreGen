<?php

declare(strict_types=1);

namespace OffreGen\Tests\Http;

use OffreGen\Tests\Outils\Jeu;
use OffreGen\Tests\Outils\Monde;
use OffreGen\Tests\Outils\Navigateur;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Les trois pages du pied de page (mentions légales, protection des données, conditions) : publiques,
 * statiques, conformes à la CSP fermée, reliées entre elles et depuis la page de présentation.
 */
final class PagesJuridiquesTest extends TestCase
{
    /** @return array<string, array{string, string}> */
    public static function pages(): array
    {
        return [
            'mentions' => ['/mentions-legales', '<h1>Mentions légales</h1>'],
            'données' => ['/protection-des-donnees', '<h1>Protection des données</h1>'],
            'conditions' => ['/conditions', "<h1>Conditions d'utilisation</h1>"],
        ];
    }

    #[DataProvider('pages')]
    public function testLeVisiteurSansSessionLitLaPage(string $chemin, string $titre): void
    {
        $reponse = (new Navigateur(Monde::conteneur()))->get($chemin);
        self::assertSame(200, $reponse->getStatusCode());
        $page = (string) $reponse->getContent();
        self::assertStringContainsString($titre, $page);
        self::assertStringContainsString('<meta name="robots" content="noindex">', $page);
        self::assertStringContainsString('<a href="' . $chemin . '" aria-current="page">', $page, 'Le pied de page marque la page lue.');
        self::assertSame(1, substr_count($page, 'aria-current="page"'));
    }

    #[DataProvider('pages')]
    public function testLArtisanConnecteLitAussiLaPage(string $chemin, string $titre): void
    {
        new Jeu();
        $artisan = new Navigateur(Monde::conteneur());
        $artisan->connecter('a@exemple.ch');
        self::assertStringContainsString($titre, (string) $artisan->get($chemin)->getContent());
    }

    #[DataProvider('pages')]
    public function testLaPageRespecteLaCspFermee(string $chemin, string $titre): void
    {
        $page = (string) (new Navigateur(Monde::conteneur()))->get($chemin)->getContent();
        self::assertStringNotContainsString(' style=', $page);
        self::assertStringNotContainsString('<style', $page);
        self::assertStringNotContainsString('<script', $page);
        self::assertDoesNotMatchRegularExpression('/\son[a-z]+="/', $page);
        self::assertDoesNotMatchRegularExpression('/\s(?:src)="(?!\/)/', $page, 'Une ressource vient d\'un autre site.');
    }

    #[DataProvider('pages')]
    public function testAucunTiretLong(string $chemin, string $titre): void
    {
        $page = (string) (new Navigateur(Monde::conteneur()))->get($chemin)->getContent();
        self::assertDoesNotMatchRegularExpression('/\x{2014}|\x{2013}/u', $page);
    }

    public function testLaPresentationRenvoieAuxTroisPages(): void
    {
        $page = (string) (new Navigateur(Monde::conteneur()))->get('/')->getContent();
        foreach (self::pages() as [$chemin]) {
            self::assertStringContainsString('href="' . $chemin . '"', $page);
        }
        self::assertStringNotContainsString('<a href="#">', $page, 'Plus aucun lien vide dans le pied de page.');
    }

    public function testLesRenvoisEntrePagesExistent(): void
    {
        $visiteur = new Navigateur(Monde::conteneur());
        $donnees = (string) $visiteur->get('/protection-des-donnees')->getContent();
        self::assertStringContainsString('href="/conditions#sous-traitance"', $donnees);
        self::assertStringContainsString('id="sous-traitance"', (string) $visiteur->get('/conditions')->getContent());
    }
}
