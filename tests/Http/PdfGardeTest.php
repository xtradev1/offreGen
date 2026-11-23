<?php

declare(strict_types=1);

namespace OffreGen\Tests\Http;

use OffreGen\Http\Morceaux;
use OffreGen\Tests\Outils\BaseDeTest;
use OffreGen\Tests\Outils\Jeu;
use OffreGen\Tests\Outils\Monde;
use OffreGen\Tests\Outils\Navigateur;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

/**
 * Lot 5 du chantier sans rechargement (2026-11-23) : le PDF d'une offre qui n'a pas changé n'est ni refait ni
 * retéléchargé (ETag, 304) ; changée, elle a un autre PDF. La déconnexion vide le cache du navigateur, pas son stockage.
 */
final class PdfGardeTest extends TestCase
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
    }

    private function pdf(?string $etag = null): \Symfony\Component\HttpFoundation\Response
    {
        $r = Request::create($this->offre . '/pdf', 'GET', server: $etag === null ? [] : ['HTTP_IF_NONE_MATCH' => $etag]);
        $r->setSession($this->a->session);
        return $this->a->c->noyau()->traiter($r);
    }

    public function testLeMemePdfRevientEn304EtUneOffreChangeeEnAUnAutre(): void
    {
        $premier = $this->pdf();
        self::assertSame(200, $premier->getStatusCode());
        $etag = (string) $premier->headers->get('ETag');
        self::assertMatchesRegularExpression('/^"[0-9a-f]{16}"$/', $etag);
        self::assertStringContainsString('no-cache', (string) $premier->headers->get('Cache-Control'));
        self::assertStringStartsWith('%PDF', (string) $premier->getContent());

        $encore = $this->pdf($etag);
        self::assertSame(304, $encore->getStatusCode(), 'le navigateur qui l\'a déjà ne le retélécharge pas');
        self::assertSame('', (string) $encore->getContent());
        self::assertSame($etag, $encore->headers->get('ETag'));
        self::assertSame((string) $premier->getContent(), (string) $this->pdf()->getContent(), 'gardé au serveur : le même PDF');

        // L'offre change (une quantité) : un autre PDF, l'ancien ETag ne vaut plus.
        $ligne = (int) BaseDeTest::ligne("SELECT id FROM ligne WHERE offre_id = {$this->jeu->offre['a']}")['id'];
        $this->a->get($this->offre);
        $this->a->post("{$this->offre}/lignes/$ligne", ['quantite' => '20'], entetes: [Morceaux::DEMANDE => "l$ligne"]);
        $apres = $this->pdf($etag);
        self::assertSame(200, $apres->getStatusCode());
        self::assertNotSame($etag, $apres->headers->get('ETag'));
    }

    public function testLaDeconnexionVideLeCacheDuNavigateurSansToucherAuStockage(): void
    {
        $this->a->get('/plus');
        $r = $this->a->post('/deconnexion', []);
        self::assertSame(303, $r->getStatusCode());
        // « storage » effacerait les dictées à l'abri pas encore envoyées (sous-chantier 27).
        self::assertSame('"cache"', $r->headers->get('Clear-Site-Data'));
    }
}
