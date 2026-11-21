<?php

declare(strict_types=1);

namespace OffreGen\Tests\Http;

use OffreGen\Tests\Outils\Jeu;
use OffreGen\Tests\Outils\Monde;
use OffreGen\Tests\Outils\Navigateur;
use PHPUnit\Framework\TestCase;

/**
 * Sous-chantier 27 (Xavier, 2026-11-17) : le téléphone garde la dictée jusqu'à ce que son offre soit prête. Le
 * navigateur range chaque dictée sous le compte qui l'a faite : sur un appareil partagé, un autre compte ne la voit pas.
 * Le comportement du navigateur se vérifie au parcours cliqué (Playwright) ; ici, ce que la page lui donne.
 */
final class EnregistrementAbriTest extends TestCase
{
    private Jeu $jeu;

    protected function setUp(): void
    {
        $this->jeu = new Jeu();
    }

    public function testLaPageDonneLeCompteAuNavigateur(): void
    {
        $n = new Navigateur(Monde::conteneur());
        $n->connecter('a@exemple.ch');
        self::assertStringContainsString("<body data-compte=\"{$this->jeu->a}\">", (string) $n->get('/')->getContent());
        $b = new Navigateur(Monde::conteneur());
        $b->connecter('b@exemple.ch');
        self::assertStringContainsString("<body data-compte=\"{$this->jeu->b}\">", (string) $b->get('/clients')->getContent());
    }

    public function testSansSessionAucunCompte(): void
    {
        self::assertStringContainsString('<body>', (string) (new Navigateur(Monde::conteneur()))->get('/connexion')->getContent());
    }

    public function testChaqueEcranAOngletsPorteLaFenetreDeReprise(): void
    {
        $n = new Navigateur(Monde::conteneur());
        $n->connecter('a@exemple.ch');
        foreach (['/', '/clients', '/factures', '/reglages', '/import'] as $page) {
            $html = (string) $n->get($page)->getContent();
            self::assertStringContainsString('data-etat="reprise"', $html, $page);
            self::assertStringContainsString('data-reprise-envoyer', $html, $page);
        }
    }
}
