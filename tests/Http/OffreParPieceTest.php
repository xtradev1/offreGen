<?php

declare(strict_types=1);

namespace OffreGen\Tests\Http;

use OffreGen\Donnees\Base;
use OffreGen\Donnees\DepotOffres;
use OffreGen\Donnees\Portee;
use OffreGen\Offre\LigneExtraite;
use OffreGen\Tests\Outils\BaseDeTest;
use OffreGen\Tests\Outils\Jeu;
use OffreGen\Tests\Outils\Monde;
use OffreGen\Tests\Outils\Navigateur;
use PHPUnit\Framework\TestCase;

/**
 * Sous-chantier 28, décisions de Xavier du 2026-11-17 : par pièce quand la dictée en donne, sinon par catégorie (P1 A) ;
 * « Général » en tête (P2 A) ; sous-total par pièce dans le PDF (P3) ; l'ajout demande la pièce (P4) ; renommer une
 * pièce depuis son titre, déplacer une ligne depuis sa fiche (P5).
 */
final class OffreParPieceTest extends TestCase
{
    private Jeu $jeu;
    private Navigateur $a;
    private int $offre;
    private string $chemin;

    protected function setUp(): void
    {
        $this->jeu = new Jeu();
        $this->a = new Navigateur(Monde::conteneur());
        $this->a->connecter('a@exemple.ch');
        $this->offre = (new DepotOffres(new Base(BaseDeTest::pdo()), new Portee($this->jeu->a)))->creerExemple('Appartement', [
            new LigneExtraite('Protection des sols', 1000, 'forfait', 18000, false, null),
            new LigneExtraite('Rebouchage', 38000, 'm²', 900, false, null, null, null, 'Salon'),
            new LigneExtraite('Peinture murale a', 38000, 'm²', 2800, false, $this->jeu->produit['a'], null, null, 'Salon'),
            new LigneExtraite('Peinture murale a', 24000, 'm²', 2800, false, $this->jeu->produit['a'], null, null, 'Chambre'),
            new LigneExtraite('Plinthes', 14000, 'm', 1200, false, null, null, null, 'chambre'),
        ], $this->jeu->maintenant, $this->jeu->client['a']);
        $this->chemin = "/offres/{$this->offre}";
    }

    private function page(string $chemin): string
    {
        $r = $this->a->get($chemin);
        self::assertSame(200, $r->getStatusCode(), $chemin);
        return (string) $r->getContent();
    }

    /** @return list<string|null> pièces des lignes, dans l'ordre */
    private function pieces(): array
    {
        $q = BaseDeTest::pdo()->query("SELECT piece FROM ligne WHERE offre_id = {$this->offre} AND supprimee_le IS NULL ORDER BY rang, id");
        self::assertNotFalse($q);
        return array_column($q->fetchAll(), 'piece');
    }

    private function ligne(int $rang): int
    {
        return (int) BaseDeTest::ligne("SELECT id FROM ligne WHERE offre_id = {$this->offre} AND rang = $rang")['id'];
    }

    public function testLaPieceDeChaqueLigneEstGardee(): void
    {
        self::assertSame([null, 'Salon', 'Salon', 'Chambre', 'chambre'], $this->pieces());
    }

    public function testLOffreSeRangeParPieceGeneralEnTeteAvecSousTotal(): void
    {
        $page = $this->page($this->chemin);
        // Général, puis les pièces dans l'ordre dicté ; « chambre » rejoint « Chambre ».
        self::assertMatchesRegularExpression('#<h5>Général <span class="st">180.00</span></h5>.*<h5>Salon <span class="st">1’406.00</span>.*<h5>Chambre <span class="st">840.00</span>#s', $page);
        self::assertSame(1, substr_count($page, '<h5>Chambre '));
        self::assertStringContainsString("href=\"{$this->chemin}/pieces?nom=Salon\"", $page);
        self::assertStringNotContainsString('<h5>Peinture', $page, 'plus de catégorie quand la dictée donne des pièces');
    }

    public function testUneOffreSansPieceResteParCategorie(): void
    {
        $page = $this->page('/offres/' . $this->jeu->offre['a']);
        self::assertStringContainsString('<h5>Peinture</h5>', $page);
        self::assertStringNotContainsString('/pieces?nom=', $page);
    }

    public function testLeDocumentFaitUnSousTotalParPieceMaisPasPourGeneral(): void
    {
        $doc = $this->page("{$this->chemin}/document");
        self::assertStringContainsString('Total salon', $doc);
        self::assertStringContainsString('Total chambre', $doc);
        self::assertStringNotContainsString('Total général', $doc, 'il se confondrait avec le total de l\'offre');
    }

    public function testLAjoutProposeLesPiecesEtPrendLaDerniereParDefaut(): void
    {
        $page = $this->page("{$this->chemin}/ajouter");
        // Des liens, sans JavaScript : la pièce choisie part ensuite avec chaque ajout, en champ caché.
        foreach (['', 'Salon', 'Chambre'] as $valeur) {
            self::assertStringContainsString("href=\"{$this->chemin}/ajouter?piece=$valeur\"", $page);
        }
        self::assertMatchesRegularExpression('#href="[^"]*\?piece=Chambre" aria-current="true"#', $page);
        self::assertStringContainsString('<input type="hidden" name="piece" value="Chambre">', $page);
        self::assertStringContainsString('Autre pièce', $page);
        // Une pièce choisie reste choisie.
        self::assertStringContainsString('<input type="hidden" name="piece" value="Salon">', $this->page("{$this->chemin}/ajouter?piece=Salon"));
        self::assertStringContainsString('<input type="hidden" name="piece" value="">', $this->page("{$this->chemin}/ajouter?piece="));
    }

    public function testLAjoutRangeLaLigneDansLaPieceChoisie(): void
    {
        $this->page("{$this->chemin}/ajouter");
        $this->a->post("{$this->chemin}/lignes", ['produit' => (string) $this->jeu->produit['a'], 'piece' => 'Salon']);
        $this->page("{$this->chemin}/ajouter");
        $this->a->post("{$this->chemin}/lignes", ['produit' => (string) $this->jeu->produit['a'], 'piece' => '']);
        $this->page("{$this->chemin}/ajouter");
        $this->a->post("{$this->chemin}/lignes", ['produit' => (string) $this->jeu->produit['a'], 'piece' => ' cuisine ']);
        self::assertSame(['Salon', null, 'Cuisine'], array_slice($this->pieces(), 5));
        // Rester sur la feuille d'ajout garde la pièce.
        $this->page("{$this->chemin}/ajouter?piece=Salon");
        $r = $this->a->post("{$this->chemin}/lignes", ['produit' => (string) $this->jeu->produit['a'], 'piece' => 'Salon', 'reste' => '1']);
        self::assertStringContainsString('piece=Salon', (string) $r->headers->get('Location'));
    }

    public function testLAjoutSansPieceDiteVaDansLaDernierePiece(): void
    {
        // L'ajout rapide au clavier (PC) n'envoie pas de pièce.
        $this->page($this->chemin);
        $this->a->post("{$this->chemin}/lignes", ['produit' => (string) $this->jeu->produit['a']]);
        // Sous le nom que l'offre affiche pour cette pièce.
        self::assertSame('Chambre', $this->pieces()[5]);
    }

    public function testRenommerUnePieceChangeToutesSesLignes(): void
    {
        $page = $this->page("{$this->chemin}/pieces?nom=Chambre");
        self::assertStringContainsString('value="Chambre"', $page);
        self::assertSame(303, $this->a->post("{$this->chemin}/pieces", ['ancienne' => 'Chambre', 'nouvelle' => 'Chambre des enfants'])->getStatusCode());
        self::assertSame([null, 'Salon', 'Salon', 'Chambre des enfants', 'Chambre des enfants'], $this->pieces());
    }

    public function testRenommerEnVideEstRefuse(): void
    {
        $this->page("{$this->chemin}/pieces?nom=Salon");
        self::assertSame(422, $this->a->post("{$this->chemin}/pieces", ['ancienne' => 'Salon', 'nouvelle' => '  '])->getStatusCode());
        self::assertSame('Salon', $this->pieces()[1]);
    }

    public function testDeplacerUneLigneDepuisSaFiche(): void
    {
        $id = $this->ligne(2);
        $page = $this->page("{$this->chemin}/lignes/$id");
        self::assertStringContainsString('name="piece" value="Salon"', $page);
        $r = $this->a->post("{$this->chemin}/lignes/$id", ['designation' => 'Rebouchage', 'quantite' => '38', 'unite' => 'm²', 'prix' => '9', 'piece' => 'Chambre']);
        self::assertSame(303, $r->getStatusCode());
        self::assertSame('Chambre', $this->pieces()[1]);
    }

    public function testUnAutreArtisanNeRenommePasLesPieces(): void
    {
        $b = new Navigateur(Monde::conteneur());
        $b->connecter('b@exemple.ch');
        $b->get('/offres/' . $this->jeu->offre['b']);
        self::assertSame(404, $b->post("{$this->chemin}/pieces", ['ancienne' => 'Salon', 'nouvelle' => 'Pirate'])->getStatusCode());
        self::assertSame(404, $b->get("{$this->chemin}/pieces?nom=Salon")->getStatusCode());
        self::assertSame('Salon', $this->pieces()[1]);
    }
}
