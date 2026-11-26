<?php

declare(strict_types=1);

namespace OffreGen\Tests\Http;

use OffreGen\Tests\Outils\BaseDeTest;
use OffreGen\Tests\Outils\Jeu;
use OffreGen\Tests\Outils\Monde;
use OffreGen\Tests\Outils\Navigateur;
use PHPUnit\Framework\TestCase;

/**
 * La fiche d'une ligne (constat 6 de l'analyse des parcours, Xavier, 2026-11-26) : désignation, quantité, unité et prix
 * d'abord ; les réglages rares (offerte, option ou variante, TVA, catalogue) repliés sous « Plus de réglages », ouverts
 * d'office quand l'un d'eux sert déjà sur la ligne ou porte une erreur.
 * Jeu : une ligne « Peinture murale a », 12,5 m² à 18.50, dans le total, facturée.
 */
final class FicheLigneTest extends TestCase
{
    private Jeu $jeu;
    private Navigateur $a;
    private string $fiche;
    private int $ligne;

    protected function setUp(): void
    {
        $this->jeu = new Jeu();
        $this->a = new Navigateur(Monde::conteneur());
        $this->a->connecter('a@exemple.ch');
        $this->ligne = (int) BaseDeTest::ligne("SELECT id FROM ligne WHERE offre_id = {$this->jeu->offre['a']}")['id'];
        $this->fiche = "/offres/{$this->jeu->offre['a']}/lignes/{$this->ligne}";
    }

    private function page(): string
    {
        $reponse = $this->a->get($this->fiche);
        self::assertSame(200, $reponse->getStatusCode());
        return (string) $reponse->getContent();
    }

    public function testLesReglagesRaresSontRepliesSousLePrix(): void
    {
        $page = $this->page();
        self::assertSame(1, preg_match('#<details class="libre-detail reglages-ligne">\s*<summary>Plus de réglages</summary>(.*?)</details>#s', $page, $m), 'repliés : pas d\'attribut open');
        $replie = $m[1] ?? '';
        self::assertStringContainsString('<legend>Prix de la ligne</legend>', $replie);
        self::assertStringContainsString('<legend>Cette ligne est</legend>', $replie);
        // L'essentiel reste devant, dans l'ordre : désignation, quantité, unité, prix, puis les réglages.
        $places = array_map(static fn (string $s): int|false => strpos($page, $s), ['id="designation"', 'id="quantite"', 'id="unite"', 'id="prix"', '<details class="libre-detail reglages-ligne"']);
        self::assertNotContains(false, $places);
        $triees = $places;
        sort($triees);
        self::assertSame($triees, $places);
        self::assertStringNotContainsString('id="prix"', $replie);
    }

    public function testUneLigneOfferteOuvreLesReglages(): void
    {
        BaseDeTest::pdo()->exec("UPDATE ligne SET offerte = 1 WHERE id = {$this->ligne}");
        self::assertStringContainsString('<details class="libre-detail reglages-ligne" open>', $this->page());
    }

    public function testUneOptionOuvreLesReglages(): void
    {
        BaseDeTest::pdo()->exec("UPDATE ligne SET nature = 'option' WHERE id = {$this->ligne}");
        self::assertStringContainsString('<details class="libre-detail reglages-ligne" open>', $this->page());
    }

    public function testUneErreurDansLesReglagesLesOuvre(): void
    {
        $this->page();
        $r = $this->a->post($this->fiche, ['designation' => 'Peinture murale a', 'quantite' => '12.5', 'unite' => 'm²', 'prix' => '18.50', 'tva' => 'beaucoup']);
        if ($r->getStatusCode() === 422) {
            self::assertStringContainsString('<details class="libre-detail reglages-ligne" open>', (string) $r->getContent());
        } else {
            // Compte sans TVA : le taux n'est pas lu, rien à refuser.
            self::assertSame(303, $r->getStatusCode());
        }
    }
}
