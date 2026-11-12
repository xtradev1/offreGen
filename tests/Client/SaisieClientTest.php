<?php

declare(strict_types=1);

namespace OffreGen\Tests\Client;

use OffreGen\Client\SaisieClient;
use OffreGen\Offre\SaisieRefusee;
use PHPUnit\Framework\TestCase;

final class SaisieClientTest extends TestCase
{
    public function testUneFicheJusteEstNettoyee(): void
    {
        self::assertSame(
            ['nom' => 'Mme Rossi', 'adresse' => "Rue du Stand 3\n2800 Delémont", 'telephone' => '079 555 44 10', 'courriel' => 'rossi@exemple.ch'],
            SaisieClient::lire(['nom' => '  Mme   Rossi ', 'adresse' => "Rue du Stand 3\r\n\r\n2800 Delémont", 'telephone' => ' 079 555 44 10', 'courriel' => ' rossi@exemple.ch ']),
        );
        // Seul le nom est obligatoire.
        self::assertSame(['nom' => 'Famille Chappuis', 'adresse' => null, 'telephone' => null, 'courriel' => null], SaisieClient::lire(['nom' => 'Famille Chappuis']));
    }

    public function testChaqueChampFauxEstSignale(): void
    {
        try {
            SaisieClient::lire(['nom' => ' ', 'adresse' => "1\n2\n3\n4\n5", 'telephone' => 'abc', 'courriel' => 'pas-un-courriel']);
            self::fail('saisie acceptée');
        } catch (SaisieRefusee $e) {
            self::assertSame(['nom', 'adresse', 'telephone', 'courriel'], array_keys($e->erreurs));
        }
    }
}
