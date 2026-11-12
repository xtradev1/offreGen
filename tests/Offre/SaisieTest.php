<?php

declare(strict_types=1);

namespace OffreGen\Tests\Offre;

use OffreGen\Catalogue\SaisieProduit;
use OffreGen\Http\Formats;
use OffreGen\Offre\Saisie;
use OffreGen\Offre\SaisieRefusee;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class SaisieTest extends TestCase
{
    /** @return iterable<string, array{string, int|null}> */
    public static function quantites(): iterable
    {
        yield 'entier' => ['42', 42000];
        yield 'point' => ['12.5', 12500];
        yield 'virgule' => ['12,5', 12500];
        yield 'trois décimales' => ['0,333', 333];
        yield 'apostrophe' => ["1'250", 1250000];
        yield 'apostrophe typographique' => ['1’250.5', 1250500];
        yield 'espaces' => [' 3 ', 3000];
        yield 'zéro refusé' => ['0', null];
        yield 'négatif refusé' => ['-2', null];
        yield 'quatre décimales refusées' => ['1.2345', null];
        yield 'texte refusé' => ['douze', null];
        yield 'vide refusé' => ['', null];
        yield 'au-delà du million refusé' => ['1000001', null];
        yield 'deux points refusés' => ['1.2.3', null];
    }

    #[DataProvider('quantites')]
    public function testQuantite(string $saisie, ?int $attendu): void
    {
        self::assertSame($attendu, Saisie::milliemes($saisie));
    }

    /** @return iterable<string, array{string, int|null}> */
    public static function prix(): iterable
    {
        yield 'francs' => ['28', 2800];
        yield 'centimes' => ['28.50', 2850];
        yield 'un chiffre après la virgule' => ['28,5', 2850];
        yield 'notation suisse 28.-' => ['28.-', 2800];
        yield 'milliers' => ["2'600.00", 260000];
        yield 'vide vaut prix manquant' => ['', 0];
        yield 'espaces seuls valent prix manquant' => ['  ', 0];
        yield 'zéro' => ['0', 0];
        yield 'trois décimales refusées' => ['1.005', null];
        yield 'négatif refusé' => ['-5', null];
        yield 'devise refusée' => ['CHF 28', null];
        yield 'au-delà du million refusé' => ['1000000.01', null];
    }

    #[DataProvider('prix')]
    public function testPrix(string $saisie, ?int $attendu): void
    {
        self::assertSame($attendu, Saisie::centimes($saisie));
    }

    public function testCeQueLEcranAfficheSeRelitALIdentique(): void
    {
        foreach ([1, 333, 12500, 42000, 1250500] as $q) {
            self::assertSame($q, Saisie::milliemes(Formats::saisieQuantite($q)), "quantité $q");
        }
        foreach ([1, 5, 2800, 2850, 260000] as $p) {
            self::assertSame($p, Saisie::centimes(Formats::saisiePrix($p)), "prix $p");
        }
    }

    public function testUniteHorsListeSeulementSiCEstLActuelle(): void
    {
        self::assertSame('m²', Saisie::unite('m²'));
        self::assertNull(Saisie::unite('litre'));
        self::assertSame('litre', Saisie::unite('litre', 'litre'));
    }

    public function testTexteReduitLesEspacesEtRefuseLeVide(): void
    {
        self::assertSame('Peinture des murs', Saisie::texte("  Peinture \n des   murs ", 255));
        self::assertNull(Saisie::texte('   ', 255));
        self::assertNull(Saisie::texte(str_repeat('a', 256), 255));
    }

    public function testProduitValideEtSynonymesSansDoublon(): void
    {
        $p = SaisieProduit::lire(['designation' => 'Crépi', 'unite' => 'm²', 'prix' => '34,50', 'categorie' => 'Façade',
            'nouvelle_categorie' => '', 'synonymes' => 'crépi, Crépi ,  rustique,,']);
        self::assertSame(['categorie' => 'Façade', 'designation' => 'Crépi', 'unite' => 'm²', 'prix_centimes' => 3450, 'synonymes' => 'crépi, rustique'], $p);
    }

    public function testNouvelleCategorieLEmporte(): void
    {
        $p = SaisieProduit::lire(['designation' => 'Crépi', 'unite' => 'm²', 'prix' => '', 'categorie' => 'Façade',
            'nouvelle_categorie' => ' Isolation ', 'synonymes' => '']);
        self::assertSame('Isolation', $p['categorie']);
        self::assertSame(0, $p['prix_centimes']);
        self::assertSame(['synonymes' => null], array_intersect_key($p, ['synonymes' => 0]));
    }

    public function testProduitFauxDonneUnMessageParChamp(): void
    {
        try {
            SaisieProduit::lire(['designation' => ' ', 'unite' => 'litre', 'prix' => 'cher', 'categorie' => '', 'nouvelle_categorie' => '', 'synonymes' => '']);
            self::fail('Saisie acceptée');
        } catch (SaisieRefusee $e) {
            self::assertSame(['designation', 'unite', 'prix', 'categorie'], array_keys($e->erreurs));
        }
    }
}
