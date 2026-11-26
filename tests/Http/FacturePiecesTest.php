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
 * La facture garde les pièces de l'offre (Xavier, 2026-11-23, planche des modèles) : copiées à la création, elles rangent
 * les lignes comme sur l'offre ; plus de « Hors catalogue ». Refaite après annulation, la facture les garde.
 */
final class FacturePiecesTest extends TestCase
{
    private Jeu $jeu;
    private Navigateur $a;
    private string $offre;

    protected function setUp(): void
    {
        $this->jeu = new Jeu();
        $this->a = new Navigateur(Monde::conteneur());
        $this->a->connecter('a@exemple.ch');
        $o = $this->jeu->offre['a'];
        $this->offre = "/offres/$o";
        (new DepotOffres($this->jeu->base, new Portee($this->jeu->a)))->rattacherClient($o, $this->jeu->client['a'], $this->jeu->maintenant);
        $pdo = BaseDeTest::pdo();
        $pdo->exec("UPDATE ligne SET piece = 'Salon' WHERE offre_id = $o");
        $pdo->exec("INSERT INTO ligne (artisan_id, offre_id, rang, designation, quantite_milliemes, unite, prix_centimes, offerte, a_verifier, piece)
            VALUES ({$this->jeu->a}, $o, 9, 'Laquage des plinthes', 18000, 'm', 1200, 0, 0, 'Chambre')");
    }

    private function texte(string $chemin): string
    {
        $r = $this->a->get($chemin);
        self::assertSame(200, $r->getStatusCode(), $chemin);
        return (string) preg_replace('/\s+/u', ' ', html_entity_decode((string) preg_replace('/<[^>]+>/', ' ', (string) $r->getContent()), ENT_QUOTES));
    }

    private function finale(string $suite): int
    {
        $this->a->get($this->offre . '/facturer');
        $this->a->post($this->offre . '/facturer', ['genre' => 'finale', 'numero' => '', 'date' => '2026-11-07', 'echeance' => '2026-12-07', 'suite' => $suite]);
        return (int) BaseDeTest::ligne("SELECT MAX(id) AS id FROM facture WHERE offre_id = {$this->jeu->offre['a']} AND genre = 'finale'")['id'];
    }

    public function testLaFinaleGardeLesPiecesDeLOffre(): void
    {
        $id = $this->finale('corriger');
        self::assertSame(1, BaseDeTest::compter('facture_ligne', "facture_id = $id AND piece = 'Salon'"));
        self::assertSame(1, BaseDeTest::compter('facture_ligne', "facture_id = $id AND piece = 'Chambre' AND designation = 'Laquage des plinthes'"));
        $doc = $this->texte("/factures/$id/document");
        self::assertStringNotContainsString('Hors catalogue', $doc);
        $salon = mb_strpos($doc, 'Salon');
        $chambre = mb_strpos($doc, 'Chambre');
        self::assertNotFalse($salon, 'la pièce Salon en titre');
        self::assertNotFalse($chambre, 'la pièce Chambre en titre');
        self::assertLessThan($chambre, $salon, "l'ordre de l'offre");
        self::assertLessThan(mb_strpos($doc, 'Laquage des plinthes'), $chambre, 'la ligne sous sa pièce');
    }

    public function testLApercuEtLaFactureRefaiteGardentLesPieces(): void
    {
        $this->a->get($this->offre . '/facturer');
        $apercu = $this->texte($this->offre . '/facturer/apercu?genre=finale&numero=2026-001&date=2026-11-07&echeance=2026-12-07');
        self::assertStringContainsString('Chambre', $apercu);
        self::assertStringNotContainsString('Hors catalogue', $apercu);
        $id = $this->finale('creer');
        $this->a->get("/factures/$id/annuler");
        $this->a->post("/factures/$id/annuler", ['refaire' => '1']);
        $refaite = (int) BaseDeTest::ligne("SELECT id FROM facture WHERE refaite_depuis = $id")['id'];
        self::assertGreaterThan(0, $refaite);
        self::assertSame(1, BaseDeTest::compter('facture_ligne', "facture_id = $refaite AND piece = 'Chambre'"));
    }
}
