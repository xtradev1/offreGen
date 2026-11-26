<?php

declare(strict_types=1);

namespace OffreGen\Tests\Http;

use OffreGen\Audio\DureeAudio;
use OffreGen\Tests\Outils\BaseDeTest;
use OffreGen\Tests\Outils\Dossiers;
use OffreGen\Tests\Outils\Jeu;
use OffreGen\Tests\Outils\Monde;
use OffreGen\Tests\Outils\Navigateur;
use PHPUnit\Framework\TestCase;

/**
 * Sous-chantier 31 (PLAN, lot 5) : la réception chronométrée, le navigateur, l'origine et la durée de la dictée, puis la
 * durée d'envoi que le téléphone envoie après coup (D6 de Xavier, 2026-11-24).
 */
final class ChronoEnvoiTest extends TestCase
{
    private const SAFARI = 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_1 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.1 Mobile/15E148 Safari/604.1';

    private Dossiers $dossiers;

    protected function setUp(): void
    {
        new Jeu();
        $this->dossiers = new Dossiers();
    }

    protected function tearDown(): void
    {
        $this->dossiers->effacer();
    }

    /** @param array<string, string> $env */
    private function navigateur(array $env = []): Navigateur
    {
        $n = new Navigateur(Monde::conteneur(env: $this->dossiers->env() + $env + ['TRAITEMENT_IMMEDIAT' => '0', 'DUREE_MIN_SECONDES' => '0']));
        $n->connecter('a@exemple.ch');
        $n->get('/import');
        return $n;
    }

    /** @param array<string, string> $champs */
    private function importer(Navigateur $n, array $champs): string
    {
        $r = $n->post('/import', $champs, ['audio' => $this->dossiers->envoi('visite.m4a')], entetes: ['User-Agent' => self::SAFARI]);
        self::assertSame(303, $r->getStatusCode(), (string) $r->getContent());
        return basename((string) $r->headers->get('Location'));
    }

    /** @return array<string, mixed> */
    private function dictee(string $uuid): array
    {
        return BaseDeTest::ligne("SELECT id, navigateur, origine, duree_ms FROM enregistrement WHERE uuid = '$uuid'");
    }

    /** @return list<array{etape: string, processus: string, duree_ms: int, debut: string, fin: string}> */
    private function etapes(string $uuid): array
    {
        $r = BaseDeTest::pdo()->query("SELECT d.etape, d.processus, d.duree_ms, d.debut, d.fin FROM dictee_etape d JOIN enregistrement e ON e.id = d.enregistrement_id
            WHERE e.uuid = '$uuid' ORDER BY d.debut, d.id");
        self::assertNotFalse($r);
        /** @var list<array{etape: string, processus: string, duree_ms: int, debut: string, fin: string}> */
        return $r->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function testLaReceptionGardeLeNavigateurLOrigineEtLaDureeDuTelephone(): void
    {
        // ffprobe introuvable, comme sur le site web d'Infomaniak : la durée du téléphone est gardée.
        $uuid = $this->importer($this->navigateur(['FFPROBE' => '/inexistant/ffprobe']), ['origine' => 'micro', 'duree_ms' => '94000']);

        $d = $this->dictee($uuid);
        self::assertSame([self::SAFARI, 'micro', 94000], [$d['navigateur'], $d['origine'], $d['duree_ms']]);
        $etapes = $this->etapes($uuid);
        self::assertSame([['reception', 'web']], array_map(static fn (array $e): array => [$e['etape'], $e['processus']], $etapes));
    }

    public function testLaDureeMesureeAuServeurPasseAvantCelleDuTelephoneEtLesValeursFaussesSontIgnorees(): void
    {
        $uuid = $this->importer($this->navigateur(), ['origine' => 'scanner', 'duree_ms' => '-5']);
        $d = $this->dictee($uuid);
        self::assertNull($d['origine']);
        $mesure = (new DureeAudio())->secondes(dirname(__DIR__) . '/fixtures/audio/visite.m4a');
        self::assertNotNull($mesure, 'ffprobe présent en local');
        self::assertSame((int) round($mesure * 1000), $d['duree_ms']);
    }

    public function testLEnvoiDuTelephoneSePlaceJusteAvantLaReceptionUneSeuleFois(): void
    {
        $n = $this->navigateur(['FFPROBE' => '/inexistant/ffprobe']);
        $uuid = $this->importer($n, ['origine' => 'fichier']);
        // Le téléphone est sur l'écran d'attente quand il envoie la durée : le jeton vient de cette page.
        $n->get("/enregistrements/$uuid");
        $jeton = $n->jeton();

        self::assertSame(204, $n->post("/enregistrements/$uuid/envoi", ['envoi_ms' => '3800', '_csrf' => $jeton])->getStatusCode());
        self::assertSame(204, $n->post("/enregistrements/$uuid/envoi", ['envoi_ms' => '9999', '_csrf' => $jeton])->getStatusCode(), 'renvoyé : ignoré');
        self::assertSame(422, $n->post("/enregistrements/$uuid/envoi", ['envoi_ms' => 'beaucoup', '_csrf' => $jeton])->getStatusCode());

        $etapes = $this->etapes($uuid);
        self::assertSame(['envoi', 'reception'], array_column($etapes, 'etape'));
        self::assertSame(['telephone', 3800], [$etapes[0]['processus'], $etapes[0]['duree_ms']]);
        self::assertSame($etapes[1]['debut'], $etapes[0]['fin'], 'l\'envoi finit où la réception commence');
    }

    public function testSansJetonLEnvoiEstRefuse(): void
    {
        $n = $this->navigateur();
        $uuid = $this->importer($n, []);
        $n->get("/enregistrements/$uuid");
        self::assertSame(403, $n->post("/enregistrements/$uuid/envoi", ['envoi_ms' => '3800'], avecJeton: false)->getStatusCode());
        self::assertSame(['reception'], array_column($this->etapes($uuid), 'etape'));
    }
}
