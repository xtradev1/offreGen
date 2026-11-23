<?php

declare(strict_types=1);

namespace OffreGen\Tests\Offre;

use OffreGen\Compte\Marque;
use OffreGen\Donnees\Portee;
use OffreGen\Http\Conteneur;
use OffreGen\Http\Controleur\OffreControleur;
use OffreGen\Tests\Outils\Jeu;
use OffreGen\Tests\Outils\Monde;
use OffreGen\Tests\Outils\OffreDEssai;
use OffreGen\Tests\Outils\TextePdf;
use PHPUnit\Framework\TestCase;

/**
 * PDF resserré (Xavier, 2026-11-23 : la page 2 qui ne portait que conditions et signatures) : un document qui déborde
 * est essayé resserré, gardé s'il y gagne une page ; sinon la mise en page normale reste.
 *
 * @phpstan-import-type Ligne from \OffreGen\Donnees\DepotOffres
 */
final class PdfSerreTest extends TestCase
{
    private Jeu $jeu;
    private Conteneur $c;

    protected function setUp(): void
    {
        $this->jeu = new Jeu();
        $this->c = Monde::conteneur();
    }

    /** @return list<Ligne> $n postes en deux pièces, une variante du premier, deux options */
    private static function lignes(int $n): array
    {
        $l = static fn (int $id, string $d, string $nature = 'ligne', ?int $remplace = null, ?string $piece = null): array => ['id' => $id, 'offre_id' => 0, 'rang' => $id,
            'designation' => $d, 'quantite_milliemes' => 12000, 'unite' => 'm²', 'prix_centimes' => 2200, 'offerte' => 0, 'a_verifier' => 0, 'entendu' => null,
            'produit_id' => null, 'categorie' => null, 'tva_pour_dix_mille' => null, 'tva_ligne' => null, 'prix_dicte_centimes' => null, 'prix_catalogue' => null,
            'piece' => $piece, 'nature' => $nature, 'remplace_id' => $remplace, 'retenue' => 0];
        $lignes = [];
        foreach (range(1, $n) as $i) {
            $lignes[] = $l($i, "Poste de travaux numéro $i", 'ligne', null, $i <= intdiv($n, 2) ? 'Salon' : 'Chambre');
        }
        return [...$lignes, $l(100, 'Variante du poste 1', 'variante', 1, 'Salon'), $l(101, 'Option A', 'option', null, 'Salon'), $l(102, 'Option B', 'option', null, 'Chambre')];
    }

    /** @return array<string, mixed> */
    private function vue(int $n): array
    {
        return (new OffreControleur($this->c))->vueDocument(new Portee($this->jeu->a), OffreDEssai::offre(), self::lignes($n), new Marque('sobre', '#B8470F'));
    }

    public function testUneOffreQuiDebordeDePeuTientSurUnePageResserree(): void
    {
        $pdf = $this->c->documentPdf();
        $vue = $this->vue(5);
        self::assertSame(2, $pdf->pages(OffreControleur::htmlPdf($this->c, $vue)), 'sans resserrer, le bloc de fin passe en page 2');
        self::assertSame(1, $pdf->pages(OffreControleur::htmlPdf($this->c, ['serre' => true] + $vue)));
        $pages = TextePdf::pages(OffreControleur::rendrePdf($this->c, $vue));
        self::assertCount(1, $pages, 'rendrePdf garde la version resserrée');
        self::assertStringContainsString('Lieu, date et signature du client', $pages[0]);
    }

    public function testSansPageGagneeLaMiseEnPageNormaleReste(): void
    {
        $pdf = $this->c->documentPdf();
        $vue = $this->vue(12);
        $normal = $pdf->pages(OffreControleur::htmlPdf($this->c, $vue));
        self::assertGreaterThan(1, $normal);
        self::assertSame($normal, $pdf->pages(OffreControleur::htmlPdf($this->c, ['serre' => true] + $vue)), 'resserrer ne gagne pas de page');
        self::assertCount($normal, TextePdf::pages(OffreControleur::rendrePdf($this->c, $vue)));
        self::assertDoesNotMatchRegularExpression('/class="doc [^"]*doc-serre/', OffreControleur::htmlPdf($this->c, $vue), 'la classe ne vient que sur demande');
        self::assertMatchesRegularExpression('/class="doc m-sobre doc-serre"/', OffreControleur::htmlPdf($this->c, ['serre' => true] + $vue));
    }
}
