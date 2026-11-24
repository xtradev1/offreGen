<?php

declare(strict_types=1);

namespace OffreGen\Tests\Http;

use OffreGen\Tests\Outils\BaseDeTest;
use OffreGen\Tests\Outils\Jeu;
use OffreGen\Tests\Outils\Monde;
use OffreGen\Tests\Outils\Navigateur;
use PHPUnit\Framework\TestCase;

/**
 * Variantes (chantier documents et réglages, décisions V1 A, V2 A et V3 de Xavier, 2026-11-24) : une variante reprend la
 * quantité et l'unité de la ligne qu'elle remplace, modifiables avant l'ajout ; l'article et le prix changent. Un produit
 * d'une autre unité garde cette quantité et cette unité, et passe dans « Relire ».
 *
 * L'offre du jeu : « Peinture murale a », 12,5 m² à 18.50 (la base) ; le produit du jeu est en m².
 */
final class VarianteQuantiteTest extends TestCase
{
    private Jeu $jeu;
    private Navigateur $a;
    private string $offre;
    private int $base;
    private int $porte;

    protected function setUp(): void
    {
        $this->jeu = new Jeu();
        $this->a = new Navigateur(Monde::conteneur());
        $this->a->connecter('a@exemple.ch');
        $o = $this->jeu->offre['a'];
        $this->offre = '/offres/' . $o;
        $this->base = (int) BaseDeTest::ligne("SELECT id FROM ligne WHERE offre_id = $o")['id'];
        $pdo = BaseDeTest::pdo();
        $pdo->exec("INSERT INTO produit (artisan_id, categorie, designation, unite, prix_centimes) VALUES ({$this->jeu->a}, 'Peinture', 'Laquage de porte', 'pce', 16000)");
        $this->porte = (int) $pdo->lastInsertId();
    }

    private function page(string $chemin): string
    {
        $reponse = $this->a->get($chemin);
        self::assertSame(200, $reponse->getStatusCode(), $chemin);
        return (string) $reponse->getContent();
    }

    /** @param array<string, string> $champs */
    private function ajouter(array $champs): void
    {
        $this->page("{$this->offre}/ajouter?nature=variante&remplace={$this->base}");
        $r = $this->a->post("{$this->offre}/lignes", $champs + ['reste' => '1', 'nature' => 'variante', 'remplace' => (string) $this->base]);
        self::assertSame(303, $r->getStatusCode(), (string) $r->getContent());
    }

    /** @return array{designation: string, quantite_milliemes: int, unite: string, prix_centimes: int, a_verifier: int, nature: string} */
    private function derniere(): array
    {
        $l = BaseDeTest::ligne('SELECT designation, quantite_milliemes, unite, prix_centimes, a_verifier, nature FROM ligne WHERE id = (SELECT MAX(id) FROM ligne WHERE offre_id = ' . $this->jeu->offre['a'] . ')');
        return ['designation' => (string) $l['designation'], 'quantite_milliemes' => (int) $l['quantite_milliemes'], 'unite' => (string) $l['unite'],
            'prix_centimes' => (int) $l['prix_centimes'], 'a_verifier' => (int) $l['a_verifier'], 'nature' => (string) $l['nature']];
    }

    public function testUnProduitDeMemeUniteReprendLaQuantiteSansRelecture(): void
    {
        $this->ajouter(['produit' => (string) $this->jeu->produit['a']]);
        self::assertSame(['designation' => 'Peinture murale a', 'quantite_milliemes' => 12500, 'unite' => 'm²', 'prix_centimes' => 1850, 'a_verifier' => 0, 'nature' => 'variante'], $this->derniere());
    }

    public function testUnProduitDUneAutreUniteReprendQuantiteEtUniteEtPasseDansRelire(): void
    {
        $this->ajouter(['produit' => (string) $this->porte]);
        self::assertSame(['designation' => 'Laquage de porte', 'quantite_milliemes' => 12500, 'unite' => 'm²', 'prix_centimes' => 16000, 'a_verifier' => 1, 'nature' => 'variante'], $this->derniere());
        self::assertStringContainsString('Laquage de porte', $this->page("{$this->offre}/relire"));
    }

    public function testLaQuantiteTapeeVautPourLeProduit(): void
    {
        $this->ajouter(['produit' => (string) $this->jeu->produit['a'], 'reprise' => '30']);
        self::assertSame(30000, $this->derniere()['quantite_milliemes']);
        $this->ajouter(['produit' => (string) $this->jeu->produit['a'], 'reprise' => 'abc']);
        self::assertSame(12500, $this->derniere()['quantite_milliemes'], 'illisible : celle de la ligne remplacée');
    }

    public function testUneLigneLibreRapidePrendLUniteDeLaLigneRemplacee(): void
    {
        $page = $this->page("{$this->offre}/ajouter?nature=variante&remplace={$this->base}&q=Peinture+min%C3%A9rale");
        self::assertMatchesRegularExpression('#<form class="pick-libre".*?<input type="hidden" name="reprise" value="12.5" data-quantite-reprise>\s*<input type="hidden" name="unite" value="m²">#s', $page);
        $this->ajouter(['designation' => 'Peinture minérale', 'reprise' => '30', 'unite' => 'm²', 'prix' => '']);
        self::assertSame(['Peinture minérale', 30000, 'm²'], [$this->derniere()['designation'], $this->derniere()['quantite_milliemes'], $this->derniere()['unite']]);
    }

    public function testLaLigneLibreDetailleeEstPreremplie(): void
    {
        $page = $this->page("{$this->offre}/ajouter?nature=variante&remplace={$this->base}");
        self::assertMatchesRegularExpression('#<input id="libre-quantite" name="quantite" value="12.5"#', $page);
        self::assertStringContainsString('<option selected>m²</option>', $page);
        $this->ajouter(['designation' => 'Peinture lessivable', 'quantite' => '', 'unite' => 'm²', 'prix' => '34']);
        self::assertSame([12500, 'm²', 3400], [$this->derniere()['quantite_milliemes'], $this->derniere()['unite'], $this->derniere()['prix_centimes']]);
    }

    /** Une ligne dictée peut porter une unité hors de la liste : la variante la garde. */
    public function testUneUniteHorsDeLaListeSeGarde(): void
    {
        BaseDeTest::pdo()->exec("UPDATE ligne SET unite = 'rouleau', quantite_milliemes = 8000 WHERE id = {$this->base}");
        $page = $this->page("{$this->offre}/ajouter?nature=variante&remplace={$this->base}");
        self::assertStringContainsString('<option selected>rouleau</option>', $page);
        $this->ajouter(['designation' => 'Papier ingrain', 'quantite' => '8', 'unite' => 'rouleau', 'prix' => '19']);
        self::assertSame([8000, 'rouleau'], [$this->derniere()['quantite_milliemes'], $this->derniere()['unite']]);
    }

    public function testLaPageMontreLaQuantiteRepriseEtLesProduitsARelire(): void
    {
        $page = $this->page("{$this->offre}/ajouter?nature=variante&remplace={$this->base}");
        self::assertMatchesRegularExpression('#<form class="piece-autre remplace-choix".*?<input id="quantite-reprise" name="quantite" value="12.5" inputmode="decimal"#s', $page);
        self::assertStringContainsString('<input type="hidden" name="reprise" value="12.5" data-quantite-reprise>', $page);
        self::assertMatchesRegularExpression('#value="' . $this->porte . '".*?<span data-quantite-texte>12\.5</span>&nbsp;m², à relire#su', $page);
        self::assertDoesNotMatchRegularExpression('#value="' . $this->jeu->produit['a'] . '"[^>]*>[^<]*<b class="pn">Peinture murale a</b>\s*<span>[^<]*</span><small#su', $page, 'même unité : pas de mention');

        $tape = $this->page("{$this->offre}/ajouter?nature=variante&remplace={$this->base}&quantite=30");
        self::assertStringContainsString('<input id="quantite-reprise" name="quantite" value="30"', $tape);
        self::assertStringContainsString('<input type="hidden" name="reprise" value="30" data-quantite-reprise>', $tape);
    }

    public function testHorsVarianteRienNeChange(): void
    {
        $page = $this->page("{$this->offre}/ajouter");
        self::assertStringNotContainsString('quantite-reprise', $page);
        self::assertStringNotContainsString('data-quantite-reprise', $page);
        $r = $this->a->post("{$this->offre}/lignes", ['produit' => (string) $this->porte, 'reste' => '1']);
        self::assertSame(303, $r->getStatusCode());
        self::assertSame(['quantite_milliemes' => 1000, 'unite' => 'pce', 'a_verifier' => 0, 'nature' => 'ligne'],
            array_intersect_key($this->derniere(), array_flip(['quantite_milliemes', 'unite', 'a_verifier', 'nature'])));
    }

    /** Revue : la quantité propre d'une ligne libre détaillée ne remplace pas celle de la variante pour l'ajout suivant. */
    public function testLaQuantiteDeLaLigneLibreNeRemplacePasLaReprise(): void
    {
        $this->page("{$this->offre}/ajouter?nature=variante&remplace={$this->base}&quantite=30");
        $r = $this->a->post("{$this->offre}/lignes", ['designation' => 'Ponçage fin', 'quantite' => '5', 'unite' => 'h', 'prix' => '60', 'reprise' => '30',
            'reste' => '1', 'nature' => 'variante', 'remplace' => (string) $this->base]);
        self::assertStringContainsString('&quantite=30', (string) $r->headers->get('Location'));
        self::assertSame([5000, 'h'], [$this->derniere()['quantite_milliemes'], $this->derniere()['unite']]);
    }

    /** Revue : un formulaire refusé (422) garde la quantité de la variante tapée en haut. */
    public function testUnRefusGardeLaReprise(): void
    {
        $this->page("{$this->offre}/ajouter?nature=variante&remplace={$this->base}&quantite=30");
        $r = $this->a->post("{$this->offre}/lignes", ['designation' => 'Peinture lessivable', 'quantite' => '30', 'unite' => 'm²', 'prix' => 'abc', 'reprise' => '30',
            'reste' => '1', 'nature' => 'variante', 'remplace' => (string) $this->base]);
        self::assertSame(422, $r->getStatusCode());
        self::assertStringContainsString('<input id="quantite-reprise" name="quantite" value="30"', (string) $r->getContent());
    }

    /** Revue : « Garder aussi dans le catalogue » accepte l'unité de la ligne remplacée, même hors de la liste. */
    public function testGarderAuCatalogueAvecLUniteDeLaLigne(): void
    {
        BaseDeTest::pdo()->exec("UPDATE ligne SET unite = 'rouleau', quantite_milliemes = 8000 WHERE id = {$this->base}");
        $this->ajouter(['designation' => 'Papier ingrain', 'quantite' => '', 'unite' => 'rouleau', 'prix' => '19', 'garder' => '1', 'categorie' => 'Peinture']);
        self::assertSame([8000, 'rouleau'], [$this->derniere()['quantite_milliemes'], $this->derniere()['unite']]);
        self::assertSame('rouleau', BaseDeTest::ligne("SELECT unite FROM produit WHERE designation = 'Papier ingrain'")['unite']);
    }

    /** Revue : la ligne remplacée a disparu entre l'affichage et l'envoi : la ligne tapée entre en option, quantité 1. */
    public function testLigneTapeeSansLigneRemplaceeDevientUneOptionDeQuantiteUn(): void
    {
        $this->page("{$this->offre}/ajouter?nature=variante&remplace={$this->base}");
        BaseDeTest::pdo()->exec("UPDATE ligne SET supprimee_le = NOW() WHERE id = {$this->base}");
        $r = $this->a->post("{$this->offre}/lignes", ['designation' => 'Peinture minérale', 'reprise' => '12.5', 'unite' => 'm²', 'prix' => '',
            'reste' => '1', 'nature' => 'variante', 'remplace' => (string) $this->base]);
        self::assertSame(303, $r->getStatusCode());
        self::assertSame(['quantite_milliemes' => 1000, 'nature' => 'option'], array_intersect_key($this->derniere(), array_flip(['quantite_milliemes', 'nature'])));
    }

    /** Revue : « OK » de la quantité garde la recherche en cours. */
    public function testLaQuantiteGardeLaRecherche(): void
    {
        $page = $this->page("{$this->offre}/ajouter?nature=variante&remplace={$this->base}&q=lessiv");
        self::assertMatchesRegularExpression('#<form class="piece-autre quantite-choix".*?<input type="hidden" name="q" value="lessiv">#s', $page);
    }
}
