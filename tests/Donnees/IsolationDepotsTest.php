<?php

declare(strict_types=1);

namespace OffreGen\Tests\Donnees;

use OffreGen\Donnees\DepotEnregistrements;
use OffreGen\Donnees\DepotOffres;
use OffreGen\Donnees\DepotProduits;
use OffreGen\Donnees\Portee;
use OffreGen\Offre\LigneExtraite;
use OffreGen\Tests\Outils\Jeu;
use PHPUnit\Framework\TestCase;

/**
 * Avec la portée de A, les données de B n'existent pas.
 */
final class IsolationDepotsTest extends TestCase
{
    private Jeu $jeu;

    protected function setUp(): void
    {
        $this->jeu = new Jeu();
    }

    public function testEnregistrementDeBInvisiblePourA(): void
    {
        $depot = new DepotEnregistrements($this->jeu->base, new Portee($this->jeu->a));
        self::assertNotNull($depot->parUuid($this->jeu->uuid['a']));
        self::assertNull($depot->parUuid($this->jeu->uuid['b']));
        self::assertSame([$this->jeu->uuid['a']], array_column($depot->enCours(), 'uuid'));
    }

    public function testOffreEtLignesDeBInvisiblesPourA(): void
    {
        $depot = new DepotOffres($this->jeu->base, new Portee($this->jeu->a));
        self::assertNotNull($depot->parId($this->jeu->offre['a']));
        self::assertNull($depot->parId($this->jeu->offre['b']));
        self::assertSame([], $depot->lignes($this->jeu->offre['b']));
        self::assertSame([], $depot->lignesDe([$this->jeu->offre['b']])[$this->jeu->offre['b']]);
        self::assertNull($depot->idParEnregistrement($this->jeu->enregistrement['b']));
        self::assertSame([$this->jeu->offre['a']], array_column($depot->liste(), 'id'));
    }

    public function testProduitsDeBInvisiblesPourA(): void
    {
        $depot = new DepotProduits($this->jeu->base, new Portee($this->jeu->a));
        self::assertSame([$this->jeu->produit['a']], array_column($depot->tous(), 'id'));
    }

    public function testRemplacerLeCatalogueDeANeTouchePasCeluiDeB(): void
    {
        (new DepotProduits($this->jeu->base, new Portee($this->jeu->a)))->remplacerTout([]);
        self::assertCount(1, (new DepotProduits($this->jeu->base, new Portee($this->jeu->b)))->tous());
    }

    public function testUneLigneNePeutPasPointerLeProduitDeB(): void
    {
        $depot = new DepotOffres($this->jeu->base, new Portee($this->jeu->a));
        $enr = (new DepotEnregistrements($this->jeu->base, new Portee($this->jeu->a)))
            ->creer('00000000-0000-4000-8000-000000000009', 'x.m4a', 'audio/mp4', 1, 'x', $this->jeu->maintenant);
        $offre = $depot->creerDepuisEnregistrement($enr, 'Essai', [new LigneExtraite('Intrus', 1000, 'pce', 100, false, $this->jeu->produit['b'])], $this->jeu->maintenant);
        self::assertNull($depot->lignes($offre)[0]['produit_id']);
    }

    public function testUnEnregistrementNeProduitQuUneOffre(): void
    {
        $depot = new DepotOffres($this->jeu->base, new Portee($this->jeu->a));
        $this->expectException(\PDOException::class);
        $depot->creerDepuisEnregistrement($this->jeu->enregistrement['a'], 'Doublon', [new LigneExtraite('X', 1000, 'pce', 1, false, null)], $this->jeu->maintenant);
    }

    public function testPorteeSansArtisanRefusee(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new Portee(0);
    }
}
