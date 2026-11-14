<?php

declare(strict_types=1);

namespace OffreGen\Tests\Compte;

use OffreGen\Compte\Iban;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * La QR-facture n'accepte qu'un IBAN suisse ou liechtensteinois. Exemples vérifiés à part (modulo 97 en Python).
 */
final class IbanTest extends TestCase
{
    /** @return iterable<string, array{string, string|null}> */
    public static function saisies(): iterable
    {
        yield 'exemple de la norme, en groupes' => ['CH93 0076 2011 6238 5295 7', 'CH9300762011623852957'];
        yield 'minuscules et espaces' => [' ch93 0076 2011 6238 5295 7 ', 'CH9300762011623852957'];
        yield 'QR-IBAN' => ['CH44 3199 9123 0008 8901 2', 'CH4431999123000889012'];
        yield 'Liechtenstein, lettres au bout' => ['LI21 0881 0000 2324 013A A', 'LI21088100002324013AA'];
        yield 'vide permis' => ['  ', ''];
        yield 'clé fausse' => ['CH93 0076 2011 6238 5295 8', null];
        yield 'IBAN allemand, juste mais refusé' => ['DE89 3704 0044 0532 0130 00', null];
        yield 'trop court' => ['CH93 0076 2011 6238 5295', null];
        yield 'signe interdit' => ['CH93-0076-2011-6238-5295-7', null];
    }

    #[DataProvider('saisies')]
    public function testSaisie(string $saisie, ?string $attendu): void
    {
        self::assertSame($attendu, Iban::lire($saisie));
    }

    public function testLeQrIbanSeReconnaitASonNumeroDeBanque(): void
    {
        self::assertTrue(Iban::estQrIban('CH4431999123000889012'), 'IID 31999');
        self::assertFalse(Iban::estQrIban('CH9300762011623852957'), 'IID 00762');
        self::assertFalse(Iban::estQrIban(''));
    }

    public function testFormeLisible(): void
    {
        self::assertSame('CH93 0076 2011 6238 5295 7', Iban::lisible('CH9300762011623852957'));
        self::assertSame('', Iban::lisible(''));
    }
}
