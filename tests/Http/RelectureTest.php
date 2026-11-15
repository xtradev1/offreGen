<?php

declare(strict_types=1);

namespace OffreGen\Tests\Http;

use OffreGen\Tests\Outils\BaseDeTest;
use OffreGen\Tests\Outils\Jeu;
use OffreGen\Tests\Outils\Monde;
use OffreGen\Tests\Outils\Navigateur;
use PHPUnit\Framework\TestCase;

/**
 * Relecture au pouce, une ligne à la fois (décision D5 de Xavier, maquette 24, 2026-11-15). Une ligne est à relire si
 * elle est « à vérifier » ou sans prix. « C'est juste » enregistre la quantité et le prix montrés, puis passe à la
 * suivante. Le rappel de la dictée est permis ici (D5, CODING_RULES).
 * Jeu : une ligne « Peinture murale a », 12,5 m² à 18.50.
 */
final class RelectureTest extends TestCase
{
    private Jeu $jeu;
    private Navigateur $a;
    private int $ligne;
    private string $offre;

    protected function setUp(): void
    {
        $this->jeu = new Jeu();
        $this->a = new Navigateur(Monde::conteneur());
        $this->a->connecter('a@exemple.ch');
        $this->offre = '/offres/' . $this->jeu->offre['a'];
        $this->ligne = (int) BaseDeTest::ligne("SELECT id FROM ligne WHERE offre_id = {$this->jeu->offre['a']}")['id'];
    }

    private function page(string $chemin): string
    {
        $reponse = $this->a->get($chemin);
        self::assertSame(200, $reponse->getStatusCode(), $chemin);
        return (string) $reponse->getContent();
    }

    private function nouvelleLigne(string $designation, int $milliemes, int $centimes, int $aVerifier, ?string $entendu = null): int
    {
        $pdo = BaseDeTest::pdo();
        $requete = $pdo->prepare('INSERT INTO ligne (artisan_id, offre_id, rang, designation, quantite_milliemes, unite, prix_centimes, a_verifier, entendu) VALUES (?, ?, 9, ?, ?, ?, ?, ?, ?)');
        $requete->execute([$this->jeu->a, $this->jeu->offre['a'], $designation, $milliemes, 'm²', $centimes, $aVerifier, $entendu]);
        return (int) $pdo->lastInsertId();
    }

    /** @return array<string, mixed> */
    private function ligneEnBase(int $id): array
    {
        return BaseDeTest::ligne("SELECT * FROM ligne WHERE id = $id");
    }

    public function testUneLigneAVerifierSeMontreEnGrandAvecCeQuiAEteEntendu(): void
    {
        BaseDeTest::pdo()->exec("UPDATE ligne SET a_verifier = 1, entendu = 'les murs, environ douze mètres et demi' WHERE id = {$this->ligne}");
        $page = $this->page($this->offre . '/relire');
        self::assertStringContainsString('<h2 class="relu-nom">Peinture murale a</h2>', $page);
        self::assertStringContainsString('Entendu&nbsp;: «&nbsp;les murs, environ douze mètres et demi&nbsp;»', $page);
        self::assertMatchesRegularExpression("#<form id=\"relecture\" method=\"post\" action=\"{$this->offre}/relire/{$this->ligne}\">#", $page);
        self::assertMatchesRegularExpression('#name="quantite" value="12.5"[^>]*inputmode="decimal"#', $page);
        self::assertMatchesRegularExpression('#name="prix" value="18.50"[^>]*inputmode="decimal"#', $page);
        self::assertStringContainsString('Encore 1 à relire', $page);
        self::assertMatchesRegularExpression('#<button class="btn ok grand" type="submit" form="relecture">.*C\'est juste</button>#s', $page);
        self::assertStringContainsString("href=\"{$this->offre}/lignes/{$this->ligne}\"", $page, '« Corriger » ouvre la ligne entière');
    }

    public function testCestJusteEnregistrePuisPasseALaSuivante(): void
    {
        BaseDeTest::pdo()->exec("UPDATE ligne SET a_verifier = 1 WHERE id = {$this->ligne}");
        $deuxieme = $this->nouvelleLigne('Plafond', 24000, 3200, 1);
        $this->page($this->offre . '/relire');
        $r = $this->a->post("{$this->offre}/relire/{$this->ligne}", ['quantite' => '14', 'prix' => '18.50']);
        self::assertSame(303, $r->getStatusCode());
        self::assertSame($this->offre . '/relire', $r->headers->get('Location'));
        $l = $this->ligneEnBase($this->ligne);
        self::assertSame([14000, 1850, 0], [(int) $l['quantite_milliemes'], (int) $l['prix_centimes'], (int) $l['a_verifier']]);
        self::assertStringContainsString('<h2 class="relu-nom">Plafond</h2>', $this->page($this->offre . '/relire'));
        $this->a->post("{$this->offre}/relire/$deuxieme", ['quantite' => '24', 'prix' => '32']);
        $fin = $this->page($this->offre . '/relire');
        self::assertStringContainsString('Toutes les lignes sont relues', $fin);
        self::assertStringContainsString("href=\"{$this->offre}/document\"", $fin, 'le geste suivant : générer l\'offre');
    }

    public function testUneLigneSansPrixDemandeUnPrix(): void
    {
        $libre = $this->nouvelleLigne('Crépi', 18000, 0, 0, 'refaire le crépi en bas');
        $page = $this->page($this->offre . '/relire');
        self::assertStringContainsString('<h2 class="relu-nom">Crépi</h2>', $page);
        self::assertMatchesRegularExpression('#name="prix" value=""#', $page);
        $r = $this->a->post("{$this->offre}/relire/$libre", ['quantite' => '18', 'prix' => '']);
        self::assertSame(422, $r->getStatusCode());
        self::assertStringContainsString('Indiquez le prix', (string) $r->getContent());
        self::assertSame(0, (int) $this->ligneEnBase($libre)['prix_centimes']);
        $this->a->post("{$this->offre}/relire/$libre", ['quantite' => '18', 'prix' => '38']);
        self::assertSame(3800, (int) $this->ligneEnBase($libre)['prix_centimes']);
    }

    public function testUneQuantiteIllisibleNeChangeRien(): void
    {
        BaseDeTest::pdo()->exec("UPDATE ligne SET a_verifier = 1 WHERE id = {$this->ligne}");
        $this->page($this->offre . '/relire');
        $r = $this->a->post("{$this->offre}/relire/{$this->ligne}", ['quantite' => 'beaucoup', 'prix' => '18.50']);
        self::assertSame(422, $r->getStatusCode());
        self::assertSame([12500, 1], [(int) $this->ligneEnBase($this->ligne)['quantite_milliemes'], (int) $this->ligneEnBase($this->ligne)['a_verifier']]);
    }
}
