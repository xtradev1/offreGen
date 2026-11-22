<?php

declare(strict_types=1);

namespace OffreGen\Tests\Offre;

use OffreGen\Offre\Rabais;
use PHPUnit\Framework\TestCase;

final class RabaisTest extends TestCase
{
    public function testLesPucesCinqEtDixPourCentSePassentDeValeur(): void
    {
        [$r, $erreurs] = Rabais::depuisSaisie('Rabais fidélité', '5', 'ignoré');
        self::assertSame([], $erreurs);
        self::assertNotNull($r);
        self::assertSame([500, null, '5'], [$r->pourDixMille, $r->centimes, $r->choix()]);
        self::assertSame('10', Rabais::depuisSaisie('', '10', '')[0]?->choix());
    }

    public function testUnLibelleVideDevientRabais(): void
    {
        self::assertSame('Rabais', Rabais::depuisSaisie("  \n ", '5', '')[0]?->libelle);
    }

    public function testUnAutrePourcentageADeuxDecimales(): void
    {
        $r = Rabais::depuisSaisie('Rabais', 'pct', '7,5')[0];
        self::assertSame([750, 'pct'], [$r?->pourDixMille, $r?->choix()]);
        foreach (['', '0', '100', '-3', 'abc', '12.345'] as $faux) {
            self::assertArrayHasKey('valeur', Rabais::depuisSaisie('Rabais', 'pct', $faux)[1], $faux);
        }
    }

    public function testUnMontantEnFrancs(): void
    {
        $r = Rabais::depuisSaisie('Geste commercial', 'chf', '150')[0];
        self::assertSame([null, 15000, 'chf'], [$r?->pourDixMille, $r?->centimes, $r?->choix()]);
        foreach (['', '0', '-10', 'x'] as $faux) {
            self::assertArrayHasKey('valeur', Rabais::depuisSaisie('Rabais', 'chf', $faux)[1], $faux);
        }
    }

    public function testUnChoixInconnuOuUnLibelleTropLongSontRefuses(): void
    {
        self::assertArrayHasKey('choix', Rabais::depuisSaisie('Rabais', 'tout', '5')[1]);
        self::assertArrayHasKey('libelle', Rabais::depuisSaisie(str_repeat('a', 121), '5', '')[1]);
    }

    public function testLeJsonFaitLAllerRetour(): void
    {
        $liste = [Rabais::pourcent('Rabais été', 750), Rabais::montant('Geste', 15000)];
        $json = Rabais::versJson($liste);
        self::assertSame('[{"libelle":"Rabais été","pour_dix_mille":750,"centimes":null},{"libelle":"Geste","pour_dix_mille":null,"centimes":15000}]', $json);
        self::assertEquals($liste, Rabais::liste($json));
        self::assertNull(Rabais::versJson([]));
    }

    public function testUnJsonAbimeNEmpechePasDOuvrirLOffre(): void
    {
        self::assertSame([], Rabais::liste(null));
        self::assertSame([], Rabais::liste('{pas du json'));
        self::assertSame([], Rabais::liste('"texte"'));
        $restes = Rabais::liste('[{"libelle":"Bon","centimes":100},{"libelle":5},{"libelle":"Négatif","centimes":-5},{"libelle":"Cent","pour_dix_mille":10000}]');
        self::assertCount(1, $restes);
        self::assertSame('Bon', $restes[0]->libelle);
    }
}
