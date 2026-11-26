<?php

declare(strict_types=1);

namespace OffreGen\Tests\Http;

use OffreGen\Tests\Outils\BaseDeTest;
use OffreGen\Tests\Outils\Jeu;
use OffreGen\Tests\Outils\Monde;
use OffreGen\Tests\Outils\Navigateur;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

/**
 * Le suivi d'un membre de l'équipe (docs/plans/2026-11-21_onboarding/PLAN.md, lot 7) : ses démos ouvertes et leur
 * activité, « Pas encore dicté » 48 heures après l'ouverture, ses chiffres ; jamais les démos d'un autre commercial.
 */
final class SuiviTest extends TestCase
{
    private Jeu $jeu;

    protected function setUp(): void
    {
        $this->jeu = new Jeu();
        BaseDeTest::pdo()->exec("UPDATE artisan SET role = 'commercial', entreprise = 'Nina Rey' WHERE id = {$this->jeu->a}");
        BaseDeTest::pdo()->exec("UPDATE artisan SET role = 'commercial', entreprise = 'Marc Favre' WHERE id = {$this->jeu->b}");
    }

    /** Une démo du membre, ouverte par le prospect à la date donnée (null : jamais ouverte). */
    private function demo(int $membre, string $nom, ?string $ouverte, string $fin = '2026-11-12 10:00:00', int $entrainement = 0): int
    {
        $pdo = BaseDeTest::pdo();
        $pdo->exec("INSERT INTO artisan (courriel, mot_de_passe, entreprise, demo, demo_fin, cree_par, apporte_par, cree_le, metier, entrainement)
                    VALUES ('" . uniqid('demo-', true) . "@demo.invalid', 'x', '$nom', 1, '$fin', $membre, " . ($entrainement === 1 ? 'NULL' : $membre) . ", '2026-11-01 08:00:00', 'peintre', $entrainement)");
        $id = (int) $pdo->lastInsertId();
        if ($ouverte !== null) {
            $pdo->exec("INSERT INTO jeton_acces (artisan_id, empreinte, motif, cree_le, expire_le, utilise_le) VALUES ($id, '" . hash('sha256', uniqid('', true)) . "', 'demo_qr', '$ouverte', '2026-12-01 00:00:00', '$ouverte')");
        }
        return $id;
    }

    private function suivi(string $courriel, string $maintenant = '2026-11-07 10:00:00'): string
    {
        $n = new Navigateur(Monde::conteneur(new MockClock($maintenant, 'UTC')));
        self::assertSame(303, $n->connecter($courriel)->getStatusCode());
        return (string) $n->get('/admin/suivi')->getContent();
    }

    public function testLeCommercialVoitSesDemosEtPasCellesDUnAutre(): void
    {
        $this->demo($this->jeu->a, 'Menuiserie Joly', '2026-11-06 09:00:00');
        $this->demo($this->jeu->b, 'Carrelage Bovet', '2026-11-06 09:00:00');
        $page = $this->suivi('a@exemple.ch');
        self::assertStringContainsString('Mon suivi', $page);
        self::assertStringContainsString('Menuiserie Joly', $page);
        self::assertStringNotContainsString('Carrelage Bovet', $page);
    }

    public function testPasEncoreDicteApresQuaranteHuitHeuresSeulement(): void
    {
        $this->demo($this->jeu->a, 'Menuiserie Joly', '2026-11-05 11:00:00');
        self::assertStringNotContainsString('Pas encore dicté', $this->suivi('a@exemple.ch', '2026-11-07 10:59:00'));
        self::assertStringContainsString('Pas encore dicté', $this->suivi('a@exemple.ch', '2026-11-07 11:00:00'));
    }

    public function testDemoFinieOuDEntrainementHorsDuSuivi(): void
    {
        $this->demo($this->jeu->a, 'Démo finie', '2026-11-01 09:00:00', '2026-11-06 10:00:00');
        $this->demo($this->jeu->a, 'Démo entraînement', '2026-11-06 09:00:00', '2026-11-12 10:00:00', 1);
        $page = $this->suivi('a@exemple.ch');
        self::assertStringNotContainsString('Démo finie', $page);
        self::assertStringNotContainsString('Démo entraînement', $page);
        self::assertStringContainsString('Aucune démo ouverte', $page);
    }

    public function testLesChiffresComptentLesArtisansAmenes(): void
    {
        $this->demo($this->jeu->a, 'Démo un', null);
        $essai = $this->demo($this->jeu->a, 'Essai deux', null);
        $compte = $this->demo($this->jeu->a, 'Compte trois', null);
        $this->demo($this->jeu->a, 'Entraînement', null, '2026-11-12 10:00:00', 1);
        BaseDeTest::pdo()->exec("UPDATE artisan SET demo = 0, demo_fin = NULL, offres_max = 20 WHERE id = $essai");
        BaseDeTest::pdo()->exec("UPDATE artisan SET demo = 0, demo_fin = NULL, offres_max = NULL WHERE id = $compte");
        $page = preg_replace('/\s+/', ' ', $this->suivi('a@exemple.ch'));
        self::assertMatchesRegularExpression('#Amenés par vous</span><b>3</b>#', (string) $page);
        self::assertMatchesRegularExpression('#À l\'essai</span><b>1</b>#', (string) $page);
        self::assertMatchesRegularExpression('#Abonnés</span><b>1</b>#', (string) $page);
    }

    public function testLAdministrateurVoitToutesLesDemos(): void
    {
        $this->demo($this->jeu->b, 'Carrelage Bovet', '2026-11-06 09:00:00');
        BaseDeTest::pdo()->exec("UPDATE artisan SET role = NULL, administrateur = 1 WHERE id = {$this->jeu->a}");
        $page = $this->suivi('a@exemple.ch');
        self::assertStringContainsString('Carrelage Bovet', $page);
        self::assertStringContainsString('<h1>Suivi</h1>', $page);
    }
}
