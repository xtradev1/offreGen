<?php

declare(strict_types=1);

namespace OffreGen\Tests\Http;

use OffreGen\Tests\Outils\BaseDeTest;
use OffreGen\Tests\Outils\Dossiers;
use OffreGen\Tests\Outils\Jeu;
use OffreGen\Tests\Outils\Monde;
use OffreGen\Tests\Outils\Navigateur;
use PHPUnit\Framework\TestCase;

/**
 * Le métier d'une démo fixe son activité (docs/plans/2026-11-22_familles-activite, SPEC points 2, 7 et 8) : le commercial
 * choisit le métier sous son activité, le prospect entre dans une application qui parle sa langue.
 */
final class ActiviteDemoTest extends TestCase
{
    private Jeu $jeu;
    private Dossiers $dossiers;

    protected function setUp(): void
    {
        $this->jeu = new Jeu();
        $this->dossiers = new Dossiers();
        BaseDeTest::pdo()->exec("UPDATE artisan SET role = 'commercial', entreprise = 'Nina Rey' WHERE id = {$this->jeu->a}");
    }

    protected function tearDown(): void
    {
        $this->dossiers->effacer();
    }

    private function navigateur(?string $courriel, string $ip = '127.0.0.1'): Navigateur
    {
        $n = new Navigateur(Monde::conteneur(null, $this->dossiers->env() + ['APP_URL' => 'https://exemple.test']), $ip);
        if ($courriel !== null) {
            self::assertSame(303, $n->connecter($courriel)->getStatusCode());
        }
        return $n;
    }

    /**
     * Nina crée une démo du métier donné, le prospect ouvre son lien.
     *
     * @return array{int, string, Navigateur} le compte de la démo, la page d'entrée du prospect, son navigateur une fois entré
     */
    private function demo(string $metier): array
    {
        $nina = $this->navigateur('a@exemple.ch');
        $nina->get('/admin/demos/nouvelle');
        self::assertSame(303, $nina->post('/admin/demos', ['metier' => $metier, 'metier_saisi' => '', 'entreprise' => '', 'courriel' => ''])->getStatusCode());
        $id = (int) BaseDeTest::ligne('SELECT id FROM artisan WHERE demo = 1 ORDER BY id DESC LIMIT 1')['id'];
        self::assertSame(1, preg_match('#https://exemple\.test(/d/[A-Za-z0-9_-]{43})#', (string) $nina->get("/admin/artisans/$id/qr")->getContent(), $m));
        $prospect = $this->navigateur(null, '198.51.100.40');
        $entree = (string) $prospect->get($m[1] ?? '')->getContent();
        self::assertSame(303, $prospect->post($m[1] ?? '', ['courriel' => 'info@exemple.ch'])->getStatusCode());
        return [$id, $entree, $prospect];
    }

    public function testLaCreationDUneDemoRangeLesMetiersSousLeurActivite(): void
    {
        $nina = $this->navigateur('a@exemple.ch');
        $page = (string) $nina->get('/admin/demos/nouvelle')->getContent();
        $positions = [];
        foreach (['>Bâtiment</span>', 'value="peintre"', 'value="autre"', '>Extérieur</span>', 'value="paysagiste"', '>Prestations</span>',
            'value="informatique"', 'value="conseil"', 'value="marketing"', 'value="prestation"'] as $repere) {
            $position = strpos($page, $repere);
            self::assertNotFalse($position, $repere);
            $positions[] = $position;
        }
        $triees = $positions;
        sort($triees);
        self::assertSame($triees, $positions, 'chaque métier suit le titre de son activité');
        self::assertStringContainsString('<span>Jardinier, paysagiste</span>', $page);
        self::assertSame(13, substr_count($page, 'name="metier" value='), 'un seul groupe de choix, treize métiers');
        // Le texte libre d'« Autre métier » reste sous son groupe, avant l'intertitre suivant (revue du 2026-11-22).
        self::assertSame(1, substr_count($page, 'name="metier_saisi"'));
        self::assertLessThan(strpos($page, '>Extérieur</span>'), strpos($page, 'name="metier_saisi"'));
    }

    public function testUneDemoDInformatiqueEstUnCompteDePrestations(): void
    {
        [$id, $entree, $prospect] = $this->demo('informatique');
        self::assertSame(['metier' => 'informatique', 'activite' => 'prestations', 'entreprise' => 'Démo Informatique'],
            BaseDeTest::ligne("SELECT metier, activite, entreprise FROM artisan WHERE id = $id"));
        self::assertSame(12, BaseDeTest::compter('produit', "artisan_id = $id"));
        self::assertSame(2, BaseDeTest::compter('offre', "artisan_id = $id"));
        self::assertSame('technique', BaseDeTest::ligne("SELECT modele FROM artisan WHERE id = $id")['modele']);

        self::assertStringContainsString('dictez votre première offre.', $entree);
        self::assertStringNotContainsString('visite', $entree);
        // L'assistant du premier lancement s'ouvre : « Plus tard », puis l'accueil.
        $prospect->get('/bienvenue/entreprise');
        self::assertSame(303, $prospect->post('/bienvenue/passer', [])->getStatusCode());
        $accueil = (string) $prospect->get('/')->getContent();
        self::assertStringContainsString('Parlez, prestation par prestation.', $accueil);
        self::assertStringContainsString('Site vitrine de la boutique', $accueil);
    }

    public function testUneDemoDeJardinierEstEnExterieurEtUnPlatrierAuBatiment(): void
    {
        [$id, $entree] = $this->demo('paysagiste');
        self::assertSame('exterieur', BaseDeTest::ligne("SELECT activite FROM artisan WHERE id = $id")['activite']);
        self::assertStringContainsString('dictez votre première visite.', $entree);

        [$id] = $this->demo('platrier');
        self::assertSame('batiment', BaseDeTest::ligne("SELECT activite FROM artisan WHERE id = $id")['activite']);
    }
}
