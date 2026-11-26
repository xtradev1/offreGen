<?php

declare(strict_types=1);

namespace OffreGen\Tests\Http;

use OffreGen\Tests\Outils\BaseDeTest;
use OffreGen\Tests\Outils\Jeu;
use OffreGen\Tests\Outils\Monde;
use OffreGen\Tests\Outils\Navigateur;
use PHPUnit\Framework\TestCase;

/**
 * Page de l'offre : les lignes plus haut (constat 3 de l'analyse des parcours, Xavier, 2026-11-26). Tant qu'ils sont
 * vides, « Représenté par » et « Texte avant les lignes » tiennent sur une ligne de deux liens ; remplis, ils reprennent
 * leur place : la carte du représentant sous le client, le texte dans son champ.
 */
final class OffreLignesPlusHautTest extends TestCase
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

    private function page(): string
    {
        $reponse = $this->a->get($this->offre);
        self::assertSame(200, $reponse->getStatusCode());
        return (string) $reponse->getContent();
    }

    public function testVidesLeRepresentantEtLeTexteSontDeuxLiensSurUneLigne(): void
    {
        $page = $this->page();
        self::assertSame(1, preg_match('#<div id="avant-lignes">\s*<div class="ajouts-offre" id="ajouts-offre">(.*?)</div>\s*</div>\s*<div class="rows#s', $page, $m), 'la ligne des ajouts, juste avant les lignes');
        $ajouts = $m[1] ?? '';
        self::assertStringContainsString("<a class=\"lien\" href=\"{$this->offre}/representant\" data-en-feuille>+ Représentant</a>", $ajouts);
        self::assertStringContainsString('<summary>+ Texte avant les lignes</summary>', $ajouts);
        self::assertStringContainsString('<textarea id="texte-avant" name="texte"', $ajouts, 'le champ est dans le bloc replié');
        self::assertStringNotContainsString('id="carte-representant"', $page);
        self::assertStringNotContainsString('Ajouter une régie, un architecte', $page);
        // Les fenêtres qui changent l'offre redemandent cette ligne avec le reste de l'en-tête.
        self::assertMatchesRegularExpression('#data-fenetre="[^"]*\bavant-lignes\b[^"]*"#', $page);
    }

    public function testRemplisIlsReprennentLeurPlace(): void
    {
        $regie = (int) BaseDeTest::ligne("SELECT id FROM client WHERE artisan_id = {$this->jeu->a} LIMIT 1")['id'];
        BaseDeTest::pdo()->exec("UPDATE offre SET representant_id = $regie, texte_avant = 'Suite à notre visite.' WHERE id = {$this->jeu->offre['a']}");
        $page = $this->page();
        self::assertMatchesRegularExpression('#<a class="rep carte-lien" id="carte-representant" data-apres="carte-client" href="' . $this->offre . '/representant" data-en-feuille>.*?<small>Représenté par</small><b>Client a</b>#s', $page);
        self::assertMatchesRegularExpression('#<label for="texte-avant">Texte avant les lignes</label>\s*<textarea id="texte-avant" name="texte"[^>]*>Suite à notre visite\.</textarea>#', $page);
        self::assertStringNotContainsString('+ Représentant', $page);
        self::assertStringNotContainsString('<summary>+ Texte avant les lignes</summary>', $page);
        // Revue du 2026-11-26 (I4) : le champ rempli et la ligne des liens sont dans le même morceau. Une fenêtre fermée
        // (client, objet) le remplace entier ; le texte tapé dans le bloc replié reste à l'écran.
        self::assertMatchesRegularExpression('#<div id="avant-lignes">\s*<form class="texte-offre".*?</form>\s*<div class="ajouts-offre" id="ajouts-offre" hidden>#s', $page);
    }

    public function testUnSeulDesDeuxVideGardeSonLien(): void
    {
        BaseDeTest::pdo()->exec("UPDATE offre SET texte_avant = 'Suite à notre visite.' WHERE id = {$this->jeu->offre['a']}");
        $page = $this->page();
        self::assertStringContainsString('+ Représentant', $page);
        self::assertStringNotContainsString('<summary>+ Texte avant les lignes</summary>', $page);
    }
}
