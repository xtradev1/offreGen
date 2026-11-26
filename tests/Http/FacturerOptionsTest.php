<?php

declare(strict_types=1);

namespace OffreGen\Tests\Http;

use OffreGen\Donnees\DepotOffres;
use OffreGen\Donnees\Portee;
use OffreGen\Tests\Outils\BaseDeTest;
use OffreGen\Tests\Outils\Jeu;
use OffreGen\Tests\Outils\Monde;
use OffreGen\Tests\Outils\Navigateur;
use PHPUnit\Framework\TestCase;

/**
 * Facturer une offre à options et variantes (docs/plans/2026-11-22_options-variantes/PLAN.md, lot 6 ; O5, E1 A) :
 * les cases du choix du client dans « Facturer », l'acompte et la finale sur ce qui est coché.
 *
 * L'offre du jeu : « Peinture murale a », 12,5 m² à 18.50 = 231.25. En plus : une variante « Peinture lessivable »
 * (12,5 m² à 22.00 = 275.00), deux options « Laquage des portes » (100.00) et « Radiateur » (50.00).
 */
final class FacturerOptionsTest extends TestCase
{
    private Jeu $jeu;
    private Navigateur $a;
    private string $offre;
    private int $base;
    private int $variante;
    private int $portes;
    private int $radiateur;

    protected function setUp(): void
    {
        $this->jeu = new Jeu();
        $this->a = new Navigateur(Monde::conteneur());
        $this->a->connecter('a@exemple.ch');
        $o = $this->jeu->offre['a'];
        $this->offre = '/offres/' . $o;
        (new DepotOffres($this->jeu->base, new Portee($this->jeu->a)))->rattacherClient($o, $this->jeu->client['a'], $this->jeu->maintenant);
        $this->base = (int) BaseDeTest::ligne("SELECT id FROM ligne WHERE offre_id = $o")['id'];
        $this->variante = $this->inserer('Peinture lessivable', 12500, 'm²', 2200, 'variante', $this->base);
        $this->portes = $this->inserer('Laquage des portes', 1000, 'pce', 10000, 'option', null);
        $this->radiateur = $this->inserer('Radiateur', 1000, 'pce', 5000, 'option', null);
    }

    private function inserer(string $designation, int $q, string $u, int $prix, string $nature, ?int $remplace): int
    {
        $pdo = BaseDeTest::pdo();
        $pdo->prepare('INSERT INTO ligne (artisan_id, offre_id, rang, designation, quantite_milliemes, unite, prix_centimes, nature, remplace_id)
                       VALUES (:a, :o, (SELECT COALESCE(MAX(rang), 0) + 1 FROM ligne l2 WHERE l2.offre_id = :o2), :d, :q, :u, :p, :n, :r)')
            ->execute(['a' => $this->jeu->a, 'o' => $this->jeu->offre['a'], 'o2' => $this->jeu->offre['a'], 'd' => $designation, 'q' => $q, 'u' => $u, 'p' => $prix, 'n' => $nature, 'r' => $remplace]);
        return (int) $pdo->lastInsertId();
    }

    private function page(string $chemin): string
    {
        $r = $this->a->get($chemin);
        self::assertSame(200, $r->getStatusCode(), $chemin);
        return (string) $r->getContent();
    }

    private function retenirDepuisLaFeuille(int $ligne): void
    {
        $this->page("{$this->offre}/facturer");
        $r = $this->a->post("{$this->offre}/lignes/$ligne/retenue", ['retenue' => '1', 'retour' => 'facturer']);
        self::assertSame(303, $r->getStatusCode());
        self::assertSame("{$this->offre}/facturer", $r->headers->get('Location'));
    }

    /** @return list<string> les désignations de la facture, dans l'ordre */
    private function lignesDeLaFacture(string $genre): array
    {
        $f = (int) BaseDeTest::ligne("SELECT id FROM facture WHERE offre_id = {$this->jeu->offre['a']} AND genre = '$genre' ORDER BY id DESC LIMIT 1")['id'];
        $r = BaseDeTest::pdo()->query("SELECT designation FROM facture_ligne WHERE facture_id = $f ORDER BY rang, id");
        self::assertNotFalse($r);
        return array_values(array_map('strval', $r->fetchAll(\PDO::FETCH_COLUMN)));
    }

    public function testLaFeuilleMontreLesCasesDuChoix(): void
    {
        $page = $this->page("{$this->offre}/facturer");
        self::assertStringContainsString('Choix du client', $page);
        self::assertSame(3, substr_count($page, 'class="case-client" type="submit" aria-pressed="false"'));
        self::assertStringContainsString("Variante de «\u{00A0}Peinture murale a\u{00A0}»", $page);
        foreach ([$this->variante, $this->portes, $this->radiateur] as $ligne) {
            self::assertStringContainsString("action=\"{$this->offre}/lignes/$ligne/retenue\"", $page);
        }
        self::assertStringContainsString("Les lignes de l'offre et ce que le client a retenu", $page);
    }

    public function testCocherDansLaFeuilleYRevient(): void
    {
        $this->retenirDepuisLaFeuille($this->portes);
        self::assertSame(1, (int) BaseDeTest::ligne("SELECT retenue FROM ligne WHERE id = {$this->portes}")['retenue']);
        self::assertSame(1, substr_count($this->page("{$this->offre}/facturer"), 'aria-pressed="true"'));
    }

    public function testLAcompteSeCompteSurLeChoix(): void
    {
        $this->retenirDepuisLaFeuille($this->variante);
        $this->retenirDepuisLaFeuille($this->portes);
        $this->page("{$this->offre}/facturer");
        $this->a->post("{$this->offre}/facturer", ['genre' => 'acompte', 'pourcent' => '30', 'numero' => '', 'date' => '2026-11-07', 'echeance' => '2026-12-07', 'suite' => 'creer']);
        // HT avec le choix : 275.00 + 100.00 = 375.00 ; 30 % : 112.50 HT.
        $f = (int) BaseDeTest::ligne("SELECT id FROM facture WHERE offre_id = {$this->jeu->offre['a']} AND genre = 'acompte'")['id'];
        self::assertSame(11250, (int) BaseDeTest::ligne("SELECT SUM(prix_centimes) AS s FROM facture_ligne WHERE facture_id = $f")['s']);
    }

    public function testLaFinaleNeCopieQueCeQuiEstDansLeTotal(): void
    {
        $this->retenirDepuisLaFeuille($this->variante);
        $this->retenirDepuisLaFeuille($this->portes);
        $this->page("{$this->offre}/facturer");
        $this->a->post("{$this->offre}/facturer", ['genre' => 'finale', 'numero' => '', 'date' => '2026-11-07', 'echeance' => '2026-12-07', 'suite' => 'creer']);
        // La variante au rang de sa ligne (point 2), l'option retenue ; ni la ligne remplacée, ni l'option libre.
        self::assertSame(['Peinture lessivable', 'Laquage des portes'], $this->lignesDeLaFacture('finale'));
    }

    public function testSansChoixLaFinaleGardeLaLigne(): void
    {
        $this->page("{$this->offre}/facturer");
        $this->a->post("{$this->offre}/facturer", ['genre' => 'finale', 'numero' => '', 'date' => '2026-11-07', 'echeance' => '2026-12-07', 'suite' => 'creer']);
        self::assertSame(['Peinture murale a'], $this->lignesDeLaFacture('finale'));
    }

    public function testUneOffreSansOptionNAPasDeCases(): void
    {
        BaseDeTest::pdo()->exec("DELETE FROM ligne WHERE nature <> 'ligne' AND offre_id = " . $this->jeu->offre['a']);
        $page = $this->page("{$this->offre}/facturer");
        self::assertStringNotContainsString('Choix du client', $page);
        self::assertStringContainsString("Toutes les lignes de l'offre", $page);
    }
}
