<?php

declare(strict_types=1);

namespace OffreGen\Tests\Http;

use OffreGen\Tests\Outils\BaseDeTest;
use OffreGen\Tests\Outils\Jeu;
use OffreGen\Tests\Outils\Monde;
use OffreGen\Tests\Outils\Navigateur;
use PHPUnit\Framework\TestCase;

final class PagesTest extends TestCase
{
    private Jeu $jeu;
    private Navigateur $a;

    protected function setUp(): void
    {
        $this->jeu = new Jeu();
        $this->a = new Navigateur(Monde::conteneur());
        $this->a->connecter('a@exemple.ch');
    }

    public function testOffreAfficheLignesEtTotaux(): void
    {
        // Jeu : 12,5 m² × 18.50 = 231.25 ; TVA 18.73 ; 249.98 → 250.00 (arrondi +0.02).
        $page = (string) $this->a->get('/offres/' . $this->jeu->offre['a'])->getContent();
        self::assertStringContainsString('Peinture murale a', $page);
        self::assertStringContainsString('12.5', $page);
        self::assertStringContainsString("CHF\u{00A0}231.25", $page);
        self::assertStringContainsString("CHF\u{00A0}18.73", $page);
        self::assertStringContainsString("CHF\u{00A0}0.02", $page);
        self::assertStringContainsString("CHF\u{00A0}250.00", $page);
    }

    public function testListeAfficheLeTotalTtc(): void
    {
        self::assertStringContainsString("CHF\u{00A0}250.00", (string) $this->a->get('/')->getContent());
    }

    public function testPageDEtatSeRafraichitTantQueCeNEstPasFini(): void
    {
        $page = (string) $this->a->get('/enregistrements/' . $this->jeu->uuid['a'])->getContent();
        self::assertStringContainsString('http-equiv="refresh"', $page);
        self::assertStringContainsString('class="cur"', $page);
    }

    public function testPageDEtatPreteMeneALOffreSansRafraichir(): void
    {
        BaseDeTest::pdo()->exec("UPDATE enregistrement SET statut = 'pret' WHERE uuid = '{$this->jeu->uuid['a']}'");
        $page = (string) $this->a->get('/enregistrements/' . $this->jeu->uuid['a'])->getContent();
        self::assertStringNotContainsString('http-equiv="refresh"', $page);
        self::assertStringContainsString('href="/offres/' . $this->jeu->offre['a'] . '"', $page);
    }

    public function testPageDEtatEnEchecNeMontrePasLErreurBrute(): void
    {
        BaseDeTest::pdo()->exec("UPDATE enregistrement SET statut = 'echec', erreur = 'SQLSTATE secret interne' WHERE uuid = '{$this->jeu->uuid['a']}'");
        $page = (string) $this->a->get('/enregistrements/' . $this->jeu->uuid['a'])->getContent();
        self::assertStringContainsString('La préparation a échoué', $page);
        self::assertStringNotContainsString('SQLSTATE', $page);
        self::assertStringNotContainsString('http-equiv="refresh"', $page);
    }
}
