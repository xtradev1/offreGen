<?php

declare(strict_types=1);

namespace OffreGen\Tests\Compte;

use OffreGen\Tests\Outils\BaseDeTest;
use OffreGen\Tests\Outils\Jeu;
use OffreGen\Tests\Outils\Monde;
use OffreGen\Tests\Outils\Navigateur;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

final class ConnexionTest extends TestCase
{
    private MockClock $horloge;

    protected function setUp(): void
    {
        new Jeu();
        $this->horloge = new MockClock('2026-11-07 10:00:00', 'UTC');
    }

    private function navigateur(string $ip = '127.0.0.1'): Navigateur
    {
        return new Navigateur(Monde::conteneur($this->horloge), $ip);
    }

    private function lignesJournal(bool $reussi): int
    {
        return BaseDeTest::compter('journal_connexion', 'reussi = ' . ($reussi ? 1 : 0));
    }

    public function testBonMotDePasseRedirigeEtRegenereLaSession(): void
    {
        $n = $this->navigateur();
        $n->get('/connexion');
        $avant = $n->session->getId();
        $reponse = $n->post('/connexion', ['courriel' => 'A@exemple.ch ', 'mot_de_passe' => Jeu::MOT_DE_PASSE]);
        self::assertSame(303, $reponse->getStatusCode());
        self::assertSame('/', $reponse->headers->get('Location'));
        self::assertNotSame($avant, $n->session->getId());
        self::assertSame(200, $n->get('/')->getStatusCode());
        self::assertSame(1, $this->lignesJournal(true));
    }

    public function testMauvaisMotDePasseRend200AvecErreurEtJournal(): void
    {
        $reponse = $this->navigateur()->connecter('a@exemple.ch', 'faux');
        self::assertSame(200, $reponse->getStatusCode());
        self::assertStringContainsString('Courriel ou mot de passe incorrect', (string) $reponse->getContent());
        self::assertSame(1, $this->lignesJournal(false));
    }

    public function testCourrielInconnuRendLaMemeErreur(): void
    {
        $reponse = $this->navigateur()->connecter('personne@exemple.ch', 'faux');
        self::assertSame(200, $reponse->getStatusCode());
        self::assertStringContainsString('Courriel ou mot de passe incorrect', (string) $reponse->getContent());
    }

    public function testSixiemeEssaiRend429MemeAvecLeBonMotDePasse(): void
    {
        $n = $this->navigateur();
        for ($i = 0; $i < 5; $i++) {
            self::assertSame(200, $n->connecter('a@exemple.ch', 'faux')->getStatusCode());
        }
        self::assertSame(429, $n->connecter('a@exemple.ch')->getStatusCode());
    }

    public function testLeBlocageViseAussiLAdresseIp(): void
    {
        $n = $this->navigateur('203.0.113.7');
        for ($i = 0; $i < 5; $i++) {
            $n->connecter("inconnu$i@exemple.ch", 'faux');
        }
        self::assertSame(429, $n->connecter('a@exemple.ch')->getStatusCode());
        // Une autre adresse, pour un autre courriel, n'est pas bloquée.
        self::assertSame(303, $this->navigateur('198.51.100.2')->connecter('b@exemple.ch')->getStatusCode());
    }

    public function testLeBlocageCesseApresQuinzeMinutes(): void
    {
        $n = $this->navigateur();
        for ($i = 0; $i < 5; $i++) {
            $n->connecter('a@exemple.ch', 'faux');
        }
        $this->horloge->modify('+16 minutes');
        self::assertSame(303, $n->connecter('a@exemple.ch')->getStatusCode());
    }

    public function testPostSansJetonRend403(): void
    {
        $n = $this->navigateur();
        $n->get('/connexion');
        $reponse = $n->post('/connexion', ['courriel' => 'a@exemple.ch', 'mot_de_passe' => Jeu::MOT_DE_PASSE], avecJeton: false);
        self::assertSame(403, $reponse->getStatusCode());
        self::assertSame(0, $this->lignesJournal(true));
    }

    public function testPostAvecFauxJetonRend403(): void
    {
        $n = $this->navigateur();
        $n->get('/connexion');
        $reponse = $n->post('/connexion', ['courriel' => 'a@exemple.ch', 'mot_de_passe' => Jeu::MOT_DE_PASSE, '_csrf' => 'invente']);
        self::assertSame(403, $reponse->getStatusCode());
    }

    public function testRouteArtisanSansSessionRedirigeVersConnexion(): void
    {
        $reponse = $this->navigateur()->get('/');
        self::assertSame(303, $reponse->getStatusCode());
        self::assertSame('/connexion', $reponse->headers->get('Location'));
    }

    public function testDeconnexionFermeLaSession(): void
    {
        $n = $this->navigateur();
        $n->connecter('a@exemple.ch');
        self::assertStringContainsString('action="/deconnexion"', (string) $n->get('/reglages')->getContent());
        self::assertSame(303, $n->post('/deconnexion', [])->getStatusCode());
        self::assertSame(303, $n->get('/')->getStatusCode());
    }

    public function testCompteSupprimeNeGardePasSaSession(): void
    {
        $n = $this->navigateur();
        $n->connecter('a@exemple.ch');
        BaseDeTest::vider();
        self::assertSame(303, $n->get('/')->getStatusCode());
    }
}
