<?php

declare(strict_types=1);

namespace OffreGen\Tests\Facture;

use OffreGen\Compte\Reglages;
use OffreGen\Facture\Montants;
use PHPUnit\Framework\TestCase;

/**
 * Brouillon : la TVA et l'arrondi du compte, les acomptes émis jusqu'ici. Émise ou annulée : ce qui a été figé.
 */
final class MontantsTest extends TestCase
{
    /** 287 400 centimes HT, comme la facture de la maquette. */
    private const LIGNES = [['quantite_milliemes' => 1000, 'prix_centimes' => 287400]];

    /** @return array{statut: string, genre: string, tva_pour_dix_mille: int|null, arrondi_cinq: int|null, deja_facture: int|null} */
    private static function facture(string $statut, string $genre = 'finale', ?int $taux = null, ?int $arrondi = null, ?int $deja = null): array
    {
        return ['statut' => $statut, 'genre' => $genre, 'tva_pour_dix_mille' => $taux, 'arrondi_cinq' => $arrondi, 'deja_facture' => $deja];
    }

    public function testUnBrouillonPrendLesReglagesDuCompte(): void
    {
        $m = Montants::de(self::facture('brouillon'), self::LIGNES, new Reglages('X', 810, true), 0);
        self::assertSame([287400, 23279, 1, 310680, 810, 0, 310680], [$m['ht'], $m['tva'], $m['arrondi'], $m['ttc'], $m['taux'], $m['deja'], $m['a_payer']]);
    }

    public function testLaFinaleDeduitLesAcomptesEmis(): void
    {
        $m = Montants::de(self::facture('brouillon'), self::LIGNES, new Reglages('X', 810, true), 93205);
        self::assertSame([310680, 93205, 217475], [$m['ttc'], $m['deja'], $m['a_payer']]);
    }

    public function testUnAcompteNeDeduitRien(): void
    {
        $m = Montants::de(self::facture('brouillon', 'acompte'), self::LIGNES, new Reglages('X', 810, true), 93205);
        self::assertSame([0, 310680], [$m['deja'], $m['a_payer']]);
    }

    public function testUneFactureEmiseGardeSesTauxMemeSiLeCompteChange(): void
    {
        $m = Montants::de(self::facture('emise', 'finale', 810, 1, 93205), self::LIGNES, new Reglages('X', 0, false), 0);
        self::assertSame([810, 23279, 1, 310680, 93205, 217475], [$m['taux'], $m['tva'], $m['arrondi'], $m['ttc'], $m['deja'], $m['a_payer']]);
    }

    public function testUneFactureAnnuleeAussi(): void
    {
        $m = Montants::de(self::facture('annulee', 'finale', 0, 0, 0), self::LIGNES, new Reglages('X', 810, true), 5000);
        self::assertSame([0, 0, 287400, 0, 287400], [$m['taux'], $m['tva'], $m['ttc'], $m['deja'], $m['a_payer']]);
    }
}
