<?php

declare(strict_types=1);

namespace OffreGen\Tests\Http;

use OffreGen\Compte\Demos;
use OffreGen\Tests\Outils\BaseDeTest;
use OffreGen\Tests\Outils\Dossiers;
use OffreGen\Tests\Outils\Jeu;
use OffreGen\Tests\Outils\Monde;
use OffreGen\Tests\Outils\Navigateur;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

/**
 * La fin d'une démo (docs/plans/2026-11-21_onboarding/PLAN.md, lot 2) : après demo_fin, dictée et import refusés,
 * l'écran de fin, la fenêtre des deux derniers jours avant, et l'attente d'une démo sans promesse de courriel.
 * L'horloge des tests est au 2026-11-07 10:00 UTC (11:00 à Zurich).
 */
final class FinDeDemoTest extends TestCase
{
    private Jeu $jeu;
    private Dossiers $dossiers;

    protected function setUp(): void
    {
        $this->jeu = new Jeu();
        $this->dossiers = new Dossiers();
    }

    protected function tearDown(): void
    {
        $this->dossiers->effacer();
    }

    private function demoFinissantLe(string $fin): void
    {
        BaseDeTest::pdo()->exec("UPDATE artisan SET demo = 1, plafond_jour = 10, demo_fin = '$fin', reglages_faits_le = '2026-11-01 08:00:00', guide_artisan_le = '2026-11-01 08:00:00' WHERE id = {$this->jeu->a}");
    }

    private function connecte(): Navigateur
    {
        $n = new Navigateur(Monde::conteneur(new MockClock('2026-11-07 10:00:00', 'UTC'), $this->dossiers->env() + ['TRAITEMENT_IMMEDIAT' => '0', 'DUREE_MIN_SECONDES' => '0']));
        self::assertSame(303, $n->connecter('a@exemple.ch')->getStatusCode());
        return $n;
    }

    public function testApresLaFinImportEtDicteeSontRefuses(): void
    {
        $this->demoFinissantLe('2026-11-07 09:59:00');
        $n = $this->connecte();
        $n->get('/import');
        $avant = (int) BaseDeTest::ligne("SELECT COUNT(*) AS n FROM enregistrement WHERE artisan_id = {$this->jeu->a}")['n'];
        $r = $n->post('/import', [], ['audio' => $this->dossiers->envoi('visite.m4a')]);
        self::assertSame(410, $r->getStatusCode());
        self::assertStringContainsString('Votre démo est terminée', (string) $r->getContent());
        self::assertSame($avant, (int) BaseDeTest::ligne("SELECT COUNT(*) AS n FROM enregistrement WHERE artisan_id = {$this->jeu->a}")['n']);
        // La feuille de dictée refuse d'avance, avec le même message.
        self::assertSame(303, $n->get('/')->getStatusCode());
        self::assertStringContainsString('data-epuise="Votre démo est terminée', (string) $n->get('/')->getContent());
    }

    public function testApresLaFinNiImportDeCatalogueNiRelanceDUneDictee(): void
    {
        $this->demoFinissantLe('2026-11-07 09:59:00');
        $uuid = (string) BaseDeTest::ligne("SELECT uuid FROM enregistrement WHERE artisan_id = {$this->jeu->a} LIMIT 1")['uuid'];
        $n = $this->connecte();
        $n->get('/fin-de-demo');
        $jeton = $n->jeton();
        // L'import du catalogue lit des offres par l'IA : refusé après la fin (revue du 2026-11-22).
        self::assertSame(410, $n->post('/import-catalogue', ['_csrf' => $jeton])->getStatusCode());
        if ($uuid !== '') {
            self::assertSame('/fin-de-demo', $n->post("/enregistrements/$uuid/reessayer", ['_csrf' => $jeton])->headers->get('Location'));
        }
    }

    public function testAvantLaFinLImportPasse(): void
    {
        $this->demoFinissantLe('2026-11-07 10:01:00');
        $n = $this->connecte();
        $n->get('/import');
        self::assertSame(303, $n->post('/import', [], ['audio' => $this->dossiers->envoi('visite.m4a')])->getStatusCode());
    }

    public function testUnEssaiEtUnCompteNeSontJamaisRefuses(): void
    {
        BaseDeTest::pdo()->exec("UPDATE artisan SET demo = 0, demo_fin = NULL, offres_max = 10 WHERE id = {$this->jeu->a}");
        $n = $this->connecte();
        $n->get('/import');
        self::assertSame(303, $n->post('/import', [], ['audio' => $this->dossiers->envoi('visite.m4a')])->getStatusCode());
    }

    public function testLEcranDeFinSOuvreUneFoisParSessionPuisResteAccessible(): void
    {
        $this->demoFinissantLe('2026-11-06 12:00:00');
        $n = $this->connecte();
        self::assertSame('/fin-de-demo', $n->get('/')->headers->get('Location'));
        $fin = (string) $n->get('/fin-de-demo')->getContent();
        self::assertStringContainsString('Votre démo est terminée', $fin);
        self::assertStringContainsString('href="/passer-a-l-essai"', $fin);
        // K1 tel que validé : la date de suppression, 30 jours après la fin, et « Demander un appel » (E1, 2026-11-23).
        self::assertStringContainsString("Vos offres restent consultables jusqu'au 06.12.2026, puis elles sont supprimées.", $fin);
        self::assertStringContainsString('un mois gratuit, sans carte bancaire', $fin);
        self::assertStringContainsString('href="/demander-un-appel"', $fin);
        self::assertStringContainsString('<b>Démo</b> · terminée', $fin);
        self::assertSame(200, $n->get('/')->getStatusCode());
    }

    public function testAvantLaFinLEcranDeFinRenvoieAuxOffresEtUnCompteOrdinaireRecoit404(): void
    {
        $this->demoFinissantLe('2026-11-10 12:00:00');
        self::assertSame('/', $this->connecte()->get('/fin-de-demo')->headers->get('Location'));
        BaseDeTest::pdo()->exec("UPDATE artisan SET demo = 0, demo_fin = NULL WHERE id = {$this->jeu->a}");
        self::assertSame(404, $this->connecte()->get('/fin-de-demo')->getStatusCode());
    }

    public function testLaFenetreSeMontreLesDeuxDerniersJoursSeulement(): void
    {
        // À Zurich, le 7 à 11:00 : fin le 9 = « dans 2 jours », le 8 = « demain », le 10 = rien.
        foreach (['2026-11-09 20:00:00' => 'se termine dans 2&nbsp;jours', '2026-11-08 06:00:00' => 'se termine demain', '2026-11-10 20:00:00' => null] as $fin => $attendu) {
            $this->demoFinissantLe($fin);
            $page = (string) $this->connecte()->get('/')->getContent();
            if ($attendu === null) {
                self::assertStringNotContainsString('data-fenetre-demo', $page, $fin);
            } else {
                self::assertStringContainsString($attendu, $page, $fin);
                self::assertStringContainsString('data-continuer', $page, $fin);
            }
        }
    }

    public function testJoursAvantLaFinSeComptentAuCalendrierDeZurich(): void
    {
        $maintenant = new \DateTimeImmutable('2026-11-07 22:30:00', new \DateTimeZone('UTC'));
        // 23:30 à Zurich le 7 ; une fin le 8 à 00:30 à Zurich (7 à 23:30 UTC) est « demain ».
        self::assertSame(1, Demos::joursAvantLaFin('2026-11-07 23:30:00', $maintenant));
        self::assertSame(0, Demos::joursAvantLaFin('2026-11-07 22:45:00', $maintenant));
        self::assertTrue(Demos::terminee('2026-11-07 22:30:00', $maintenant));
        self::assertFalse(Demos::terminee(null, $maintenant));
    }

    public function testLAttenteDUneDemoNePrometPasDeCourriel(): void
    {
        $this->demoFinissantLe('2026-11-14 10:00:00');
        $n = $this->connecte();
        $n->get('/import');
        $r = $n->post('/import', [], ['audio' => $this->dossiers->envoi('visite.m4a')]);
        $page = (string) $n->get((string) $r->headers->get('Location'))->getContent();
        self::assertStringNotContainsString('courriel vous préviendra', $page);
        self::assertStringContainsString('revenez plus tard', $page);
        BaseDeTest::pdo()->exec("UPDATE artisan SET demo = 0, demo_fin = NULL WHERE id = {$this->jeu->a}");
        self::assertStringContainsString('courriel vous préviendra', (string) $n->get((string) $r->headers->get('Location'))->getContent());
    }
}
