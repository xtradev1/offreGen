<?php

declare(strict_types=1);

namespace OffreGen\Tests\Compte;

use OffreGen\Tests\Outils\BaseDeTest;
use OffreGen\Tests\Outils\Dossiers;
use OffreGen\Tests\Outils\Jeu;
use OffreGen\Tests\Outils\Monde;
use PHPUnit\Framework\TestCase;

final class CreationCompteTest extends TestCase
{
    private Dossiers $dossiers;

    protected function setUp(): void
    {
        new Jeu();
        $this->dossiers = new Dossiers();
    }

    protected function tearDown(): void
    {
        $this->dossiers->effacer();
    }

    public function testCompteDEssaiGardeSonPlafondTotalSansEtreDeDemonstration(): void
    {
        // Un compte d'essai n'est pas un compte de démonstration : bin/reinitialiser-demo effacerait ses offres.
        $mdp = Monde::conteneur(env: $this->dossiers->env())->comptes()->creer('Essai@Exemple.ch', 'Peinture Essai', false, null, 20);
        self::assertGreaterThanOrEqual(16, strlen($mdp));
        $ligne = BaseDeTest::ligne("SELECT demo, plafond_jour, offres_max FROM artisan WHERE courriel = 'essai@exemple.ch'");
        self::assertSame([0, null, 20], [$ligne['demo'], $ligne['plafond_jour'], $ligne['offres_max']]);
    }

    public function testCompteOrdinaireSansPlafondTotal(): void
    {
        Monde::conteneur(env: $this->dossiers->env())->comptes()->creer('libre@exemple.ch', 'Libre', false, null);
        self::assertNull(BaseDeTest::ligne("SELECT offres_max FROM artisan WHERE courriel = 'libre@exemple.ch'")['offres_max']);
    }

    public function testPlafondTotalNulRefuse(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Monde::conteneur(env: $this->dossiers->env())->comptes()->creer('zero@exemple.ch', 'Zéro', false, null, 0);
    }

    public function testLePlafondTotalSeChangeApresCoup(): void
    {
        $comptes = Monde::conteneur(env: $this->dossiers->env())->comptes();
        $comptes->creer('plus@exemple.ch', 'Plus', false, null, 20);
        $comptes->changerOffresMax('plus@exemple.ch', 30);
        self::assertSame(30, BaseDeTest::ligne("SELECT offres_max FROM artisan WHERE courriel = 'plus@exemple.ch'")['offres_max']);
        $comptes->changerOffresMax('plus@exemple.ch', null);
        self::assertNull(BaseDeTest::ligne("SELECT offres_max FROM artisan WHERE courriel = 'plus@exemple.ch'")['offres_max']);
    }
}
