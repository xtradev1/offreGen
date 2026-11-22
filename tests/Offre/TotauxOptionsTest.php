<?php

declare(strict_types=1);

namespace OffreGen\Tests\Offre;

use OffreGen\Offre\Rabais;
use OffreGen\Offre\Totaux;
use PHPUnit\Framework\TestCase;

/**
 * L'offre de la maquette (MAQUETTE-options-variantes.html), TVA 8,1 %, total aux 5 centimes, rabais de 5 %. Chaque
 * attendu est calculé à la main dans le commentaire qui le précède ; le prototype affichait les mêmes montants.
 */
final class TotauxOptionsTest extends TestCase
{
    /** @return list<array{id: int, quantite_milliemes: int, prix_centimes: int, nature: string, remplace_id: int|null, retenue: int}> */
    private static function offre(int ...$retenues): array
    {
        $l = static fn (int $id, int $q, int $p, string $n = 'ligne', ?int $r = null): array => ['id' => $id, 'quantite_milliemes' => $q * 1000,
            'prix_centimes' => $p, 'nature' => $n, 'remplace_id' => $r, 'retenue' => in_array($id, $retenues, true) ? 1 : 0];
        return [
            $l(1, 42, 2800),                    // Peinture des murs, séjour : 1'176.00
            $l(2, 42, 3400, 'variante', 1),     // Peinture lessivable : 1'428.00
            $l(3, 24, 3200),                    // Plafonds : 768.00
            $l(4, 30, 2800),                    // Murs, chambre : 840.00
            $l(5, 26, 1200, 'option'),          // Plinthes : 312.00
            $l(6, 1, 8500, 'option'),           // Radiateur : 85.00
            $l(7, 1, 6000),                     // Déplacement : 60.00
            $l(8, 1, 12000),                    // Protection : 120.00
            $l(9, 3, 14000, 'option'),          // Portes : 420.00
            $l(10, 24, 4500, 'variante', 3),    // Plafond acoustique : 1'080.00
        ];
    }

    /** @return list<Rabais> */
    private static function cinqPourcent(): array
    {
        return [Rabais::pourcent('Rabais fidélité', 500)];
    }

    public function testRienDeRetenu(): void
    {
        // Lignes du total : 1176 + 768 + 840 + 60 + 120 = 2'964.00 ; rabais 148.20 ; HT 2'815.80 ;
        // TVA 228.0798 → 228.08 ; 3'043.88 → 3'043.90 (arrondi 0.02).
        $t = Totaux::calculer(self::offre(), 810, true, self::cinqPourcent());
        self::assertSame([117600, 0, 76800, 84000, 0, 0, 6000, 12000, 0, 0], $t['lignes'], 'une ligne hors du total compte 0, comme une ligne offerte');
        self::assertSame([296400, 14820, 281580, 22808, 2, 304390], [$t['sous_total'], $t['rabais'][0]['montant'], $t['ht'], $t['tva'], $t['arrondi'], $t['ttc']]);
        // Avec toutes les options, sans les variantes (point 4) : 2964 + 312 + 85 + 420 = 3'781.00 ; rabais 5 % sur tout
        // (22.11) : 189.05 ; HT 3'591.95 ; TVA 290.94795 → 290.95 ; 3'882.90.
        self::assertSame(['rabais' => 18905, 'ht' => 359195, 'tva' => 29095, 'arrondi' => 0, 'ttc' => 388290], $t['avec_options']);
        self::assertSame([['taux' => 810, 'ht' => 281580, 'tva' => 22808]], Totaux::parTaux(self::offre(), 810, self::cinqPourcent()));
    }

    public function testLaLessivableEtLesPortesRetenues(): void
    {
        // 2964 - 1176 + 1428 + 420 = 3'636.00 ; rabais 181.80 ; HT 3'454.20 ; TVA 279.7902 → 279.79 ; 3'733.99 → 3'734.00.
        $t = Totaux::calculer(self::offre(2, 9), 810, true, self::cinqPourcent());
        self::assertSame([363600, 345420, 27979, 1, 373400], [$t['sous_total'], $t['ht'], $t['tva'], $t['arrondi'], $t['ttc']]);
        // Restent les plinthes et le radiateur : 3636 + 312 + 85 = 4'033.00 ; rabais 201.65 ; HT 3'831.35 ;
        // TVA 310.33935 → 310.34 ; 4'141.69 → 4'141.70.
        self::assertSame(['rabais' => 20165, 'ht' => 383135, 'tva' => 31034, 'arrondi' => 1, 'ttc' => 414170], $t['avec_options']);
    }

    public function testLeClientRenonceAuxPortes(): void
    {
        // 3636 - 420 = 3'216.00 ; rabais 160.80 ; HT 3'055.20 ; TVA 247.4712 → 247.47 ; 3'302.67 → 3'302.65 (arrondi -0.02).
        $t = Totaux::calculer(self::offre(2), 810, true, self::cinqPourcent());
        self::assertSame([321600, 305520, 24747, -2, 330265], [$t['sous_total'], $t['ht'], $t['tva'], $t['arrondi'], $t['ttc']]);
    }

    public function testToutRetenuPlusDOptionLibre(): void
    {
        $t = Totaux::calculer(self::offre(2, 5, 6, 9), 810, true, self::cinqPourcent());
        self::assertNull($t['avec_options']);
    }

    public function testUnRabaisEnFrancsResteLeMemeAvecLesOptions(): void
    {
        // Lignes : 2'964.00 - 100.00 = HT 2'864.00. Avec les options : 3'781.00 - 100.00 = HT 3'681.00.
        $rabais = [Rabais::montant('Geste', 10000)];
        $t = Totaux::calculer(self::offre(), 810, true, $rabais);
        self::assertSame([10000, 286400], [$t['rabais'][0]['montant'], $t['ht']]);
        self::assertNotNull($t['avec_options']);
        self::assertSame([10000, 368100], [$t['avec_options']['rabais'], $t['avec_options']['ht']]);
    }

    public function testUneOptionNeCreePasDeGroupeDeTva(): void
    {
        // 100.00 à 8,1 % ; une option de 50.00 à 2,6 % : pas de « TVA 2,6 % : 0.00 » sur le document.
        $lignes = [['id' => 1, 'quantite_milliemes' => 1000, 'prix_centimes' => 10000],
                   ['id' => 2, 'quantite_milliemes' => 1000, 'prix_centimes' => 5000, 'tva_pour_dix_mille' => 260, 'nature' => 'option', 'remplace_id' => null, 'retenue' => 0]];
        self::assertSame([['taux' => 810, 'ht' => 10000, 'tva' => 810]], Totaux::parTaux($lignes, 810));
        // Avec elle : 8.10 + 1.30 = 9.40 de TVA, 159.40.
        self::assertSame(['rabais' => 0, 'ht' => 15000, 'tva' => 940, 'arrondi' => 0, 'ttc' => 15940], Totaux::calculer($lignes, 810)['avec_options']);
    }
}
