<?php

declare(strict_types=1);

namespace OffreGen\Tests\Offre;

use OffreGen\Offre\Offerte;
use PHPUnit\Framework\TestCase;

/**
 * Ligne offerte (décisions D1, D2 et D17 de Xavier, 2026-11-21) : taper 0 offre la ligne, « Offert » seul ;
 * « Offerte » dans la fenêtre de la ligne garde le prix, barré ; un prix vide reste « Prix manquant ».
 */
final class OfferteTest extends TestCase
{
    public function testUnPrixZeroNonOffertEstManquant(): void
    {
        self::assertTrue(Offerte::prixManquant(['prix_centimes' => 0, 'offerte' => 0]));
        self::assertTrue(Offerte::prixManquant(['prix_centimes' => 0]), 'une ligne sans la colonne (extraction) est manquante');
        self::assertFalse(Offerte::prixManquant(['prix_centimes' => 0, 'offerte' => 1]));
        self::assertFalse(Offerte::prixManquant(['prix_centimes' => 2800, 'offerte' => 0]));
    }

    /** Liste des lignes : seul le champ touché part ; pas de choix « Facturée / Offerte ». */
    public function testDansLaListeZeroOffreEtVideRendManquant(): void
    {
        self::assertTrue(Offerte::apresSaisie(false, 2800, '0', 0, null));
        self::assertTrue(Offerte::apresSaisie(false, 0, '0.00', 0, null));
        self::assertFalse(Offerte::apresSaisie(true, 0, '', 0, null), 'vider le prix d\'une ligne offerte la rend manquante');
        self::assertFalse(Offerte::apresSaisie(true, 2800, ' ', 0, null));
    }

    public function testDansLaListeUnVraiPrixRefactureUneLigneOfferteAZero(): void
    {
        self::assertFalse(Offerte::apresSaisie(true, 0, '45', 4500, null));
    }

    public function testDansLaListeChangerLePrixDUneLigneOfferteBarreLaLaisseOfferte(): void
    {
        self::assertTrue(Offerte::apresSaisie(true, 2800, '30', 3000, null));
    }

    public function testDansLaListeChangerLaQuantiteNeToucheARien(): void
    {
        self::assertTrue(Offerte::apresSaisie(true, 2800, null, 2800, null));
        self::assertFalse(Offerte::apresSaisie(false, 2800, null, 2800, null));
    }

    /** Fenêtre de la ligne : le choix envoyé fait foi, sauf un 0 tapé à l'instant (D2), même laissé sur « Facturée ». */
    public function testDansLaFenetreLeChoixFaitFoi(): void
    {
        self::assertTrue(Offerte::apresSaisie(false, 2800, '28', 2800, 'offerte'));
        self::assertFalse(Offerte::apresSaisie(true, 2800, '28', 2800, 'facturee'));
    }

    public function testDansLaFenetreUnZeroTapeOffreMemeSurFacturee(): void
    {
        self::assertTrue(Offerte::apresSaisie(false, 2800, '0', 0, 'facturee'));
    }

    public function testDansLaFenetreRepasserEnFactureeUneLigneOfferteAZeroLaRendManquante(): void
    {
        // Le 0 était déjà là : « Facturée » l'emporte, la ligne attend un prix.
        self::assertFalse(Offerte::apresSaisie(true, 0, '0', 0, 'facturee'));
    }
}
