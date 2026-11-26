<?php

declare(strict_types=1);

namespace OffreGen\Tests\Http;

use OffreGen\Tests\Outils\BaseDeTest;
use OffreGen\Tests\Outils\Jeu;
use OffreGen\Tests\Outils\Monde;
use OffreGen\Tests\Outils\Navigateur;
use PHPUnit\Framework\TestCase;

/**
 * OV-3 (chantier documents et réglages, décision O1 de Xavier, 2026-11-24) : plus de filigrane « Aperçu » sur la
 * facture pas encore créée, ni dans la feuille « Facturer », ni sur la page d'un brouillon. La légende sous la vignette
 * dit toujours que rien n'est créé.
 */
final class FiligraneTest extends TestCase
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
        BaseDeTest::pdo()->exec("UPDATE offre SET client_id = {$this->jeu->client['a']} WHERE id = {$this->jeu->offre['a']}");
    }

    private function page(string $chemin): string
    {
        $reponse = $this->a->get($chemin);
        self::assertSame(200, $reponse->getStatusCode(), $chemin);
        return (string) $reponse->getContent();
    }

    public function testLaFeuilleFacturerSansFiligrane(): void
    {
        $feuille = $this->page($this->offre . '/facturer');
        self::assertStringContainsString('data-apercu-mini', $feuille);
        self::assertStringNotContainsString('class="filigrane"', $feuille);
        self::assertStringContainsString("Aperçu&nbsp;: rien n'est encore créé.", $feuille);
        $apercu = $this->page($this->offre . '/facturer/apercu?genre=finale&numero=2026-050&date=2026-11-07&echeance=2026-12-07');
        self::assertStringContainsString('<article class="paper apercu">', $apercu);
        self::assertStringNotContainsString('class="filigrane"', $apercu);
    }

    public function testLeBrouillonSansFiligrane(): void
    {
        $this->page($this->offre . '/facturer');
        $r = $this->a->post($this->offre . '/facturer', ['genre' => 'finale', 'numero' => '2026-001', 'date' => '2026-11-07', 'echeance' => '2026-12-07', 'suite' => 'corriger']);
        self::assertSame(303, $r->getStatusCode());
        $id = (int) BaseDeTest::ligne("SELECT id FROM facture WHERE statut = 'brouillon'")['id'];
        $document = $this->page("/factures/$id/document");
        self::assertStringContainsString('<article class="paper apercu">', $document);
        self::assertStringNotContainsString('class="filigrane"', $document);
    }
}
