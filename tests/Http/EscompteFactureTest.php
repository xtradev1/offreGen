<?php

declare(strict_types=1);

namespace OffreGen\Tests\Http;

use OffreGen\Donnees\DepotOffres;
use OffreGen\Donnees\Portee;
use OffreGen\Offre\Totaux;
use OffreGen\Tests\Outils\BaseDeTest;
use OffreGen\Tests\Outils\Jeu;
use OffreGen\Tests\Outils\Monde;
use OffreGen\Tests\Outils\Navigateur;
use PHPUnit\Framework\TestCase;

/**
 * Escompte sur chaque facture, acomptes compris, en ligne sous le total (Xavier, 2026-11-23) ; le total et le code QR
 * gardent le montant plein (D13 A, D14 A, 2026-11-21). Offre de A : 250.00 ; escompte 2 % à 10 jours : 245.00 si payé jusqu'au 17.11.2026 (facture du 7).
 */
final class EscompteFactureTest extends TestCase
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
        $this->conditions('2', '10');
    }

    private function page(string $chemin): string
    {
        $r = $this->a->get($chemin);
        self::assertSame(200, $r->getStatusCode(), $chemin);
        return (string) $r->getContent();
    }

    private function texte(string $html): string
    {
        return (string) preg_replace('/\s+/u', ' ', html_entity_decode((string) preg_replace('/<[^>]+>/', ' ', $html), ENT_QUOTES));
    }

    private function conditions(string $pourcent, string $jours): void
    {
        $this->page($this->offre . '/conditions');
        $this->a->post($this->offre . '/conditions', ['validite_choix' => '30', 'paiement_type' => 'jours', 'paiement_jours_choix' => '30',
            'escompte_pourcent' => $pourcent, 'escompte_jours' => $jours]);
    }

    private function finaleEnBrouillon(): int
    {
        $this->page($this->offre . '/facturer');
        $this->a->post($this->offre . '/facturer', ['genre' => 'finale', 'numero' => '', 'date' => '2026-11-07', 'echeance' => '2026-12-07', 'suite' => 'corriger']);
        return (int) BaseDeTest::ligne("SELECT id FROM facture WHERE offre_id = {$this->jeu->offre['a']} AND genre = 'finale'")['id'];
    }

    public function testLeMontantEscompteSeCalculeDansTotaux(): void
    {
        self::assertSame(24500, Totaux::escompte(25000, 200, true));
        // 100.10 moins 2,5 % (2.5025 arrondi à 2.50) : 97.60.
        self::assertSame(9760, Totaux::escompte(10010, 250, true));
        // 100.13 moins 2 % (2.0026 arrondi à 2.00) : 98.13, aux 5 centimes 98.15 ; au centime, 98.13.
        self::assertSame(9815, Totaux::escompte(10013, 200, true));
        self::assertSame(9813, Totaux::escompte(10013, 200, false));
    }

    public function testLaFinaleCopieLEscompteEtLeMentionne(): void
    {
        $id = $this->finaleEnBrouillon();
        self::assertSame(1, BaseDeTest::compter('facture', "id = $id AND escompte_pour_dix_mille = 200 AND escompte_jours = 10"));
        $doc = $this->texte($this->page("/factures/$id/document"));
        self::assertStringContainsString("Total TTC CHF 250.00 Payé jusqu'au 17.11.2026, escompte de 2 % CHF 245.00", $doc);
        self::assertStringContainsString('Total TTC CHF 250.00', $doc, 'le montant à payer reste plein (D13 A)');
        // Changer l'escompte de l'offre ensuite ne change pas la facture.
        $this->conditions('3', '8');
        self::assertSame(1, BaseDeTest::compter('facture', "id = $id AND escompte_pour_dix_mille = 200 AND escompte_jours = 10"));
    }

    public function testUnAcompteAussiPorteLEscompte(): void
    {
        $this->page($this->offre . '/facturer');
        $apercu = $this->texte($this->page($this->offre . '/facturer/apercu?genre=acompte&pourcent=30&numero=2026-001&date=2026-11-07&echeance=2026-12-07'));
        // 75.00 moins 2 % : 73.50.
        self::assertStringContainsString("Payé jusqu'au 17.11.2026, escompte de 2 % CHF 73.50", $apercu);
        $this->page($this->offre . '/facturer');
        $this->a->post($this->offre . '/facturer', ['genre' => 'acompte', 'pourcent' => '30', 'numero' => '', 'date' => '2026-11-07', 'echeance' => '2026-12-07', 'suite' => 'creer']);
        self::assertSame(1, BaseDeTest::compter('facture', "offre_id = {$this->jeu->offre['a']} AND genre = 'acompte' AND escompte_pour_dix_mille = 200"));
    }

    public function testSansEscompteRienNeParait(): void
    {
        $this->conditions('', '');
        $id = $this->finaleEnBrouillon();
        self::assertSame(1, BaseDeTest::compter('facture', "id = $id AND escompte_pour_dix_mille = 0 AND escompte_jours = 0"));
        $doc = $this->texte($this->page("/factures/$id/document"));
        self::assertStringNotContainsStringIgnoringCase('escompte', $doc);
        self::assertStringNotContainsString("Payé jusqu'au", $doc);
    }

    public function testLaFactureRefaiteGardeLEscompte(): void
    {
        $this->page($this->offre . '/facturer');
        $this->a->post($this->offre . '/facturer', ['genre' => 'finale', 'numero' => '', 'date' => '2026-11-07', 'echeance' => '2026-12-07', 'suite' => 'creer']);
        $id = (int) BaseDeTest::ligne("SELECT id FROM facture WHERE offre_id = {$this->jeu->offre['a']}")['id'];
        $this->page("/factures/$id/annuler");
        $this->a->post("/factures/$id/annuler", ['refaire' => '1']);
        self::assertSame(1, BaseDeTest::compter('facture', "refaite_depuis = $id AND escompte_pour_dix_mille = 200 AND escompte_jours = 10"));
    }
}
