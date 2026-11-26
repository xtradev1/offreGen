<?php

declare(strict_types=1);

namespace OffreGen\Tests\Audio;

use OffreGen\Audio\VocabulaireDictee;
use PHPUnit\Framework\TestCase;

/**
 * Le catalogue de l'artisan, mis en forme pour le prompt de Whisper (sous-chantier 30, lot 8). La forme est celle
 * mesurée au banc du 2026-11-24 : « Offre. » puis les désignations séparées par des virgules.
 */
final class VocabulaireDicteeTest extends TestCase
{
    public function testLaFormeMesureeAuBanc(): void
    {
        self::assertSame('Offre. Sol travertin, Laquage des plinthes, Peinture des murs, 2 couches.',
            VocabulaireDictee::depuis(['Sol travertin', 'Laquage des plinthes', 'Peinture des murs, 2 couches']));
    }

    public function testUnCatalogueVideNeDonneAucunVocabulaire(): void
    {
        self::assertSame('', VocabulaireDictee::depuis([]));
        self::assertSame('', VocabulaireDictee::depuis(['', '   ']));
    }

    public function testLesDoublonsEtLesEspacesSontRetires(): void
    {
        self::assertSame('Offre. Crépi, Déplacement.',
            VocabulaireDictee::depuis(['  Crépi ', 'crépi', "Déplacement\n", 'CRÉPI']));
    }

    public function testAuDelaDuPlafondLesDerniersProduitsTombentEntiers(): void
    {
        $designations = [];
        for ($i = 1; $i <= 100; $i++) {
            $designations[] = sprintf('Produit numéro %03d', $i);
        }
        $vocabulaire = VocabulaireDictee::depuis($designations);

        self::assertLessThanOrEqual(VocabulaireDictee::LONGUEUR_MAX, mb_strlen($vocabulaire));
        self::assertStringStartsWith('Offre. Produit numéro 001, Produit numéro 002,', $vocabulaire);
        // Aucun produit coupé : le texte finit sur une désignation entière, suivie du point.
        self::assertMatchesRegularExpression('/, Produit numéro \d{3}\.$/u', $vocabulaire);
        self::assertStringNotContainsString('Produit numéro 100', $vocabulaire);
    }

    public function testUneDesignationPlusLongueQueLePlafondEstLaissee(): void
    {
        self::assertSame('Offre. Déplacement.',
            VocabulaireDictee::depuis([str_repeat('x', VocabulaireDictee::LONGUEUR_MAX), 'Déplacement']));
    }
}
