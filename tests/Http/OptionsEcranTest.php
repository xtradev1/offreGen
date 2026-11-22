<?php

declare(strict_types=1);

namespace OffreGen\Tests\Http;

use OffreGen\Tests\Outils\BaseDeTest;
use OffreGen\Tests\Outils\Jeu;
use OffreGen\Tests\Outils\Monde;
use OffreGen\Tests\Outils\Navigateur;
use PHPUnit\Framework\TestCase;

/**
 * Options et variantes à l'écran de l'offre (docs/plans/2026-11-22_options-variantes/PLAN.md, lot 3) : la variante
 * sous sa ligne avec l'écart (O2), les options sous le total (E2 A), le choix du client (O5), la ligne remplacée
 * visible (E5).
 *
 * L'offre du jeu : 12,5 m² de « Peinture murale a » à 18.50 = 231.25, TVA 8,1 % 18.73, 249.98, soit 250.00 TTC.
 * On y ajoute une variante (12,5 m² à 22.00 = 275.00, écart +43.75) et une option (1 pce à 100.00).
 */
final class OptionsEcranTest extends TestCase
{
    /** Formats::chf pose une insécable entre la devise et le montant. */
    private const CHF = "CHF\u{00A0}";

    private Jeu $jeu;
    private Navigateur $a;
    private string $offre;
    private int $base;
    private int $variante;
    private int $option;

    protected function setUp(): void
    {
        $this->jeu = new Jeu();
        $this->a = new Navigateur(Monde::conteneur());
        $this->a->connecter('a@exemple.ch');
        $o = $this->jeu->offre['a'];
        $this->offre = '/offres/' . $o;
        $this->base = (int) BaseDeTest::ligne("SELECT id FROM ligne WHERE offre_id = $o")['id'];
        $this->variante = $this->inserer($o, 'Peinture lessivable', 12500, 'm²', 2200, 'variante', $this->base);
        $this->option = $this->inserer($o, 'Laquage des portes', 1000, 'pce', 10000, 'option', null);
    }

    private function inserer(int $offre, string $designation, int $q, string $u, int $prix, string $nature, ?int $remplace, int $aVerifier = 0): int
    {
        $pdo = BaseDeTest::pdo();
        $pdo->prepare('INSERT INTO ligne (artisan_id, offre_id, rang, designation, quantite_milliemes, unite, prix_centimes, a_verifier, nature, remplace_id)
                       VALUES (:a, :o, (SELECT COALESCE(MAX(rang), 0) + 1 FROM ligne l2 WHERE l2.offre_id = :o2), :d, :q, :u, :p, :v, :n, :r)')
            ->execute(['a' => $this->jeu->a, 'o' => $offre, 'o2' => $offre, 'd' => $designation, 'q' => $q, 'u' => $u, 'p' => $prix, 'v' => $aVerifier, 'n' => $nature, 'r' => $remplace]);
        return (int) $pdo->lastInsertId();
    }

    private function page(string $chemin): string
    {
        $reponse = $this->a->get($chemin);
        self::assertSame(200, $reponse->getStatusCode(), $chemin);
        return (string) $reponse->getContent();
    }

    /** @param array<string, string> $plus */
    private function retenir(int $ligne, string $valeur, array $plus = []): int
    {
        $this->page($this->offre);
        return $this->a->post("{$this->offre}/lignes/$ligne/retenue", ['retenue' => $valeur] + $plus)->getStatusCode();
    }

    private static function texte(string $html): string
    {
        return (string) preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5));
    }

    public function testLaVarianteSuitSaLigneEtLOptionEstSousLeTotal(): void
    {
        $page = $this->page($this->offre);
        $texte = self::texte($page);
        // Dans l'ordre : la ligne, sa variante avec l'écart, le total, puis les options.
        $ligne = mb_strpos($texte, 'Peinture murale a');
        $variante = mb_strpos($texte, 'Peinture lessivable');
        $options = mb_strpos($texte, 'Options hors du total');
        $portes = mb_strpos($texte, 'Laquage des portes');
        self::assertNotFalse($ligne);
        self::assertNotFalse($variante);
        self::assertStringContainsString('<small class="sorte">Variante</small>Peinture lessivable', $page, 'la variante porte son étiquette');
        self::assertNotFalse($options);
        self::assertTrue($ligne < $variante && $variante < $options && $options < $portes, 'ligne, variante, total, options');
        self::assertStringContainsString('275.00+43.75', str_replace(' ', '', $texte), 'montant de la variante et écart avec sa ligne');
        self::assertSame(2, substr_count($page, 'Retenue par le client'), 'une case par option et par variante');
        // Le total ne compte que la ligne ; avec l'option : 331.25 + TVA 26.83 = 358.08, soit 358.10.
        self::assertStringContainsString('data-total-fixe><span>Total TTC</span><b>' . self::CHF . '250.00</b>', str_replace("\n", '', $page));
        self::assertStringContainsString('Total avec toutes les options</span><span>' . self::CHF . '358.10</span>', $page);
    }

    public function testRetenirLaVarianteLaMetALaPlaceDeLaLigne(): void
    {
        self::assertSame(303, $this->retenir($this->variante, '1'));
        self::assertSame(1, (int) BaseDeTest::ligne("SELECT retenue FROM ligne WHERE id = {$this->variante}")['retenue']);
        $page = $this->page($this->offre);
        // 275.00 + TVA 22.275 → 22.28 = 297.28, soit 297.30 ; avec l'option : 375.00 + 30.375 → 30.38 = 405.38, soit 405.40.
        self::assertStringContainsString('<b>' . self::CHF . '297.30</b>', $page);
        self::assertStringContainsString(self::CHF . '405.40', $page);
        self::assertStringContainsString('Remplacée par la variante retenue', $page);
        self::assertStringContainsString('Retenue, à la place', $page);
        self::assertStringContainsString('class="row remplacee"', $page, 'la ligne de base reste visible (E5)');
    }

    public function testUneSeuleVarianteRetenueParLigne(): void
    {
        $autre = $this->inserer($this->jeu->offre['a'], 'Peinture minérale', 12500, 'm²', 2600, 'variante', $this->base);
        self::assertSame(303, $this->retenir($this->variante, '1'));
        self::assertSame(303, $this->retenir($autre, '1'));
        $r = BaseDeTest::ligne("SELECT SUM(retenue) AS n, MAX(CASE WHEN retenue = 1 THEN id END) AS laquelle FROM ligne WHERE remplace_id = {$this->base}");
        self::assertSame([1, $autre], [(int) $r['n'], (int) $r['laquelle']]);
    }

    public function testRetenirLOptionLaCompteEtRenoncerLaRetire(): void
    {
        self::assertSame(303, $this->retenir($this->option, '1'));
        // 331.25 + 26.83 = 358.08 → 358.10 ; plus aucune option libre : plus de « Total avec toutes les options ».
        $page = $this->page($this->offre);
        self::assertStringContainsString('<b>' . self::CHF . '358.10</b>', $page);
        self::assertStringNotContainsString('Total avec toutes les options', $page);
        self::assertStringContainsString('Retenue, dans le total', $page);
        self::assertSame(303, $this->retenir($this->option, '0'));
        self::assertStringContainsString('<b>' . self::CHF . '250.00</b>', $this->page($this->offre));
    }

    public function testUneLigneDuTotalNeSeRetientPas(): void
    {
        self::assertSame(409, $this->retenir($this->base, '1'));
        self::assertSame(409, $this->retenir($this->option, 'oui'));
        self::assertSame(0, (int) BaseDeTest::ligne("SELECT retenue FROM ligne WHERE id = {$this->option}")['retenue']);
    }

    public function testLeRetourVersFacturer(): void
    {
        $this->page($this->offre);
        $r = $this->a->post("{$this->offre}/lignes/{$this->option}/retenue", ['retenue' => '1', 'retour' => 'facturer']);
        self::assertSame(303, $r->getStatusCode());
        self::assertSame("{$this->offre}/facturer", $r->headers->get('Location'));
    }

    public function testUnAutreArtisanNAtteintPasLeChoix(): void
    {
        $b = new Navigateur(Monde::conteneur());
        $b->connecter('b@exemple.ch');
        $b->get('/offres/' . $this->jeu->offre['b']);
        self::assertSame(404, $b->post("{$this->offre}/lignes/{$this->option}/retenue", ['retenue' => '1'])->getStatusCode());
        self::assertSame(0, (int) BaseDeTest::ligne("SELECT retenue FROM ligne WHERE id = {$this->option}")['retenue']);
    }

    public function testUneOptionAVerifierEstARelire(): void
    {
        // Tout est relu, sauf l'option ajoutée : la relecture n'a qu'elle à montrer.
        BaseDeTest::pdo()->exec('UPDATE ligne SET a_verifier = 0 WHERE offre_id = ' . $this->jeu->offre['a']);
        self::assertStringNotContainsString('Relire 1 ligne', self::texte($this->page($this->offre)));
        $this->inserer($this->jeu->offre['a'], 'Plinthes', 26000, 'm', 1200, 'option', null, 1);
        self::assertStringContainsString('Relire 1 ligne', self::texte($this->page($this->offre)));
        self::assertStringContainsString('Plinthes', $this->page("{$this->offre}/relire"), "la relecture passe par l'option à vérifier");
    }
}
