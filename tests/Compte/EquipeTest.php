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
 * L'équipe (décisions R1, R4 et E1 de Xavier, 2026-11-19 ; écran refait d'après la maquette v7, L1 à L4) :
 * l'administrateur crée un commercial ou un modérateur, change son rôle et ses offres, lui renvoie un lien d'accès,
 * le retire. Le droit administrateur, lui, ne se donne que par commande.
 */
final class EquipeTest extends TestCase
{
    private const LIEN = '#https://exemple\.test(/mot-de-passe/[A-Za-z0-9_-]{43})#';

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
        return BaseDeTest::ligne("SELECT id, entreprise, role, administrateur, cree_par, apporte_par, offres_max FROM artisan WHERE courriel = '$courriel'");
    }

    /** @param array<string, string> $champs */
    private function inviter(Navigateur $n, array $champs): \Symfony\Component\HttpFoundation\Response
    {
        $n->get('/admin/equipe/nouveau');
        return $n->post('/admin/equipe', $champs + ['nom' => 'Nina Rey', 'courriel' => 'nina@exemple.ch', 'role' => 'commercial', 'offres' => '10']);
    }

    /** Nina est invitée ; l'artisan B est celui qu'elle a amené et qu'elle suit. */
    private function ninaEtSonArtisan(Navigateur $admin): int
    {
        $this->inviter($admin, []);
        $nina = (int) $this->compte('nina@exemple.ch')['id'];
        BaseDeTest::pdo()->exec("UPDATE artisan SET cree_par = $nina, apporte_par = $nina WHERE id = {$this->jeu->b}");
        return $nina;
    }

    /** @return list<string> */
    private function gestes(int $compte): array
    {
        $requete = BaseDeTest::pdo()->query("SELECT geste FROM journal_admin WHERE artisan_id = $compte ORDER BY id");
        self::assertNotFalse($requete);
        return array_values(array_map(strval(...), $requete->fetchAll(\PDO::FETCH_COLUMN)));
    }

    public function testLAdministrateurCreeUnCommercialQuiEntreParSonLien(): void
    {
        $admin = $this->navigateur();
        $reponse = $this->inviter($admin, []);
        self::assertSame(200, $reponse->getStatusCode());
        $compte = $this->compte('nina@exemple.ch');
        self::assertSame(['Nina Rey', 'commercial', 0, $this->jeu->a, 10], [$compte['entreprise'], $compte['role'], $compte['administrateur'], $compte['cree_par'], $compte['offres_max']]);

        // Ce site n'envoie pas de courriels : le lien d'accès est sur la page, et rien n'est écrit ailleurs.
        self::assertSame(1, preg_match(self::LIEN, (string) $reponse->getContent(), $m));
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

    public function testSeulDansLEquipeLEcranExpliqueLesDeuxRoles(): void
    {
        $admin = $this->navigateur();
        $page = (string) $admin->get('/admin/equipe')->getContent();
        foreach (['Vous êtes seul pour l\'instant.', 'Vous pouvez ajouter', 'Un commercial', 'Un modérateur', 'Ajouter un membre', 'Entreprise A'] as $texte) {
            self::assertStringContainsString($texte, $page, $texte);
        }
        // Dès qu'il y a quelqu'un, l'explication laisse la place à l'équipe.
        $this->inviter($admin, []);
        self::assertStringNotContainsString('Vous pouvez ajouter', (string) $admin->get('/admin/equipe')->getContent());
    }

    public function testLaListeRangeLEquipeParRoleEtCompteLesArtisansAmenes(): void
    {
        $admin = $this->navigateur();
        $nina = $this->ninaEtSonArtisan($admin);
        // Tant que l'accès n'est pas ouvert, la ligne dit la date de l'invitation ; ici, Nina a choisi son mot de passe.
        BaseDeTest::pdo()->exec("UPDATE artisan SET acces_en_attente = 0 WHERE id = $nina");
        $page = (string) $admin->get('/admin/equipe')->getContent();
        foreach (['Vous et 1 personne', 'Ils créent des démos et ne voient que leurs artisans.', 'Nina Rey', '1 artisan ·', 'Entreprise A', 'Ajouter un membre'] as $texte) {
            self::assertStringContainsString($texte, $page, $texte);
        }
        // Aucun modérateur : pas de groupe vide.
        self::assertStringNotContainsString('Ils voient tous les artisans', $page);
        // Un administrateur ne se modifie pas ici : aucune fiche pour lui.
        self::assertStringNotContainsString('href="/admin/equipe/' . $this->jeu->a . '"', $page);
        self::assertStringContainsString('href="/admin/equipe/' . $nina . '"', $page);
    }

    public function testUnAccesPasEncoreOuvertSeDitJusquAuChoixDuMotDePasse(): void
    {
        $admin = $this->navigateur();
        $reponse = $this->inviter($admin, []);
        self::assertSame(1, preg_match(self::LIEN, (string) $reponse->getContent(), $m));
        $lien = $m[1] ?? '';
        self::assertStringContainsString('Accès pas encore ouvert', (string) $admin->get('/admin/equipe')->getContent());

        $nina = new Navigateur(Monde::conteneur(null, $this->dossiers->env()), '198.51.100.7');
        $nina->get($lien);
        self::assertSame(303, $nina->post($lien, ['mot_de_passe' => 'un-mot-de-passe-long', 'confirmation' => 'un-mot-de-passe-long'])->getStatusCode());
        self::assertStringNotContainsString('Accès pas encore ouvert', (string) $admin->get('/admin/equipe')->getContent());

        // Un compte qui existait déjà a son mot de passe : rien à attendre.
        $this->inviter($admin, ['courriel' => 'b@exemple.ch', 'role' => 'moderateur']);
        $page = (string) $admin->get('/admin/equipe')->getContent();
        self::assertStringContainsString('Entreprise B', $page);
        self::assertStringNotContainsString('Accès pas encore ouvert', $page);
    }

    public function testUneSaisieFausseEstRefuseeSansCreerDeCompte(): void
    {
        $admin = $this->navigateur();
        // Le formulaire porte son bouton (sur PC, il est sous les champs) et le compteur de la fiche.
        $formulaire = (string) $admin->get('/admin/equipe/nouveau')->getContent();
        self::assertSame(2, substr_count($formulaire, "Créer et envoyer l'accès</button>"));
        self::assertStringContainsString('data-pas="1"', $formulaire);
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

    public function testLaFicheEnregistreLeRoleEtLesOffres(): void
    {
        $admin = $this->navigateur();
        $nina = $this->ninaEtSonArtisan($admin);
        $fiche = (string) $admin->get("/admin/equipe/$nina")->getContent();
        foreach (['Nina Rey', 'nina@exemple.ch', 'Enregistrer', 'Ses artisans', '1 artisan amené', 'Renvoyer un lien d\'accès', 'Retirer de l\'équipe', 'href="/admin/artisans?par=' . $nina . '"'] as $texte) {
            self::assertStringContainsString($texte, $fiche, $texte);
        }

        self::assertSame(303, $admin->post("/admin/equipe/$nina", ['role' => 'moderateur', 'offres' => '25'])->getStatusCode());
        $compte = $this->compte('nina@exemple.ch');
        self::assertSame(['moderateur', 25], [$compte['role'], $compte['offres_max']]);
        self::assertSame(['membre_cree', 'role_change', 'offres_membre'], $this->gestes($nina));

        // Enregistrer sans rien changer n'écrit rien au journal.
        $admin->get("/admin/equipe/$nina");
        self::assertSame(303, $admin->post("/admin/equipe/$nina", ['role' => 'moderateur', 'offres' => '25'])->getStatusCode());
        self::assertCount(3, $this->gestes($nina));

        // Un nombre hors bornes est refusé, et rien ne change.
        foreach (['0', '501', 'beaucoup', ''] as $faux) {
            $admin->get("/admin/equipe/$nina");
            $reponse = $admin->post("/admin/equipe/$nina", ['role' => 'commercial', 'offres' => $faux]);
            self::assertSame(422, $reponse->getStatusCode(), "offres « $faux »");
            self::assertStringContainsString('role="alert"', (string) $reponse->getContent());
        }
        // Le rôle administrateur ne se donne pas ici.
        $admin->get("/admin/equipe/$nina");
        self::assertSame(404, $admin->post("/admin/equipe/$nina", ['role' => 'administrateur', 'offres' => '25'])->getStatusCode());
        $compte = $this->compte('nina@exemple.ch');
        self::assertSame(['moderateur', 25], [$compte['role'], $compte['offres_max']]);
    }

    public function testRenvoyerUnLienDAccesLeMontreSurUnSiteSansCourriels(): void
    {
        $admin = $this->navigateur();
        $nina = $this->ninaEtSonArtisan($admin);
        $admin->get("/admin/equipe/$nina");
        $reponse = $admin->post("/admin/equipe/$nina/acces", []);
        self::assertSame(200, $reponse->getStatusCode());
        self::assertSame(1, preg_match(self::LIEN, (string) $reponse->getContent(), $m));
        self::assertStringContainsString('no-store', (string) $reponse->headers->get('Cache-Control'));
        self::assertCount(0, $this->dossiers->courriels());
        $visiteur = new Navigateur(Monde::conteneur(null, $this->dossiers->env()), '198.51.100.7');
        self::assertSame(200, $visiteur->get($m[1] ?? '')->getStatusCode());
        self::assertSame(['membre_cree', 'acces_renvoye'], $this->gestes($nina));

        // Ni pour son propre compte, ni pour un compte hors de l'équipe.
        foreach ([$this->jeu->a, $this->jeu->b, 999999] as $id) {
            $admin->get('/admin/plus');
            self::assertSame(404, $admin->post("/admin/equipe/$id/acces", [])->getStatusCode(), "compte $id");
        }
    }

    public function testChangerLeRolePuisRetirerRendSesArtisansALAdministrateur(): void
    {
        $admin = $this->navigateur();
        $nina = $this->ninaEtSonArtisan($admin);

        $admin->get("/admin/equipe/$nina");
        self::assertSame(303, $admin->post("/admin/equipe/$nina/role", ['role' => 'moderateur'])->getStatusCode());
        self::assertSame('moderateur', $this->compte('nina@exemple.ch')['role']);

        $admin->get("/admin/equipe/$nina");
        self::assertSame(303, $admin->post("/admin/equipe/$nina/role", ['role' => ''])->getStatusCode());
        self::assertNull($this->compte('nina@exemple.ch')['role']);
        self::assertSame(404, $admin->get("/admin/equipe/$nina")->getStatusCode());
        $journal = BaseDeTest::ligne("SELECT geste, detail FROM journal_admin ORDER BY id DESC LIMIT 1");
        self::assertSame(['membre_retire', '1 artisan repris'], array_values($journal));

        // L'administrateur suit l'artisan ; Nina reste notée comme celle qui l'a amené (commission, Xavier, 2026-11-19).
        $b = $this->compte('b@exemple.ch');
        self::assertSame([$this->jeu->a, $nina], [$b['cree_par'], $b['apporte_par']]);
        $fiche = (string) $admin->get("/admin/artisans/{$this->jeu->b}")->getContent();
        self::assertStringContainsString('amené par Nina Rey', $fiche);
        self::assertStringContainsString('suivi par Entreprise A', $fiche);
        self::assertStringContainsString('Entreprise B', (string) $admin->get("/admin/artisans?par=$nina")->getContent());
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
            $admin->get('/admin/plus');
            self::assertSame(404, $admin->post("/admin/equipe/$id", ['role' => 'commercial', 'offres' => '10'])->getStatusCode());
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
        foreach (["/admin/equipe/{$this->jeu->b}/role", "/admin/equipe/{$this->jeu->b}", "/admin/equipe/{$this->jeu->b}/acces"] as $chemin) {
            $b->get('/admin/plus');
            self::assertSame(404, $b->post($chemin, ['role' => '', 'offres' => '400'])->getStatusCode(), $chemin);
        }
        self::assertSame(2, (int) BaseDeTest::ligne('SELECT COUNT(*) AS n FROM artisan')['n']);
        self::assertSame('moderateur', $this->compte('b@exemple.ch')['role']);
    }
}
