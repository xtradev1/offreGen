<?php

declare(strict_types=1);

namespace OffreGen\Tests\Catalogue;

use OffreGen\Catalogue\CatalogueFictif;
use OffreGen\Offre\Saisie;
use PHPUnit\Framework\TestCase;

/**
 * Les neuf catalogues de démonstration (huit métiers et le général, décision de Xavier du 2026-11-19) :
 * contenu dans docs/plans/2026-11-19_metiers-demo/CATALOGUES.md.
 */
final class CatalogueFictifTest extends TestCase
{
    private const CLES = ['peintre', 'platrier', 'carreleur', 'macon', 'sanitaire', 'menuisier', 'sols', 'paysagiste', 'autre'];

    public function testNeufMetiersDansLOrdreDAffichage(): void
    {
        self::assertSame(self::CLES, CatalogueFictif::metiers());
        self::assertSame(self::CLES, array_keys(CatalogueFictif::libelles()));
    }

    public function testChaqueMetierAUnLibelle(): void
    {
        foreach (CatalogueFictif::libelles() as $cle => $libelle) {
            self::assertNotSame('', trim($libelle), $cle);
            self::assertSame($libelle, CatalogueFictif::libelle($cle));
        }
        self::assertSame('Poseur de sols', CatalogueFictif::libelle('sols'));
        self::assertSame('Autre métier', CatalogueFictif::libelle('autre'));
    }

    public function testLibelleDUnMetierInconnuRefuse(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        CatalogueFictif::libelle('boulanger');
    }

    public function testChaqueCatalogueADouzeProduitsUniquesAuxUnitesAdmises(): void
    {
        foreach (self::CLES as $metier) {
            $produits = CatalogueFictif::produits($metier);
            self::assertCount(12, $produits, $metier);
            self::assertCount(12, array_unique(array_column($produits, 'designation')), $metier);
            foreach ($produits as $p) {
                self::assertContains($p['unite'], Saisie::UNITES, "$metier : {$p['designation']}");
                self::assertNotSame('', $p['categorie'], "$metier : {$p['designation']}");
                self::assertNotSame('', $p['synonymes'], "$metier : {$p['designation']}");
            }
        }
    }

    /** Xavier veut des prix partout (2026-11-19) ; le peintre garde sa ligne à 0, qui montre « à compléter ». */
    public function testSeulLePeintreAUnPrixManquant(): void
    {
        foreach (self::CLES as $metier) {
            $sansPrix = array_filter(CatalogueFictif::produits($metier), static fn (array $p): bool => $p['prix_centimes'] <= 0);
            self::assertCount($metier === 'peintre' ? 1 : 0, $sansPrix, $metier);
        }
    }

    public function testChaqueMetierADeuxOffresTireesDeSonCatalogue(): void
    {
        foreach (self::CLES as $metier) {
            $designations = array_column(CatalogueFictif::produits($metier), 'designation');
            $offres = CatalogueFictif::offres($metier);
            self::assertCount(2, $offres, $metier);
            self::assertCount(2, array_unique(array_column($offres, 'il_y_a_jours')), "$metier : la plus ancienne reçoit le numéro 1");
            foreach ($offres as $o) {
                self::assertNotSame('', $o['titre'], $metier);
                self::assertNotSame('', $o['client'], $metier);
                self::assertStringContainsString("\n", $o['adresse'], "$metier : rue, puis numéro postal et localité");
                self::assertGreaterThanOrEqual(3, count($o['lignes']), "$metier : {$o['titre']}");
                foreach ($o['lignes'] as $designation => $quantite) {
                    self::assertContains($designation, $designations, "$metier : {$o['titre']}");
                    self::assertGreaterThan(0, $quantite, "$metier : $designation");
                }
            }
        }
    }

    public function testLeParquetEstChezLePoseurDeSolsEtLeMenuisierVendDesFenetres(): void
    {
        $sols = array_column(CatalogueFictif::produits('sols'), 'prix_centimes', 'designation');
        self::assertSame(9500, $sols['Parquet chêne contrecollé, pose collée']);
        $menuisier = array_column(CatalogueFictif::produits('menuisier'), 'prix_centimes', 'designation');
        self::assertArrayNotHasKey('Parquet chêne contrecollé, pose collée', $menuisier);
        self::assertSame(120000, $menuisier['Fenêtre bois, double vitrage, posée']);
    }
}
