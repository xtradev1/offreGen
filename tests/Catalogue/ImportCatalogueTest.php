<?php

declare(strict_types=1);

namespace OffreGen\Tests\Catalogue;

use OffreGen\Catalogue\Comparateur;
use OffreGen\Catalogue\ComparateurApi;
use OffreGen\Catalogue\ComparateurFactice;
use OffreGen\Catalogue\Integration;
use OffreGen\Catalogue\LecteurFactice;
use OffreGen\Catalogue\Regroupement;
use OffreGen\Catalogue\Ressemblance;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

/**
 * Import du catalogue (chantier 2026-11-20_import-catalogue) : ressemblance de deux libellés, comparaison par le modèle,
 * regroupement des lignes lues en propositions, intégration des réponses de l'artisan.
 *
 * @phpstan-import-type Proposition from Regroupement
 * @phpstan-import-type LigneImport from Regroupement
 */
final class ImportCatalogueTest extends TestCase
{
    /** Un comparateur qui répond « même » pour les paires listées, « autre » pour toutes les autres. */
    private static function comparateur(string ...$memes): ComparateurRegle
    {
        return new ComparateurRegle(array_values($memes));
    }

    /** @return LigneImport */
    private static function ligne(string $designation, ?string $unite, int $prix, int $offre): array
    {
        return ['designation' => $designation, 'unite' => $unite, 'unite_ecrite' => (string) $unite, 'prix_centimes' => $prix, 'offre' => $offre];
    }

    /** @return array{id: int, categorie: string, designation: string, unite: string, prix_centimes: int, tva_pour_dix_mille: int|null, synonymes: string|null, prix_exemple_centimes: int|null} */
    private static function produit(int $id, string $designation, string $unite, int $prix, ?string $synonymes = null): array
    {
        return ['id' => $id, 'categorie' => 'Peinture', 'designation' => $designation, 'unite' => $unite, 'prix_centimes' => $prix,
                'tva_pour_dix_mille' => null, 'synonymes' => $synonymes, 'prix_exemple_centimes' => null];
    }

    /**
     * @param list<Proposition> $propositions
     * @return array<string, Proposition>
     */
    private static function parDesignation(array $propositions): array
    {
        return array_column($propositions, null, 'designation');
    }

    public function testRessemblanceParMotsCommuns(): void
    {
        self::assertSame(1.0, Ressemblance::score('Dispersion plafonds, 2 couches', 'Plafonds, dispersion 2 couches'));
        self::assertSame(0.0, Ressemblance::score('Plinthes carrelées assorties', 'Joints silicone sanitaires'));
        self::assertEqualsWithDelta(0.5, Ressemblance::score('Protection des sols et du mobilier', 'Protection des sols et des meubles'), 0.01);
    }

    public function testComparateurApiEnvoieLesPairesEtLitLesReponses(): void
    {
        $corps = [];
        $http = new MockHttpClient(function (string $m, string $u, array $o) use (&$corps): MockResponse {
            $corps[] = json_decode((string) $o['body'], true);
            return new MockResponse((string) json_encode(['choices' => [['finish_reason' => 'stop', 'message' => ['content' => "1 | même\n2 | autre\nVoilà.\n3 | Même"]]]]));
        });
        $r = (new ComparateurApi($http, 'https://api.exemple.test/v1', '', 'qwen-test', new NullLogger()))
            ->memes([['Lessivage des murs', 'Lavage des murs'], ['Crépi 1,5 mm', 'Crépi 2 mm'], ['Émail sur portes', 'Peinture émail sur portes'], ['A', 'B']]);
        self::assertSame([true, false, true, false], $r, 'une paire sans réponse vaut « autre »');
        self::assertStringContainsString('1. « Lessivage des murs » / « Lavage des murs »', $corps[0]['messages'][1]['content']);
        self::assertSame('none', $corps[0]['reasoning_effort']);
    }

    public function testComparateurApiSansPaireSansAppel(): void
    {
        $http = new MockHttpClient(static fn (): MockResponse => throw new \LogicException('Aucun appel attendu.'));
        self::assertSame([], (new ComparateurApi($http, 'https://api.exemple.test/v1', '', 'qwen-test', new NullLogger()))->memes([]));
    }

    public function testLeComparateurFacticeNeReunitQueLesLibellesPresqueIdentiques(): void
    {
        self::assertSame([true, false], (new ComparateurFactice())->memes([
            ['Dispersion plafonds, 2 couches', 'Plafonds, dispersion 2 couches'], ['Dispersion sur murs', 'Dispersion sur plafonds'],
        ]));
    }

    public function testLeLecteurFacticeLitLesLignesDeTableau(): void
    {
        $r = (new LecteurFactice())->lire(['Pos. | Désignation | Qté | Unité | Prix | Montant', '1.4 | Dispersion sur murs | 62 | m2 | 18.50 | 1\'147.00',
            'Total | 1\'147.00', 'Une phrase sans prix.']);
        self::assertSame([['Dispersion sur murs', 'm²', 1850]], array_map(static fn ($l): array => [$l->designation, $l->unite, $l->prixCentimes], $r->lignes));
    }

    public function testDeuxFormulationsDuMemeTravailFontUneSeulePropositionEtDesMotsReconnus(): void
    {
        $r = new Regroupement(self::comparateur('Dispersion sur murs, 2 couches / Peinture murs 2 couches dispersion'));
        $p = $r->propositions([
            self::ligne('Dispersion sur murs, 2 couches', 'm²', 1850, 0),
            self::ligne('Peinture murs 2 couches dispersion', 'm²', 1850, 1),
            self::ligne('Dispersion sur murs, 2 couches', 'm²', 1850, 2),
        ], []);
        self::assertCount(1, $p);
        self::assertSame(['nouveau', 'Dispersion sur murs, 2 couches', 'm²', 1850, 3], [$p[0]['type'], $p[0]['designation'], $p[0]['unite'], $p[0]['prix'], $p[0]['vu']]);
        self::assertSame(['Peinture murs 2 couches dispersion'], $p[0]['synonymes']);
    }

    public function testLesPairesDUnitesDifferentesOuSansMotCommunNeSontPasDemandees(): void
    {
        $comparateur = self::comparateur();
        (new Regroupement($comparateur))->propositions([
            self::ligne('Peinture des plinthes', 'ml', 750, 0),
            self::ligne('Peinture des portes', 'pce', 9500, 0),
            self::ligne('Évacuation des déchets', 'forfait', 15000, 0),
            self::ligne('Déplacement', 'forfait', 6000, 0),
        ], []);
        self::assertSame([], $comparateur->demandees);
    }

    public function testDeuxPrixPourLeMemeTravailDonnentUneQuestionSansPrixChoisi(): void
    {
        $p = (new Regroupement(self::comparateur('Rebouchage des fissures et ponçage / Rebouchage et ponçage des murs')))->propositions([
            self::ligne('Rebouchage des fissures et ponçage', 'm²', 600, 0),
            self::ligne('Rebouchage et ponçage des murs', 'm²', 650, 2),
        ], []);
        self::assertSame('choix', $p[0]['type']);
        self::assertNull($p[0]['prix']);
        self::assertSame([['prix' => 600, 'source' => 'offre 1'], ['prix' => 650, 'source' => 'offre 3']], $p[0]['options']);
    }

    public function testLeCatalogueDonneLeNomEtLeTypeDeLaProposition(): void
    {
        $catalogue = [
            self::produit(11, 'Dispersion murs, 2 couches', 'm²', 0),
            self::produit(12, 'Déplacement', 'forfait', 6000),
            self::produit(13, 'Protection des sols et des meubles', 'forfait', 18000, 'bâcher'),
        ];
        $p = self::parDesignation((new Regroupement(self::comparateur(
            'Dispersion sur murs, 2 couches / Dispersion murs, 2 couches',
            'Protection des sols et du mobilier / Protection des sols et des meubles',
        )))->propositions([
            self::ligne('Dispersion sur murs, 2 couches', 'm²', 1850, 0),
            self::ligne('Déplacement', 'forfait', 7000, 1),
            self::ligne('Protection des sols et du mobilier', 'forfait', 18000, 0),
        ], $catalogue));
        self::assertSame(['complete', 11, 1850, ['Dispersion sur murs, 2 couches']], [$p['Dispersion murs, 2 couches']['type'], $p['Dispersion murs, 2 couches']['produit_id'], $p['Dispersion murs, 2 couches']['prix'], $p['Dispersion murs, 2 couches']['synonymes']]);
        // Même libellé que le catalogue : réuni sans demander au modèle.
        self::assertSame('choix', $p['Déplacement']['type']);
        self::assertSame([['prix' => 6000, 'source' => 'votre catalogue'], ['prix' => 7000, 'source' => 'offre 2']], $p['Déplacement']['options']);
        self::assertSame(['identique', ['Protection des sols et du mobilier']], [$p['Protection des sols et des meubles']['type'], $p['Protection des sols et des meubles']['synonymes']]);
    }

    public function testDeuxProduitsDuCatalogueNeSontJamaisReunis(): void
    {
        $catalogue = [self::produit(1, 'Dispersion murs', 'm²', 1800), self::produit(2, 'Dispersion murs intérieurs', 'm²', 1900)];
        $p = (new Regroupement(self::comparateur('Dispersion murs / Dispersion murs, 2 couches', 'Dispersion murs intérieurs / Dispersion murs, 2 couches')))
            ->propositions([self::ligne('Dispersion murs, 2 couches', 'm²', 1850, 0)], $catalogue);
        self::assertCount(1, $p);
        self::assertSame(1, $p[0]['produit_id']);
    }

    public function testUneUniteInconnueResteUnNouveauProduitSansUnite(): void
    {
        $p = (new Regroupement(self::comparateur()))->propositions([self::ligne('Sacs de plâtre', null, 1200, 0)], []);
        self::assertSame(['nouveau', null], [$p[0]['type'], $p[0]['unite']]);
    }

    /** @return list<Proposition> */
    private static function propositionsDeLExemple(): array
    {
        return [
            ['type' => 'choix', 'designation' => 'Déplacement', 'unite' => 'forfait', 'prix' => null, 'produit_id' => 12, 'synonymes' => [], 'vu' => 1,
             'options' => [['prix' => 6000, 'source' => 'votre catalogue'], ['prix' => 7000, 'source' => 'offre 2']]],
            ['type' => 'complete', 'designation' => 'Dispersion murs', 'unite' => 'm²', 'prix' => 1850, 'produit_id' => 11, 'synonymes' => ['Peinture murs'], 'vu' => 2, 'options' => []],
            ['type' => 'nouveau', 'designation' => 'Peinture des plinthes', 'unite' => 'ml', 'prix' => 750, 'produit_id' => null, 'synonymes' => ['Plinthes, 2 couches'], 'vu' => 2, 'options' => []],
            ['type' => 'nouveau', 'designation' => 'Peinture de radiateur', 'unite' => 'pce', 'prix' => 12000, 'produit_id' => null, 'synonymes' => [], 'vu' => 1, 'options' => []],
            ['type' => 'identique', 'designation' => 'Protection', 'unite' => 'forfait', 'prix' => 18000, 'produit_id' => 13, 'synonymes' => ['Protection du mobilier'], 'vu' => 1, 'options' => []],
            ['type' => 'choix', 'designation' => 'Rebouchage', 'unite' => 'm²', 'prix' => null, 'produit_id' => null, 'synonymes' => [], 'vu' => 2,
             'options' => [['prix' => 600, 'source' => 'offre 1'], ['prix' => 650, 'source' => 'offre 3']]],
        ];
    }

    public function testLIntegrationDitCeQuIlFautEcrireAuCatalogue(): void
    {
        $r = Integration::actions(self::propositionsDeLExemple(), [
            0 => ['choix' => '1'],
            1 => ['garder' => '1', 'prix' => '18.50'],
            2 => ['garder' => '1', 'designation' => 'Peinture de plinthes', 'unite' => 'ml', 'prix' => '8'],
            3 => [],
            5 => ['choix' => '0'],
        ]);
        self::assertSame([], $r['erreurs']);
        self::assertSame([
            ['prix', 12, 7000],
            ['prix', 11, 1850], ['synonymes', 11, ['Peinture murs']],
            ['creer', ['categorie' => Integration::CATEGORIE, 'designation' => 'Peinture de plinthes', 'unite' => 'ml', 'prix_centimes' => 800, 'synonymes' => 'Plinthes, 2 couches']],
            ['synonymes', 13, ['Protection du mobilier']],
            ['creer', ['categorie' => Integration::CATEGORIE, 'designation' => 'Rebouchage', 'unite' => 'm²', 'prix_centimes' => 600, 'synonymes' => null]],
        ], $r['actions']);
        self::assertSame(['nouveaux' => 2, 'completes' => 1, 'changes' => 1, 'cote' => 1], $r['bilan']);
    }

    public function testUnPrixAChoisirSansReponseOuUneSaisieFausseBloquentTout(): void
    {
        $r = Integration::actions(self::propositionsDeLExemple(), [
            0 => [],
            2 => ['garder' => '1', 'designation' => 'Peinture de plinthes', 'unite' => 'palette', 'prix' => 'cher'],
            5 => ['choix' => '7'],
        ]);
        self::assertSame([0, 2, 5], array_keys($r['erreurs']));
        self::assertSame([], $r['actions']);
    }

    public function testGarderLePrixDuCatalogueNeChangeRienQueLesMotsReconnus(): void
    {
        $r = Integration::actions([
            ['type' => 'choix', 'designation' => 'Déplacement', 'unite' => 'forfait', 'prix' => null, 'produit_id' => 12, 'synonymes' => ['Frais de déplacement'], 'vu' => 1,
             'options' => [['prix' => 6000, 'source' => 'votre catalogue'], ['prix' => 7000, 'source' => 'offre 2']]],
        ], [0 => ['choix' => '0']]);
        self::assertSame([['synonymes', 12, ['Frais de déplacement']]], $r['actions']);
        self::assertSame(0, $r['bilan']['changes']);
    }
}

/** Un comparateur qui répond « même » pour les paires listées (« a / b »), « autre » pour toutes les autres. */
final class ComparateurRegle implements Comparateur
{
    /** @var list<array{string, string}> */
    public array $demandees = [];

    /** @param list<string> $memes */
    public function __construct(private readonly array $memes)
    {
    }

    public function memes(array $paires): array
    {
        $this->demandees = array_merge($this->demandees, $paires);
        return array_map(fn (array $p): bool => in_array($p[0] . ' / ' . $p[1], $this->memes, true) || in_array($p[1] . ' / ' . $p[0], $this->memes, true), $paires);
    }

    public function jetonsConsommes(): array
    {
        return [0, 0];
    }
}
