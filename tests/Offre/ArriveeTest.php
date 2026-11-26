<?php

declare(strict_types=1);

namespace OffreGen\Tests\Offre;

use OffreGen\Offre\Arrivee;
use PHPUnit\Framework\TestCase;

/**
 * Où mène une offre (Xavier, 2026-11-26) : la relecture tant qu'une ligne est à relire, le choix du client tant qu'il
 * manque, le PDF sinon. Une ligne est à relire si elle est marquée « à vérifier » ou si son prix manque ; une ligne
 * offerte à 0 a son prix.
 */
final class ArriveeTest extends TestCase
{
    public function testUneLigneEstARelireSiElleEstIncertaineOuSansPrix(): void
    {
        self::assertFalse(Arrivee::aRelire(['prix_centimes' => 1850, 'a_verifier' => 0, 'offerte' => 0]), 'ligne juste');
        self::assertTrue(Arrivee::aRelire(['prix_centimes' => 1850, 'a_verifier' => 1, 'offerte' => 0]), 'ligne incertaine');
        self::assertTrue(Arrivee::aRelire(['prix_centimes' => 0, 'a_verifier' => 0, 'offerte' => 0]), 'prix manquant');
        self::assertFalse(Arrivee::aRelire(['prix_centimes' => 0, 'a_verifier' => 0, 'offerte' => 1]), 'offerte à 0 : le prix ne manque pas');
        self::assertTrue(Arrivee::aRelire(['prix_centimes' => 0, 'a_verifier' => 1, 'offerte' => 1]), 'offerte mais incertaine');
    }

    public function testLeCompteNeCompteChaqueLigneQuUneFois(): void
    {
        self::assertSame(0, Arrivee::compter([]));
        self::assertSame(2, Arrivee::compter([
            ['prix_centimes' => 1850, 'a_verifier' => 0, 'offerte' => 0],
            ['prix_centimes' => 0, 'a_verifier' => 1, 'offerte' => 0],
            ['prix_centimes' => 0, 'a_verifier' => 0, 'offerte' => 0],
        ]), 'une ligne incertaine et sans prix compte une fois');
    }

    public function testLAdresseSuitLEtatDeLOffre(): void
    {
        self::assertSame('/offres/7/relire', Arrivee::adresse(['id' => 7, 'client_id' => 3], 2, 5), 'des lignes à relire : la relecture');
        self::assertSame('/offres/7/relire', Arrivee::adresse(['id' => 7, 'client_id' => null], 1, 5), 'la relecture passe avant le client');
        self::assertSame('/offres/7/client?suite=apercu', Arrivee::adresse(['id' => 7, 'client_id' => null], 0, 5), 'client à confirmer');
        self::assertSame('/offres/7/apercu', Arrivee::adresse(['id' => 7, 'client_id' => 3], 0, 5), 'offre propre : le PDF');
    }

    /** Revue du 2026-11-26 (I3) : une offre sans ligne n'est pas « propre » ; son PDF serait vide. Elle s'ouvre elle-même. */
    public function testUneOffreSansLigneSOuvreElleMeme(): void
    {
        self::assertSame('/offres/7', Arrivee::adresse(['id' => 7, 'client_id' => 3], 0, 0));
        self::assertSame('/offres/7', Arrivee::adresse(['id' => 7, 'client_id' => null], 0, 0));
    }
}
