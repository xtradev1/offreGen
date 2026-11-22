<?php

declare(strict_types=1);

namespace OffreGen\Tests\Http;

use OffreGen\Tests\Outils\BaseDeTest;
use OffreGen\Tests\Outils\Jeu;
use OffreGen\Tests\Outils\Monde;
use OffreGen\Tests\Outils\Navigateur;
use PHPUnit\Framework\TestCase;

/**
 * Une offre reste modifiable après l'envoi ; changée depuis, l'écran le dit et propose « Renvoyer » (Xavier, 2026-11-21).
 * Horloge des tests : 7 novembre 2026, 10 h ; l'offre a été envoyée le 5.
 */
final class RenvoiOffreTest extends TestCase
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
        BaseDeTest::pdo()->exec("UPDATE offre SET client_id = {$this->jeu->client['a']}, envoyee_le = '2026-11-05 08:00:00', modifiee_le = '2026-11-04 08:00:00' WHERE id = {$this->jeu->offre['a']}");
    }

    private function page(string $chemin): string
    {
        $r = $this->a->get($chemin);
        self::assertSame(200, $r->getStatusCode(), $chemin);
        return (string) $r->getContent();
    }

    public function testEnvoyeeSansChangementPasDeBandeau(): void
    {
        self::assertStringNotContainsString('data-renvoi', $this->page($this->offre));
    }

    public function testUneCorrectionApresLEnvoiDemandeDeRenvoyer(): void
    {
        $this->page($this->offre);
        self::assertSame(303, $this->a->post("{$this->offre}/lignes/{$this->ligne}", ['quantite' => '14'])->getStatusCode());
        $page = $this->page($this->offre);
        self::assertStringContainsString("Modifiée depuis l'envoi du 05.11.2026", $page);
        self::assertStringContainsString('<a class="btn" href="' . $this->offre . '/apercu">Renvoyer</a>', $page);
        // Renvoyée : le bandeau part.
        $this->page($this->offre . '/apercu');
        self::assertSame(303, $this->a->post($this->offre . '/envoyee', [])->getStatusCode());
        self::assertStringNotContainsString('data-renvoi', $this->page($this->offre));
    }

    public function testUnRabaisOuUnTexteApresLEnvoiDemandentAussiDeRenvoyer(): void
    {
        $this->page($this->offre);
        $this->a->post($this->offre . '/textes', ['ou' => 'apres', 'texte' => 'Travaux dès janvier.']);
        self::assertStringContainsString('data-renvoi', $this->page($this->offre));
    }

    public function testUneOffreJamaisEnvoyeeNAPasDeBandeau(): void
    {
        BaseDeTest::pdo()->exec("UPDATE offre SET envoyee_le = NULL WHERE id = {$this->jeu->offre['a']}");
        $this->page($this->offre);
        $this->a->post("{$this->offre}/lignes/{$this->ligne}", ['quantite' => '14']);
        self::assertStringNotContainsString('data-renvoi', $this->page($this->offre));
    }
}
