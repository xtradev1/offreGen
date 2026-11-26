<?php

declare(strict_types=1);

namespace OffreGen\Tests\Donnees;

use OffreGen\Donnees\FabriqueConnexion;
use OffreGen\Tests\Outils\BaseDeTest;
use PHPUnit\Framework\TestCase;

final class FabriqueConnexionTest extends TestCase
{
    public function testLaConnexionSurvitAuxAttentesDuModele(): void
    {
        $attente = self::variable(BaseDeTest::fabrique()->pdo(), 'wait_timeout');

        self::assertSame((string) FabriqueConnexion::ATTENTE_SANS_REQUETE_SECONDES, $attente);
        // Extraction (600 s au plus) puis pièces (180 s au plus), sans requête entre les deux.
        self::assertGreaterThan(600 + 180, FabriqueConnexion::ATTENTE_SANS_REQUETE_SECONDES);
    }

    public function testLesDatesRestentEnUtc(): void
    {
        self::assertSame('+00:00', self::variable(BaseDeTest::fabrique()->pdo(), 'time_zone'));
    }

    private static function variable(\PDO $pdo, string $nom): string
    {
        $requete = $pdo->query('SELECT @@SESSION.' . $nom);
        self::assertNotFalse($requete);
        return (string) $requete->fetchColumn();
    }
}
