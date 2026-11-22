<?php

declare(strict_types=1);

namespace OffreGen\Tests\Offre;

use OffreGen\Audio\Traiteur;
use OffreGen\Http\Controleur\OffreControleur;
use OffreGen\Offre\ExtracteurApi;
use OffreGen\Offre\LigneExtraite;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

/**
 * Trouvailles retenues de la revue (docs/plans/2026-11-22_options-variantes/REVUE.md), côté extraction et regroupement.
 */
final class RevueOptionsTest extends TestCase
{
    private static function outil(string $nom, string $arguments): MockResponse
    {
        return new MockResponse((string) json_encode(['choices' => [['message' => ['role' => 'assistant', 'content' => '', 'tool_calls' => [
            ['id' => 'a', 'type' => 'function', 'function' => ['name' => $nom, 'arguments' => $arguments]],
        ]]]]]), ['http_code' => 200]);
    }

    /** S1, R3 : les trois appels tiennent sous le verrou du traitement, sinon une dictée lente se rejoue et se paie deux fois. */
    public function testLesTroisAppelsTiennentSousLeVerrou(): void
    {
        $durees = [];
        $reponses = [self::outil('proposer_offre', '{"titre":"T","lignes":[{"produit_id":null,"designation":"Murs","quantite":42,"unite":"m²","certitude":"sure","extrait":"murs"},'
            . '{"produit_id":null,"designation":"Portes","quantite":3,"unite":"pce","certitude":"sure","extrait":"en option les portes"}]}'),
            self::outil('ranger_par_piece', '{"pieces":[]}'), self::outil('classer_lignes', '{"lignes":[]}')];
        $http = new MockHttpClient(function (string $m, string $u, array $options) use (&$reponses, &$durees): MockResponse {
            $durees[] = $options['max_duration'];
            return array_shift($reponses) ?? throw new \LogicException('Appel en trop.');
        });
        (new ExtracteurApi($http, 'https://api.exemple.test/v1', 'j', 'm', new NullLogger()))->extraire('Les murs. En option, les portes.', []);
        self::assertCount(3, $durees);
        self::assertLessThanOrEqual(Traiteur::DUREE_VERROU_SECONDES - 30, array_sum($durees), 'somme des durées maximales : ' . implode(' + ', $durees));
    }

    private static function extracteur(): ExtracteurApi
    {
        return new ExtracteurApi(new MockHttpClient(), 'x', '', 'm', new NullLogger());
    }

    /** C2 : une variante au forfait (« 800 francs le tout ») garde sa quantité ; seule une variante de même unité prend celle de sa ligne. */
    public function testUneVarianteAuForfaitGardeSaQuantite(): void
    {
        $lignes = [new LigneExtraite('Murs', 42000, 'm²', 2800, false, null), new LigneExtraite('Papier peint', 1000, 'forfait', 80000, true, null),
            new LigneExtraite('Lessivable', 1000, 'm²', 3400, true, null)];
        $sortie = self::extracteur()->validerOptions(['lignes' => [['ligne' => 2, 'nature' => 'variante', 'remplace' => 1], ['ligne' => 3, 'nature' => 'variante', 'remplace' => 1]]], $lignes);
        self::assertSame([1000, 'forfait'], [$sortie[1]->quantiteMilliemes, $sortie[1]->unite]);
        self::assertSame([42000, 'm²'], [$sortie[2]->quantiteMilliemes, $sortie[2]->unite]);
    }

    /** R1, S4 : une ligne que le troisième appel sort du total passe « à vérifier » : une erreur du modèle ne fait pas disparaître d'argent en silence. */
    public function testUneLigneSortieDuTotalParLeModeleEstAVerifier(): void
    {
        $lignes = [new LigneExtraite('Murs', 42000, 'm²', 2800, false, null), new LigneExtraite('Portes', 3000, 'pce', 14000, false, null),
            new LigneExtraite('Lessivable', 42000, 'm²', 3400, false, null)];
        $sortie = self::extracteur()->validerOptions(['lignes' => [['ligne' => 2, 'nature' => 'option'], ['ligne' => 3, 'nature' => 'variante', 'remplace' => 1]]], $lignes);
        self::assertSame([false, true, true], array_map(static fn (LigneExtraite $l): bool => $l->aVerifier, $sortie));
    }

    /** C4 : une option retenue qui a une pièce ne fait pas passer le document par pièce quand les lignes ne le sont pas. */
    public function testUneOptionRetenueNeChangePasLeRegroupementDuDocument(): void
    {
        $l = static fn (int $id, ?string $piece, string $nature, int $retenue): array => ['id' => $id, 'categorie' => 'Peinture', 'piece' => $piece,
            'quantite_milliemes' => 1000, 'prix_centimes' => 100, 'offerte' => 0, 'nature' => $nature, 'remplace_id' => null, 'retenue' => $retenue];
        $lignes = [$l(1, null, 'ligne', 0), $l(2, 'Salon', 'option', 1)];
        $ecran = OffreControleur::grouper($lignes, [100, 100]);
        $document = OffreControleur::grouper($lignes, [100, 100], true);
        self::assertSame(['Peinture'], array_column($ecran, 'nom'));
        self::assertSame(['Peinture'], array_column($document, 'nom'), 'par catégorie, comme l\'écran');
        self::assertSame([1, 2], array_map(static fn (array $r): int => $r['l']['id'], $document[0]['lignes']));
    }
}
