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
 * De la démo à l'essai, par le prospect lui-même (Xavier, 2026-11-20 : « il faut un petit bouton passer à l'essai,
 * pouf, et là ça passe », « visible en haut »). Le ruban ne se montre que dans une démo créée par l'équipe : une
 * démonstration partagée, d'avant, ne se passe pas à l'essai depuis l'écran.
 */
final class PassageDemoTest extends TestCase
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

    private function navigateur(?string $courriel, string $ip = '127.0.0.1', string $motDePasse = Jeu::MOT_DE_PASSE): Navigateur
    {
        $n = new Navigateur(Monde::conteneur(null, $this->dossiers->env() + ['APP_URL' => 'https://exemple.test']), $ip);
        if ($courriel !== null) {
            self::assertSame(303, $n->connecter($courriel, $motDePasse)->getStatusCode());
        }
        return $n;
    }

    /**
     * Nina crée une démo de plâtrier ; le prospect y entre avec son courriel.
     *
     * @return array{Navigateur, int} le navigateur du prospect, et le compte de la démo
     */
    private function prospectDansSaDemo(): array
    {
        $nina = $this->navigateur('a@exemple.ch');
        $nina->get('/admin/demos/nouvelle');
        self::assertSame(303, $nina->post('/admin/demos', ['metier' => 'platrier', 'metier_saisi' => '', 'entreprise' => '', 'courriel' => ''])->getStatusCode());
        $id = (int) BaseDeTest::ligne('SELECT id FROM artisan WHERE demo = 1 ORDER BY id DESC LIMIT 1')['id'];
        self::assertSame(1, preg_match('#https://exemple\.test(/d/[A-Za-z0-9_-]{43})#', (string) $nina->get("/admin/artisans/$id/qr")->getContent(), $m));
        $lien = $m[1] ?? '';
        $prospect = $this->navigateur(null, '198.51.100.40');
        $prospect->get($lien);
        self::assertSame(303, $prospect->post($lien, ['courriel' => 'info@platrerie-roux.ch'])->getStatusCode());
        // Le courriel « revenir dans votre démo », envoyé après la réponse.
        $prospect->c->apresReponse()->executer();
        return [$prospect, $id];
    }

    /** @return array<string, mixed> */
    private function compte(int $id): array
    {
        return BaseDeTest::ligne("SELECT courriel, demo, offres_max FROM artisan WHERE id = $id");
    }

    public function testLeRubanProposeLEssaiSurLesEcransDUneDemoDeLEquipe(): void
    {
        [$prospect] = $this->prospectDansSaDemo();
        foreach (['/', '/catalogue', '/clients'] as $ecran) {
            $page = (string) $prospect->get($ecran)->getContent();
            self::assertStringContainsString('href="/passer-a-l-essai"', $page, $ecran);
            self::assertStringContainsString('Passer à l\'essai', $page, $ecran);
            self::assertStringContainsString('7 jours restants', $page, $ecran);
        }

        // Sous le ruban, le plafond de la démo ne se dit pas « compte d'essai ».
        $offres = (string) $prospect->get('/')->getContent();
        self::assertStringContainsString('10 offres restantes', $offres);
        self::assertStringNotContainsString('Compte d\'essai', html_entity_decode($offres, ENT_QUOTES));

        // Un artisan ordinaire n'a ni ruban ni écran de passage.
        $b = $this->navigateur('b@exemple.ch', '198.51.100.41');
        self::assertStringNotContainsString('href="/passer-a-l-essai"', (string) $b->get('/catalogue')->getContent());
        self::assertSame(404, $b->get('/passer-a-l-essai')->getStatusCode());

        // Une démonstration partagée, sans fin ni créateur : son mot de passe est connu de plusieurs personnes.
        $motDePasse = Monde::conteneur()->comptes()->creer('partagee@exemple.ch', 'Démo partagée', true, 5, null, 'peintre');
        $partagee = $this->navigateur('partagee@exemple.ch', '198.51.100.42', $motDePasse);
        self::assertStringNotContainsString('href="/passer-a-l-essai"', (string) $partagee->get('/catalogue')->getContent());
        self::assertSame(404, $partagee->get('/passer-a-l-essai')->getStatusCode());
        $partagee->get('/plus');
        self::assertSame(404, $partagee->post('/passer-a-l-essai', ['courriel' => 'quelquun@exemple.ch'])->getStatusCode());
        self::assertSame(1, (int) BaseDeTest::ligne("SELECT demo FROM artisan WHERE courriel = 'partagee@exemple.ch'")['demo']);
    }

    /** Xavier, 2026-11-24 : « un mail au commercial, votre machin est arrivé à l'essai ». */
    public function testLaPersonneQuiAPresenteLaDemoApprendLePassage(): void
    {
        [$prospect, $id] = $this->prospectDansSaDemo();
        $avant = $this->dossiers->courriels();
        $prospect->get('/passer-a-l-essai');
        self::assertSame(303, $prospect->post('/passer-a-l-essai', ['courriel' => 'info@platrerie-roux.ch'])->getStatusCode());
        $prospect->c->apresReponse()->executer();
        $nouveaux = array_values(array_diff($this->dossiers->courriels(), $avant));
        self::assertCount(1, $nouveaux);
        $courriel = (string) file_get_contents($nouveaux[0]);
        self::assertStringContainsString("To: a@exemple.ch\r\n", $courriel);
        self::assertStringContainsString("passe à l'essai", $courriel);
        self::assertStringContainsString('info@platrerie-roux.ch', $courriel);
        self::assertStringContainsString("https://exemple.test/admin/artisans/$id", $courriel);
    }

    public function testUneDemoDEntrainementNePrevientPersonne(): void
    {
        [$prospect, $id] = $this->prospectDansSaDemo();
        BaseDeTest::pdo()->exec("UPDATE artisan SET entrainement = 1 WHERE id = $id");
        $avant = $this->dossiers->courriels();
        $prospect->get('/passer-a-l-essai');
        $prospect->post('/passer-a-l-essai', ['courriel' => 'info@platrerie-roux.ch']);
        $prospect->c->apresReponse()->executer();
        self::assertSame([], array_values(array_diff($this->dossiers->courriels(), $avant)));
    }

    public function testLePassageGardeLaSessionEtFaitChoisirUnMotDePasse(): void
    {
        [$prospect, $id] = $this->prospectDansSaDemo();
        $adresseDeDemo = $this->compte($id)['courriel'];
        $ecran = $prospect->get('/passer-a-l-essai');
        self::assertSame(200, $ecran->getStatusCode());
        self::assertStringContainsString('value="info@platrerie-roux.ch"', (string) $ecran->getContent());
        // L'écran du passage ne se propose pas lui-même.
        self::assertStringNotContainsString('href="/passer-a-l-essai"', (string) $ecran->getContent());

        // Un courriel vide, faux, ou déjà pris par un compte : refusé, la démo reste une démo.
        foreach (['', 'pas une adresse', 'b@exemple.ch'] as $faux) {
            $refus = $prospect->post('/passer-a-l-essai', ['courriel' => $faux]);
            self::assertSame(422, $refus->getStatusCode(), "courriel « $faux »");
            self::assertStringContainsString('role="alert"', (string) $refus->getContent());
        }
        self::assertSame([$adresseDeDemo, 1, 10], array_values($this->compte($id)));
        self::assertSame(2, (int) BaseDeTest::ligne("SELECT COUNT(*) AS n FROM offre WHERE artisan_id = $id")['n']);

        $passage = $prospect->post('/passer-a-l-essai', ['courriel' => ' Info@Platrerie-Roux.ch ']);
        self::assertSame(303, $passage->getStatusCode());
        // Ce site n'envoie pas de courriels : le mot de passe se choisit tout de suite.
        $suite = (string) $passage->headers->get('Location');
        self::assertMatchesRegularExpression('#^/mot-de-passe/[A-Za-z0-9_-]{43}$#', $suite);
        // Le seul courriel écrit est celui de l'entrée dans la démo ; son lien de retour ne vaut plus pour un essai.
        $courriels = $this->dossiers->courriels();
        self::assertCount(1, $courriels);
        self::assertSame(1, preg_match('#https://exemple\.test(/d/[A-Za-z0-9_-]{43})#', (string) file_get_contents($courriels[0]), $m));
        self::assertSame(410, $this->navigateur(null, '198.51.100.45')->get($m[1] ?? '')->getStatusCode());

        // Le compte est un essai, à l'adresse du prospect ; les offres d'exemple sont parties, le catalogue reste.
        self::assertSame(['info@platrerie-roux.ch', 0, 10], array_values($this->compte($id)));
        self::assertSame(0, (int) BaseDeTest::ligne("SELECT COUNT(*) AS n FROM offre WHERE artisan_id = $id")['n']);
        self::assertSame(12, (int) BaseDeTest::ligne("SELECT COUNT(*) AS n FROM produit WHERE artisan_id = $id")['n']);
        $journal = BaseDeTest::ligne('SELECT acteur_id, artisan_id, geste FROM journal_admin ORDER BY id DESC LIMIT 1');
        self::assertSame([$id, $id, 'passage_essai'], array_values($journal));

        // Le prospect n'a pas été déconnecté, et le ruban a disparu.
        $catalogue = $prospect->get('/catalogue');
        self::assertSame(200, $catalogue->getStatusCode());
        self::assertStringNotContainsString('href="/passer-a-l-essai"', (string) $catalogue->getContent());
        self::assertSame(404, $prospect->get('/passer-a-l-essai')->getStatusCode());

        // Il choisit son mot de passe, et revient plus tard avec son courriel.
        self::assertSame(200, $prospect->get($suite)->getStatusCode());
        self::assertSame(303, $prospect->post($suite, ['mot_de_passe' => 'un-mot-de-passe-long', 'confirmation' => 'un-mot-de-passe-long'])->getStatusCode());
        $this->navigateur('info@platrerie-roux.ch', '198.51.100.43', 'un-mot-de-passe-long');
        // Chez le commercial, la démo est devenue un essai, toujours amené par lui.
        $fiche = (string) $this->navigateur('a@exemple.ch', '198.51.100.44')->get("/admin/artisans/$id")->getContent();
        self::assertStringContainsString('Essai · info@platrerie-roux.ch', $fiche);
        // Twig écrit l'apostrophe d'une valeur sous sa forme échappée.
        self::assertStringContainsString('Passé à l&#039;essai', $fiche);
    }
}
