<?php

declare(strict_types=1);

namespace OffreGen\Tests\Offre;

use OffreGen\Compte\Marque;
use OffreGen\Http\Conteneur;
use OffreGen\Offre\DocumentPdf;
use OffreGen\Tests\Outils\BaseDeTest;
use OffreGen\Tests\Outils\FactureDEssai;
use OffreGen\Tests\Outils\Jeu;
use OffreGen\Tests\Outils\Monde;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Les douze modèles en facture (modèles du bâtiment, lot 8). La section de paiement (QR-facture, norme SIX) garde sa
 * place quel que soit le modèle : sa consigne « À détacher » à 187 mm du haut de la page, soit 530 points, au bord
 * gauche du papier. Courte, la facture la porte au bas de sa page ; longue, sur une page à part (Xavier, 2026-11-12).
 */
final class GabaritsFactureTest extends TestCase
{
    /** 187 mm, en points. */
    private const SECTION = 530.08;

    private Jeu $jeu;
    private Conteneur $c;

    protected function setUp(): void
    {
        $this->jeu = new Jeu();
        $this->c = Monde::conteneur();
        BaseDeTest::pdo()->exec("UPDATE artisan SET telephone = '032 000 00 01', courriel_contact = 'info@peinture-morel.example', contact_nom = 'Luc Morel' WHERE id = {$this->jeu->a}");
        BaseDeTest::pdo()->exec("UPDATE offre SET chantier_adresse = 'Rue du Test 9, 2900 Porrentruy', non_compris = 'Peinture de finition' WHERE id = {$this->jeu->offre['a']}");
    }

    /** @return array<string, array{string}> */
    public static function modeles(): array
    {
        return array_combine(array_keys(Marque::MODELES), array_map(static fn (string $cle): array => [$cle], array_keys(Marque::MODELES)));
    }

    /**
     * Une facture d'acompte tient en une ligne : sa section va au bas de la page. Lettre fait exception : sa formule
     * d'appel, ses phrases et sa signature ne laissent pas la place, la section a toujours sa page.
     */
    #[DataProvider('modeles')]
    public function testUneFactureDUneLigneGardeSaSectionAuBasDeLaPage(string $modele): void
    {
        $pdf = $this->c->documentPdf();
        $marque = new Marque($modele, '#B8470F');
        $essai = $pdf->pages(FactureDEssai::html($this->c, $this->jeu, $marque, 1, 'essai'));
        if ($modele === 'lettre') {
            self::assertGreaterThan(1, $essai, 'si Lettre se met à tenir, retirer cette exception');
            return;
        }
        self::assertSame(1, $essai, 'le premier rendu, avec la marge de la section, tient sur une page');
        $html = FactureDEssai::html($this->c, $this->jeu, $marque, 1, 'bas');
        self::assertSame(1, $pdf->pages($html));
        $blocs = $pdf->blocs($html, ['doc-fin', 'doc-pied', 'doc-bas', 'doc-colonne', 'doc-mentions', 'qr-section']);
        self::assertCount(1, $blocs['qr-section']);
        $section = $blocs['qr-section'][0];
        // Collée au bas du papier, la section commence 0,75 point plus haut que 187 mm : l'épaisseur de son trait pointillé,
        // comme avant les modèles du bâtiment (0,26 mm).
        self::assertEqualsWithDelta(self::SECTION, $section['haut'], 1.0, 'la consigne « À détacher » à 187 mm du haut');
        self::assertEqualsWithDelta(0.0, $section['gauche'], 0.1, 'au bord gauche du papier');
        self::assertEqualsWithDelta(841.9, $section['bas'], 0.6, 'jusqu\'au bas du papier');
        self::assertLessThanOrEqual($section['haut'], $blocs['doc-fin'][0]['bas'], 'les mentions finissent au-dessus de la section');
        self::assertSame([], [...$blocs['doc-pied'], ...$blocs['doc-bas']], 'ni pied ni bande : la section en prend la place');
        self::assertCount(1, $blocs['doc-mentions'], 'le nom et le numéro IDE de l\'entreprise passent sous les mentions');
        foreach ($blocs['doc-colonne'] as $colonne) {
            self::assertLessThanOrEqual($section['haut'] + 0.5, $colonne['bas'], 'la colonne de Latéral s\'arrête au-dessus de la section');
        }
        self::assertStringContainsString('avec la section de paiement ci-dessous', $html);
    }

    #[DataProvider('modeles')]
    public function testUneFactureLonguePorteSaSectionSurUnePageAPart(string $modele): void
    {
        $pdf = $this->c->documentPdf();
        $marque = new Marque($modele, '#B8470F');
        self::assertGreaterThan(1, $pdf->pages(FactureDEssai::html($this->c, $this->jeu, $marque, 30, 'essai')));
        $html = FactureDEssai::html($this->c, $this->jeu, $marque, 30, 'apart');
        $pages = $pdf->pages($html);
        $blocs = $pdf->blocs($html, ['doc-fin', 'doc-mentions', 'qr-section']);
        self::assertCount(1, $blocs['qr-section']);
        $section = $blocs['qr-section'][0];
        self::assertSame($pages, $section['page'], 'la section est sur la dernière page');
        self::assertEqualsWithDelta(self::SECTION, $section['haut'], 0.6, 'la consigne « À détacher » à 187 mm du haut');
        self::assertEqualsWithDelta(0.0, $section['gauche'], 0.1);
        self::assertLessThan($pages, $blocs['doc-fin'][0]['page'], 'la facture finit avant la page de la section');
        self::assertSame([], $blocs['doc-mentions'], 'le pied porte les mentions');
        // L'IBAN d'une facture est celui de sa section de paiement, figé à l'émission : le pied ne répète pas celui des réglages.
        self::assertStringNotContainsString('IBAN CH93', (string) strstr($html, '<body'));
        self::assertStringContainsString('avec la section de paiement de la page suivante', $html);
    }

    /**
     * Latéral : sa colonne de couleur se répète sur chaque page ; sur la page de la section, un cache blanc la couvre du
     * haut du papier jusqu'au trait (Xavier, 2026-11-23 : « je préfère vide »). Les autres modèles n'en ont pas besoin.
     */
    public function testLaPageDeLaSectionDeLateralEstVide(): void
    {
        $pdf = $this->c->documentPdf();
        $html = FactureDEssai::html($this->c, $this->jeu, new Marque('lateral', '#B8470F'), 30, 'apart');
        $pages = $pdf->pages($html);
        $blocs = $pdf->blocs($html, ['qr-cache', 'qr-section', 'doc-colonne']);
        self::assertCount(1, $blocs['qr-cache']);
        $cache = $blocs['qr-cache'][0];
        $section = $blocs['qr-section'][0];
        $colonne = $blocs['doc-colonne'][0];
        self::assertSame($pages, $cache['page'], 'le cache est sur la page de la section');
        self::assertLessThanOrEqual(0.5, $cache['haut'], 'depuis le haut du papier');
        self::assertGreaterThanOrEqual($section['haut'], $cache['bas'], "jusqu'au trait");
        self::assertLessThanOrEqual($colonne['gauche'], $cache['gauche']);
        self::assertGreaterThanOrEqual($colonne['droite'], $cache['droite'], 'toute la largeur de la colonne');
        $sobre = FactureDEssai::html($this->c, $this->jeu, new Marque('sobre', '#B8470F'), 30, 'apart');
        self::assertSame([], $pdf->blocs($sobre, ['qr-cache'])['qr-cache'], 'rien chez les autres');
    }

    #[DataProvider('modeles')]
    public function testUneFactureAnnuleeLeDitEtNePorteAucuneSection(string $modele): void
    {
        $html = FactureDEssai::html($this->c, $this->jeu, new Marque($modele, '#B8470F'), 2, 'bas', [], true);
        self::assertStringContainsString('<span class="doc-annulee">Annulée le 08.11.2026</span>', $html);
        self::assertStringContainsString('Facture annulée le 08.11.2026&nbsp;: elle ne doit pas être payée.', $html);
        self::assertStringNotContainsString('class="qr-section', $html);
        self::assertStringNotContainsString('section de paiement', (string) strstr($html, '<body'));
        self::assertSame(1, $this->c->documentPdf()->pages($html));
    }

    /** Une facture ne se signe pas et ne montre pas le chantier de l'offre ; elle dit d'où elle vient. */
    #[DataProvider('modeles')]
    public function testUneFactureDitSaReferenceSansChantierNiAccord(string $modele): void
    {
        $corps = (string) strstr(FactureDEssai::html($this->c, $this->jeu, new Marque($modele, '#B8470F'), 2, 'bas'), '<body');
        self::assertStringContainsString($modele === 'lettre' ? 'Nous vous adressons notre facture pour les travaux suivants. Selon notre offre n°&nbsp;1 du 07.11.2026.' : 'selon offre n°&nbsp;1 du 07.11.2026', $corps);
        self::assertStringContainsString('2026-001', $corps);
        foreach (['Bon pour accord', 'Rue du Test 9', 'Non compris', 'Peinture de finition', 'valable jusqu', 'Valable jusqu'] as $absent) {
            self::assertStringNotContainsString($absent, $corps);
        }
    }

    /** La section de paiement est en Helvetica (norme SIX) ; le reste, dans les polices du document. */
    #[DataProvider('modeles')]
    public function testLePdfDUneFactureNEmploiePasDePoliceDeRepli(string $modele): void
    {
        $pdf = $this->c->documentPdf()->rendre(FactureDEssai::html($this->c, $this->jeu, new Marque($modele, '#B8470F'), 6, 'apart'));
        preg_match_all('#/BaseFont /(?:[A-Z]{6}\+)?([A-Za-z0-9]+)#', $pdf, $trouvees);
        $permises = implode('|', array_map(static fn (string $f): string => str_replace(' ', '', $f), array_keys(DocumentPdf::FACES)));
        foreach (array_unique($trouvees[1]) as $police) {
            self::assertMatchesRegularExpression("/^($permises|Helvetica)/", $police, implode(', ', array_unique($trouvees[1])));
        }
    }
}
