<?php

declare(strict_types=1);

namespace OffreGen\Tests\Audio;

use OffreGen\Audio\RapportDictees;
use PHPUnit\Framework\TestCase;

/** Les chiffres de la page des dictées (sous-chantier 31, D1 et D2) : colonnes, statistiques, marques, frise. */
final class RapportDicteesTest extends TestCase
{
    /** @return array{id: int, uuid: string, artisan_id: int, recu_le: string, pret_le: string|null, statut: string, tentatives: int, taille: int, type_mime: string, navigateur: string|null, origine: string|null, duree_ms: int|null, offre_id: int|null} */
    private static function dictee(int $id): array
    {
        return ['id' => $id, 'uuid' => "u$id", 'artisan_id' => 1, 'recu_le' => '2026-11-24 11:05:43', 'pret_le' => null, 'statut' => 'pret',
            'tentatives' => 0, 'taille' => 1000, 'type_mime' => 'audio/webm', 'navigateur' => null, 'origine' => 'micro', 'duree_ms' => 1000, 'offre_id' => null];
    }

    /** @return array{enregistrement_id: int, etape: string, tentative: int, processus: string, debut: string, fin: string|null, duree_ms: int|null, issue: string, erreur: string|null, modele: string|null, jetons_envoyes: int|null, jetons_recus: int|null, premier_octet_ms: int|null} */
    private static function etape(int $id, string $etape, string $debut, ?string $fin, int $tentative = 1, string $issue = 'ok', ?int $recus = null): array
    {
        return ['enregistrement_id' => $id, 'etape' => $etape, 'tentative' => $tentative, 'processus' => 'cron', 'debut' => $debut, 'fin' => $fin,
            'duree_ms' => $fin === null ? null : RapportDictees::ecart($debut, $fin), 'issue' => $issue, 'erreur' => null, 'modele' => $recus === null ? null : 'Qwen',
            'jetons_envoyes' => $recus === null ? null : 1000, 'jetons_recus' => $recus, 'premier_octet_ms' => null];
    }

    public function testStatistiquesMedianeCentileEtMaximum(): void
    {
        self::assertSame(['n' => 4, 'mediane' => 25.0, 'centile90' => 1000, 'max' => 1000, 'max_id' => 4],
            RapportDictees::stat([1 => 10, 2 => 20, 3 => 30, 4 => 1000]));
        self::assertSame(['n' => 3, 'mediane' => 20.0, 'centile90' => 30, 'max' => 30, 'max_id' => 3], RapportDictees::stat([1 => 20, 2 => 10, 3 => 30]));
        self::assertSame(0, RapportDictees::stat([])['n']);
    }

    /** L'offre 87 : deux tentatives, extraction additionnée, reprise dans l'attente, jetons des deux appels. */
    public function testUneLigneAdditionneLesTentativesEtGardeLeTotal(): void
    {
        $etapes = [
            self::etape(87, 'reception', '2026-11-24 11:05:43.000', '2026-11-24 11:05:43.600'),
            self::etape(87, 'ia_extraction', '2026-11-24 11:06:51.500', '2026-11-24 11:07:50.900', 1, 'ok', 793),
            self::etape(87, 'ecriture', '2026-11-24 11:07:52.400', null, 1, 'interrompue'),
            self::etape(87, 'reprise', '2026-11-24 11:07:52.400', '2026-11-24 11:21:00.300', 2),
            self::etape(87, 'ia_extraction', '2026-11-24 11:21:00.300', '2026-11-24 11:21:06.300', 2, 'ok', 776),
        ];
        [$l] = RapportDictees::lignes([self::dictee(87)], $etapes);

        self::assertSame(600, $l['colonnes']['envoi']);
        self::assertSame(787900, $l['colonnes']['attente']);
        self::assertSame(65400, $l['colonnes']['extraction']);
        self::assertNull($l['colonnes']['options']);
        self::assertSame([2000, 1569], $l['jetons']['ia_extraction']);
        self::assertSame(923300, $l['total']);
        self::assertSame(923300 - 600 - 787900 - 65400 - 0, $l['hors']);
        self::assertTrue($l['accroc']);
    }

    public function testUneDureePlusDeDeuxFoisLaMedianeEtPlusDUneSecondeEstMarquee(): void
    {
        $dictees = [];
        $etapes = [];
        foreach ([1 => 6000, 2 => 5000, 3 => 7000, 4 => 59400] as $id => $ms) {
            $dictees[] = self::dictee($id);
            $etapes[] = self::etape($id, 'ia_extraction', '2026-11-24 10:00:00.000', (new \DateTimeImmutable('2026-11-24 10:00:00'))->modify("+$ms milliseconds")->format('Y-m-d H:i:s.v'));
            // 40 ms contre 15 ms de médiane : trop court pour être marqué.
            $etapes[] = self::etape($id, 'attente', '2026-11-24 09:59:59.000', '2026-11-24 09:59:59.' . ($id === 4 ? '040' : '015'));
        }
        $lignes = RapportDictees::lignes($dictees, $etapes);
        $lignes = RapportDictees::marquer($lignes, RapportDictees::statistiques($lignes));

        self::assertSame([[], [], [], ['extraction']], array_column($lignes, 'lentes'));
    }

    public function testLaDureePour100JetonsSepareUnAppelLongDeBeaucoupDeJetons(): void
    {
        $lignes = RapportDictees::lignes([self::dictee(1), self::dictee(2)], [
            self::etape(1, 'ia_extraction', '2026-11-24 10:00:00.000', '2026-11-24 10:00:06.000', 1, 'ok', 776),
            self::etape(2, 'ia_extraction', '2026-11-24 10:00:00.000', '2026-11-24 10:00:59.400', 1, 'ok', 793),
        ]);
        $stats = RapportDictees::statistiques($lignes);
        self::assertSame(['n' => 2, 'max' => 7491, 'max_id' => 2], array_intersect_key($stats['par_jetons']['extraction'], ['n' => 0, 'max' => 0, 'max_id' => 0]));
    }

    /** Revue M1 : une étape sans durée (interrompue) n'entre pas dans sa colonne ni dans les statistiques. */
    public function testUneEtapeSansDureeLaisseLaColonneVide(): void
    {
        [$l] = RapportDictees::lignes([self::dictee(1)], [
            self::etape(1, 'reception', '2026-11-24 10:00:00.000', '2026-11-24 10:00:00.400'),
            self::etape(1, 'ecriture', '2026-11-24 10:00:01.000', null, 1, 'interrompue'),
        ]);
        self::assertNull($l['colonnes']['ecriture']);
        self::assertTrue($l['accroc']);
        self::assertSame(0, RapportDictees::statistiques([$l])['colonnes']['ecriture']['n']);
    }

    /** Revue M9 : une reprise seule dans sa tentative (le passage repris est mort aussitôt) reste sur la frise. */
    public function testUneRepriseSeuleResteSurLaFrise(): void
    {
        $blocs = RapportDictees::frise([
            self::etape(1, 'attente', '2026-11-24 10:00:00.000', '2026-11-24 10:00:00.100'),
            self::etape(1, 'reprise', '2026-11-24 10:00:00.100', '2026-11-24 10:02:40.000', 2),
        ]);
        self::assertCount(2, $blocs);
        self::assertSame([[], 'reprise'], [$blocs[1]['lignes'], $blocs[1]['reprise']['etape'] ?? null]);
    }

    public function testLaFriseMetUnBlocParTentativeEtLaRepriseAPart(): void
    {
        $blocs = RapportDictees::frise([
            self::etape(87, 'attente', '2026-11-24 11:05:43.600', '2026-11-24 11:05:43.650'),
            self::etape(87, 'ia_extraction', '2026-11-24 11:05:43.650', '2026-11-24 11:05:53.650'),
            self::etape(87, 'ecriture', '2026-11-24 11:05:53.650', null, 1, 'interrompue'),
            self::etape(87, 'reprise', '2026-11-24 11:05:53.650', '2026-11-24 11:08:30.000', 2),
            self::etape(87, 'ecriture', '2026-11-24 11:08:30.000', '2026-11-24 11:08:30.200', 2),
        ]);

        self::assertCount(2, $blocs);
        self::assertNull($blocs[0]['reprise']);
        self::assertSame(['attente', 'ia_extraction', 'ecriture'], array_column($blocs[0]['lignes'], 'etape'));
        // Bloc de 10,05 s : l'extraction commence 50 ms après l'attente (5 sur 1000), l'écriture ouverte à la fin (997, 3 de large).
        self::assertSame([0, 5, 997], array_column($blocs[0]['lignes'], 'x'));
        self::assertSame(3, $blocs[0]['lignes'][2]['largeur']);
        self::assertSame(['fa', 'f3', 'fx'], array_column($blocs[0]['lignes'], 'classe'));
        self::assertSame('reprise', $blocs[1]['reprise']['etape'] ?? null);
        self::assertSame(['ecriture'], array_column($blocs[1]['lignes'], 'etape'));
    }
}
