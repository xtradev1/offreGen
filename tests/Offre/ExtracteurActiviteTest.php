<?php

declare(strict_types=1);

namespace OffreGen\Tests\Offre;

use OffreGen\Compte\Activite;
use OffreGen\Offre\ExtracteurApi;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

/**
 * La consigne et le rangement suivent l'activité du compte (docs/plans/2026-11-22_familles-activite, SPEC points 3 et 4).
 * Le Bâtiment garde, au caractère près, les deux consignes mesurées au banc : leurs empreintes ont été relevées sur
 * le commit 8385e78, avant ce chantier. La consigne principale a changé le 2026-11-22 (chantier des options : une option
 * ou une variante dite est une ligne à part), après le banc du corpus écrit, moitié de contrôle : dictées sans option
 * 100 % comme avant, dictées à options 94,7 % au lieu de 57 % (docs/plans/2026-11-22_options-variantes/ETAT.md, Preuves).
 */
final class ExtracteurActiviteTest extends TestCase
{
    private const CONSIGNE_DU_BANC = 'f5a0605c845b025c505a509a37399a6372113e6427c74679d07caedec7a67f95';
    private const CONSIGNE_PIECES_DU_BANC = 'f83d5c17b9fd19b7cde96dc168ef42fe1f1191b86d63b0fd720d928ddf53b456';

    private const CATALOGUE = [
        ['id' => 4, 'categorie' => 'Développement', 'designation' => 'Heure de développement', 'unite' => 'h', 'prix_centimes' => 15000, 'synonymes' => 'développement'],
    ];

    private const LIGNES = '{"titre":"Site vitrine","lignes":[{"certitude":"sure","extrait":"Douze heures de développement.","produit_id":4,"quantite":12},'
        . '{"certitude":"sure","extrait":"Une formation.","produit_id":null,"designation":"Formation","quantite":1,"unite":"forfait"}]}';

    /** @var list<array<string, mixed>> le corps de chaque requête envoyée au modèle */
    private array $corps = [];

    /** @param list<MockResponse> $reponses */
    private function extracteur(array $reponses): ExtracteurApi
    {
        $http = new MockHttpClient(function (string $methode, string $url, array $options) use (&$reponses): MockResponse {
            $corps = json_decode((string) $options['body'], true);
            self::assertIsArray($corps);
            $this->corps[] = $corps;
            return array_shift($reponses) ?? throw new \LogicException('Appel en trop.');
        });
        return new ExtracteurApi($http, 'https://api.exemple.test/v1', 'jeton', 'qwen-test', new NullLogger());
    }

    /**
     * Les corps des requêtes envoyées pendant l'appel donné, et eux seuls.
     *
     * @param \Closure(): mixed $appel
     * @return list<array<string, mixed>>
     */
    private function envois(\Closure $appel): array
    {
        $this->corps = [];
        $appel();
        return $this->corps;
    }

    private static function outil(string $nom, string $arguments): MockResponse
    {
        return new MockResponse((string) json_encode(['choices' => [['message' => ['role' => 'assistant', 'content' => '', 'tool_calls' => [
            ['id' => 'appel', 'type' => 'function', 'function' => ['name' => $nom, 'arguments' => $arguments]],
        ]]]]]), ['http_code' => 200]);
    }

    public function testLeBatimentGardeLesConsignesMesureesAuBanc(): void
    {
        self::assertSame(self::CONSIGNE_DU_BANC, hash('sha256', ExtracteurApi::consigne(Activite::Batiment)));
        self::assertSame(self::CONSIGNE_PIECES_DU_BANC, hash('sha256', ExtracteurApi::consigneGroupes(Activite::Batiment)));

        // Et ce sont elles qui partent, sans activité donnée comme avec le Bâtiment.
        foreach ([[], [Activite::Batiment]] as $activite) {
            $corps = $this->envois(fn () => $this->extracteur([self::outil('proposer_offre', self::LIGNES), self::outil('ranger_par_piece', '{"pieces":[{"ligne":1,"piece":"salon"}]}')])
                ->extraire('Dictée.', self::CATALOGUE, ...$activite));
            self::assertCount(2, $corps);
            self::assertSame(self::CONSIGNE_DU_BANC, hash('sha256', $corps[0]['messages'][0]['content']));
            self::assertSame(self::CONSIGNE_PIECES_DU_BANC, hash('sha256', $corps[1]['messages'][0]['content']));
            self::assertSame('Pièce dite, ou « général »', $corps[1]['tools'][0]['function']['parameters']['properties']['pieces']['items']['properties']['piece']['description']);
        }
    }

    /**
     * Retours de Xavier du 2026-11-23 : le second appel range une prestation dans les parties annoncées, « seulement si
     * des catégories sont utiles ». Rien d'annoncé : tout « général », l'offre sort par catégorie du catalogue.
     */
    public function testUnePrestationSansPartieAnnonceeResteParCategorieDuCatalogue(): void
    {
        $r = $this->extracteur([self::outil('proposer_offre', self::LIGNES), self::outil('ranger_par_piece', '{"pieces":[{"ligne":1,"piece":"général"},{"ligne":2,"piece":"général"}]}')])
            ->extraire('Dictée.', self::CATALOGUE, Activite::Prestations);
        self::assertCount(2, $this->corps);
        self::assertCount(2, $r->lignes);
        self::assertSame([null, null], [$r->lignes[0]->piece, $r->lignes[1]->piece]);
        self::assertSame([12000, 15000], [$r->lignes[0]->quantiteMilliemes, $r->lignes[0]->prixCentimes], 'le prix reste celui du catalogue');

        $consigne = $this->corps[0]['messages'][0]['content'];
        self::assertSame(ExtracteurApi::consigne(Activite::Prestations), $consigne);
        self::assertStringContainsString('prestataire de services suisse', $consigne);
        self::assertStringContainsString('- titre : court, la nature de la prestation, sans prix.', $consigne);
        self::assertStringNotContainsString('artisan', $consigne);
        self::assertStringNotContainsString('visite', $consigne);

        $second = $this->corps[1]['messages'][0]['content'];
        self::assertSame(ExtracteurApi::consigneGroupes(Activite::Prestations), $second);
        self::assertStringContainsString('Range seulement si le prestataire annonce des parties', $second);
        self::assertStringContainsString("N'invente aucune partie.", $second);
        self::assertDoesNotMatchRegularExpression('/pièce|zone|chantier|artisan|visite/iu', $second);
        self::assertSame('Partie dite, ou « général »', $this->corps[1]['tools'][0]['function']['parameters']['properties']['pieces']['items']['properties']['piece']['description']);
    }

    public function testUnePrestationSeRangeDansLesPartiesAnnoncees(): void
    {
        $r = $this->extracteur([self::outil('proposer_offre', self::LIGNES), self::outil('ranger_par_piece', '{"pieces":[{"ligne":1,"piece":"pour le site"},{"ligne":2,"piece":"formation"}]}')])
            ->extraire('Dictée.', self::CATALOGUE, Activite::Prestations);
        self::assertSame(['Pour le site', 'Formation'], [$r->lignes[0]->piece, $r->lignes[1]->piece]);
    }

    public function testEnExterieurLeSecondAppelRangeParZone(): void
    {
        $r = $this->extracteur([self::outil('proposer_offre', self::LIGNES), self::outil('ranger_par_piece', '{"pieces":[{"ligne":1,"piece":"devant la maison"},{"ligne":2,"piece":"général"}]}')])
            ->extraire('Dictée.', self::CATALOGUE, Activite::Exterieur);
        self::assertCount(2, $this->corps);
        self::assertSame(['Devant la maison', null], [$r->lignes[0]->piece, $r->lignes[1]->piece]);

        self::assertStringContainsString('un jardinier ou un paysagiste suisse', $this->corps[0]['messages'][0]['content']);
        $second = $this->corps[1]['messages'][0]['content'];
        self::assertSame(ExtracteurApi::consigneGroupes(Activite::Exterieur), $second);
        self::assertStringContainsString('zone par zone', $second);
        self::assertStringNotContainsString('pièce', $second);
        self::assertSame('Zone dite, ou « général »', $this->corps[1]['tools'][0]['function']['parameters']['properties']['pieces']['items']['properties']['piece']['description']);
    }

    /** Seules les phrases prévues changent : une en Extérieur, deux en Prestations. Les règles communes restent. */
    public function testLesReglesCommunesSontLesMemes(): void
    {
        $batiment = explode("\n", ExtracteurApi::consigne(Activite::Batiment));
        foreach ([[Activite::Exterieur, 1], [Activite::Prestations, 2]] as [$activite, $attendu]) {
            $lignes = explode("\n", ExtracteurApi::consigne($activite));
            self::assertCount(count($batiment), $lignes, $activite->value);
            self::assertCount($attendu, array_diff_assoc($lignes, $batiment), $activite->value);
            self::assertDoesNotMatchRegularExpression('/[\x{2014}\x{2013}]/u', implode("\n", $lignes));
        }
        self::assertDoesNotMatchRegularExpression('/[\x{2014}\x{2013}]/u', ExtracteurApi::consigneGroupes(Activite::Exterieur));
        self::assertDoesNotMatchRegularExpression('/[\x{2014}\x{2013}]/u', ExtracteurApi::consigneGroupes(Activite::Prestations));
    }
}
