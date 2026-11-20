<?php

declare(strict_types=1);

namespace OffreGen\Tests\Http;

use OffreGen\Tests\Outils\Jeu;
use OffreGen\Tests\Outils\Monde;
use OffreGen\Tests\Outils\Navigateur;
use PHPUnit\Framework\TestCase;

/**
 * La page de présentation de Dicta, servie à « / » pour un visiteur sans session : statique, et conforme à la
 * CSP fermée du site (default-src 'self' : ni style en ligne, ni script en ligne, ni ressource d'un autre site).
 * L'artisan connecté trouve à la même adresse la liste de ses offres.
 */
final class PresentationTest extends TestCase
{
    private Navigateur $visiteur;

    protected function setUp(): void
    {
        $this->visiteur = new Navigateur(Monde::conteneur());
    }

    public function testLeVisiteurSansSessionVoitLaPresentation(): void
    {
        $reponse = $this->visiteur->get('/');
        self::assertSame(200, $reponse->getStatusCode());
        $page = (string) $reponse->getContent();
        self::assertStringContainsString('Dictez votre visite de chantier.', $page);
        self::assertStringContainsString('href="/connexion"', $page);
        self::assertStringContainsString('<meta name="robots" content="noindex">', $page);
    }

    public function testLArtisanConnecteTrouveSesOffres(): void
    {
        new Jeu();
        $artisan = new Navigateur(Monde::conteneur());
        $artisan->connecter('a@exemple.ch');
        $page = (string) $artisan->get('/')->getContent();
        self::assertStringNotContainsString('Dictez votre visite de chantier.', $page);
        self::assertStringContainsString("CHF\u{00A0}250.00", $page);
    }

    public function testLaPageNAPasDAutreAdresse(): void
    {
        self::assertSame(404, $this->visiteur->get('/presentation')->getStatusCode());
    }

    public function testLaPageRespecteLaCspFermee(): void
    {
        $page = (string) $this->visiteur->get('/')->getContent();
        self::assertStringNotContainsString(' style=', $page, 'Un attribut style serait refusé par la CSP.');
        self::assertStringNotContainsString('<style', $page);
        self::assertDoesNotMatchRegularExpression('/\son[a-z]+="/', $page, 'Un gestionnaire en ligne serait refusé par la CSP.');
        self::assertDoesNotMatchRegularExpression('/<script(?![^>]*\ssrc=)/', $page, 'Un script en ligne serait refusé par la CSP.');
        self::assertDoesNotMatchRegularExpression('/\s(?:src|href|action)="(?![\/#])/', $page, 'Une ressource vient d\'un autre site.');
    }

    public function testLesRessourcesDeLaPageExistent(): void
    {
        $page = (string) $this->visiteur->get('/')->getContent();
        preg_match_all('/\s(?:src|href)="(\/(?:css|js|img|fonts)\/[^"]+)"/', $page, $trouves);
        self::assertGreaterThanOrEqual(8, count($trouves[1]), 'La feuille, le script et les six modèles au moins.');
        foreach (array_unique($trouves[1]) as $chemin) {
            self::assertFileExists(dirname(__DIR__, 2) . '/public' . $chemin);
        }
    }

    public function testLaFeuilleEtLeScriptNAppellentRienDehors(): void
    {
        $public = dirname(__DIR__, 2) . '/public';
        $feuille = (string) file_get_contents($public . '/css/site.css');
        self::assertStringNotContainsString('@import', $feuille);
        self::assertStringNotContainsString('http', $feuille);
        preg_match_all('/url\("([^"]+)"\)/', $feuille, $polices);
        self::assertNotEmpty($polices[1]);
        foreach ($polices[1] as $chemin) {
            self::assertFileExists($public . $chemin);
        }
        self::assertStringNotContainsString('http', (string) file_get_contents($public . '/js/site.js'));
    }

    public function testLEtatAvantLOuvertureSeChoisitParLAdresse(): void
    {
        self::assertStringContainsString('<html lang="fr-CH">', (string) $this->visiteur->get('/')->getContent());
        self::assertStringContainsString('<html lang="fr-CH" class="avant">', (string) $this->visiteur->get('/?etat=avant')->getContent());
        self::assertStringContainsString('<html lang="fr-CH">', (string) $this->visiteur->get('/?etat=autre')->getContent());
    }
}
