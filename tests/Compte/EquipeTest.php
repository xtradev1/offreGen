<?php

declare(strict_types=1);

namespace OffreGen\Tests\Compte;

use OffreGen\Tests\Outils\BaseDeTest;
use OffreGen\Tests\Outils\Dossiers;
use OffreGen\Tests\Outils\Jeu;
use OffreGen\Tests\Outils\Monde;
use OffreGen\Tests\Outils\Navigateur;
use PHPUnit\Framework\TestCase;

/**
 * L'équipe (décisions R1, R4 et E1 de Xavier, 2026-11-19) : l'administrateur crée un commercial ou un modérateur,
 * change son rôle, le retire. Le droit administrateur, lui, ne se donne que par commande.
 */
final class EquipeTest extends TestCase
{
    private Jeu $jeu;
    private Dossiers $dossiers;

    protected function setUp(): void
    {
        $this->jeu = new Jeu();
        $this->dossiers = new Dossiers();
        BaseDeTest::pdo()->exec("UPDATE artisan SET administrateur = 1 WHERE id = {$this->jeu->a}");
    }

    protected function tearDown(): void
    {
        $this->dossiers->effacer();
    }

    private function navigateur(string $courriel = 'a@exemple.ch', string $ip = '127.0.0.1'): Navigateur
    {
        $n = new Navigateur(Monde::conteneur(null, $this->dossiers->env() + ['APP_URL' => 'https://exemple.test']), $ip);
        $n->connecter($courriel);
        return $n;
    }

    /** @return array<string, mixed> */
    private function compte(string $courriel): array
    {
        return BaseDeTest::ligne("SELECT id, entreprise, role, administrateur, cree_par, offres_max FROM artisan WHERE courriel = '$courriel'");
    }

    /** @param array<string, string> $champs */
    private function inviter(Navigateur $n, array $champs): \Symfony\Component\HttpFoundation\Response
    {
        $n->get('/admin/equipe/nouveau');
        return $n->post('/admin/equipe', $champs + ['nom' => 'Nina Rey', 'courriel' => 'nina@exemple.ch', 'role' => 'commercial', 'offres' => '10']);
    }

    public function testLAdministrateurCreeUnCommercialQuiEntreParSonLien(): void
    {
        $admin = $this->navigateur();
        $reponse = $this->inviter($admin, []);
        self::assertSame(200, $reponse->getStatusCode());
        $compte = $this->compte('nina@exemple.ch');
        self::assertSame(['Nina Rey', 'commercial', 0, $this->jeu->a, 10], [$compte['entreprise'], $compte['role'], $compte['administrateur'], $compte['cree_par'], $compte['offres_max']]);

        // Ce site n'envoie pas de courriels : le lien d'accès est sur la page, et rien n'est écrit ailleurs.
        self::assertSame(1, preg_match('#https://exemple\.test(/mot-de-passe/[A-Za-z0-9_-]{43})#', (string) $reponse->getContent(), $m));
        self::assertStringContainsString('no-store', (string) $reponse->headers->get('Cache-Control'));
        self::assertCount(0, $this->dossiers->courriels());
        $lien = $m[1] ?? '';

        $nina = new Navigateur(Monde::conteneur(null, $this->dossiers->env()), '198.51.100.7');
        self::assertSame(200, $nina->get($lien)->getStatusCode());
        self::assertSame(303, $nina->post($lien, ['mot_de_passe' => 'un-mot-de-passe-long', 'confirmation' => 'un-mot-de-passe-long'])->getStatusCode());
        self::assertSame(200, $nina->get('/admin/suivi')->getStatusCode());
        self::assertSame(404, $nina->get('/admin/equipe')->getStatusCode());

        $journal = BaseDeTest::ligne("SELECT acteur_id, artisan_id, geste, detail FROM journal_admin ORDER BY id DESC LIMIT 1");
        self::assertSame([$this->jeu->a, $compte['id'], 'membre_cree', 'commercial'], array_values($journal));
    }

    public function testLaListeMontreLEquipeEtSesArtisans(): void
    {
        $admin = $this->navigateur();
        $this->inviter($admin, []);
        $nina = $this->compte('nina@exemple.ch')['id'];
        BaseDeTest::pdo()->exec("UPDATE artisan SET cree_par = $nina WHERE id = {$this->jeu->b}");
        $page = (string) $admin->get('/admin/equipe')->getContent();
        self::assertStringContainsString('Nina Rey', $page);
        self::assertStringContainsString('1 artisan', $page);
        self::assertStringContainsString('Entreprise A', $page);
        // Un administrateur ne se modifie pas ici : aucune fiche pour lui.
        self::assertStringNotContainsString('href="/admin/equipe/' . $this->jeu->a . '"', $page);
        self::assertStringContainsString('href="/admin/equipe/' . $nina . '"', $page);
    }

    public function testUneSaisieFausseEstRefuseeSansCreerDeCompte(): void
    {
        $admin = $this->navigateur();
        foreach ([['role' => 'administrateur'], ['role' => 'chef'], ['courriel' => 'pas une adresse'], ['nom' => ' '], ['offres' => '0'], ['offres' => '501']] as $faute) {
            $reponse = $this->inviter($admin, $faute);
            self::assertSame(422, $reponse->getStatusCode(), (string) json_encode($faute));
            self::assertStringContainsString('role="alert"', (string) $reponse->getContent());
        }
        self::assertSame(2, (int) BaseDeTest::ligne('SELECT COUNT(*) AS n FROM artisan')['n']);
    }

    public function testUnCompteExistantRejointLEquipeSansChangerDeMotDePasse(): void
    {
        $admin = $this->navigateur();
        $reponse = $this->inviter($admin, ['courriel' => 'B@exemple.ch', 'role' => 'moderateur']);
        self::assertSame(200, $reponse->getStatusCode());
        self::assertStringNotContainsString('/mot-de-passe/', (string) $reponse->getContent());
        $b = $this->compte('b@exemple.ch');
        self::assertSame(['Entreprise B', 'moderateur'], [$b['entreprise'], $b['role']]);
        self::assertSame(200, $this->navigateur('b@exemple.ch', '198.51.100.8')->get('/admin/a-aider')->getStatusCode());
        // Une seconde invitation ne change rien.
        self::assertSame(422, $this->inviter($admin, ['courriel' => 'b@exemple.ch'])->getStatusCode());
    }

    public function testChangerLeRolePuisRetirerRendSesArtisansALAdministrateur(): void
    {
        $admin = $this->navigateur();
        $this->inviter($admin, []);
        $nina = (int) $this->compte('nina@exemple.ch')['id'];
        BaseDeTest::pdo()->exec("UPDATE artisan SET cree_par = $nina WHERE id = {$this->jeu->b}");

        $admin->get("/admin/equipe/$nina");
        self::assertSame(303, $admin->post("/admin/equipe/$nina/role", ['role' => 'moderateur'])->getStatusCode());
        self::assertSame('moderateur', $this->compte('nina@exemple.ch')['role']);

        $admin->get("/admin/equipe/$nina");
        self::assertSame(303, $admin->post("/admin/equipe/$nina/role", ['role' => ''])->getStatusCode());
        self::assertNull($this->compte('nina@exemple.ch')['role']);
        self::assertSame($this->jeu->a, $this->compte('b@exemple.ch')['cree_par']);
        self::assertSame(404, $admin->get("/admin/equipe/$nina")->getStatusCode());
        $journal = BaseDeTest::ligne("SELECT geste, detail FROM journal_admin ORDER BY id DESC LIMIT 1");
        self::assertSame(['membre_retire', '1 artisan repris'], array_values($journal));
    }

    public function testNiSonPropreRoleNiCeluiDUnAdministrateurNeSeChangentIci(): void
    {
        $admin = $this->navigateur();
        BaseDeTest::pdo()->exec("UPDATE artisan SET administrateur = 1 WHERE id = {$this->jeu->b}");
        foreach ([$this->jeu->a, $this->jeu->b, 999999] as $id) {
            self::assertSame(404, $admin->get("/admin/equipe/$id")->getStatusCode());
            // Le jeton vient d'une page qui porte un formulaire.
            $admin->get('/admin/plus');
            self::assertSame(404, $admin->post("/admin/equipe/$id/role", ['role' => 'commercial'])->getStatusCode());
        }
        self::assertSame(1, $this->compte('b@exemple.ch')['administrateur']);
        // Le rôle « administrateur » ne se donne pas non plus à un membre.
        $this->inviter($admin, []);
        $nina = (int) $this->compte('nina@exemple.ch')['id'];
        $admin->get("/admin/equipe/$nina");
        self::assertSame(404, $admin->post("/admin/equipe/$nina/role", ['role' => 'administrateur'])->getStatusCode());
        self::assertSame('commercial', $this->compte('nina@exemple.ch')['role']);
    }

    public function testUnModerateurNeTouchePasALEquipe(): void
    {
        BaseDeTest::pdo()->exec("UPDATE artisan SET role = 'moderateur' WHERE id = {$this->jeu->b}");
        $b = $this->navigateur('b@exemple.ch', '198.51.100.9');
        $b->get('/admin/plus');
        self::assertSame(404, $b->post('/admin/equipe', ['nom' => 'X', 'courriel' => 'x@exemple.ch', 'role' => 'commercial', 'offres' => '10'])->getStatusCode());
        $b->get('/admin/plus');
        self::assertSame(404, $b->post("/admin/equipe/{$this->jeu->b}/role", ['role' => ''])->getStatusCode());
        self::assertSame(2, (int) BaseDeTest::ligne('SELECT COUNT(*) AS n FROM artisan')['n']);
    }
}
