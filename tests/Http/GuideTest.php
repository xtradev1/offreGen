<?php

declare(strict_types=1);

namespace OffreGen\Tests\Http;

use OffreGen\Tests\Outils\BaseDeTest;
use OffreGen\Tests\Outils\Jeu;
use OffreGen\Tests\Outils\Monde;
use OffreGen\Tests\Outils\Navigateur;
use PHPUnit\Framework\TestCase;

/**
 * Le guide à anneau de l'artisan (chantier 2026-11-21_onboarding, PLAN.md, lots 0 et 3) : il se montre à une démo
 * de l'équipe, « Compris » ou « Quitter le guide » le referme.
 */
final class GuideTest extends TestCase
{
    private Jeu $jeu;

    protected function setUp(): void
    {
        $this->jeu = new Jeu();
    }

    private function connecte(): Navigateur
    {
        $n = new Navigateur(Monde::conteneur());
        self::assertSame(303, $n->connecter('a@exemple.ch')->getStatusCode());
        return $n;
    }

    private function enDemo(): void
    {
        BaseDeTest::pdo()->exec("UPDATE artisan SET demo = 1, demo_fin = DATE_ADD(NOW(), INTERVAL 7 DAY), reglages_faits_le = NOW() WHERE id = {$this->jeu->a}");
    }

    public function testUneDemoDeLEquipeVoitLeGuideSurSesOffres(): void
    {
        $this->enDemo();
        $page = (string) $this->connecte()->get('/')->getContent();
        self::assertStringContainsString('data-guide="artisan"', $page);
        self::assertStringContainsString('src="/js/guide.js"', $page);
    }

    public function testUnCompteOrdinaireNeLeVoitPas(): void
    {
        $page = (string) $this->connecte()->get('/')->getContent();
        self::assertStringNotContainsString('data-guide=', $page);
    }

    public function testFiniDemandeLeJetonEtUnEtatConnu(): void
    {
        $this->enDemo();
        $n = $this->connecte();
        $n->get('/');
        $jeton = $n->jeton();
        self::assertSame(403, $n->post('/guide/fini', ['etat' => 'fini'], [], false)->getStatusCode());
        self::assertSame(422, $n->post('/guide/fini', ['etat' => 'autre', '_csrf' => $jeton])->getStatusCode());
        self::assertSame(204, $n->post('/guide/fini', ['etat' => 'fini', '_csrf' => $jeton])->getStatusCode());
        self::assertSame(204, $n->post('/guide/fini', ['etat' => 'quitte', '_csrf' => $jeton])->getStatusCode());
    }

    public function testFiniLeRefermePourDeBonEtRevoirLeRouvre(): void
    {
        $this->enDemo();
        $n = $this->connecte();
        $n->get('/');
        $jeton = $n->jeton();
        self::assertSame(204, $n->post('/guide/fini', ['etat' => 'quitte', '_csrf' => $jeton])->getStatusCode());
        $premiere = BaseDeTest::ligne("SELECT guide_artisan_le FROM artisan WHERE id = {$this->jeu->a}")['guide_artisan_le'];
        self::assertNotNull($premiere);
        self::assertStringNotContainsString('data-guide=', (string) $n->get('/')->getContent());
        // Un second « Compris » ne déplace pas la date.
        self::assertSame(204, $n->post('/guide/fini', ['etat' => 'fini', '_csrf' => $jeton])->getStatusCode());
        self::assertSame($premiere, BaseDeTest::ligne("SELECT guide_artisan_le FROM artisan WHERE id = {$this->jeu->a}")['guide_artisan_le']);
        $r = $n->post('/guide/revoir', ['_csrf' => $jeton]);
        self::assertSame('/?guide=depart', $r->headers->get('Location'));
        self::assertStringContainsString('data-guide="artisan"', (string) $n->get('/')->getContent());
    }

    public function testUnEssaiVoitLeGuideUnMembreDeLEquipeJamais(): void
    {
        BaseDeTest::pdo()->exec("UPDATE artisan SET offres_max = 10, reglages_faits_le = NOW() WHERE id = {$this->jeu->a}");
        self::assertStringContainsString('data-guide="artisan"', (string) $this->connecte()->get('/')->getContent());
        BaseDeTest::pdo()->exec("UPDATE artisan SET role = 'commercial' WHERE id = {$this->jeu->a}");
        self::assertStringNotContainsString('data-guide=', (string) $this->connecte()->get('/')->getContent());
    }

    public function testLesOffresPortentLesQuatrePremieresEtapesEtLesConseils(): void
    {
        $this->enDemo();
        $page = (string) $this->connecte()->get('/')->getContent();
        foreach (['data-n="1"', 'data-n="2"', 'data-n="3"', 'data-n="4"', 'data-guide-conseils', 'Avant de dicter', 'Commencer la dictée'] as $attendu) {
            self::assertStringContainsString($attendu, $page);
        }
        self::assertStringNotContainsString('data-n="5"', $page);
    }

    public function testLesReperesSeulementSurUneOffreDictee(): void
    {
        $this->enDemo();
        $n = $this->connecte();
        $dictee = (int) BaseDeTest::ligne("SELECT id FROM offre WHERE artisan_id = {$this->jeu->a} AND enregistrement_id IS NOT NULL LIMIT 1")['id'];
        self::assertGreaterThan(0, $dictee, 'le jeu de test porte une offre dictée');
        self::assertStringContainsString('data-n="5"', (string) $n->get("/offres/$dictee")->getContent());
        BaseDeTest::pdo()->exec("UPDATE offre SET enregistrement_id = NULL WHERE id = $dictee");
        self::assertStringNotContainsString('data-guide=', (string) $n->get("/offres/$dictee")->getContent());
    }

    public function testLePassageALEssaiNeRejouePasLeGuide(): void
    {
        BaseDeTest::pdo()->exec("UPDATE artisan SET demo = 1, demo_fin = DATE_ADD(NOW(), INTERVAL 7 DAY), metier = 'peintre', iban = 'CH9300762011623852957' WHERE id = {$this->jeu->a}");
        Monde::conteneur()->comptes()->passerEnEssai('a@exemple.ch', 'a@exemple.ch');
        $ligne = BaseDeTest::ligne("SELECT guide_artisan_le, reglages_faits_le, offres_max, iban FROM artisan WHERE id = {$this->jeu->a}");
        self::assertSame('CH9300762011623852957', $ligne['iban'], 'l\x27IBAN tapé dans la démo reste');
        self::assertNotNull($ligne['guide_artisan_le']);
        self::assertNull($ligne['reglages_faits_le'], 'la mise en route s\'ouvre');
        self::assertNotNull($ligne['offres_max']);
    }

    public function testSansSessionLaRouteRenvoieALaConnexion(): void
    {
        $n = new Navigateur(Monde::conteneur());
        $n->get('/connexion');
        self::assertSame(303, $n->post('/guide/fini', ['etat' => 'fini'])->getStatusCode());
    }
}
