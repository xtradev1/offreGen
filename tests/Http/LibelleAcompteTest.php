<?php

declare(strict_types=1);

namespace OffreGen\Tests\Http;

use OffreGen\Donnees\DepotFactures;
use OffreGen\Donnees\DepotOffres;
use OffreGen\Donnees\Portee;
use OffreGen\Tests\Outils\BaseDeTest;
use OffreGen\Tests\Outils\Jeu;
use OffreGen\Tests\Outils\Monde;
use OffreGen\Tests\Outils\Navigateur;
use PHPUnit\Framework\TestCase;

/**
 * Libellé d'acompte libre (D11 A) et acomptes listés sur la facture finale (D12 A), 2026-11-21. Offre de A : 250.00 TTC ;
 * un acompte de 30 % : 69.38 HT, 5.62 de TVA, 75.00.
 */
final class LibelleAcompteTest extends TestCase
{
    private Jeu $jeu;
    private Navigateur $a;
    private string $offre;

    protected function setUp(): void
    {
        $this->jeu = new Jeu();
        $this->a = new Navigateur(Monde::conteneur());
        $this->a->connecter('a@exemple.ch');
        $this->offre = '/offres/' . $this->jeu->offre['a'];
        (new DepotOffres($this->jeu->base, new Portee($this->jeu->a)))->rattacherClient($this->jeu->offre['a'], $this->jeu->client['a'], $this->jeu->maintenant);
    }

    private function page(string $chemin): string
    {
        $r = $this->a->get($chemin);
        self::assertSame(200, $r->getStatusCode(), $chemin);
        return (string) $r->getContent();
    }

    /** @param array<string, string> $champs */
    private function facturer(array $champs, int $attendu = 303): string
    {
        $this->page($this->offre . '/facturer');
        $r = $this->a->post($this->offre . '/facturer', $champs + ['numero' => '', 'date' => '2026-11-07', 'echeance' => '2026-12-07', 'suite' => 'creer']);
        self::assertSame($attendu, $r->getStatusCode(), (string) $r->getContent());
        return (string) $r->getContent();
    }

    private function texte(string $html): string
    {
        return (string) preg_replace('/\s+/u', ' ', html_entity_decode((string) preg_replace('/<[^>]+>/', ' ', $html), ENT_QUOTES));
    }

    public function testLaFeuilleProposeTroisLibelles(): void
    {
        $page = $this->page($this->offre . '/facturer');
        self::assertStringContainsString('name="libelle" value=""', $page);
        foreach (['Acompte à la commande', 'Acompte au début des travaux', 'Acompte à mi-chantier'] as $libelle) {
            self::assertStringContainsString('data-libelle-puce="' . $libelle . ', selon offre n°&nbsp;1"', $page);
        }
    }

    public function testUnLibelleTapeNommeLaLigneEtLAcompte(): void
    {
        $this->facturer(['genre' => 'acompte', 'pourcent' => '30', 'libelle' => "Acompte à la commande, selon offre n°\u{00A0}1"]);
        $f = BaseDeTest::ligne("SELECT f.libelle, fl.designation, f.ttc FROM facture f JOIN facture_ligne fl ON fl.facture_id = f.id WHERE f.offre_id = {$this->jeu->offre['a']}");
        self::assertSame(["Acompte à la commande, selon offre n°\u{00A0}1", "Acompte à la commande, selon offre n°\u{00A0}1", 7500], [$f['libelle'], $f['designation'], (int) $f['ttc']]);
    }

    public function testUnLibelleQuiFinitParUnAccentResteEntier(): void
    {
        $this->facturer(['genre' => 'acompte', 'pourcent' => '30', 'libelle' => "  Acompte payé à \u{00A0}\n"]);
        self::assertSame('Acompte payé à', BaseDeTest::ligne("SELECT libelle FROM facture WHERE offre_id = {$this->jeu->offre['a']}")['libelle']);
    }

    public function testSansLibelleCeluiDesConditions(): void
    {
        $this->facturer(['genre' => 'acompte', 'pourcent' => '30', 'libelle' => '']);
        $f = BaseDeTest::ligne("SELECT f.libelle, fl.designation FROM facture f JOIN facture_ligne fl ON fl.facture_id = f.id WHERE f.offre_id = {$this->jeu->offre['a']}");
        self::assertSame("Acompte 1 (30\u{00A0}%), selon offre n°\u{00A0}1", $f['designation']);
        self::assertSame($f['designation'], $f['libelle']);
    }

    public function testUnLibelleTropLongEstRefuse(): void
    {
        $page = $this->facturer(['genre' => 'acompte', 'pourcent' => '30', 'libelle' => str_repeat('a', 201)], 422);
        self::assertStringContainsString('200 caractères au plus', $page);
        self::assertSame(0, BaseDeTest::compter('facture'));
    }

    public function testLaFinaleListeChaqueAcompte(): void
    {
        $this->facturer(['genre' => 'acompte', 'pourcent' => '30', 'libelle' => 'Acompte à la commande']);
        $this->facturer(['genre' => 'acompte', 'pourcent' => '30', 'libelle' => 'Acompte à mi-chantier']);
        $this->page($this->offre . '/facturer');
        $this->a->post($this->offre . '/facturer', ['genre' => 'finale', 'numero' => '', 'date' => '2026-11-07', 'echeance' => '2026-12-07', 'suite' => 'corriger']);
        $finale = (int) BaseDeTest::ligne("SELECT id FROM facture WHERE offre_id = {$this->jeu->offre['a']} AND genre = 'finale'")['id'];
        $doc = $this->texte($this->page("/factures/$finale/document"));
        self::assertStringContainsString('Acompte à la commande, facture 2026-001 du 07.11.2026 − 75.00', $doc);
        self::assertStringContainsString('Acompte à mi-chantier, facture 2026-002 du 07.11.2026 − 75.00', $doc);
        self::assertStringNotContainsString('Acomptes déjà facturés', $doc);
        // 250.00 moins 150.00.
        self::assertStringContainsString('À payer CHF 100.00', $doc);
        $ecran = $this->texte($this->page("/factures/$finale"));
        self::assertStringContainsString('Acompte à mi-chantier, facture 2026-002 − CHF 75.00', $ecran);
    }

    public function testUneFinaleEmiseNeListeQueLesAcomptesQuElleADeduits(): void
    {
        $this->facturer(['genre' => 'acompte', 'pourcent' => '30', 'libelle' => 'Acompte à la commande']);
        $depot = new DepotFactures($this->jeu->base, new Portee($this->jeu->a));
        self::assertCount(1, $depot->acomptesEmis($this->jeu->offre['a'], '2026-11-07 10:00:00'));
        self::assertCount(0, $depot->acomptesEmis($this->jeu->offre['a'], '2026-11-01 00:00:00'));
        self::assertSame([['numero' => '2026-001', 'libelle' => 'Acompte à la commande', 'acompte_rang' => 1, 'date_facture' => '2026-11-07', 'ttc' => 7500]],
            $depot->acomptesEmis($this->jeu->offre['a']));
    }
}
