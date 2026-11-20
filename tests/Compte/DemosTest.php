<?php

declare(strict_types=1);

namespace OffreGen\Tests\Compte;

use OffreGen\Tests\Outils\BaseDeTest;
use OffreGen\Tests\Outils\Dossiers;
use OffreGen\Tests\Outils\Jeu;
use OffreGen\Tests\Outils\Monde;
use OffreGen\Tests\Outils\Navigateur;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Response;

/**
 * La démo créée par l'équipe (lot 5 de docs/plans/2026-11-19_admin-comptes ; demande de Xavier du 2026-11-20) :
 * le commercial choisit le métier, le prospect ouvre le lien, tape son courriel et entre. Aucun mot de passe,
 * aucun courriel envoyé ; le courriel tapé ne devient pas l'adresse du compte avant d'être confirmé.
 */
final class DemosTest extends TestCase
{
    private const LIEN = '#https://exemple\.test(/d/([A-Za-z0-9_-]{43}))#';

    private Jeu $jeu;
    private Dossiers $dossiers;

    protected function setUp(): void
    {
        // A est Nina, commerciale ; B, un artisan sans rôle.
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
            $n->connecter($courriel);
        }
        return $n;
    }

    /** @param array<string, string> $champs */
    private function creer(Navigateur $n, array $champs = []): Response
    {
        $n->get('/admin/demos/nouvelle');
        return $n->post('/admin/demos', $champs + ['metier' => 'peintre', 'metier_saisi' => '', 'entreprise' => '', 'courriel' => '']);
    }

    /** @return array<string, mixed> la dernière démo créée */
    private function demo(): array
    {
        return BaseDeTest::ligne('SELECT id, courriel, entreprise, demo, offres_max, plafond_jour, demo_fin, cree_par, apporte_par, metier, courriel_saisi FROM artisan WHERE demo = 1 ORDER BY id DESC LIMIT 1');
    }

    private function compter(string $sql): int
    {
        return (int) BaseDeTest::ligne($sql)['n'];
    }

    /**
     * Le lien de démo écrit sur la page.
     *
     * @return array{string, string} son chemin et son jeton
     */
    private function lien(Response $page): array
    {
        self::assertSame(1, preg_match(self::LIEN, (string) $page->getContent(), $m), 'aucun lien de démo sur la page');
        return [$m[1] ?? '', $m[2] ?? ''];
    }

    public function testLeCommercialCreeUneDemoPourUnMetier(): void
    {
        $nina = $this->navigateur('a@exemple.ch');
        $formulaire = (string) $nina->get('/admin/demos/nouvelle')->getContent();
        foreach (['Peintre', 'Carreleur', 'Poseur de sols', 'Autre métier', 'Créer la démo'] as $texte) {
            self::assertStringContainsString($texte, $formulaire, $texte);
        }

        $reponse = $this->creer($nina, ['metier' => 'carreleur', 'entreprise' => 'Carrelage Buchs']);
        self::assertSame(303, $reponse->getStatusCode());
        $demo = $this->demo();
        self::assertSame("/admin/artisans/{$demo['id']}/qr", $reponse->headers->get('Location'));
        self::assertSame(
            ['Carrelage Buchs', 1, 10, null, $this->jeu->a, $this->jeu->a, 'carreleur', null],
            [$demo['entreprise'], $demo['demo'], $demo['offres_max'], $demo['plafond_jour'], $demo['cree_par'], $demo['apporte_par'], $demo['metier'], $demo['courriel_saisi']],
        );
        // Une adresse fabriquée, que personne ne peut recevoir ; la démo vaut sept jours.
        self::assertMatchesRegularExpression('/^demo-[0-9a-f]{16}@demo\.invalid$/', (string) $demo['courriel']);
        self::assertStringStartsWith('2026-11-14', (string) $demo['demo_fin']);
        // Posée tout de suite : le catalogue du métier et ses offres d'exemple.
        self::assertSame(12, $this->compter("SELECT COUNT(*) AS n FROM produit WHERE artisan_id = {$demo['id']}"));
        self::assertSame(2, $this->compter("SELECT COUNT(*) AS n FROM offre WHERE artisan_id = {$demo['id']}"));
        $journal = BaseDeTest::ligne('SELECT acteur_id, artisan_id, geste, detail FROM journal_admin ORDER BY id DESC LIMIT 1');
        self::assertSame([$this->jeu->a, $demo['id'], 'demo_creee', 'carreleur'], array_values($journal));
        self::assertCount(0, $this->dossiers->courriels());

        // Sans nom d'entreprise : un nom provisoire, qui dit que c'est une démo.
        self::assertSame(303, $this->creer($nina)->getStatusCode());
        self::assertSame('Démo Peintre', $this->demo()['entreprise']);
        // Elle figure dans la liste du commercial, avec son métier.
        $liste = (string) $nina->get('/admin/artisans?type=demos')->getContent();
        self::assertStringContainsString('Carrelage Buchs', $liste);
        self::assertStringContainsString('Créer une démo', $liste);
    }

    public function testUneSaisieFausseNeCreeRien(): void
    {
        $nina = $this->navigateur('a@exemple.ch');
        foreach ([['metier' => 'boucher'], ['metier' => ''], ['courriel' => 'pas une adresse'], ['metier' => 'autre', 'metier_saisi' => str_repeat('x', 61)]] as $faute) {
            $reponse = $this->creer($nina, $faute);
            self::assertSame(422, $reponse->getStatusCode(), (string) json_encode($faute));
            self::assertStringContainsString('role="alert"', (string) $reponse->getContent());
        }
        self::assertSame(2, $this->compter('SELECT COUNT(*) AS n FROM artisan'));

        // Le texte du métier ne compte qu'avec « Autre métier » : ailleurs, un reste de saisie est ignoré.
        self::assertSame(303, $this->creer($nina, ['metier' => 'peintre', 'metier_saisi' => 'Ferblantier'])->getStatusCode());
        self::assertNull(BaseDeTest::ligne('SELECT metier_saisi FROM artisan WHERE demo = 1 ORDER BY id DESC LIMIT 1')['metier_saisi']);
        self::assertSame(303, $this->creer($nina, ['metier' => 'autre', 'metier_saisi' => 'Ferblantier'])->getStatusCode());
        self::assertSame('Ferblantier', BaseDeTest::ligne('SELECT metier_saisi FROM artisan WHERE demo = 1 ORDER BY id DESC LIMIT 1')['metier_saisi']);
    }

    public function testLeLienOuvreLaDemoUneFoisAvecLeCourrielDuProspect(): void
    {
        $nina = $this->navigateur('a@exemple.ch');
        $this->creer($nina);
        $demo = $this->demo();
        $page = $nina->get("/admin/artisans/{$demo['id']}/qr");
        self::assertSame(200, $page->getStatusCode());
        self::assertStringContainsString('<svg', (string) $page->getContent());
        self::assertStringContainsString('no-store', (string) $page->headers->get('Cache-Control'));
        [$lien] = $this->lien($page);
        $jeton = BaseDeTest::ligne('SELECT motif, TIMESTAMPDIFF(MINUTE, cree_le, expire_le) AS minutes FROM jeton_acces ORDER BY id DESC LIMIT 1');
        self::assertSame(['demo_qr', 60], [$jeton['motif'], (int) $jeton['minutes']]);

        // Ouvrir la page ne consomme rien : les aperçus de lien des messageries la chargent aussi.
        $prospect = $this->navigateur(null, '198.51.100.20');
        foreach ([1, 2] as $_) {
            $accueil = $prospect->get($lien);
            self::assertSame(200, $accueil->getStatusCode());
            self::assertStringContainsString('Commencer', (string) $accueil->getContent());
        }
        self::assertSame(1, $this->compter('SELECT COUNT(*) AS n FROM jeton_acces WHERE utilise_le IS NULL'));

        // Sans courriel, ou avec un courriel faux : refusé, le lien reste bon.
        foreach (['', 'pas une adresse'] as $faux) {
            $refus = $prospect->post($lien, ['courriel' => $faux]);
            self::assertSame(422, $refus->getStatusCode(), "courriel « $faux »");
            self::assertStringContainsString('role="alert"', (string) $refus->getContent());
        }

        $entree = $prospect->post($lien, ['courriel' => ' Info@Peinture-Morand.ch ']);
        self::assertSame(303, $entree->getStatusCode());
        self::assertSame('/', $entree->headers->get('Location'));
        // Il arrive sur ses offres, prêt à dicter : pas d'assistant de bienvenue avant.
        self::assertSame(200, $prospect->get('/')->getStatusCode());
        self::assertSame(200, $prospect->get('/catalogue')->getStatusCode());
        // Le courriel tapé est noté à part : il ne devient pas l'adresse du compte avant d'être confirmé.
        $apres = $this->demo();
        self::assertSame('info@peinture-morand.ch', $apres['courriel_saisi']);
        self::assertSame($demo['courriel'], $apres['courriel']);

        // Le lien a servi : il ne rouvre rien, pour personne.
        $autre = $this->navigateur(null, '198.51.100.21');
        self::assertSame(410, $autre->get($lien)->getStatusCode());
        $autre->get('/connexion');
        self::assertSame(410, $autre->post($lien, ['courriel' => 'x@exemple.ch'])->getStatusCode());
        self::assertCount(0, $this->dossiers->courriels());
    }

    public function testLeCourrielDejaTapeParLeCommercialSuffit(): void
    {
        $nina = $this->navigateur('a@exemple.ch');
        $this->creer($nina, ['courriel' => 'info@peinture-morand.ch']);
        $demo = $this->demo();
        self::assertSame('info@peinture-morand.ch', $demo['courriel_saisi']);
        [$lien] = $this->lien($nina->get("/admin/artisans/{$demo['id']}/qr"));
        $prospect = $this->navigateur(null, '198.51.100.20');
        self::assertStringContainsString('value="info@peinture-morand.ch"', (string) $prospect->get($lien)->getContent());
        self::assertSame(303, $prospect->post($lien, ['courriel' => ''])->getStatusCode());
        self::assertSame('info@peinture-morand.ch', $this->demo()['courriel_saisi']);
    }

    public function testLeLienParMessageVautVingtQuatreHeuresPuisExpire(): void
    {
        $nina = $this->navigateur('a@exemple.ch');
        $this->creer($nina);
        $demo = $this->demo();
        $nina->get("/admin/artisans/{$demo['id']}");
        $page = $nina->post("/admin/artisans/{$demo['id']}/message", []);
        self::assertSame(200, $page->getStatusCode());
        self::assertStringContainsString('https://wa.me/?text=', (string) $page->getContent());
        self::assertStringContainsString('mailto:', (string) $page->getContent());
        [$lien] = $this->lien($page);
        $jeton = BaseDeTest::ligne('SELECT motif, TIMESTAMPDIFF(MINUTE, cree_le, expire_le) AS minutes FROM jeton_acces ORDER BY id DESC LIMIT 1');
        self::assertSame(['demo_msg', 1440], [$jeton['motif'], (int) $jeton['minutes']]);

        BaseDeTest::pdo()->exec("UPDATE jeton_acces SET expire_le = '2026-11-01 00:00:00'");
        $prospect = $this->navigateur(null, '198.51.100.20');
        self::assertSame(410, $prospect->get($lien)->getStatusCode());
        $prospect->get('/connexion');
        self::assertSame(410, $prospect->post($lien, ['courriel' => 'info@peinture-morand.ch'])->getStatusCode());
        self::assertNull($this->demo()['courriel_saisi']);
    }

    public function testUnLienDeDemoNeChoisitPasDeMotDePasseEtInversement(): void
    {
        $nina = $this->navigateur('a@exemple.ch');
        $this->creer($nina);
        $demo = $this->demo();
        [, $jeton] = $this->lien($nina->get("/admin/artisans/{$demo['id']}/qr"));
        $visiteur = $this->navigateur(null, '198.51.100.20');
        self::assertSame(410, $visiteur->get("/mot-de-passe/$jeton")->getStatusCode());
        $visiteur->get('/connexion');
        self::assertSame(410, $visiteur->post("/mot-de-passe/$jeton", ['mot_de_passe' => 'un-mot-de-passe-long', 'confirmation' => 'un-mot-de-passe-long'])->getStatusCode());
        // Le lien de démo n'a pas servi pour autant.
        self::assertSame(200, $visiteur->get("/d/$jeton")->getStatusCode());

        // Un lien d'accès à un compte n'ouvre pas de démo.
        $acces = $visiteur->c->liensAcces()->lienDAcces($this->jeu->b);
        self::assertSame(1, preg_match('#/mot-de-passe/([A-Za-z0-9_-]{43})$#', $acces, $m));
        $autre = $m[1] ?? '';
        self::assertSame(410, $visiteur->get("/d/$autre")->getStatusCode());
        $visiteur->get('/connexion');
        self::assertSame(410, $visiteur->post("/d/$autre", ['courriel' => 'x@exemple.ch'])->getStatusCode());
        self::assertSame(200, $visiteur->get("/mot-de-passe/$autre")->getStatusCode());
    }

    public function testLeCommercialSArreteADixDemosEnVingtQuatreHeures(): void
    {
        $pdo = BaseDeTest::pdo();
        $dix = static function (int $acteur, string $quand) use ($pdo): void {
            for ($i = 0; $i < 10; $i++) {
                $pdo->exec("INSERT INTO journal_admin (acteur_id, artisan_id, geste, detail, quand) VALUES ($acteur, NULL, 'demo_creee', 'peintre', '$quand')");
            }
        };
        $nina = $this->navigateur('a@exemple.ch');
        // Dix démos il y a deux jours ne comptent plus.
        $dix($this->jeu->a, '2026-11-05 09:00:00');
        self::assertSame(303, $this->creer($nina)->getStatusCode());
        // Avec dix de plus aujourd'hui, la suivante est refusée.
        $dix($this->jeu->a, '2026-11-07 09:00:00');
        $refus = $this->creer($nina);
        self::assertSame(429, $refus->getStatusCode());
        self::assertStringContainsString('role="alert"', (string) $refus->getContent());
        self::assertSame(1, $this->compter('SELECT COUNT(*) AS n FROM artisan WHERE demo = 1'));

        // L'administrateur n'a pas de limite.
        $pdo->exec("UPDATE artisan SET administrateur = 1 WHERE id = {$this->jeu->b}");
        $dix($this->jeu->b, '2026-11-07 09:00:00');
        self::assertSame(303, $this->creer($this->navigateur('b@exemple.ch', '198.51.100.30'))->getStatusCode());
    }

    public function testLaDemoDUnAutreCommercialNExistePas(): void
    {
        $nina = $this->navigateur('a@exemple.ch');
        $this->creer($nina);
        $demo = $this->demo();
        BaseDeTest::pdo()->exec("UPDATE artisan SET role = 'commercial' WHERE id = {$this->jeu->b}");
        $leo = $this->navigateur('b@exemple.ch', '198.51.100.30');
        self::assertSame(404, $leo->get("/admin/artisans/{$demo['id']}/qr")->getStatusCode());
        $leo->get('/admin/plus');
        self::assertSame(404, $leo->post("/admin/artisans/{$demo['id']}/message", [])->getStatusCode());
        self::assertSame(0, $this->compter('SELECT COUNT(*) AS n FROM jeton_acces'));

        // Un compte qui n'est pas une démo n'a ni code ni lien de démo.
        Monde::conteneur()->comptes()->creer('essai@exemple.ch', 'Toiture Essai', false, null, 10);
        $essai = (int) BaseDeTest::ligne("SELECT id FROM artisan WHERE courriel = 'essai@exemple.ch'")['id'];
        BaseDeTest::pdo()->exec("UPDATE artisan SET cree_par = {$this->jeu->a} WHERE id = $essai");
        self::assertSame(404, $nina->get("/admin/artisans/$essai/qr")->getStatusCode());
        $nina->get('/admin/plus');
        self::assertSame(404, $nina->post("/admin/artisans/$essai/message", [])->getStatusCode());
    }

    public function testUnArtisanSansRoleNeCreePasDeDemo(): void
    {
        $b = $this->navigateur('b@exemple.ch', '198.51.100.30');
        self::assertSame(404, $b->get('/admin/demos/nouvelle')->getStatusCode());
        $b->get('/plus');
        self::assertSame(404, $b->post('/admin/demos', ['metier' => 'peintre'])->getStatusCode());
        self::assertSame(0, $this->compter('SELECT COUNT(*) AS n FROM artisan WHERE demo = 1'));
    }
}
