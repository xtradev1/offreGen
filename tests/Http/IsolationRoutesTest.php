<?php

declare(strict_types=1);

namespace OffreGen\Tests\Http;

use OffreGen\Http\Route;
use OffreGen\Tests\Outils\BaseDeTest;
use OffreGen\Tests\Outils\Jeu;
use OffreGen\Tests\Outils\Monde;
use OffreGen\Tests\Outils\Navigateur;
use PHPUnit\Framework\TestCase;

/**
 * L'artisan A n'atteint aucune donnée de B, par aucune route, en lecture comme en écriture.
 * Une nouvelle route artisan à paramètre sans cas ici fait échouer le test : on ne peut pas l'oublier.
 */
final class IsolationRoutesTest extends TestCase
{
    private Jeu $jeu;
    private Navigateur $a;
    /** @var array<string, int> */
    private array $ligne = [];

    protected function setUp(): void
    {
        $this->jeu = new Jeu();
        foreach (['a', 'b'] as $nom) {
            $this->ligne[$nom] = (int) BaseDeTest::ligne("SELECT id FROM ligne WHERE offre_id = {$this->jeu->offre[$nom]}")['id'];
        }
        $this->a = new Navigateur(Monde::conteneur());
        self::assertSame(303, $this->a->connecter('a@exemple.ch')->getStatusCode());
    }

    private function cheminLigne(string $nom, string $suite = ''): string
    {
        return '/offres/' . $this->jeu->offre[$nom] . '/lignes/' . $this->ligne[$nom] . $suite;
    }

    /**
     * Routes GET à paramètre : [chemin vers la ressource de A, chemin vers celle de B].
     *
     * @return array<string, array{string, string}>
     */
    private function cas(): array
    {
        return [
            'enregistrement' => ['/enregistrements/' . $this->jeu->uuid['a'], '/enregistrements/' . $this->jeu->uuid['b']],
            'enregistrement_etat' => ['/enregistrements/' . $this->jeu->uuid['a'] . '/etat', '/enregistrements/' . $this->jeu->uuid['b'] . '/etat'],
            'offre' => ['/offres/' . $this->jeu->offre['a'], '/offres/' . $this->jeu->offre['b']],
            'offre_entete' => ['/offres/' . $this->jeu->offre['a'] . '/modifier', '/offres/' . $this->jeu->offre['b'] . '/modifier'],
            'offre_document' => ['/offres/' . $this->jeu->offre['a'] . '/document', '/offres/' . $this->jeu->offre['b'] . '/document'],
            'offre_pdf' => ['/offres/' . $this->jeu->offre['a'] . '/pdf', '/offres/' . $this->jeu->offre['b'] . '/pdf'],
            'offre_ajout' => ['/offres/' . $this->jeu->offre['a'] . '/ajouter', '/offres/' . $this->jeu->offre['b'] . '/ajouter'],
            'ligne' => [$this->cheminLigne('a'), $this->cheminLigne('b')],
            'produit' => ['/catalogue/' . $this->jeu->produit['a'], '/catalogue/' . $this->jeu->produit['b']],
            'produit_suppression' => ['/catalogue/' . $this->jeu->produit['a'] . '/supprimer', '/catalogue/' . $this->jeu->produit['b'] . '/supprimer'],
        ];
    }

    /**
     * Routes POST à paramètre : [chemin de B, champs envoyés]. Chacune doit répondre 404 et ne rien
     * changer chez B. ligne_restaurer a son propre test : la ligne doit d'abord être supprimée.
     *
     * @return array<string, array{string, array<string, string>}>
     */
    private function casEcriture(): array
    {
        $produit = ['designation' => 'Volé', 'unite' => 'pce', 'prix' => '1', 'categorie' => 'X', 'nouvelle_categorie' => '', 'synonymes' => ''];
        return [
            'offre_entete_enregistrer' => ['/offres/' . $this->jeu->offre['b'] . '/modifier', ['titre' => 'Volée', 'client_nom' => 'A', 'client_adresse' => '']],
            'ligne_ajouter' => ['/offres/' . $this->jeu->offre['b'] . '/lignes', ['designation' => 'Volée', 'quantite' => '1', 'unite' => 'pce', 'prix' => '1']],
            'ligne_corriger' => [$this->cheminLigne('b'), ['quantite' => '99']],
            'ligne_juste' => [$this->cheminLigne('b', '/juste'), []],
            'ligne_supprimer' => [$this->cheminLigne('b', '/supprimer'), []],
            'ligne_restaurer' => [$this->cheminLigne('b', '/restaurer'), []],
            'produit_modifier' => ['/catalogue/' . $this->jeu->produit['b'], $produit],
            'produit_supprimer' => ['/catalogue/' . $this->jeu->produit['b'] . '/supprimer', []],
        ];
    }

    /** Tout ce que B possède, pour prouver qu'une requête de A n'y a rien changé. */
    private function donneesDeB(): string
    {
        $b = $this->jeu->b;
        $tout = [];
        foreach (['artisan' => "id = $b", 'offre' => "artisan_id = $b", 'ligne' => "artisan_id = $b", 'produit' => "artisan_id = $b"] as $table => $condition) {
            $requete = BaseDeTest::pdo()->query("SELECT * FROM $table WHERE $condition ORDER BY id");
            self::assertNotFalse($requete);
            $tout[$table] = $requete->fetchAll(\PDO::FETCH_ASSOC);
        }
        return (string) json_encode($tout);
    }

    public function testChaqueRouteAParametreAUnCas(): void
    {
        $sansCas = [];
        foreach ($this->a->c->noyau()->routes() as $route) {
            if ($route->droit === Route::ARTISAN && str_contains($route->chemin, '{')
                && !isset($this->cas()[$route->nom]) && !isset($this->casEcriture()[$route->nom])) {
                $sansCas[] = $route->nom;
            }
        }
        self::assertSame([], $sansCas, "Routes artisan à paramètre sans cas d'isolation");
    }

    public function testLaRessourceDeBRepond404EtCelleDeA200(): void
    {
        foreach ($this->cas() as $nom => [$cheminA, $cheminB]) {
            self::assertSame(200, $this->a->get($cheminA)->getStatusCode(), "$nom : A doit voir sa ressource");
            $reponse = $this->a->get($cheminB);
            self::assertSame(404, $reponse->getStatusCode(), "$nom : A voit la ressource de B");
            self::assertStringNotContainsString('Offre b', (string) $reponse->getContent());
            self::assertStringNotContainsString('Peinture murale b', (string) $reponse->getContent());
        }
    }

    public function testAucuneEcritureDeANeToucheB(): void
    {
        $avant = $this->donneesDeB();
        foreach ($this->casEcriture() as $nom => [$cheminB, $champs]) {
            $this->a->get('/reglages');
            self::assertSame(404, $this->a->post($cheminB, $champs)->getStatusCode(), "$nom : A écrit chez B");
            self::assertSame($avant, $this->donneesDeB(), "$nom : les données de B ont changé");
        }
    }

    public function testLaLigneDeBSupprimeeNePeutPasEtreRemiseParA(): void
    {
        BaseDeTest::pdo()->exec("UPDATE ligne SET supprimee_le = '2026-11-07 10:00:00' WHERE id = {$this->ligne['b']}");
        $avant = $this->donneesDeB();
        $this->a->get('/reglages');
        self::assertSame(404, $this->a->post($this->cheminLigne('b', '/restaurer'), [])->getStatusCode());
        self::assertSame($avant, $this->donneesDeB());
    }

    public function testMelangerLOffreDeAEtUneDonneeDeBRepond404(): void
    {
        $offreA = '/offres/' . $this->jeu->offre['a'];
        $avant = $this->donneesDeB();
        self::assertSame(404, $this->a->get("$offreA/lignes/{$this->ligne['b']}")->getStatusCode(), 'ligne de B sous l\'offre de A');
        $this->a->get('/reglages');
        self::assertSame(404, $this->a->post("$offreA/lignes/{$this->ligne['b']}", ['quantite' => '99'])->getStatusCode());
        $this->a->get('/reglages');
        self::assertSame(404, $this->a->post("$offreA/lignes", ['produit' => (string) $this->jeu->produit['b']])->getStatusCode(), 'produit de B ajouté à l\'offre de A');
        self::assertSame($avant, $this->donneesDeB());
        self::assertSame(1, BaseDeTest::compter('ligne', "offre_id = {$this->jeu->offre['a']}"), 'une ligne a été ajoutée chez A');
    }

    public function testLeCatalogueEtLesReglagesDeANeMontrentRienDeB(): void
    {
        $catalogue = (string) $this->a->get('/catalogue')->getContent();
        self::assertStringContainsString('Peinture murale a', $catalogue);
        self::assertStringNotContainsString('Peinture murale b', $catalogue);
        $ajout = (string) $this->a->get('/offres/' . $this->jeu->offre['a'] . '/ajouter')->getContent();
        self::assertStringNotContainsString('Peinture murale b', $ajout);
        $reglages = (string) $this->a->get('/reglages')->getContent();
        self::assertStringContainsString('Entreprise A', $reglages);
        self::assertStringNotContainsString('Entreprise B', $reglages);
    }

    public function testUnIdentifiantInventeRepond404CommeCeluiDeB(): void
    {
        self::assertSame(404, $this->a->get('/offres/999999999')->getStatusCode());
        self::assertSame(404, $this->a->get('/offres/abc')->getStatusCode());
        self::assertSame(404, $this->a->get('/enregistrements/pas-un-uuid')->getStatusCode());
        self::assertSame(404, $this->a->get('/enregistrements/99999999-9999-4999-8999-999999999999')->getStatusCode());
        self::assertSame(404, $this->a->get('/catalogue/99999999999')->getStatusCode());
        self::assertSame(404, $this->a->get('/offres/' . $this->jeu->offre['a'] . '/lignes/0')->getStatusCode());
    }

    public function testLaListeDeANeMontreRienDeB(): void
    {
        $page = (string) $this->a->get('/')->getContent();
        self::assertStringContainsString('Offre a', $page);
        self::assertStringNotContainsString('Offre b', $page);
        self::assertStringNotContainsString('visite-b', $page);
        self::assertStringNotContainsString('Entreprise B', $page);
    }
}
