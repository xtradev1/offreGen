<?php

declare(strict_types=1);

namespace OffreGen\Tests\Http;

use OffreGen\Tests\Outils\BaseDeTest;
use OffreGen\Tests\Outils\Jeu;
use OffreGen\Tests\Outils\Monde;
use OffreGen\Tests\Outils\Navigateur;
use PHPUnit\Framework\TestCase;

/**
 * La liste et la fiche des artisans, vues par l'équipe (décision R1 de Xavier, 2026-11-19) : le commercial ne voit
 * que les artisans qu'il a créés, « ça, c'est hyper important » ; le modérateur et l'administrateur les voient tous.
 * Offrir des offres suit la limite du rôle. Aucune page ne montre le contenu d'une offre (R2).
 */
final class ArtisansEquipeTest extends TestCase
{
    private Jeu $jeu;
    /** Artisan créé par Léo, l'autre commercial. */
    private int $d;
    /** @var array<string, string> mot de passe de chaque compte créé ici */
    private array $motsDePasse = [];

    protected function setUp(): void
    {
        // A est Nina, commerciale ; B est un artisan créé par elle, avec un plafond de 10 offres.
        $this->jeu = new Jeu();
        $pdo = BaseDeTest::pdo();
        $pdo->exec("UPDATE artisan SET role = 'commercial', entreprise = 'Nina Rey' WHERE id = {$this->jeu->a}");
        $pdo->exec("UPDATE artisan SET cree_par = {$this->jeu->a}, apporte_par = {$this->jeu->a}, offres_max = 10 WHERE id = {$this->jeu->b}");
        $leo = $this->creer('leo@exemple.ch', 'Léo Favre', false);
        $pdo->exec("UPDATE artisan SET role = 'commercial' WHERE id = $leo");
        $this->d = $this->creer('d@exemple.ch', 'Sanitaire Durand', true);
        $pdo->exec("UPDATE artisan SET cree_par = $leo, apporte_par = $leo WHERE id = {$this->d}");
        $pdo->exec('UPDATE artisan SET role = \'moderateur\' WHERE id = ' . $this->creer('mod@exemple.ch', 'Maud Modératrice', false));
        $pdo->exec('UPDATE artisan SET administrateur = 1 WHERE id = ' . $this->creer('chef@exemple.ch', 'Le Chef', false));
    }

    private function creer(string $courriel, string $nom, bool $demo): int
    {
        $this->motsDePasse[$courriel] = Monde::conteneur()->comptes()->creer($courriel, $nom, $demo, null, 10);
        return (int) BaseDeTest::ligne("SELECT id FROM artisan WHERE courriel = '$courriel'")['id'];
    }

    private function navigateur(string $courriel): Navigateur
    {
        static $ip = 10;
        $n = new Navigateur(Monde::conteneur(), '198.51.100.' . $ip++);
        self::assertSame(303, $n->connecter($courriel, $this->motsDePasse[$courriel] ?? Jeu::MOT_DE_PASSE)->getStatusCode());
        return $n;
    }

    /** @return array{int, int} plafond et offres offertes */
    private function offres(int $id): array
    {
        $l = BaseDeTest::ligne("SELECT offres_max, offres_offertes FROM artisan WHERE id = $id");
        return [(int) $l['offres_max'], (int) $l['offres_offertes']];
    }

    /** @return list<string> */
    private function colonne(string $sql): array
    {
        $requete = BaseDeTest::pdo()->query($sql);
        self::assertNotFalse($requete);
        return array_values(array_map(strval(...), $requete->fetchAll(\PDO::FETCH_COLUMN)));
    }

    private function offrir(Navigateur $n, int $id, int $ajout): int
    {
        // Le jeton vient d'une page qui porte un formulaire.
        $n->get('/admin/plus');
        return $n->post("/admin/artisans/$id/offres", ['ajout' => (string) $ajout])->getStatusCode();
    }

    public function testLeCommercialNeVoitQueSesArtisans(): void
    {
        $page = (string) $this->navigateur('a@exemple.ch')->get('/admin/artisans')->getContent();
        self::assertStringContainsString('Mes clients', $page);
        self::assertStringContainsString('Entreprise B', $page);
        self::assertStringNotContainsString('Sanitaire Durand', $page);
        // L'équipe n'est pas une liste d'artisans : ni l'autre commercial, ni la modératrice, ni l'administrateur.
        foreach (['Léo Favre', 'Maud Modératrice', 'Le Chef'] as $membre) {
            self::assertStringNotContainsString($membre, $page);
        }
    }

    public function testLArtisanDUnAutreNExistePasPourLeCommercial(): void
    {
        $nina = $this->navigateur('a@exemple.ch');
        self::assertSame(200, $nina->get("/admin/artisans/{$this->jeu->b}")->getStatusCode());
        $leo = (int) BaseDeTest::ligne("SELECT id FROM artisan WHERE courriel = 'leo@exemple.ch'")['id'];
        foreach ([$this->d, $leo, $this->jeu->a, 999999] as $id) {
            self::assertSame(404, $nina->get("/admin/artisans/$id")->getStatusCode(), "fiche $id");
            self::assertSame(404, $this->offrir($nina, $id, 2), "offres $id");
        }
        self::assertSame([10, 0], $this->offres($this->d));
    }

    public function testLeModerateurEtLAdministrateurVoientTousLesArtisans(): void
    {
        foreach (['mod@exemple.ch', 'chef@exemple.ch'] as $courriel) {
            $n = $this->navigateur($courriel);
            $page = (string) $n->get('/admin/artisans')->getContent();
            self::assertStringContainsString('Entreprise B', $page, $courriel);
            self::assertStringContainsString('Sanitaire Durand', $page, $courriel);
            self::assertStringContainsString('Léo Favre', $page, "$courriel : le créateur est nommé");
            self::assertSame(200, $n->get("/admin/artisans/{$this->d}")->getStatusCode(), $courriel);
        }
    }

    public function testOffrirDesOffresSuitLaLimiteDuRole(): void
    {
        // Le commercial : 10 de plus sur ses artisans, pas une de plus.
        $nina = $this->navigateur('a@exemple.ch');
        self::assertSame(303, $this->offrir($nina, $this->jeu->b, 10));
        self::assertSame([20, 10], $this->offres($this->jeu->b));
        self::assertSame(422, $this->offrir($nina, $this->jeu->b, 2));
        self::assertSame([20, 10], $this->offres($this->jeu->b));

        // Le modérateur : même limite, sur tous les artisans.
        $mod = $this->navigateur('mod@exemple.ch');
        $deuxFoisCinq = [$this->offrir($mod, $this->d, 5), $this->offrir($mod, $this->d, 5)];
        self::assertSame([303, 303], $deuxFoisCinq);
        self::assertSame(422, $this->offrir($mod, $this->d, 2));
        self::assertSame([20, 10], $this->offres($this->d));

        // L'administrateur : sans limite.
        $chef = $this->navigateur('chef@exemple.ch');
        self::assertSame(303, $this->offrir($chef, $this->d, 10));
        self::assertSame([30, 20], $this->offres($this->d));

        $journal = BaseDeTest::ligne("SELECT COUNT(*) AS n, MAX(detail) AS detail FROM journal_admin WHERE geste = 'offres_ajoutees' AND artisan_id = {$this->d}");
        self::assertSame(3, (int) $journal['n']);
    }

    public function testUnAjoutInconnuOuUnCompteSansPlafondEstRefuse(): void
    {
        $chef = $this->navigateur('chef@exemple.ch');
        foreach ([3, 0, -5, 1000] as $ajout) {
            self::assertSame(422, $this->offrir($chef, $this->d, $ajout), "ajout $ajout");
        }
        BaseDeTest::pdo()->exec("UPDATE artisan SET offres_max = NULL WHERE id = {$this->jeu->b}");
        self::assertSame(422, $this->offrir($chef, $this->jeu->b, 5));
        self::assertSame([10, 0], $this->offres($this->d));
        // Personne n'ajoute d'offres à son propre compte : il n'est pas dans la liste des artisans.
        $lui = (int) BaseDeTest::ligne("SELECT id FROM artisan WHERE courriel = 'chef@exemple.ch'")['id'];
        self::assertSame(404, $this->offrir($chef, $lui, 5));
    }

    public function testLesFiltresEtLaRecherche(): void
    {
        $chef = $this->navigateur('chef@exemple.ch');
        $demos = (string) $chef->get('/admin/artisans?type=demos')->getContent();
        self::assertStringContainsString('Sanitaire Durand', $demos);
        self::assertStringNotContainsString('Entreprise B', $demos);
        // Un essai : un compte avec un plafond d'offres (Xavier, 2026-11-19 : « il faudra rajouter aussi les essais »).
        // Un compte : sans plafond.
        $plein = $this->creer('plein@exemple.ch', 'Toiture Pleine', false);
        BaseDeTest::pdo()->exec("UPDATE artisan SET offres_max = NULL WHERE id = $plein");
        $essais = (string) $chef->get('/admin/artisans?type=essais')->getContent();
        self::assertStringContainsString('Entreprise B <i class="badge role">Essai</i>', $essais);
        self::assertStringNotContainsString('Sanitaire Durand', $essais);
        self::assertStringNotContainsString('Toiture Pleine', $essais);
        $comptes = (string) $chef->get('/admin/artisans?type=comptes')->getContent();
        self::assertStringContainsString('Toiture Pleine <i class="badge role">Compte</i>', $comptes);
        self::assertStringNotContainsString('Entreprise B', $comptes);
        self::assertStringNotContainsString('Sanitaire Durand', $comptes);
        self::assertStringContainsString('Essai · ', (string) $chef->get("/admin/artisans/{$this->jeu->b}")->getContent());
        $cherche = (string) $chef->get('/admin/artisans?q=sanit')->getContent();
        self::assertStringContainsString('Sanitaire Durand', $cherche);
        self::assertStringNotContainsString('Entreprise B', $cherche);
        // Un caractère joker se cherche tel quel : il ne ramène pas toute la liste.
        $joker = (string) $chef->get('/admin/artisans?q=%25')->getContent();
        self::assertStringNotContainsString('Sanitaire Durand', $joker);
        self::assertStringContainsString('Aucun client', $joker);
    }

    public function testLaListeSeFiltreParCeluiQuiAAmeneLesArtisans(): void
    {
        $leo = (int) BaseDeTest::ligne("SELECT id FROM artisan WHERE courriel = 'leo@exemple.ch'")['id'];
        $page = (string) $this->navigateur('chef@exemple.ch')->get("/admin/artisans?par=$leo")->getContent();
        self::assertStringContainsString('1 client amené par Léo Favre', $page);
        self::assertStringContainsString('Sanitaire Durand', $page);
        self::assertStringNotContainsString('Entreprise B', $page);
        // Le commercial ne sort pas de ses artisans en changeant l'adresse.
        $nina = (string) $this->navigateur('a@exemple.ch')->get("/admin/artisans?par=$leo")->getContent();
        self::assertStringContainsString('Entreprise B', $nina);
        self::assertStringNotContainsString('Sanitaire Durand', $nina);
        self::assertStringNotContainsString('Léo Favre', $nina);
    }

    public function testLaFicheNeMontreAucunContenuDOffre(): void
    {
        $fiche = (string) $this->navigateur('mod@exemple.ch')->get("/admin/artisans/{$this->jeu->b}")->getContent();
        self::assertStringContainsString('Entreprise B', $fiche);
        self::assertStringContainsString('par Nina Rey', $fiche);
        $secrets = [...$this->colonne("SELECT designation FROM produit WHERE artisan_id = {$this->jeu->b}"), ...$this->colonne("SELECT nom FROM client WHERE artisan_id = {$this->jeu->b}")];
        // Le contrôle lit bien quelque chose : le compte B a un produit et un client.
        self::assertGreaterThanOrEqual(2, count($secrets));
        foreach ($secrets as $secret) {
            self::assertStringNotContainsString($secret, $fiche);
        }
        self::assertStringContainsString('no-store', (string) $this->navigateur('mod@exemple.ch')->get("/admin/artisans/{$this->jeu->b}")->headers->get('Cache-Control'));
    }
}
