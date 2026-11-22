<?php

declare(strict_types=1);

namespace OffreGen\Tests\Http;

use OffreGen\Tests\Outils\BaseDeTest;
use OffreGen\Tests\Outils\Jeu;
use OffreGen\Tests\Outils\Monde;
use OffreGen\Tests\Outils\Navigateur;
use PHPUnit\Framework\TestCase;

/**
 * Ligne offerte, de l'écran à la facture (décisions D1, D2, D17 de Xavier, 2026-11-21) ; aucun « ? » sur un document
 * (Xavier, 2026-11-21). L'offre du jeu : 12,5 m² de « Peinture murale a » à 18.50, soit 250.00 TTC.
 */
final class LigneOfferteTest extends TestCase
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
        $this->ligne = (int) BaseDeTest::ligne("SELECT id FROM ligne WHERE offre_id = {$this->jeu->offre['a']}")['id'];
    }

    private function page(string $chemin): string
    {
        $reponse = $this->a->get($chemin);
        self::assertSame(200, $reponse->getStatusCode(), $chemin);
        return (string) $reponse->getContent();
    }

    /** @return array{int, int} prix en centimes, offerte */
    private function enBase(): array
    {
        $l = BaseDeTest::ligne("SELECT prix_centimes, offerte FROM ligne WHERE id = {$this->ligne}");
        return [(int) $l['prix_centimes'], (int) $l['offerte']];
    }

    /** @param array<string, string> $champs */
    private function depuisLaListe(array $champs): void
    {
        $this->page($this->offre);
        self::assertSame(303, $this->a->post("{$this->offre}/lignes/{$this->ligne}", $champs)->getStatusCode());
    }

    /** @param array<string, string> $champs */
    private function depuisLaFenetre(array $champs): void
    {
        $this->page("{$this->offre}/lignes/{$this->ligne}");
        $r = $this->a->post("{$this->offre}/lignes/{$this->ligne}", $champs + ['designation' => 'Peinture murale a', 'quantite' => '12.5', 'unite' => 'm²', 'prix' => '18.50']);
        self::assertSame(303, $r->getStatusCode());
    }

    public function testZeroTapeDansLaListeOffreLaLigne(): void
    {
        $this->depuisLaListe(['prix' => '0']);
        self::assertSame([0, 1], $this->enBase());
        $page = $this->page($this->offre);
        self::assertStringNotContainsString('Prix manquant', $page);
        self::assertStringContainsString('<div class="tt offert">Offert</div>', $page);
        self::assertStringNotContainsString('Relire 1 ligne', $page, 'une ligne offerte n\'est plus à relire');
    }

    public function testUnPrixVideResteManquant(): void
    {
        $this->depuisLaListe(['prix' => '']);
        self::assertSame([0, 0], $this->enBase());
        $page = $this->page($this->offre);
        self::assertStringContainsString('Prix manquant', $page);
        self::assertStringContainsString('Relire 1 ligne', $page);
    }

    public function testOfferteDepuisLaFenetreGardeLePrixBarre(): void
    {
        $this->depuisLaFenetre(['offerte' => 'offerte']);
        self::assertSame([1850, 1], $this->enBase());
        $page = $this->page($this->offre);
        self::assertMatchesRegularExpression('#<input class="saisie barre" name="prix" value="18.50"#', $page);
        self::assertStringContainsString('<div class="tt offert">Offert</div>', $page);
        // Plus rien à payer : 0.00.
        self::assertMatchesRegularExpression('#data-total-fixe><span>Total TTC</span><b>CHF\x{00A0}0.00</b>#u', $page);
    }

    public function testRepasserEnFactureeRendLePrix(): void
    {
        $this->depuisLaFenetre(['offerte' => 'offerte']);
        $this->depuisLaFenetre(['offerte' => 'facturee']);
        self::assertSame([1850, 0], $this->enBase());
    }

    public function testLaFenetreProposeFactureeEtOfferte(): void
    {
        $page = $this->page("{$this->offre}/lignes/{$this->ligne}");
        self::assertMatchesRegularExpression('#<input type="radio" name="offerte" value="facturee" checked>#', $page);
        self::assertMatchesRegularExpression('#<input type="radio" name="offerte" value="offerte">#', $page);
        self::assertStringNotContainsString('Prix&nbsp;?', $page);
    }

    public function testLeDocumentBarreLePrixOffertEtNAJamaisDePointDInterrogation(): void
    {
        $this->depuisLaFenetre(['offerte' => 'offerte']);
        $doc = $this->page($this->offre . '/document');
        self::assertMatchesRegularExpression('#<s>18.50</s>#', $doc);
        self::assertStringContainsString('Offert', $doc);

        BaseDeTest::pdo()->exec("UPDATE ligne SET prix_centimes = 0, offerte = 0 WHERE id = {$this->ligne}");
        $doc = $this->page($this->offre . '/document');
        self::assertDoesNotMatchRegularExpression('#>\s*\?\s*<#', $doc, 'un prix manquant laisse la case vide');
    }

    public function testLaRelectureAccepteZeroEtOffreLaLigne(): void
    {
        BaseDeTest::pdo()->exec("UPDATE ligne SET a_verifier = 1 WHERE id = {$this->ligne}");
        $this->page($this->offre . '/relire');
        $r = $this->a->post("{$this->offre}/relire/{$this->ligne}", ['quantite' => '12.5', 'prix' => '0']);
        self::assertSame(303, $r->getStatusCode());
        self::assertSame([0, 1], $this->enBase());
    }

    public function testLaFactureFinaleGardeLaLigneOfferte(): void
    {
        BaseDeTest::pdo()->exec("UPDATE offre SET client_id = {$this->jeu->client['a']} WHERE id = {$this->jeu->offre['a']}");
        $this->depuisLaFenetre(['offerte' => 'offerte']);
        $this->page($this->offre . '/facturer');
        $this->a->post($this->offre . '/facturer', ['genre' => 'finale', 'numero' => '2026-050', 'date' => '2026-11-07', 'echeance' => '2026-12-07', 'suite' => 'creer']);
        $l = BaseDeTest::ligne("SELECT fl.prix_centimes, fl.offerte FROM facture_ligne fl JOIN facture f ON f.id = fl.facture_id WHERE f.offre_id = {$this->jeu->offre['a']}");
        self::assertSame([1850, 1], [(int) $l['prix_centimes'], (int) $l['offerte']]);
    }
}
