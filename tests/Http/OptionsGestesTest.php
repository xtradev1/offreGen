<?php

declare(strict_types=1);

namespace OffreGen\Tests\Http;

use OffreGen\Tests\Outils\BaseDeTest;
use OffreGen\Tests\Outils\Jeu;
use OffreGen\Tests\Outils\Monde;
use OffreGen\Tests\Outils\Navigateur;
use PHPUnit\Framework\TestCase;

/**
 * Options et variantes, les gestes sans la voix (docs/plans/2026-11-22_options-variantes/PLAN.md, lot 4 ; O4, E3) :
 * la nature dans la fenêtre d'une ligne, l'ajout d'une option ou d'une variante, « + Variante ».
 *
 * L'offre du jeu : « Peinture murale a », 12,5 m² à 18.50 (la base). On y ajoute « Ponçage », 3 h à 60.00.
 */
final class OptionsGestesTest extends TestCase
{
    private Jeu $jeu;
    private Navigateur $a;
    private string $offre;
    private int $base;
    private int $poncage;

    protected function setUp(): void
    {
        $this->jeu = new Jeu();
        $this->a = new Navigateur(Monde::conteneur());
        $this->a->connecter('a@exemple.ch');
        $o = $this->jeu->offre['a'];
        $this->offre = '/offres/' . $o;
        $this->base = (int) BaseDeTest::ligne("SELECT id FROM ligne WHERE offre_id = $o")['id'];
        $this->poncage = $this->inserer($o, 'Ponçage', 3000, 'h', 6000, 'ligne', null);
    }

    private function inserer(int $offre, string $designation, int $q, string $u, int $prix, string $nature, ?int $remplace): int
    {
        $artisan = (int) BaseDeTest::ligne("SELECT artisan_id FROM offre WHERE id = $offre")['artisan_id'];
        $pdo = BaseDeTest::pdo();
        $pdo->prepare('INSERT INTO ligne (artisan_id, offre_id, rang, designation, quantite_milliemes, unite, prix_centimes, nature, remplace_id)
                       VALUES (:a, :o, 50, :d, :q, :u, :p, :n, :r)')
            ->execute(['a' => $artisan, 'o' => $offre, 'd' => $designation, 'q' => $q, 'u' => $u, 'p' => $prix, 'n' => $nature, 'r' => $remplace]);
        return (int) $pdo->lastInsertId();
    }

    /** @return array{string, int|null, int} nature, remplace_id, quantité */
    private static function enBase(int $ligne): array
    {
        $l = BaseDeTest::ligne("SELECT nature, remplace_id, quantite_milliemes FROM ligne WHERE id = $ligne");
        return [(string) $l['nature'], $l['remplace_id'] === null ? null : (int) $l['remplace_id'], (int) $l['quantite_milliemes']];
    }

    private function page(string $chemin): string
    {
        $reponse = $this->a->get($chemin);
        self::assertSame(200, $reponse->getStatusCode(), $chemin);
        return (string) $reponse->getContent();
    }

    /** @param array<string, string> $plus */
    private function depuisLaFenetre(int $ligne, array $plus): int
    {
        $this->page("{$this->offre}/lignes/$ligne");
        return $this->a->post("{$this->offre}/lignes/$ligne", $plus + ['designation' => 'Ponçage', 'quantite' => '3', 'unite' => 'h', 'prix' => '60'])->getStatusCode();
    }

    public function testLaFenetreDUneLigneProposeLaNatureEtPlusVariante(): void
    {
        $page = $this->page("{$this->offre}/lignes/{$this->poncage}");
        self::assertStringContainsString('<legend>Cette ligne est</legend>', $page);
        self::assertStringContainsString('name="nature" value="ligne" checked', $page);
        self::assertStringContainsString("<option value=\"{$this->base}\" selected>Peinture murale a</option>", $page, 'la seule autre ligne du total, proposée');
        self::assertStringContainsString("/ajouter?nature=variante&amp;remplace={$this->poncage}\">+ Variante de cette ligne</a>", $page);
    }

    public function testPasserUneLigneEnOptionPuisLaRemettre(): void
    {
        self::assertSame(303, $this->depuisLaFenetre($this->poncage, ['nature' => 'option']));
        self::assertSame(['option', null, 3000], self::enBase($this->poncage));
        self::assertSame(303, $this->depuisLaFenetre($this->poncage, ['nature' => 'ligne']));
        self::assertSame(['ligne', null, 3000], self::enBase($this->poncage));
    }

    public function testFaireDUneLigneUneVariante(): void
    {
        self::assertSame(303, $this->depuisLaFenetre($this->poncage, ['nature' => 'variante', 'remplace' => (string) $this->base]));
        self::assertSame(['variante', $this->base, 3000], self::enBase($this->poncage));
        // La base a maintenant une variante : sa fenêtre refuse « Option » et « Variante ».
        $page = $this->page("{$this->offre}/lignes/{$this->base}");
        self::assertStringContainsString('value="option" disabled', $page);
        self::assertStringContainsString("Elle a des variantes\u{00A0}: elle reste dans le total.", $page);
    }

    public function testLesVariantesImpossiblesSontRefuseesSansRienEcrire(): void
    {
        $option = $this->inserer($this->jeu->offre['a'], 'Laquage', 1000, 'pce', 10000, 'option', null);
        $chezB = (int) BaseDeTest::ligne('SELECT id FROM ligne WHERE offre_id = ' . $this->jeu->offre['b'])['id'];
        foreach (['elle-même' => $this->poncage, 'une option' => $option, 'une ligne de B' => $chezB, 'rien' => 999999] as $quoi => $remplace) {
            self::assertSame(422, $this->depuisLaFenetre($this->poncage, ['nature' => 'variante', 'remplace' => (string) $remplace, 'quantite' => '7']), $quoi);
            self::assertSame(['ligne', null, 3000], self::enBase($this->poncage), "$quoi : ni la nature ni la quantité n'ont changé");
        }
        $this->inserer($this->jeu->offre['a'], 'Peinture lessivable', 12500, 'm²', 2200, 'variante', $this->base);
        self::assertSame(422, $this->depuisLaFenetre($this->base, ['nature' => 'option', 'designation' => 'Peinture murale a', 'quantite' => '12.5', 'unite' => 'm²', 'prix' => '18.50']));
        self::assertSame('ligne', self::enBase($this->base)[0], 'une ligne qui a des variantes reste dans le total');
    }

    public function testLaListeNeTouchePasALaNature(): void
    {
        BaseDeTest::pdo()->exec("UPDATE ligne SET nature = 'option' WHERE id = {$this->poncage}");
        $this->page($this->offre);
        self::assertSame(303, $this->a->post("{$this->offre}/lignes/{$this->poncage}", ['quantite' => '4'])->getStatusCode());
        self::assertSame(['option', null, 4000], self::enBase($this->poncage));
    }

    public function testAjouterUneOptionDuCatalogue(): void
    {
        $page = $this->page("{$this->offre}/ajouter?nature=option");
        self::assertStringContainsString('aria-current="true">Option</a>', $page);
        $r = $this->a->post("{$this->offre}/lignes", ['produit' => (string) $this->jeu->produit['a'], 'reste' => '1', 'nature' => 'option']);
        self::assertSame(303, $r->getStatusCode());
        self::assertStringContainsString('&nature=option', (string) $r->headers->get('Location'), 'la feuille reste sur « Option »');
        $id = (int) BaseDeTest::ligne('SELECT MAX(id) AS id FROM ligne WHERE offre_id = ' . $this->jeu->offre['a'])['id'];
        self::assertSame(['option', null, 1000], self::enBase($id));
    }

    public function testUneVarianteDuCatalogueReprendLaQuantiteDeSaLigne(): void
    {
        $page = $this->page("{$this->offre}/ajouter?nature=variante&remplace={$this->base}");
        self::assertStringContainsString("<input type=\"hidden\" name=\"remplace\" value=\"{$this->base}\">", $page);
        $r = $this->a->post("{$this->offre}/lignes", ['produit' => (string) $this->jeu->produit['a'], 'reste' => '1', 'nature' => 'variante', 'remplace' => (string) $this->base]);
        self::assertSame(303, $r->getStatusCode());
        $id = (int) BaseDeTest::ligne('SELECT MAX(id) AS id FROM ligne WHERE offre_id = ' . $this->jeu->offre['a'])['id'];
        self::assertSame(['variante', $this->base, 12500], self::enBase($id));
    }

    public function testUneVarianteLibreSansQuantitePrendCelleDeSaLigne(): void
    {
        $this->page("{$this->offre}/ajouter?nature=variante&remplace={$this->base}");
        $r = $this->a->post("{$this->offre}/lignes", ['designation' => 'Peinture minérale', 'quantite' => '', 'unite' => 'm²', 'prix' => '26', 'reste' => '1',
            'nature' => 'variante', 'remplace' => (string) $this->base]);
        self::assertSame(303, $r->getStatusCode());
        $id = (int) BaseDeTest::ligne('SELECT MAX(id) AS id FROM ligne WHERE offre_id = ' . $this->jeu->offre['a'])['id'];
        self::assertSame(['variante', $this->base, 12500], self::enBase($id));
    }

    public function testLaVarianteProposeLaDerniereLigneDuTotal(): void
    {
        $page = $this->page("{$this->offre}/ajouter?nature=variante");
        self::assertStringContainsString("<option value=\"{$this->poncage}\" selected>Ponçage</option>", $page);
        self::assertStringContainsString("<input type=\"hidden\" name=\"remplace\" value=\"{$this->poncage}\">", $page);
    }

    public function testUneVarianteDemandeeSansLigneAuTotalDevientUneOption(): void
    {
        BaseDeTest::pdo()->exec("UPDATE ligne SET nature = 'option' WHERE offre_id = " . $this->jeu->offre['a']);
        $page = $this->page("{$this->offre}/ajouter?nature=variante");
        self::assertStringContainsString('aria-current="true">Option</a>', $page, 'O3 : rien à remplacer');
    }
}
