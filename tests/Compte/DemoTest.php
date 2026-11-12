<?php

declare(strict_types=1);

namespace OffreGen\Tests\Compte;

use OffreGen\Catalogue\CatalogueFictif;
use OffreGen\Tests\Outils\BaseDeTest;
use OffreGen\Tests\Outils\Dossiers;
use OffreGen\Tests\Outils\Jeu;
use OffreGen\Tests\Outils\Monde;
use PHPUnit\Framework\TestCase;

final class DemoTest extends TestCase
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

    private function audioDe(int $artisan, string $uuid): string
    {
        $chemin = $this->dossiers->env()['AUDIO_DOSSIER'] . "/$artisan/$uuid.m4a";
        mkdir(dirname($chemin), 0700, true);
        file_put_contents($chemin, 'audio');
        return $chemin;
    }

    public function testReinitialisationRemetLeCompteALEtatDOrigine(): void
    {
        BaseDeTest::pdo()->exec("UPDATE artisan SET demo = 1, plafond_jour = 10 WHERE id = {$this->jeu->a}");
        $audioA = $this->audioDe($this->jeu->a, $this->jeu->uuid['a']);
        $audioB = $this->audioDe($this->jeu->b, $this->jeu->uuid['b']);

        $supprimes = Monde::conteneur(env: $this->dossiers->env())->comptes()->reinitialiserDemo('a@exemple.ch', 'menuisier');

        self::assertSame(1, $supprimes);
        self::assertFileDoesNotExist($audioA);
        $a = $this->jeu->a;
        // Les anciennes offres partent ; deux offres d'exemple, tirées du catalogue fictif, les remplacent.
        self::assertSame(0, BaseDeTest::compter('offre', "artisan_id = $a AND titre = 'Offre a'"));
        self::assertSame(2, BaseDeTest::compter('offre', "artisan_id = $a AND enregistrement_id IS NULL AND client_nom IS NOT NULL"));
        // La plus ancienne porte le numéro 1.
        self::assertSame(1, BaseDeTest::compter('offre', "artisan_id = $a AND numero = 1 AND cree_le < (SELECT o2.cree_le FROM (SELECT cree_le FROM offre WHERE artisan_id = $a AND numero = 2) o2)"));
        self::assertSame(0, BaseDeTest::compter('ligne', "artisan_id = $a AND produit_id IS NULL"));
        self::assertSame(1, BaseDeTest::compter('ligne', "artisan_id = $a AND designation = 'Parquet chêne contrecollé, pose collée' AND quantite_milliemes = 16000 AND prix_centimes = 9500"));
        self::assertSame(0, BaseDeTest::compter('enregistrement', "artisan_id = $a"));
        self::assertSame(12, BaseDeTest::compter('produit', "artisan_id = $a"));
        self::assertSame(1, BaseDeTest::compter('produit', "artisan_id = $a AND designation = 'Parquet chêne contrecollé, pose collée' AND prix_centimes = 9500"));
        // Le compte lui-même reste, et B n'est pas touché.
        self::assertSame(1, BaseDeTest::compter('artisan', "id = $a AND demo = 1"));
        self::assertFileExists($audioB);
        self::assertSame(1, BaseDeTest::compter('offre', "artisan_id = {$this->jeu->b}"));
        self::assertSame(1, BaseDeTest::compter('produit', "artisan_id = {$this->jeu->b}"));
    }

    public function testCompteQuiNEstPasDemoRefuseSansRienEffacer(): void
    {
        try {
            Monde::conteneur(env: $this->dossiers->env())->comptes()->reinitialiserDemo('a@exemple.ch', 'peintre');
            self::fail('Un compte réel a été remis à zéro.');
        } catch (\InvalidArgumentException $e) {
            self::assertStringContainsString("n'est pas un compte de démonstration", $e->getMessage());
        }
        self::assertSame(1, BaseDeTest::compter('offre', "artisan_id = {$this->jeu->a}"));
        self::assertSame(1, BaseDeTest::compter('produit', "artisan_id = {$this->jeu->a}"));
    }

    public function testMetierInconnuRefuseAvantDEffacer(): void
    {
        BaseDeTest::pdo()->exec("UPDATE artisan SET demo = 1 WHERE id = {$this->jeu->a}");
        try {
            Monde::conteneur(env: $this->dossiers->env())->comptes()->reinitialiserDemo('a@exemple.ch', 'boulanger');
            self::fail('Métier inconnu accepté.');
        } catch (\InvalidArgumentException) {
        }
        self::assertSame(1, BaseDeTest::compter('offre', "artisan_id = {$this->jeu->a}"));
    }

    public function testLesOffresDExempleNeCitentQueDesProduitsDuCatalogue(): void
    {
        foreach (CatalogueFictif::metiers() as $metier) {
            $designations = array_column(CatalogueFictif::produits($metier), 'designation');
            $offres = CatalogueFictif::offres($metier);
            self::assertCount(2, $offres, $metier);
            foreach ($offres as $o) {
                foreach (array_keys($o['lignes']) as $designation) {
                    self::assertContains($designation, $designations, "$metier : {$o['titre']}");
                }
            }
        }
    }

    public function testLesTroisCataloguesOntDouzeProduitsSansDoublon(): void
    {
        foreach (CatalogueFictif::metiers() as $metier) {
            $produits = CatalogueFictif::produits($metier);
            self::assertCount(12, $produits, $metier);
            self::assertSame(12, count(array_unique(array_column($produits, 'designation'))), $metier);
        }
    }
}
