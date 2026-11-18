<?php

declare(strict_types=1);

namespace OffreGen\Tests\Http;

use OffreGen\Tests\Outils\BaseDeTest;
use OffreGen\Tests\Outils\Dossiers;
use OffreGen\Tests\Outils\Jeu;
use OffreGen\Tests\Outils\Monde;
use OffreGen\Tests\Outils\Navigateur;
use PHPUnit\Framework\TestCase;

/**
 * Revue du 2026-11-18 (dictée à l'abri, défaut 3) : l'audio arrive au serveur, la réponse se perd en route (cave,
 * antenne), le téléphone croit l'envoi raté et le refait. Le téléphone envoie la clé de sa dictée ; une clé déjà
 * reçue renvoie à l'enregistrement existant, sans deuxième offre ni deuxième offre décomptée de l'essai.
 */
final class ImportCleTest extends TestCase
{
    private const CLE = '1763481234567-a1b2c3d4';

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

    private function navigateur(string $courriel = 'a@exemple.ch'): Navigateur
    {
        $n = new Navigateur(Monde::conteneur(env: $this->dossiers->env() + ['TRAITEMENT_IMMEDIAT' => '0', 'DUREE_MIN_SECONDES' => '0']));
        $n->connecter($courriel);
        return $n;
    }

    private function envoyer(Navigateur $n, string $cle): string
    {
        $n->get('/import');
        $reponse = $n->post('/import', ['cle' => $cle], ['audio' => $this->dossiers->envoi('visite.m4a')]);
        self::assertSame(303, $reponse->getStatusCode(), (string) $reponse->getContent());
        return (string) $reponse->headers->get('Location');
    }

    public function testLaMemeDicteeEnvoyeeDeuxFoisNeDonneQuUnEnregistrement(): void
    {
        $n = $this->navigateur();
        $avant = BaseDeTest::compter('enregistrement', "artisan_id = {$this->jeu->a}");
        $premier = $this->envoyer($n, self::CLE);
        $second = $this->envoyer($n, self::CLE);
        self::assertSame($premier, $second);
        self::assertSame($avant + 1, BaseDeTest::compter('enregistrement', "artisan_id = {$this->jeu->a}"));
        // Le second fichier n'est pas gardé : un seul audio rangé pour la dictée.
        self::assertCount(1, glob($this->dossiers->env()['AUDIO_DOSSIER'] . "/{$this->jeu->a}/*") ?: []);
    }

    public function testLaCleDUnAutreCompteNeRenvoiePasASaDictee(): void
    {
        $deA = $this->envoyer($this->navigateur(), self::CLE);
        $deB = $this->envoyer($this->navigateur('b@exemple.ch'), self::CLE);
        self::assertNotSame($deA, $deB);
        self::assertSame(1, BaseDeTest::compter('enregistrement', "artisan_id = {$this->jeu->b} AND cle_client = '" . self::CLE . "'"));
    }

    public function testUneCleMalFormeeEstIgnoree(): void
    {
        $n = $this->navigateur();
        $premier = $this->envoyer($n, "x' OR 1=1 --");
        $second = $this->envoyer($n, "x' OR 1=1 --");
        self::assertNotSame($premier, $second);
        self::assertSame(0, BaseDeTest::compter('enregistrement', 'cle_client IS NOT NULL'));
    }

    /** La dernière offre d'un essai : le renvoi de la même dictée n'est pas refusé comme une offre de plus. */
    public function testLeRenvoiPasseMemeQuandLEssaiVientDeSEpuiser(): void
    {
        $recus = BaseDeTest::compter('enregistrement', "artisan_id = {$this->jeu->a}");
        BaseDeTest::pdo()->exec('UPDATE artisan SET offres_max = ' . ($recus + 1) . " WHERE id = {$this->jeu->a}");
        $n = $this->navigateur();
        $premier = $this->envoyer($n, self::CLE);
        self::assertSame($premier, $this->envoyer($n, self::CLE));
        $n->get('/import');
        $autre = $n->post('/import', ['cle' => '1763481234567-zzzzzzzz'], ['audio' => $this->dossiers->envoi('visite.m4a')]);
        self::assertSame(429, $autre->getStatusCode());
    }
}
