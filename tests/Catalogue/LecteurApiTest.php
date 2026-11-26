<?php

declare(strict_types=1);

namespace OffreGen\Tests\Catalogue;

use OffreGen\Catalogue\LecteurApi;
use OffreGen\Catalogue\UniteLue;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

/** Import du catalogue depuis d'anciennes offres (chantier 2026-11-20_import-catalogue) : lecture d'une offre par le modèle. */
final class LecteurApiTest extends TestCase
{
    private const TEXTE = [
        'Peinture Exemple Sàrl',
        'Pos. | Désignation | Qté | Unité | Prix unitaire | Total CHF',
        '1.4 | Dispersion sur murs, 2 couches | 62 | m2 | 18.50 | 1\'147.00',
        '2.2 | Peinture des plinthes | 38 | m\' | 7.50 | 285.00',
        '3.1 | Installation de chantier | 1 | Ft | 4 506,30 | 4 506,30',
        'Main-d\'œuvre jardinier | CHF 1\'248.00',
        '16 heures à CHF 78.-',
        'Total TTC, CHF | 7\'154.17',
    ];

    /** @var list<array{url: string, options: array<string, mixed>}> */
    private array $requetes = [];

    /** La réponse du modèle : une ligne « n | désignation | unité | prix » par ligne relevée. */
    private function lecteur(string ...$sortie): LecteurApi
    {
        $reponse = self::reponse(implode("\n", $sortie), 812, 143);
        $http = new MockHttpClient(function (string $methode, string $url, array $options) use ($reponse): MockResponse {
            $this->requetes[] = ['url' => $url, 'options' => $options];
            return $reponse;
        });
        return new LecteurApi($http, 'https://api.exemple.test/v1/', 'jeton', 'qwen-test', new NullLogger());
    }

    private static function reponse(string $contenu, int $envoyes, int $recus): MockResponse
    {
        return new MockResponse((string) json_encode([
            'choices' => [['finish_reason' => 'stop', 'message' => ['role' => 'assistant', 'content' => $contenu]]],
            'usage' => ['prompt_tokens' => $envoyes, 'completion_tokens' => $recus],
        ]), ['http_code' => 200]);
    }

    /** @return list<string> */
    private static function designations(\OffreGen\Catalogue\LectureOffre $r): array
    {
        return array_map(static fn ($l): string => $l->designation, $r->lignes);
    }

    /** @return iterable<string, array{string, string|null}> */
    public static function unites(): iterable
    {
        yield 'm2' => ['m2', 'm²'];
        yield 'M² en capitales' => ['M²', 'm²'];
        yield 'mètre linéaire, apostrophe' => ["m'", 'ml'];
        yield 'ml' => ['ml', 'ml'];
        yield 'mètre' => ['m', 'm'];
        yield 'm3' => ['m3', 'm³'];
        yield 'pièce' => ['pièce', 'pce'];
        yield 'U' => ['U', 'pce'];
        yield 'heures' => ['heures', 'h'];
        yield 'jours' => ['jours', 'jour'];
        yield 'j' => ['j', 'jour'];
        yield 'kilo' => ['Kg', 'kg'];
        yield 'fft' => ['fft', 'forfait'];
        yield 'Ft' => ['Ft', 'forfait'];
        yield 'global' => ['gl', 'forfait'];
        yield 'point final' => ['pce.', 'pce'];
        yield 'inconnue' => ['palette', null];
        yield 'vide' => ['', null];
    }

    #[DataProvider('unites')]
    public function testUniteEcriteRameneeALaListeDuCatalogue(string $ecrite, ?string $attendue): void
    {
        self::assertSame($attendue, UniteLue::normaliser($ecrite));
    }

    public function testLeTexteEstEnvoyeNumeroteSansOutil(): void
    {
        $this->lecteur('')->lire(self::TEXTE);
        $corps = json_decode((string) $this->requetes[0]['options']['body'], true);
        self::assertIsArray($corps);
        self::assertSame('https://api.exemple.test/v1/chat/completions', $this->requetes[0]['url']);
        self::assertArrayNotHasKey('tools', $corps);
        self::assertSame(0, $corps['temperature']);
        self::assertSame('none', $corps['reasoning_effort']);
        self::assertStringContainsString("3: 1.4 | Dispersion sur murs, 2 couches | 62 | m2 | 18.50 | 1'147.00", $corps['messages'][1]['content']);
    }

    public function testLignesLuesAvecUniteDuCatalogueEtPrixEnCentimes(): void
    {
        $r = $this->lecteur(
            '3 | Dispersion sur murs, 2 couches | m2 | 18.50',
            "4 | Peinture des plinthes | m' | 7.50",
            '5 | Installation de chantier | Ft | 4 506,30',
            "6 | Main-d'œuvre jardinier | heures | 78.-",
        )->lire(self::TEXTE);
        self::assertSame(
            [['Dispersion sur murs, 2 couches', 'm²', 1850], ['Peinture des plinthes', 'ml', 750],
             ['Installation de chantier', 'forfait', 450630], ["Main-d'œuvre jardinier", 'h', 7800]],
            array_map(static fn ($l): array => [$l->designation, $l->unite, $l->prixCentimes], $r->lignes),
        );
        self::assertSame([], $r->refusees);
        self::assertSame([812, 143], [$r->jetonsEnvoyes, $r->jetonsRecus]);
    }

    public function testUnPrixAbsentDuTexteEstRefuse(): void
    {
        $r = $this->lecteur('3 | Dispersion sur murs, 2 couches | m2 | 19.50', '4 | Peinture des plinthes | ml | 7.50')->lire(self::TEXTE);
        self::assertSame(['Peinture des plinthes'], self::designations($r));
        self::assertSame([['Dispersion sur murs, 2 couches', 'prix absent du texte']], $r->refusees);
    }

    public function testLeMontantDeLaLigneNEstPasUnPrixUnitaireQuandLaQuantiteLeDit(): void
    {
        // 62 x 18.50 = 1147.00 : le modèle a pris le montant pour le prix unitaire.
        $r = $this->lecteur("3 | Dispersion sur murs, 2 couches | m2 | 1'147.00")->lire(self::TEXTE);
        self::assertSame([], $r->lignes);
        self::assertSame([['Dispersion sur murs, 2 couches', 'montant de la ligne pris pour le prix unitaire']], $r->refusees);
    }

    public function testUnPrixEcritAvecQuatreDecimalesEstAccepteArrondi(): void
    {
        // Devis public du banc (2026-11-20) : « 131,4700 M² | 14,8995 | 1 958,84 », le modèle rend 14.8995.
        $r = $this->lecteur('1 | Laine de verre soufflée | M² | 14,8995')
            ->lire(['Fourniture et pose de laine de verre soufflée | 131,4700 M² | 14,8995 | 1 958,84 | 6']);
        self::assertSame([1490, 'm²'], [$r->lignes[0]->prixCentimes ?? null, $r->lignes[0]->unite ?? null]);
    }

    public function testUniteInconnueGardeeACorriger(): void
    {
        $r = $this->lecteur('4 | Peinture des plinthes | palette | 7.50')->lire(self::TEXTE);
        self::assertNull($r->lignes[0]->unite);
        self::assertSame('palette', $r->lignes[0]->uniteEcrite);
    }

    public function testLignesIllisiblesRefuseesCommentaireIgnoreEtTiretLongRetire(): void
    {
        $r = $this->lecteur(
            'Voici les lignes relevées :',
            '99 | Hors du texte | pce | 7.50',
            '4 |   | ml | 7.50',
            "4 | Plinthes \u{2014} peinture | ml | 7.50",
            'ligne | sans | numéro',
        )->lire(self::TEXTE);
        self::assertSame(['Plinthes, peinture'], self::designations($r));
        self::assertSame(['ligne hors du texte', 'désignation absente', 'ligne illisible'], array_column($r->refusees, 1));
    }

    public function testUneBarreDansLaDesignationNeLaCoupePas(): void
    {
        $r = $this->lecteur('4 | Peinture | des plinthes | ml | 7.50')->lire(self::TEXTE);
        self::assertSame(['Peinture des plinthes'], self::designations($r));
        self::assertSame('ml', $r->lignes[0]->unite);
    }

    /**
     * Banc du 2026-11-20 chez Infomaniak (sortie par appel d'outil) : sur l'offre du carreleur, une passe sur trois ne
     * rendait qu'une ligne sur neuf. Le tableau compte plus de lignes chiffrées que la réponse : une seconde lecture.
     */
    public function testUneReponseTropCourteEstRelueUneFois(): void
    {
        $texte = ['Pos. | Désignation | Qté | Unité | Prix | Montant', '10 | Dépose | 18,5 | m2 | 42.00 | 777.00',
            '20 | Chape | 18,5 | m2 | 28.00 | 518.00', '30 | Carrelage | 18,5 | m2 | 135.00 | 2\'497.50', 'Total | 3\'792.50'];
        $reponses = [
            self::reponse('2 | Dépose | m2 | 42.00', 500, 40),
            self::reponse("2 | Dépose | m2 | 42.00\n3 | Chape | m2 | 28.00\n4 | Carrelage | m2 | 135.00", 520, 120),
        ];
        $corps = [];
        $http = new MockHttpClient(function (string $m, string $u, array $o) use (&$reponses, &$corps): MockResponse {
            $corps[] = json_decode((string) $o['body'], true);
            return array_shift($reponses) ?? throw new \LogicException('Appel de trop.');
        });
        $r = (new LecteurApi($http, 'https://api.exemple.test/v1', '', 'qwen-test', new NullLogger()))->lire($texte);
        self::assertSame(['Dépose', 'Chape', 'Carrelage'], self::designations($r));
        self::assertCount(2, $corps);
        self::assertStringContainsString('environ 3 lignes', $corps[1]['messages'][1]['content']);
        self::assertSame([1020, 160], [$r->jetonsEnvoyes, $r->jetonsRecus]);
    }

    public function testUneReponseCompleteNEstPasRelue(): void
    {
        $this->lecteur('3 | Dispersion sur murs, 2 couches | m2 | 18.50', "4 | Peinture des plinthes | m' | 7.50", '5 | Installation de chantier | Ft | 4 506,30')
            ->lire(self::TEXTE);
        self::assertCount(1, $this->requetes);
    }

    public function testTexteVideSansAppel(): void
    {
        $r = $this->lecteur('')->lire(['', '  ']);
        self::assertSame([], $r->lignes);
        self::assertSame([], $this->requetes);
    }

    public function testTexteTropLongRefuse(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->lecteur('')->lire(array_fill(0, LecteurApi::LIGNES_MAX + 1, 'ligne'));
    }
}
