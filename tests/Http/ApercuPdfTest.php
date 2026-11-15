<?php

declare(strict_types=1);

namespace OffreGen\Tests\Http;

use OffreGen\Tests\Outils\BaseDeTest;
use OffreGen\Tests\Outils\Jeu;
use OffreGen\Tests\Outils\Monde;
use OffreGen\Tests\Outils\Navigateur;
use PHPUnit\Framework\TestCase;

/**
 * Xavier, 2026-11-15 : « quand l'offre est générée, affiche-la directement en PDF », puis décision D7 A : dans
 * l'application avec PDF.js (livré avec l'application, pas de CDN), les boutons restent en bas.
 */
final class ApercuPdfTest extends TestCase
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

    public function testGenererLOffreMeneAuPdfDansLApplication(): void
    {
        $offre = (string) $this->a->get($this->offre)->getContent();
        self::assertMatchesRegularExpression("#<a class=\"btn primary grand\" href=\"{$this->offre}/apercu\">Générer l'offre#", $offre);

        $r = $this->a->get($this->offre . '/apercu');
        self::assertSame(200, $r->getStatusCode());
        $page = (string) $r->getContent();
        self::assertMatchesRegularExpression("#<div class=\"pdf-pages\" data-pdf=\"{$this->offre}/pdf\" data-worker=\"/js/vendor/pdfjs-[0-9.]+/pdf\.worker\.min\.js\">#", $page);
        self::assertMatchesRegularExpression('#<script type="module" src="/js/apercu-pdf\.js"></script>#', $page);
        // Les gestes suivants restent en bas : Modifier, Facturer, et le PDF à télécharger.
        self::assertStringContainsString("<a class=\"btn grand\" href=\"{$this->offre}\">Modifier</a>", $page);
        self::assertStringContainsString("<a class=\"btn grand\" href=\"{$this->offre}/facturer\" data-en-feuille>Facturer</a>", $page);
        // « Envoyer » (lot 6, D8) : le lien du PDF, que le partage du téléphone prend en charge.
        self::assertMatchesRegularExpression("#<a class=\"btn primary grand\" href=\"{$this->offre}/pdf\" download=\"Offre-\d+\.pdf\" data-partager#", $page);
        // Sans JavaScript, un lien ouvre le PDF.
        self::assertStringContainsString("<noscript><p><a href=\"{$this->offre}/pdf\">Ouvrir le PDF</a></p></noscript>", $page);
    }

    public function testLesFichiersDePdfJsSontLivresAvecLApplication(): void
    {
        $page = (string) $this->a->get($this->offre . '/apercu')->getContent();
        if (preg_match('#data-worker="(/js/vendor/pdfjs-([0-9.]+)/pdf\.worker\.min\.js)"#', $page, $m) !== 1) {
            self::fail('Aucun worker de PDF.js désigné par la page.');
        }
        [, $worker, $version] = $m;
        $racine = dirname(__DIR__, 2) . '/public';
        self::assertFileExists($racine . $worker);
        self::assertFileExists($racine . "/js/vendor/pdfjs-$version/pdf.min.js");
        self::assertStringContainsString("/js/vendor/pdfjs-$version/pdf.min.js", (string) file_get_contents($racine . '/js/apercu-pdf.js'), 'le module importe la même version');
    }

    public function testLOffreDUnAutreArtisanReste404(): void
    {
        self::assertSame(404, $this->a->get('/offres/' . $this->jeu->offre['b'] . '/apercu')->getStatusCode());
    }
}
