<?php

declare(strict_types=1);

namespace OffreGen\Tests\Http;

use OffreGen\Tests\Outils\BaseDeTest;
use OffreGen\Tests\Outils\Jeu;
use OffreGen\Tests\Outils\Monde;
use OffreGen\Tests\Outils\Navigateur;
use PHPUnit\Framework\TestCase;

/**
 * Décision de Xavier (2026-11-17, dictée de Luigi) : le prix du catalogue reste par défaut. Quand l'artisan a dicté un
 * autre prix, une petite note le dit sous la ligne, avec « Prendre » ; une fois pris, l'application demande s'il faut
 * aussi changer le catalogue, oui ou non.
 */
final class PrixDicteTest extends TestCase
{
    private Jeu $jeu;
    private Navigateur $a;
    private string $offre;
    private int $ligne;

    protected function setUp(): void
    {
        $this->jeu = new Jeu();
        $this->a = new Navigateur(Monde::conteneur());
        $this->a->connecter('a@exemple.ch');
        $this->offre = '/offres/' . $this->jeu->offre['a'];
        // La ligne du jeu : « Peinture murale a », produit du catalogue à 18.50 ; l'artisan a dicté 15 francs.
        $this->ligne = (int) BaseDeTest::ligne("SELECT id FROM ligne WHERE offre_id = {$this->jeu->offre['a']} AND produit_id = {$this->jeu->produit['a']}")['id'];
        BaseDeTest::pdo()->exec("UPDATE ligne SET prix_dicte_centimes = 1500, a_verifier = 1 WHERE id = {$this->ligne}");
    }

    private function page(): string
    {
        $reponse = $this->a->get($this->offre);
        self::assertSame(200, $reponse->getStatusCode());
        return (string) $reponse->getContent();
    }

    /** @return array<string, mixed> */
    private function ligneEnBase(): array
    {
        return BaseDeTest::ligne("SELECT prix_centimes, prix_dicte_centimes, a_verifier FROM ligne WHERE id = {$this->ligne}");
    }

    private function prixCatalogue(): int
    {
        return (int) BaseDeTest::ligne("SELECT prix_centimes FROM produit WHERE id = {$this->jeu->produit['a']}")['prix_centimes'];
    }

    public function testLaNoteDitLesDeuxPrixEtProposeDePrendreLePrixDicte(): void
    {
        $page = $this->page();
        self::assertStringContainsString("Prix dicté 15.00, catalogue 18.50", $page);
        self::assertMatchesRegularExpression("#<form class=\"note prix-dit\" method=\"post\" action=\"{$this->offre}/lignes/{$this->ligne}/prix-dicte\">.*Prendre 15.00</span></button>#s", $page);
    }

    public function testPrendreMetLePrixDicteEtDemandeSIlFautChangerLeCatalogue(): void
    {
        $this->page();
        self::assertSame(303, $this->a->post("{$this->offre}/lignes/{$this->ligne}/prix-dicte", [])->getStatusCode());
        self::assertSame([1500, 1500, 0], array_map('intval', array_values($this->ligneEnBase())));
        self::assertSame(1850, $this->prixCatalogue(), 'le catalogue ne change pas sans réponse');
        $page = $this->page();
        self::assertStringContainsString("Mettre aussi 15.00 au catalogue\u{00A0}?", $page);
        self::assertStringNotContainsString('Prendre 15.00', $page);
    }

    public function testOuiChangeLeCatalogueEtLaNoteDisparait(): void
    {
        $this->page();
        $this->a->post("{$this->offre}/lignes/{$this->ligne}/prix-dicte", []);
        $this->page();
        self::assertSame(303, $this->a->post("{$this->offre}/lignes/{$this->ligne}/prix-dicte/catalogue", ['choix' => 'oui'])->getStatusCode());
        self::assertSame(1500, $this->prixCatalogue());
        self::assertNull($this->ligneEnBase()['prix_dicte_centimes']);
        $page = $this->page();
        self::assertStringContainsString("«\u{00A0}Peinture murale a\u{00A0}»\u{00A0}: 15.00 au catalogue", $page);
        self::assertStringNotContainsString('Mettre aussi', $page);
        self::assertStringNotContainsString('Prix dicté', $page);
    }

    public function testNonGardeLeCatalogueEtLePrixPrisPourCetteOffre(): void
    {
        $this->page();
        $this->a->post("{$this->offre}/lignes/{$this->ligne}/prix-dicte", []);
        $this->page();
        self::assertSame(303, $this->a->post("{$this->offre}/lignes/{$this->ligne}/prix-dicte/catalogue", ['choix' => 'non'])->getStatusCode());
        self::assertSame(1850, $this->prixCatalogue());
        self::assertSame([1500, null], [(int) $this->ligneEnBase()['prix_centimes'], $this->ligneEnBase()['prix_dicte_centimes']]);
        self::assertStringNotContainsString('Prix dicté', $this->page());
    }

    public function testCEstJusteGardeLePrixDuCatalogueEtEfface(): void
    {
        $this->page();
        self::assertSame(303, $this->a->post("{$this->offre}/lignes/{$this->ligne}/juste", [])->getStatusCode());
        self::assertSame([1850, null, 0], [(int) $this->ligneEnBase()['prix_centimes'], $this->ligneEnBase()['prix_dicte_centimes'], (int) $this->ligneEnBase()['a_verifier']]);
        self::assertStringNotContainsString('Prix dicté', $this->page());
    }

    public function testSansPrixDicteLaQuestionDuCatalogueEstRefusee(): void
    {
        BaseDeTest::pdo()->exec("UPDATE ligne SET prix_dicte_centimes = NULL WHERE id = {$this->ligne}");
        $this->page();
        self::assertSame(409, $this->a->post("{$this->offre}/lignes/{$this->ligne}/prix-dicte", [])->getStatusCode());
        $this->page();
        self::assertSame(409, $this->a->post("{$this->offre}/lignes/{$this->ligne}/prix-dicte/catalogue", ['choix' => 'oui'])->getStatusCode());
        self::assertSame(1850, $this->prixCatalogue());
    }

    public function testUnAutreArtisanNeTouchePasALaLigne(): void
    {
        $b = new Navigateur(Monde::conteneur());
        $b->connecter('b@exemple.ch');
        $b->get('/offres/' . $this->jeu->offre['b']);
        self::assertSame(404, $b->post("{$this->offre}/lignes/{$this->ligne}/prix-dicte", [])->getStatusCode());
        $b->get('/offres/' . $this->jeu->offre['b']);
        self::assertSame(404, $b->post("{$this->offre}/lignes/{$this->ligne}/prix-dicte/catalogue", ['choix' => 'oui'])->getStatusCode());
        self::assertSame([1850, 1500], [(int) $this->ligneEnBase()['prix_centimes'], (int) $this->ligneEnBase()['prix_dicte_centimes']]);
        self::assertSame(1850, $this->prixCatalogue());
    }
}
