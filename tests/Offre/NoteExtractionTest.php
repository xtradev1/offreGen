<?php

declare(strict_types=1);

namespace OffreGen\Tests\Offre;

use OffreGen\Offre\NoteExtraction;
use PHPUnit\Framework\TestCase;

/**
 * La note du banc (sous-chantier 29, « La note » ; options et variantes, lot 7).
 *
 * @phpstan-import-type Proposee from NoteExtraction
 */
final class NoteExtractionTest extends TestCase
{
    /** @return Proposee */
    private static function p(?int $produit, string $designation, int $q, string $u = 'm²', int $prix = 0, ?int $dicte = null, string $nature = 'ligne', ?int $remplace = null): array
    {
        return ['produitId' => $produit, 'designation' => $designation, 'quantiteMilliemes' => $q, 'unite' => $u, 'prixCentimes' => $prix,
            'prixDicteCentimes' => $dicte, 'nature' => $nature, 'remplace' => $remplace];
    }

    public function testToutJuste(): void
    {
        $note = NoteExtraction::noter(
            [['produit_id' => 4, 'designation' => 'Peinture des murs', 'quantite' => 42, 'prix_dicte' => null],
             ['produit_id' => null, 'designation' => 'Pose de papier peint', 'quantite' => 1, 'unite' => 'forfait', 'prix' => 380]],
            [self::p(4, 'Peinture des murs, 2 couches', 42000, 'm²', 2800), self::p(null, 'Pose du papier peint', 1000, 'fft', 38000)],
        );
        self::assertSame([2, 2, [], [], 0], [$note['attendues'], $note['justes'], $note['fausses'], $note['en_trop'], $note['corrections']]);
        self::assertSame(1.0, $note['taux']);
    }

    public function testQuantiteFausseEtLigneEnTrop(): void
    {
        $note = NoteExtraction::noter(
            [['produit_id' => 4, 'designation' => 'Peinture des murs', 'quantite' => 42], ['produit_id' => 5, 'designation' => 'Plafonds', 'quantite' => 24]],
            [self::p(4, 'Peinture des murs', 40000), self::p(5, 'Plafonds', 24000), self::p(null, 'Bloc', 1000, 'forfait')],
        );
        self::assertSame(1, $note['justes']);
        self::assertSame([['ligne' => 1, 'designation' => 'Peinture des murs', 'raisons' => ['quantite']]], $note['fausses']);
        self::assertSame(['Bloc'], $note['en_trop']);
        // 1 juste sur 2 attendues + 1 en trop.
        self::assertEqualsWithDelta(1 / 3, $note['taux'], 1e-9);
        self::assertSame(2, $note['corrections']);
    }

    public function testPrixDicteEtLigneAbsente(): void
    {
        $note = NoteExtraction::noter(
            [['produit_id' => 4, 'designation' => 'Murs', 'quantite' => 42, 'prix_dicte' => 25], ['produit_id' => 11, 'designation' => 'Déplacement', 'quantite' => 1]],
            [self::p(4, 'Murs', 42000, 'm²', 2800, null)],
        );
        self::assertSame([['ligne' => 1, 'designation' => 'Murs', 'raisons' => ['prix dicte']], ['ligne' => 2, 'designation' => 'Déplacement', 'raisons' => ['absente']]], $note['fausses']);
    }

    public function testLaNatureEtLaLigneRemplacee(): void
    {
        $attendues = [['produit_id' => 4, 'designation' => 'Murs', 'quantite' => 42],
            ['produit_id' => null, 'designation' => 'Peinture lessivable 2 couches', 'quantite' => 42, 'unite' => 'm²', 'prix' => 34, 'nature' => 'variante', 'remplace' => 0],
            ['produit_id' => 6, 'designation' => 'Plinthes', 'quantite' => 26, 'nature' => 'option']];
        // Juste : la variante désigne la proposée qui porte la ligne de base (rang 1 ici), l'option est une option.
        $juste = NoteExtraction::noter($attendues, [self::p(null, 'Peinture lessivable, deux couches', 42000, 'm2', 3400, null, 'variante', 1),
            self::p(4, 'Murs', 42000), self::p(6, 'Plinthes', 26000, 'm', 1200, null, 'option')]);
        self::assertSame(3, $juste['justes']);
        self::assertSame(['justes' => 2, 'total' => 2], $juste['natures']);
        // Faux : la variante sans base, l'option restée dans le total.
        $faux = NoteExtraction::noter($attendues, [self::p(4, 'Murs', 42000), self::p(null, 'Peinture lessivable, deux couches', 42000, 'm²', 3400, null, 'variante', null),
            self::p(6, 'Plinthes', 26000)]);
        self::assertSame([['ligne' => 2, 'designation' => 'Peinture lessivable 2 couches', 'raisons' => ['base']],
            ['ligne' => 3, 'designation' => 'Plinthes', 'raisons' => ['nature']]], $faux['fausses']);
        self::assertSame(['justes' => 1, 'total' => 2], $faux['natures']);
    }

    public function testUneLigneLibreTropDifferenteNeSApparitPas(): void
    {
        $note = NoteExtraction::noter([['produit_id' => null, 'designation' => 'Pose de papier peint', 'quantite' => 1, 'unite' => 'forfait', 'prix' => 380]],
            [self::p(null, 'Nettoyage de fin de chantier', 1000, 'forfait', 38000)]);
        self::assertSame(['absente'], $note['fausses'][0]['raisons']);
        self::assertSame(['Nettoyage de fin de chantier'], $note['en_trop']);
    }
}
