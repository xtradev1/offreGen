<?php

declare(strict_types=1);

namespace OffreGen\Tests\Client;

use OffreGen\Adresse\Adresse;
use OffreGen\Client\SaisieClient;
use OffreGen\Offre\SaisieRefusee;
use PHPUnit\Framework\TestCase;

final class SaisieClientTest extends TestCase
{
    public function testUneFicheJusteEstNettoyee(): void
    {
        self::assertEquals(
            ['nom' => 'Mme Rossi', 'adresse' => new Adresse('Rue du Stand', '3', '2800', 'Delémont'), 'telephone' => '079 555 44 10', 'courriel' => 'rossi@exemple.ch'],
            SaisieClient::lire(['nom' => '  Mme   Rossi ', 'adresse_rue' => ' Rue du  Stand', 'adresse_numero' => '3', 'adresse_npa' => '2800',
                                'adresse_localite' => 'delemont', 'adresse_pays' => 'CH', 'telephone' => ' 079 555 44 10', 'courriel' => ' rossi@exemple.ch ']),
        );
        // Seul le nom est obligatoire.
        self::assertEquals(['nom' => 'Famille Chappuis', 'adresse' => new Adresse(), 'telephone' => null, 'courriel' => null], SaisieClient::lire(['nom' => 'Famille Chappuis']));
    }

    public function testChaqueChampFauxEstSignale(): void
    {
        try {
            SaisieClient::lire(['nom' => ' ', 'adresse_rue' => 'Rue du Stand', 'adresse_npa' => '0001', 'telephone' => 'abc', 'courriel' => 'pas-un-courriel']);
            self::fail('saisie acceptée');
        } catch (SaisieRefusee $e) {
            self::assertSame(['nom', 'adresse_npa', 'telephone', 'courriel'], array_keys($e->erreurs));
        }
    }
}
