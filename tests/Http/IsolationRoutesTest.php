<?php

declare(strict_types=1);

namespace OffreGen\Tests\Http;

use OffreGen\Donnees\DepotFactures;
use OffreGen\Donnees\Portee;
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
    /** @var array<string, int> */
    private array $facture = [];
    /** @var array<string, int> */
    private array $factureLigne = [];
    /** @var array<string, int> */
    private array $factureEmise = [];

    protected function setUp(): void
    {
        $this->jeu = new Jeu();
        foreach (['a', 'b'] as $nom) {
            $this->ligne[$nom] = (int) BaseDeTest::ligne("SELECT id FROM ligne WHERE offre_id = {$this->jeu->offre[$nom]}")['id'];
            // Un brouillon de facture par artisan, avec la ligne de son offre (sous-chantier 18).
            $this->facture[$nom] = (new DepotFactures($this->jeu->base, new Portee($nom === 'a' ? $this->jeu->a : $this->jeu->b)))->creer([
                'offre_id' => $this->jeu->offre[$nom], 'numero' => '2026-001', 'genre' => 'finale', 'acompte_rang' => null, 'acompte_nombre' => null,
                'acompte_pourcent' => null, 'date_facture' => new \DateTimeImmutable('2026-11-07'), 'echeance' => new \DateTimeImmutable('2026-12-07'),
                'titre' => "Offre $nom", 'client_nom' => "Client $nom", 'client_adresse' => null,
            ], [['designation' => "Peinture murale $nom", 'quantite_milliemes' => 12500, 'unite' => 'm²', 'prix_centimes' => 1850, 'categorie' => 'Peinture', 'produit_id' => null]], $this->jeu->maintenant);
            $this->factureLigne[$nom] = (int) BaseDeTest::ligne("SELECT id FROM facture_ligne WHERE facture_id = {$this->facture[$nom]}")['id'];
            // Et une facture émise, avec son PDF : celles qui se lisent en PDF et s'annulent.
            $artisan = $nom === 'a' ? $this->jeu->a : $this->jeu->b;
            BaseDeTest::pdo()->exec("INSERT INTO facture (artisan_id, offre_id, numero, genre, statut, date_facture, echeance, titre, client_nom, tva_pour_dix_mille, arrondi_cinq, ht, tva, arrondi, ttc, deja_facture, a_payer, cree_le, emise_le)
                VALUES ($artisan, {$this->jeu->offre[$nom]}, '2026-002', 'finale', 'emise', '2026-11-07', '2026-12-07', 'Offre $nom', 'Client $nom', 810, 1, 100, 8, 2, 110, 0, 110, NOW(), NOW())");
            $this->factureEmise[$nom] = (int) BaseDeTest::pdo()->lastInsertId();
            BaseDeTest::pdo()->exec("INSERT INTO facture_pdf (facture_id, artisan_id, contenu, cree_le) VALUES ({$this->factureEmise[$nom]}, $artisan, '%PDF-$nom', NOW())");
        }
        $this->a = new Navigateur(Monde::conteneur());
        self::assertSame(303, $this->a->connecter('a@exemple.ch')->getStatusCode());
    }

    private function cheminFacture(string $nom, string $suite = ''): string
    {
        return '/factures/' . $this->facture[$nom] . $suite;
    }

    private function cheminLigneFacture(string $nom, string $suite = ''): string
    {
        return $this->cheminFacture($nom, '/lignes/' . $this->factureLigne[$nom] . $suite);
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
            'client' => ['/clients/' . $this->jeu->client['a'], '/clients/' . $this->jeu->client['b']],
            'offre_client' => ['/offres/' . $this->jeu->offre['a'] . '/client', '/offres/' . $this->jeu->offre['b'] . '/client'],
            'offre_facturer' => ['/offres/' . $this->jeu->offre['a'] . '/facturer', '/offres/' . $this->jeu->offre['b'] . '/facturer'],
            'facture' => [$this->cheminFacture('a'), $this->cheminFacture('b')],
            'facture_document' => [$this->cheminFacture('a', '/document'), $this->cheminFacture('b', '/document')],
            'facture_emission' => [$this->cheminFacture('a', '/emettre'), $this->cheminFacture('b', '/emettre')],
            'facture_pdf' => ['/factures/' . $this->factureEmise['a'] . '/pdf', '/factures/' . $this->factureEmise['b'] . '/pdf'],
            'facture_annulation' => ['/factures/' . $this->factureEmise['a'] . '/annuler', '/factures/' . $this->factureEmise['b'] . '/annuler'],
            'facture_entete' => [$this->cheminFacture('a', '/modifier'), $this->cheminFacture('b', '/modifier')],
            'facture_ajout' => [$this->cheminFacture('a', '/ajouter'), $this->cheminFacture('b', '/ajouter')],
            'facture_ligne' => [$this->cheminLigneFacture('a'), $this->cheminLigneFacture('b')],
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
            'offre_entete_enregistrer' => ['/offres/' . $this->jeu->offre['b'] . '/modifier', ['titre' => 'Volée']],
            'client_modifier' => ['/clients/' . $this->jeu->client['b'], ['nom' => 'Volé']],
            'client_supprimer' => ['/clients/' . $this->jeu->client['b'] . '/supprimer', []],
            'offre_client_choisir' => ['/offres/' . $this->jeu->offre['b'] . '/client', ['client' => (string) $this->jeu->client['a']]],
            // Une offre de A ne prend pas la fiche de B ; une fiche créée « pour » l'offre de B est refusée.
            'offre_client_choisir_fiche_de_b' => ['/offres/' . $this->jeu->offre['a'] . '/client', ['client' => (string) $this->jeu->client['b']]],
            'client_creer_pour_offre_de_b' => ['/clients', ['nom' => 'Volé', 'offre' => (string) $this->jeu->offre['b']]],
            'ligne_ajouter' => ['/offres/' . $this->jeu->offre['b'] . '/lignes', ['designation' => 'Volée', 'quantite' => '1', 'unite' => 'pce', 'prix' => '1']],
            'ligne_corriger' => [$this->cheminLigne('b'), ['quantite' => '99']],
            'ligne_juste' => [$this->cheminLigne('b', '/juste'), []],
            'ligne_supprimer' => [$this->cheminLigne('b', '/supprimer'), []],
            'ligne_restaurer' => [$this->cheminLigne('b', '/restaurer'), []],
            'produit_modifier' => ['/catalogue/' . $this->jeu->produit['b'], $produit],
            'produit_supprimer' => ['/catalogue/' . $this->jeu->produit['b'] . '/supprimer', []],
            'offre_archiver' => ['/offres/' . $this->jeu->offre['b'] . '/archiver', []],
            'offre_supprimer' => ['/offres/' . $this->jeu->offre['b'] . '/supprimer', []],
            'offre_restaurer' => ['/offres/' . $this->jeu->offre['b'] . '/restaurer', []],
            'enregistrement_reessayer' => ['/enregistrements/' . $this->jeu->uuid['b'] . '/reessayer', []],
            'enregistrement_retirer' => ['/enregistrements/' . $this->jeu->uuid['b'] . '/retirer', []],
            'offre_facturer_creer' => ['/offres/' . $this->jeu->offre['b'] . '/facturer', ['genre' => 'finale', 'numero' => '2026-009', 'date' => '2026-11-07', 'echeance' => '2026-12-07']],
            'facture_entete_enregistrer' => [$this->cheminFacture('b', '/modifier'), ['numero' => 'VOL-1', 'date' => '2026-11-07', 'echeance' => '2026-12-07']],
            'facture_supprimer' => [$this->cheminFacture('b', '/supprimer'), []],
            'facture_emettre' => [$this->cheminFacture('b', '/emettre'), []],
            'facture_annuler' => ['/factures/' . $this->factureEmise['b'] . '/annuler', ['refaire' => '1']],
            'facture_ligne_ajouter' => [$this->cheminFacture('b', '/lignes'), ['designation' => 'Volée', 'quantite' => '1', 'unite' => 'pce', 'prix' => '1']],
            'facture_ligne_corriger' => [$this->cheminLigneFacture('b'), ['quantite' => '99']],
            'facture_ligne_juste' => [$this->cheminLigneFacture('b', '/juste'), []],
            'facture_ligne_supprimer' => [$this->cheminLigneFacture('b', '/supprimer'), []],
            'facture_ligne_restaurer' => [$this->cheminLigneFacture('b', '/restaurer'), []],
        ];
    }

    /** Tout ce que B possède, pour prouver qu'une requête de A n'y a rien changé. */
    private function donneesDeB(): string
    {
        $b = $this->jeu->b;
        $tout = [];
        foreach (['artisan' => "id = $b", 'offre' => "artisan_id = $b", 'ligne' => "artisan_id = $b", 'produit' => "artisan_id = $b",
                  'enregistrement' => "artisan_id = $b", 'client' => "artisan_id = $b", 'facture' => "artisan_id = $b",
                  'facture_ligne' => "artisan_id = $b", 'facture_pdf' => "artisan_id = $b"] as $table => $condition) {
            $requete = BaseDeTest::pdo()->query("SELECT * FROM $table WHERE $condition ORDER BY 1");
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
