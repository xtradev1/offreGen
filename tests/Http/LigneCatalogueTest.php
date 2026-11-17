<?php

declare(strict_types=1);

namespace OffreGen\Tests\Http;

use OffreGen\Tests\Outils\BaseDeTest;
use OffreGen\Tests\Outils\Jeu;
use OffreGen\Tests\Outils\Monde;
use OffreGen\Tests\Outils\Navigateur;
use PHPUnit\Framework\TestCase;

/**
 * Décision D6 de Xavier (maquette 24, 2026-11-15) : une ligne proposée par l'IA hors catalogue se range en un toucher.
 * « Créer le produit » : le produit naît de la ligne (désignation, unité, prix), la ligne s'y rattache.
 * « Rattacher à un produit » : la ligne devient ce produit (désignation, unité et prix du catalogue, comme
 * l'extraction le fait) et le nom que l'IA avait donné à la ligne devient un synonyme du produit.
 */
final class LigneCatalogueTest extends TestCase
{
    private Jeu $jeu;
    private Navigateur $a;
    private string $offre;
    private int $libre;

    protected function setUp(): void
    {
        $this->jeu = new Jeu();
        $this->a = new Navigateur(Monde::conteneur());
        $this->a->connecter('a@exemple.ch');
        $this->offre = '/offres/' . $this->jeu->offre['a'];
        $pdo = BaseDeTest::pdo();
        $requete = $pdo->prepare('INSERT INTO ligne (artisan_id, offre_id, rang, designation, quantite_milliemes, unite, prix_centimes, a_verifier, entendu) VALUES (?, ?, 9, ?, ?, ?, ?, 0, ?)');
        $requete->execute([$this->jeu->a, $this->jeu->offre['a'], 'Crépi de soubassement', 18000, 'm²', 3800, 'refaire le crépi en bas, à peu près dix-huit mètres']);
        $this->libre = (int) $pdo->lastInsertId();
    }

    private function page(string $chemin): string
    {
        $reponse = $this->a->get($chemin);
        self::assertSame(200, $reponse->getStatusCode(), $chemin);
        return (string) $reponse->getContent();
    }

    /** @return array<string, mixed> */
    private function ligneEnBase(): array
    {
        return BaseDeTest::ligne("SELECT * FROM ligne WHERE id = {$this->libre}");
    }

    public function testUneLigneHorsCatalogueMontreSesDeuxBoutons(): void
    {
        $page = $this->page($this->offre);
        self::assertMatchesRegularExpression("#<form class=\"ranger\" method=\"post\" action=\"{$this->offre}/lignes/{$this->libre}/produit\">.*Créer le produit</button>#s", $page);
        self::assertStringContainsString("<a class=\"btn\" href=\"{$this->offre}/lignes/{$this->libre}/rattacher\">Rattacher à un produit</a>", $page);
        // Une ligne déjà au catalogue ne les montre pas : 2 boutons de chaque pour une seule ligne libre.
        self::assertSame(1, substr_count($page, 'Créer le produit</button>'));
    }

    public function testCreerLeProduitRangeLaLigneAuCatalogueEnUnToucher(): void
    {
        $this->page($this->offre);
        $r = $this->a->post("{$this->offre}/lignes/{$this->libre}/produit", []);
        self::assertSame(303, $r->getStatusCode());
        $p = BaseDeTest::ligne("SELECT * FROM produit WHERE artisan_id = {$this->jeu->a} AND designation = 'Crépi de soubassement'");
        self::assertSame(['m²', 3800], [$p['unite'], (int) $p['prix_centimes']]);
        self::assertSame((int) $p['id'], (int) $this->ligneEnBase()['produit_id']);
        self::assertStringContainsString('ajouté au catalogue', $this->page($this->offre));
        self::assertStringNotContainsString('Créer le produit</button>', $this->page($this->offre));
    }

    public function testRattacherReprendLeProduitEtGardeLeMotEnSynonyme(): void
    {
        $pdo = BaseDeTest::pdo();
        $pdo->exec("INSERT INTO produit (artisan_id, categorie, designation, unite, prix_centimes, synonymes) VALUES ({$this->jeu->a}, 'Façade', 'Crépi rustique', 'm²', 4200, 'ribé')");
        $produit = (int) $pdo->lastInsertId();
        $choix = $this->page("{$this->offre}/lignes/{$this->libre}/rattacher");
        self::assertStringContainsString('value="Crépi"', $choix, 'la recherche part du premier mot de la ligne');
        self::assertMatchesRegularExpression("#<form method=\"post\" action=\"{$this->offre}/lignes/{$this->libre}/rattacher\"[^>]*>.*name=\"produit\" value=\"$produit\"#s", $choix);
        self::assertStringContainsString('«&nbsp;Crépi de soubassement&nbsp;» sera ajouté aux synonymes', $choix);

        $r = $this->a->post("{$this->offre}/lignes/{$this->libre}/rattacher", ['produit' => (string) $produit]);
        self::assertSame(303, $r->getStatusCode());
        $l = $this->ligneEnBase();
        self::assertSame([$produit, 'Crépi rustique', 4200, 18000], [(int) $l['produit_id'], $l['designation'], (int) $l['prix_centimes'], (int) $l['quantite_milliemes']]);
        self::assertSame('ribé, Crépi de soubassement', BaseDeTest::ligne("SELECT synonymes FROM produit WHERE id = $produit")['synonymes']);
    }

    public function testRattacherGardeLeTauxDeTvaDuProduit(): void
    {
        // Trouvé le 2026-11-17 : le synonyme ajouté réécrivait le produit sans son taux, remis à NULL.
        $pdo = BaseDeTest::pdo();
        $pdo->exec("INSERT INTO produit (artisan_id, categorie, designation, unite, prix_centimes, tva_pour_dix_mille) VALUES ({$this->jeu->a}, 'Façade', 'Crépi rustique', 'm²', 4200, 260)");
        $produit = (int) $pdo->lastInsertId();
        $this->page("{$this->offre}/lignes/{$this->libre}/rattacher");
        self::assertSame(303, $this->a->post("{$this->offre}/lignes/{$this->libre}/rattacher", ['produit' => (string) $produit])->getStatusCode());
        $p = BaseDeTest::ligne("SELECT tva_pour_dix_mille, synonymes FROM produit WHERE id = $produit");
        self::assertSame([260, 'Crépi de soubassement'], [(int) $p['tva_pour_dix_mille'], $p['synonymes']]);
    }

    public function testUneLigneDejaAuCatalogueNeSeRangePasDeNouveau(): void
    {
        $ligneCatalogue = (int) BaseDeTest::ligne("SELECT id FROM ligne WHERE offre_id = {$this->jeu->offre['a']} AND produit_id IS NOT NULL")['id'];
        $this->page($this->offre);
        self::assertSame(409, $this->a->post("{$this->offre}/lignes/$ligneCatalogue/produit", [])->getStatusCode());
    }
}
