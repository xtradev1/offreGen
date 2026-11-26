<?php

declare(strict_types=1);

namespace OffreGen\Tests\Adresse;

use OffreGen\Adresse\Adresse;
use OffreGen\Adresse\Localites;
use OffreGen\Adresse\SaisieAdresse;
use PHPUnit\Framework\TestCase;

final class AdresseTest extends TestCase
{
    /**
     * @param array<string, string> $champs
     * @return array{Adresse|null, array<string, string>}
     */
    private static function lire(array $champs): array
    {
        return SaisieAdresse::lire($champs, 'a_');
    }

    public function testRepertoireOfficiel(): void
    {
        self::assertSame(['Delémont'], Localites::de('2800'));
        self::assertSame([], Localites::de('0000'));
        self::assertSame('Lausanne', Localites::seule('1000'));
        self::assertSame('Delémont', Localites::officielle('2800', 'DELEMONT'));
        self::assertSame('Lausanne', Localites::officielle('1000', 'lausanne'));
        self::assertSame('Lausanne 25', Localites::officielle('1000', 'Lausanne 25'));
        self::assertNull(Localites::officielle('2800', 'Porrentruy'));
        self::assertSame('2800', Localites::npaDe('delémont'));
        self::assertSame('st imier', Localites::cle('Saint-Imier'));
        self::assertSame(Localites::cle('St-Imier'), Localites::cle('Saint-Imier'));
    }

    public function testSaisieJuste(): void
    {
        [$a, $e] = self::lire(['a_rue' => '  Rue de la  Préfecture ', 'a_numero' => '2', 'a_npa' => '2800', 'a_localite' => 'delemont', 'a_pays' => 'CH']);
        self::assertSame([], $e);
        self::assertEquals(new Adresse('Rue de la Préfecture', '2', '2800', 'Delémont', 'CH'), $a);
        self::assertSame("Rue de la Préfecture 2\n2800 Delémont", $a?->lignes());
    }

    public function testLeNumeroTapeDansLaRueVaDansSonChamp(): void
    {
        self::assertEquals(new Adresse('Bundesplatz', '3', '3011', 'Bern'), self::lire(['a_rue' => 'Bundesplatz 3', 'a_npa' => '3011'])[0]);
        self::assertEquals(new Adresse('Chemin des Essais', '4a', '1700', 'Fribourg'), self::lire(['a_rue' => 'Chemin des Essais 4 a', 'a_npa' => '1700'])[0]);
        // Un numéro déjà donné ne bouge pas : la rue reste telle quelle.
        self::assertEquals(new Adresse('Route 66', '2', '1700', 'Fribourg'), self::lire(['a_rue' => 'Route 66', 'a_numero' => '2', 'a_npa' => '1700'])[0]);
    }

    public function testLocaliteRempliedepuisLeNpa(): void
    {
        [$a] = self::lire(['a_rue' => 'Rue du Lac', 'a_npa' => '2900']);
        self::assertSame('Porrentruy', $a?->localite);
        [$a] = self::lire(['a_npa' => '1000']);
        self::assertSame('Lausanne', $a?->localite);
    }

    public function testToutVidePermis(): void
    {
        [$a, $e] = self::lire([]);
        self::assertSame([], $e);
        self::assertNotNull($a);
        self::assertTrue($a->estVide());
        self::assertSame('', $a->lignes());
    }

    public function testRefus(): void
    {
        self::assertSame(['a_npa' => 'Indiquez le NPA.', 'a_localite' => 'Indiquez la localité.'], self::lire(['a_rue' => 'Rue 1'])[1]);
        self::assertSame("Un NPA suisse a quatre chiffres, par exemple 2800.", self::lire(['a_npa' => '280', 'a_localite' => 'Delémont'])[1]['a_npa']);
        self::assertSame(['a_npa' => "Ce NPA n'existe pas."], self::lire(['a_npa' => '0001', 'a_localite' => 'X'])[1]);
        self::assertSame(['a_npa' => "Ce NPA n'existe pas."], self::lire(['a_rue' => 'Rue', 'a_npa' => '0001'])[1], 'sans NPA connu, la localité ne se réclame pas');
        self::assertSame('Pour le NPA 2800, la localité est Delémont.', self::lire(['a_npa' => '2800', 'a_localite' => 'Porrentruy'])[1]['a_localite']);
        self::assertSame('Indiquez la rue de ce numéro.', self::lire(['a_numero' => '12', 'a_npa' => '2800'])[1]['a_rue']);
        self::assertSame('Choisissez un pays de la liste.', self::lire(['a_npa' => '75001', 'a_localite' => 'Paris', 'a_pays' => 'XX'])[1]['a_pays']);
        self::assertSame('35 caractères au plus.', self::lire(['a_npa' => '75001', 'a_localite' => str_repeat('x', 36), 'a_pays' => 'FR'])[1]['a_localite']);
        self::assertNull(self::lire(['a_rue' => 'Rue 1'])[0]);
    }

    public function testEtrangerSansRepertoire(): void
    {
        [$a, $e] = self::lire(['a_rue' => 'Rue de Rivoli', 'a_numero' => '10', 'a_npa' => '75001', 'a_localite' => 'Paris', 'a_pays' => 'fr']);
        self::assertSame([], $e);
        self::assertSame("Rue de Rivoli 10\n75001 Paris\nFrance", $a?->lignes());
    }

    public function testDecoupageDuTexteLibre(): void
    {
        self::assertEquals(new Adresse('Rue de l\'Exemple', '12', '2800', 'Delémont'), Adresse::depuisTexte("Rue de l'Exemple 12\n2800 Delémont"));
        self::assertEquals(new Adresse('Chemin des Essais', '4a', '1700', 'Fribourg'), Adresse::depuisTexte("Chemin des Essais 4 a\nCH-1700 Fribourg"));
        self::assertEquals(new Adresse('Route du Test', '18', '2000', 'Neuchâtel'), Adresse::depuisTexte('Route du Test 18, 2000 Neuchâtel'));
        self::assertEquals(new Adresse('Les Prés', '', '2900', 'Porrentruy'), Adresse::depuisTexte("Les Prés\n2900 Porrentruy"));
        self::assertEquals(new Adresse('', '', '2800', 'Delémont'), Adresse::depuisTexte('Delémont'));
        self::assertEquals(new Adresse('', '', '2900', 'Porrentruy'), Adresse::depuisTexte('2900 porrentruy'));
        // À reprendre à la main : trois lignes, NPA inconnu, localité qui ne correspond pas, rien de lisible.
        self::assertNull(Adresse::depuisTexte("c/o Dupont\nRue 1\n2800 Delémont"));
        self::assertNull(Adresse::depuisTexte("Rue 1\n0001 Nulle-part"));
        self::assertNull(Adresse::depuisTexte("Rue 1\n2800 Porrentruy"));
        self::assertNull(Adresse::depuisTexte('chez le client'));
        self::assertNull(Adresse::depuisTexte(null));
    }

    public function testBaseEtComparaison(): void
    {
        $a = new Adresse('Rue de la Préfecture', '2', '2800', 'Delémont');
        self::assertSame(['adresse_rue' => 'Rue de la Préfecture', 'adresse_numero' => '2', 'adresse_npa' => '2800', 'adresse_localite' => 'Delémont', 'adresse_pays' => 'CH'], $a->versBase('adresse_'));
        self::assertEquals($a, Adresse::depuisBase($a->versBase('adresse_'), 'adresse_'));
        self::assertSame(['rue' => null, 'numero' => null, 'npa' => null, 'localite' => null, 'pays' => null], (new Adresse())->versBase(''));
        self::assertTrue(Adresse::depuisBase(['rue' => null, 'pays' => null], '')->estVide());
        self::assertTrue($a->memeQue(new Adresse('rue de la prefecture', '2', '2800', 'Delemont')));
        self::assertFalse($a->memeQue(new Adresse('Rue de la Préfecture', '3', '2800', 'Delémont')));
    }
}
