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
 * L'arrivée d'un commercial (docs/plans/2026-11-21_onboarding/PLAN.md, lot 8) : la bienvenue une seule fois, la démo
 * d'entraînement hors limite et sans « amené par », le retour direct dans son espace et tous ses refus.
 */
final class ArriveeCommercialTest extends TestCase
{
    private Jeu $jeu;
    private Dossiers $dossiers;

    protected function setUp(): void
    {
        $this->jeu = new Jeu();
        $this->dossiers = new Dossiers();
        BaseDeTest::pdo()->exec("UPDATE artisan SET role = 'commercial', entreprise = 'Nina Rey', guide_equipe_le = NULL WHERE id = {$this->jeu->a}");
    }

    protected function tearDown(): void
    {
        $this->dossiers->effacer();
    }

    private function navigateur(?string $courriel = 'a@exemple.ch', string $ip = '127.0.0.1', ?MockClock $horloge = null): Navigateur
    {
        $n = new Navigateur(Monde::conteneur($horloge, $this->dossiers->env() + ['APP_URL' => 'https://exemple.test']), $ip);
        if ($courriel !== null) {
            self::assertSame(303, $n->connecter($courriel)->getStatusCode());
        }
        return $n;
    }

    /**
     * Une démo créée par Nina. Une démo d'entraînement passe par « Faire ma démo d'entraînement », qui pose la marque à
     * usage unique ; « champ seul » poste le champ sans elle.
     *
     * @return array{int, string} son id et le chemin de son lien d'entrée
     */
    private function demo(Navigateur $nina, bool $entrainement, bool $champSeul = false): array
    {
        if ($entrainement && !$champSeul) {
            $nina->get('/admin/bienvenue?ecran=3');
            self::assertSame(303, $nina->post('/admin/bienvenue/vue', ['suite' => 'guide'])->getStatusCode());
        }
        $nina->get('/admin/demos/nouvelle');
        $champs = ['metier' => 'peintre', 'metier_saisi' => '', 'entreprise' => '', 'courriel' => ''] + ($entrainement ? ['entrainement' => '1'] : []);
        self::assertSame(303, $nina->post('/admin/demos', $champs)->getStatusCode());
        $id = (int) BaseDeTest::ligne('SELECT id FROM artisan WHERE demo = 1 ORDER BY id DESC LIMIT 1')['id'];
        // Démo de prospect : le lien complet ; démo d'entraînement : le formulaire POST vers son chemin.
        self::assertSame(1, preg_match('#(?:https://exemple\.test)?(/d/[A-Za-z0-9_-]{43})#', (string) $nina->get("/admin/artisans/$id/qr")->getContent(), $m));
        return [$id, $m[1] ?? ''];
    }

    public function testLaBienvenueSeMontreUneFoisAuCommercialSeulement(): void
    {
        $nina = $this->navigateur();
        self::assertSame('/admin/bienvenue', $nina->get('/admin')->headers->get('Location'));
        $un = (string) $nina->get('/admin/bienvenue')->getContent();
        self::assertStringContainsString("Bienvenue dans l'équipe, Nina", $un);
        self::assertStringContainsString('7 jours, avec 10 offres', (string) $nina->get('/admin/bienvenue?ecran=2')->getContent());
        self::assertStringContainsString('Faire ma démo d\'entraînement', (string) $nina->get('/admin/bienvenue?ecran=3')->getContent());
        self::assertSame('/admin/suivi?guide=depart', $nina->post('/admin/bienvenue/vue', ['suite' => 'guide'])->headers->get('Location'));
        self::assertNotNull(BaseDeTest::ligne("SELECT guide_equipe_le FROM artisan WHERE id = {$this->jeu->a}")['guide_equipe_le']);
        self::assertSame('/admin/suivi', $nina->get('/admin')->headers->get('Location'));
        // Le guide est en cours : le suivi porte sa première étape, le formulaire marque la démo d'entraînement.
        self::assertStringContainsString('data-guide="equipe"', (string) $nina->get('/admin/suivi')->getContent());
        self::assertStringContainsString('name="entrainement" value="1"', (string) $nina->get('/admin/demos/nouvelle')->getContent());

        BaseDeTest::pdo()->exec("UPDATE artisan SET role = NULL, administrateur = 1, guide_equipe_le = NULL WHERE id = {$this->jeu->b}");
        self::assertSame('/admin/tableau', $this->navigateur('b@exemple.ch')->get('/admin')->headers->get('Location'));
    }

    public function testLaDemoDEntrainementEstHorsLimiteEtSansApporteur(): void
    {
        $nina = $this->navigateur();
        [$id] = $this->demo($nina, true);
        $l = BaseDeTest::ligne("SELECT entrainement, apporte_par, cree_par, courriel_saisi FROM artisan WHERE id = $id");
        self::assertSame([1, null, $this->jeu->a, 'a@exemple.ch'], [(int) $l['entrainement'], $l['apporte_par'], (int) $l['cree_par'], $l['courriel_saisi']]);
        self::assertSame(0, (int) BaseDeTest::ligne("SELECT COUNT(*) AS n FROM journal_admin WHERE acteur_id = {$this->jeu->a} AND geste = 'demo_creee'")['n']);
        // Trois en 24 heures, pas une de plus.
        $this->demo($nina, true);
        $this->demo($nina, true);
        $nina->get('/admin/bienvenue?ecran=3');
        $nina->post('/admin/bienvenue/vue', ['suite' => 'guide']);
        $nina->get('/admin/demos/nouvelle');
        self::assertSame(429, $nina->post('/admin/demos', ['metier' => 'peintre', 'metier_saisi' => '', 'entreprise' => '', 'courriel' => '', 'entrainement' => '1'])->getStatusCode());
        self::assertSame(Demos::ENTRAINEMENTS_PAR_JOUR, (int) BaseDeTest::ligne("SELECT COUNT(*) AS n FROM artisan WHERE entrainement = 1 AND cree_par = {$this->jeu->a}")['n']);
    }

    public function testLeMembreOuvreSaDemoDEntrainementEtRevientDansSonEspace(): void
    {
        $nina = $this->navigateur();
        [$id, $lien] = $this->demo($nina, true);
        // Ouverte d'un toucher par le formulaire POST de la page du code, sans page d'entrée : son courriel y est déjà. Le
        // GET du lien n'ouvre rien (revue du 2026-11-22) : il montre la page d'entrée.
        self::assertSame(200, $this->navigateur()->get($lien)->getStatusCode());
        self::assertSame('/', $nina->post($lien, [])->headers->get('Location'));
        self::assertSame(1, (int) BaseDeTest::ligne("SELECT COUNT(*) AS n FROM journal_admin WHERE acteur_id = {$this->jeu->a} AND artisan_id = $id AND geste = 'entree_entrainement'")['n']);
        $offres = (string) $nina->get('/')->getContent();
        self::assertStringContainsString('Revenir à mon espace', $offres);
        self::assertStringNotContainsString('href="/passer-a-l-essai"', $offres);
        $r = $nina->post('/retour-espace', []);
        // Démo ouverte par le guide : le retour mène à la carte de fin du guide.
        self::assertSame('/admin/suivi?guide=fin', $r->headers->get('Location'));
        self::assertStringContainsString('Le guide est terminé', (string) $nina->get('/admin/suivi?guide=fin')->getContent());
        self::assertSame(1, (int) BaseDeTest::ligne("SELECT COUNT(*) AS n FROM journal_admin WHERE acteur_id = {$this->jeu->a} AND artisan_id = $id AND geste = 'retour_entrainement'")['n']);
        // Le retour sert une fois : la session est maintenant celle de Nina.
        $nina->get('/');
        self::assertSame(404, $nina->post('/retour-espace', [])->getStatusCode());
    }

    public function testLeRetourExpireApresDeuxHeures(): void
    {
        $horloge = new MockClock('2026-11-07 10:00:00', 'UTC');
        $nina = $this->navigateur('a@exemple.ch', '127.0.0.1', $horloge);
        [, $lien] = $this->demo($nina, true);
        $nina->post($lien, []);
        $horloge->modify('+2 hours +1 second');
        self::assertStringNotContainsString('Revenir à mon espace', (string) $nina->get('/')->getContent());
        self::assertSame(404, $nina->post('/retour-espace', [])->getStatusCode());
    }

    public function testLeChampEntrainementPosteSeulNeFaitPasUneDemoDEntrainement(): void
    {
        [$id] = $this->demo($this->navigateur(), true, true);
        $l = BaseDeTest::ligne("SELECT entrainement, apporte_par FROM artisan WHERE id = $id");
        self::assertSame([0, $this->jeu->a], [(int) $l['entrainement'], (int) $l['apporte_par']]);
        // La marque sert une fois : la démo suivante, pour un prospect, est une démo ordinaire.
        $nina = $this->navigateur();
        $this->demo($nina, true);
        [$suivante] = $this->demo($nina, true, true);
        self::assertSame(0, (int) BaseDeTest::ligne("SELECT entrainement FROM artisan WHERE id = $suivante")['entrainement']);
    }

    public function testUneDemoDeProspectNOffreJamaisLeRetour(): void
    {
        $nina = $this->navigateur();
        [, $lien] = $this->demo($nina, false);
        $prospect = $this->navigateur(null, '198.51.100.7');
        $prospect->get($lien);
        self::assertSame(303, $prospect->post($lien, ['courriel' => 'info@prospect.ch'])->getStatusCode());
        self::assertStringNotContainsString('Revenir à mon espace', (string) $prospect->get('/')->getContent());
        self::assertSame(404, $prospect->post('/retour-espace', [])->getStatusCode());
        // Nina elle-même ouvre la démo d'un prospect : pas de retour non plus, ce n'est pas un entraînement.
        [, $lien2] = $this->demo($nina, false);
        $nina->get($lien2);
        $nina->post($lien2, ['courriel' => 'info@autre.ch']);
        $nina->get('/');
        self::assertSame(404, $nina->post('/retour-espace', [])->getStatusCode());
    }

    public function testLaDemoDEntrainementOuverteParUnAutreNOffrePasLeRetour(): void
    {
        $nina = $this->navigateur();
        [, $lien] = $this->demo($nina, true);
        $autre = $this->navigateur(null, '198.51.100.8');
        $autre->get($lien);
        self::assertSame(303, $autre->post($lien, [])->getStatusCode());
        $autre->get('/');
        self::assertSame(404, $autre->post('/retour-espace', [])->getStatusCode());
    }

    public function testLeRetourEstRefuseSiLaSessionDuMembreAChange(): void
    {
        $nina = $this->navigateur();
        [, $lien] = $this->demo($nina, true);
        $nina->post($lien, []);
        $nina->get('/');
        // Mot de passe changé ailleurs, accès coupé, déconnexion de partout : le numéro de session avance.
        BaseDeTest::pdo()->exec("UPDATE artisan SET version_session = version_session + 1 WHERE id = {$this->jeu->a}");
        self::assertSame(404, $nina->post('/retour-espace', [])->getStatusCode());
        // Un membre qui a quitté l'équipe ne revient pas non plus.
        $nina2 = $this->navigateur();
        [, $lien2] = $this->demo($nina2, true);
        $nina2->post($lien2, []);
        $nina2->get('/');
        BaseDeTest::pdo()->exec("UPDATE artisan SET role = NULL WHERE id = {$this->jeu->a}");
        self::assertSame(404, $nina2->post('/retour-espace', [])->getStatusCode());
    }
}
