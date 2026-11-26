<?php

declare(strict_types=1);

namespace OffreGen\Tests\Http;

use OffreGen\Compte\Demos;
use OffreGen\Tests\Outils\BaseDeTest;
use OffreGen\Tests\Outils\Jeu;
use OffreGen\Tests\Outils\Monde;
use OffreGen\Tests\Outils\Navigateur;
use PHPUnit\Framework\TestCase;

/**
 * L'avertissement avant l'envoi d'une offre au nom provisoire « Démo <métier> » (docs/plans/2026-11-21_onboarding,
 * PLAN.md, lot 5 ; O7 et O16 de Xavier : l'envoi reste possible).
 */
final class AvertissementEnvoiTest extends TestCase
{
    private Jeu $jeu;

    protected function setUp(): void
    {
        $this->jeu = new Jeu();
    }

    public function testLeNomProvisoireSeReconnaitParSonMetier(): void
    {
        self::assertTrue(Demos::estNomProvisoire('Démo Peintre', 'peintre', null));
        self::assertTrue(Demos::estNomProvisoire('Démo Couvreur', 'autre', 'Couvreur'));
        self::assertFalse(Demos::estNomProvisoire('Peinture Morand Sàrl', 'peintre', null));
        self::assertFalse(Demos::estNomProvisoire('Démo Peintre', null, null));
        self::assertFalse(Demos::estNomProvisoire('Démo Plâtrier', 'peintre', ''));
    }

    private function apercu(): string
    {
        $offre = (int) BaseDeTest::ligne("SELECT id FROM offre WHERE artisan_id = {$this->jeu->a} LIMIT 1")['id'];
        $n = new Navigateur(Monde::conteneur());
        self::assertSame(303, $n->connecter('a@exemple.ch')->getStatusCode());
        return (string) $n->get("/offres/$offre/apercu")->getContent();
    }

    public function testLAvertissementSeulementSousLeNomProvisoire(): void
    {
        BaseDeTest::pdo()->exec("UPDATE artisan SET entreprise = 'Démo Peintre', metier = 'peintre', metier_saisi = NULL WHERE id = {$this->jeu->a}");
        $page = $this->apercu();
        self::assertStringContainsString('data-avertissement-envoi', $page);
        self::assertStringContainsString('Démo Peintre', $page);
        self::assertStringContainsString('Envoyer quand même', $page);
        self::assertStringContainsString('href="/reglages/entreprise"', $page);
        // L'envoi reste là : l'avertissement se place devant, il ne le retire pas (O7).
        self::assertStringContainsString('data-partager', $page);

        BaseDeTest::pdo()->exec("UPDATE artisan SET entreprise = 'Peinture Morand Sàrl' WHERE id = {$this->jeu->a}");
        self::assertStringNotContainsString('data-avertissement-envoi', $this->apercu());
    }
}
