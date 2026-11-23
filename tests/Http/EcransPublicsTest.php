<?php

declare(strict_types=1);

namespace OffreGen\Tests\Http;

use OffreGen\Tests\Outils\Jeu;
use OffreGen\Tests\Outils\Monde;
use OffreGen\Tests\Outils\Navigateur;
use PHPUnit\Framework\TestCase;

/**
 * Connexion et mot de passe oublié sur tablette et ordinateur (Xavier, 2026-11-20) : une carte centrée, le
 * bouton sous les champs. La mise en page tient dans une feuille chargée par ces seuls écrans.
 */
final class EcransPublicsTest extends TestCase
{
    public function testLaConnexionPorteLaMiseEnPageEnCarte(): void
    {
        $page = (string) (new Navigateur(Monde::conteneur()))->get('/connexion')->getContent();
        self::assertStringContainsString('<div class="app app-seule">', $page);
        self::assertMatchesRegularExpression('#<link rel="stylesheet" href="/css/connexion\.css\?v=[0-9a-f]{12}">#', $page);
        self::assertFileExists(dirname(__DIR__, 2) . '/public/css/connexion.css');
        self::assertStringContainsString('<a class="vers-accueil" href="/"', $page, 'Le logo ramène à la page de présentation.');
    }

    public function testLeMotDePasseOubliePorteLaMemeMiseEnPage(): void
    {
        $page = (string) (new Navigateur(Monde::conteneur()))->get('/mot-de-passe/oubli')->getContent();
        self::assertStringContainsString('<div class="app app-seule">', $page);
        self::assertMatchesRegularExpression('#<link rel="stylesheet" href="/css/connexion\.css\?v=[0-9a-f]{12}">#', $page);
    }

    public function testLesEcransDeLArtisanNeLaPortentPas(): void
    {
        new Jeu();
        $artisan = new Navigateur(Monde::conteneur());
        $artisan->connecter('a@exemple.ch');
        $page = (string) $artisan->get('/catalogue')->getContent();
        self::assertStringContainsString('<div class="app">', $page);
        self::assertStringNotContainsString('/css/connexion.css', $page);
    }
}
