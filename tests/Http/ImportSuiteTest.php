<?php

declare(strict_types=1);

namespace OffreGen\Tests\Http;

use OffreGen\Donnees\DepotEnregistrements;
use OffreGen\Donnees\Portee;
use OffreGen\Tests\Outils\BaseDeTest;
use OffreGen\Tests\Outils\Dossiers;
use OffreGen\Tests\Outils\Jeu;
use OffreGen\Tests\Outils\Monde;
use OffreGen\Tests\Outils\Navigateur;
use PHPUnit\Framework\TestCase;

/**
 * Sous-chantier 27, recette AB-1 (Xavier, 2026-11-24) : une dictée coupée (Safari fermé de force) se continue « à la
 * suite ». Deux enregistreurs du navigateur ne se recollent pas en un fichier lisible : la suite arrive en fichiers à
 * part (`suite[]`), dans le même envoi que le début. Une seule dictée, un seul import compté.
 */
final class ImportSuiteTest extends TestCase
{
    private Jeu $jeu;
    private Dossiers $dossiers;

    protected function setUp(): void
    {
        $this->jeu = new Jeu();
        $this->dossiers = new Dossiers();
    }

    protected function tearDown(): void
    {
        $this->dossiers->effacer();
    }

    private function navigateur(): Navigateur
    {
        $n = new Navigateur(Monde::conteneur(env: $this->dossiers->env() + ['TRAITEMENT_IMMEDIAT' => '0', 'DUREE_MIN_SECONDES' => '0']));
        $n->connecter('a@exemple.ch');
        $n->get('/import');
        return $n;
    }

    /** @return list<string> les audios rangés pour A */
    private function audiosDeA(): array
    {
        $liste = glob($this->dossiers->env()['AUDIO_DOSSIER'] . "/{$this->jeu->a}/*") ?: [];
        sort($liste);
        return $liste;
    }

    public function testLaSuiteEstRangeeAvecLeDebutSousUneSeuleDictee(): void
    {
        $avant = BaseDeTest::compter('enregistrement', "artisan_id = {$this->jeu->a}");
        $reponse = $this->navigateur()->post('/import', ['cle' => '1763481234567-suite001'], [
            'audio' => $this->dossiers->envoi('visite.m4a'),
            'suite' => [$this->dossiers->envoi('visite.webm'), $this->dossiers->envoi('visite.m4a')],
        ]);
        self::assertSame(303, $reponse->getStatusCode(), (string) $reponse->getContent());
        self::assertSame($avant + 1, BaseDeTest::compter('enregistrement', "artisan_id = {$this->jeu->a}"));
        $uuid = basename((string) $reponse->headers->get('Location'));
        $ligne = BaseDeTest::ligne("SELECT fichier, fichiers_suite FROM enregistrement WHERE uuid = '$uuid'");
        self::assertSame("{$this->jeu->a}/$uuid.m4a", $ligne['fichier']);
        self::assertSame(["{$this->jeu->a}/$uuid-2.webm", "{$this->jeu->a}/$uuid-3.m4a"], json_decode((string) $ligne['fichiers_suite'], true));
        self::assertSame(3, count($this->audiosDeA()));
        // La remise à zéro d'une démo et la suppression des démos effacent les fichiers de cette liste : suites comprises.
        $connus = (new DepotEnregistrements($this->jeu->base, new Portee($this->jeu->a)))->fichiers();
        foreach (["$uuid.m4a", "$uuid-2.webm", "$uuid-3.m4a"] as $nom) {
            self::assertContains("{$this->jeu->a}/$nom", $connus);
        }
    }

    public function testSansSuiteRienNeChange(): void
    {
        $reponse = $this->navigateur()->post('/import', [], ['audio' => $this->dossiers->envoi('visite.m4a')]);
        self::assertSame(303, $reponse->getStatusCode());
        $uuid = basename((string) $reponse->headers->get('Location'));
        self::assertNull(BaseDeTest::ligne("SELECT fichiers_suite FROM enregistrement WHERE uuid = '$uuid'")['fichiers_suite']);
    }

    /** Une partie refusée refuse tout l'envoi, et rien n'est gardé : pas de dictée à moitié rangée. */
    public function testUneSuiteQuiNEstPasDeLAudioRefuseToutLEnvoi(): void
    {
        $avant = BaseDeTest::compter('enregistrement', "artisan_id = {$this->jeu->a}");
        $reponse = $this->navigateur()->post('/import', [], [
            'audio' => $this->dossiers->envoi('visite.m4a'),
            'suite' => [$this->dossiers->envoi('texte.m4a')],
        ]);
        self::assertSame(422, $reponse->getStatusCode());
        self::assertSame($avant, BaseDeTest::compter('enregistrement', "artisan_id = {$this->jeu->a}"));
        self::assertSame([], $this->audiosDeA());
    }

    public function testCinqPartiesAuPlus(): void
    {
        $suites = array_map(fn (): mixed => $this->dossiers->envoi('visite.m4a'), range(1, 5));
        $reponse = $this->navigateur()->post('/import', [], ['audio' => $this->dossiers->envoi('visite.m4a'), 'suite' => $suites]);
        self::assertSame(422, $reponse->getStatusCode());
        self::assertStringContainsString('5 parties au plus', (string) $reponse->getContent());
        self::assertSame([], $this->audiosDeA());
    }

    /** Un champ « suite » qui n'est pas une liste de fichiers (formulaire fabriqué) : refusé proprement, pas d'erreur 500. */
    public function testUneSuiteSeuleHorsListeEstRefusee(): void
    {
        $reponse = $this->navigateur()->post('/import', [], ['audio' => $this->dossiers->envoi('visite.m4a'), 'suite' => $this->dossiers->envoi('visite.m4a')]);
        self::assertSame(422, $reponse->getStatusCode());
        self::assertSame([], $this->audiosDeA());
    }
}
