<?php

declare(strict_types=1);

namespace OffreGen\Tests\Client;

use OffreGen\Client\Rapprochement;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Règle de Xavier (2026-11-12) : « il faut tenter de trouver une approximation et à plus de 80 % on valide,
 * sinon on crée un nouveau ».
 */
final class RapprochementTest extends TestCase
{
    /** @return array<string, array{string, string, float, float}> nom dicté, fiche, ressemblance minimale, maximale */
    public static function paires(): array
    {
        return [
            'civilité abrégée' => ['Madame Rossi', 'Mme Rossi', 1.0, 1.0],
            'majuscules' => ['rossi', 'Rossi', 1.0, 1.0],
            'accents' => ['Muller', 'Müller', 1.0, 1.0],
            'ordre des mots' => ['Rossi Maria', 'Maria Rossi', 1.0, 1.0],
            'nom de famille seul' => ['Rossi', 'Maria Rossi', 0.85, 0.95],
            'faute de transcription' => ['Chapuis', 'Chappuis', 0.85, 0.9],
            'une lettre sur cinq' => ['Rossé', 'Rossi', 0.8, 0.8],
            'autre personne' => ['Schaller', 'Rossi', 0.0, 0.3],
            'madame et monsieur' => ['Madame Rossi', 'Monsieur Rossi', 0.0, 0.5],
            'famille et madame' => ['Famille Rossi', 'Mme Rossi', 1.0, 1.0],
            'vide' => ['', 'Rossi', 0.0, 0.0],
            'civilité seule' => ['Madame', 'Mme Rossi', 0.0, 0.0],
        ];
    }

    #[DataProvider('paires')]
    public function testRessemblance(string $dicte, string $fiche, float $min, float $max): void
    {
        $r = Rapprochement::ressemblance($dicte, $fiche);
        self::assertGreaterThanOrEqual($min, $r, "$dicte / $fiche");
        self::assertLessThanOrEqual($max, $r, "$dicte / $fiche");
        self::assertSame($r, Rapprochement::ressemblance($fiche, $dicte), 'symétrique');
    }

    public function testPlusDe80PourCentRattache(): void
    {
        $clients = [['id' => 1, 'nom' => 'Mme Rossi'], ['id' => 2, 'nom' => 'M. Schaller'], ['id' => 3, 'nom' => 'Famille Chappuis']];
        self::assertSame(1, Rapprochement::meilleur('Madame Rossi', $clients));
        self::assertSame(3, Rapprochement::meilleur('Chapuis', $clients));
        self::assertSame(2, Rapprochement::meilleur('monsieur schaller', $clients));
    }

    public function testA80PourCentOuMoinsRienNEstRattache(): void
    {
        self::assertNull(Rapprochement::meilleur('Rossé', [['id' => 1, 'nom' => 'Rossi']]), '80 % tout juste : pas « plus de 80 % »');
        self::assertNull(Rapprochement::meilleur('Dupont', [['id' => 1, 'nom' => 'Mme Rossi']]));
        self::assertNull(Rapprochement::meilleur('Dupont', []));
        self::assertNull(Rapprochement::meilleur('', [['id' => 1, 'nom' => 'Mme Rossi']]));
    }

    public function testDeuxFichesAEgaliteLArtisanChoisit(): void
    {
        $clients = [['id' => 1, 'nom' => 'Mme Rossi'], ['id' => 2, 'nom' => 'M. Rossi']];
        self::assertNull(Rapprochement::meilleur('Rossi', $clients));
        // La civilité dictée départage.
        self::assertSame(2, Rapprochement::meilleur('Monsieur Rossi', $clients));
    }
}
