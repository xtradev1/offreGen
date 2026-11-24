<?php

declare(strict_types=1);

namespace OffreGen\Tests\Http;

use OffreGen\Tests\Outils\BaseDeTest;
use OffreGen\Tests\Outils\Jeu;
use OffreGen\Tests\Outils\Monde;
use OffreGen\Tests\Outils\Navigateur;
use PHPUnit\Framework\TestCase;

/**
 * Trouvailles retenues de la revue (docs/plans/2026-11-22_options-variantes/REVUE.md), côté gestes.
 * L'offre du jeu : « Peinture murale a », 12,5 m² à 18.50 (la base) ; on y ajoute « Ponçage », 3 h.
 */
final class RevueGestesTest extends TestCase
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
        $this->offre = '/offres/' . $this->jeu->offre['a'];
        $this->base = (int) BaseDeTest::ligne('SELECT id FROM ligne WHERE offre_id = ' . $this->jeu->offre['a'])['id'];
        $this->poncage = $this->inserer('Ponçage', 3000, 'h', 6000, 'ligne', null);
    }

    private function inserer(string $designation, int $q, string $u, int $prix, string $nature, ?int $remplace, int $retenue = 0): int
    {
        $pdo = BaseDeTest::pdo();
        $pdo->prepare('INSERT INTO ligne (artisan_id, offre_id, rang, designation, quantite_milliemes, unite, prix_centimes, nature, remplace_id, retenue)
                       VALUES (:a, :o, 50, :d, :q, :u, :p, :n, :r, :t)')
            ->execute(['a' => $this->jeu->a, 'o' => $this->jeu->offre['a'], 'd' => $designation, 'q' => $q, 'u' => $u, 'p' => $prix, 'n' => $nature, 'r' => $remplace, 't' => $retenue]);
        return (int) $pdo->lastInsertId();
    }

    private function page(string $chemin): string
    {
        $r = $this->a->get($chemin);
        self::assertSame(200, $r->getStatusCode(), $chemin);
        return (string) $r->getContent();
    }

    /** @return array{string, int|null, int} */
    private static function enBase(int $ligne): array
    {
        $l = BaseDeTest::ligne("SELECT nature, remplace_id, retenue FROM ligne WHERE id = $ligne");
        return [(string) $l['nature'], $l['remplace_id'] === null ? null : (int) $l['remplace_id'], (int) $l['retenue']];
    }

    /** C1 : corriger la variante d'une ligne supprimée ne la rattache pas à une autre ; « Annuler » sur la base la remet. */
    public function testLaVarianteDUneLigneSupprimeeGardeSaLigne(): void
    {
        $variante = $this->inserer('Lessivable', 12500, 'm²', 2200, 'variante', $this->base, 1);
        $this->page($this->offre);
        $this->a->post("{$this->offre}/lignes/{$this->base}/supprimer", []);
        $page = $this->page("{$this->offre}/lignes/$variante");
        self::assertStringContainsString('name="nature" value="option" checked', $page, 'elle se lit comme une option (O3)');
        self::assertSame(303, $this->a->post("{$this->offre}/lignes/$variante", ['designation' => 'Lessivable', 'quantite' => '12.5', 'unite' => 'm²',
            'prix' => '23', 'nature' => 'option', 'remplace' => (string) $this->poncage])->getStatusCode());
        self::assertSame(['variante', $this->base, 1], self::enBase($variante), 'ni rattachée au ponçage, ni décochée');
        $this->page($this->offre);
        self::assertSame(303, $this->a->post("{$this->offre}/lignes/{$this->base}/restaurer", [])->getStatusCode());
        self::assertStringContainsString('Retenue, à la place', $this->page($this->offre));
    }

    /** C3 : deux variantes d'une ligne supprimée se lisent comme deux options, que le client retient l'une et l'autre. */
    public function testDeuxVariantesOrphelinesSeRetiennentChacune(): void
    {
        $v1 = $this->inserer('Lessivable', 12500, 'm²', 2200, 'variante', $this->base);
        $v2 = $this->inserer('Minérale', 12500, 'm²', 2600, 'variante', $this->base);
        BaseDeTest::pdo()->exec("UPDATE ligne SET supprimee_le = NOW() WHERE id = {$this->base}");
        foreach ([$v1, $v2] as $v) {
            $this->page($this->offre);
            self::assertSame(303, $this->a->post("{$this->offre}/lignes/$v/retenue", ['retenue' => '1'])->getStatusCode());
        }
        self::assertSame([1, 1], [self::enBase($v1)[2], self::enBase($v2)[2]]);
    }

    /**
     * C6, remplacée par V1 A de Xavier (2026-11-24, chantier documents et réglages) : une variante du catalogue dans une
     * autre unité que sa ligne prend la quantité et l'unité de la ligne, le prix du produit, et passe dans « Relire »
     * (42 m² ne font pas 42 rouleaux : l'artisan corrige).
     */
    public function testUneVarianteDuCatalogueDAutreUnitePasseDansRelire(): void
    {
        $produit = (int) $this->jeu->produit['a'];
        BaseDeTest::pdo()->exec("UPDATE ligne SET unite = 'rouleau' WHERE id = {$this->base}");
        $this->page("{$this->offre}/ajouter?nature=variante&remplace={$this->base}");
        $this->a->post("{$this->offre}/lignes", ['produit' => (string) $produit, 'reste' => '1', 'nature' => 'variante', 'remplace' => (string) $this->base]);
        $l = BaseDeTest::ligne('SELECT quantite_milliemes, unite, nature, a_verifier FROM ligne WHERE offre_id = ' . $this->jeu->offre['a'] . ' ORDER BY id DESC LIMIT 1');
        self::assertSame([12500, 'rouleau', 'variante', 1], [(int) $l['quantite_milliemes'], (string) $l['unite'], (string) $l['nature'], (int) $l['a_verifier']]);
    }

    /** C7 : la ligne à remplacer a disparu entre l'affichage et l'envoi : la ligne entre en option, pas en variante d'une autre. */
    public function testUneLigneARemplacerDisparueDonneUneOption(): void
    {
        $this->page("{$this->offre}/ajouter?nature=variante&remplace={$this->poncage}");
        BaseDeTest::pdo()->exec("UPDATE ligne SET supprimee_le = NOW() WHERE id = {$this->poncage}");
        $this->a->post("{$this->offre}/lignes", ['designation' => 'Ponçage fin', 'quantite' => '2', 'unite' => 'h', 'prix' => '70', 'reste' => '1',
            'nature' => 'variante', 'remplace' => (string) $this->poncage]);
        $l = BaseDeTest::ligne('SELECT nature, remplace_id FROM ligne WHERE offre_id = ' . $this->jeu->offre['a'] . ' ORDER BY id DESC LIMIT 1');
        self::assertSame(['option', null], [(string) $l['nature'], $l['remplace_id']]);
    }
}
