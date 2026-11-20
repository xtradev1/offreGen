<?php

declare(strict_types=1);

namespace OffreGen\Tests\Compte;

use OffreGen\Catalogue\CatalogueFictif;
use OffreGen\Tests\Outils\BaseDeTest;
use OffreGen\Tests\Outils\Dossiers;
use OffreGen\Tests\Outils\Jeu;
use OffreGen\Tests\Outils\Monde;
use PHPUnit\Framework\TestCase;

final class CreationCompteTest extends TestCase
{
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

    public function testCompteDEssaiGardeSonPlafondTotalSansEtreDeDemonstration(): void
    {
        // Un compte d'essai n'est pas un compte de démonstration : bin/reinitialiser-demo effacerait ses offres.
        $mdp = Monde::conteneur(env: $this->dossiers->env())->comptes()->creer('Essai@Exemple.ch', 'Peinture Essai', false, null, 20);
        self::assertGreaterThanOrEqual(16, strlen($mdp));
        $ligne = BaseDeTest::ligne("SELECT demo, plafond_jour, offres_max FROM artisan WHERE courriel = 'essai@exemple.ch'");
        self::assertSame([0, null, 20], [$ligne['demo'], $ligne['plafond_jour'], $ligne['offres_max']]);
    }

    public function testCompteOrdinaireSansPlafondTotal(): void
    {
        Monde::conteneur(env: $this->dossiers->env())->comptes()->creer('libre@exemple.ch', 'Libre', false, null);
        self::assertNull(BaseDeTest::ligne("SELECT offres_max FROM artisan WHERE courriel = 'libre@exemple.ch'")['offres_max']);
    }

    public function testPlafondTotalNulRefuse(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Monde::conteneur(env: $this->dossiers->env())->comptes()->creer('zero@exemple.ch', 'Zéro', false, null, 0);
    }

    public function testEssaiCreeAvecUnMetierRecoitSonCatalogueAuxPrixAZero(): void
    {
        Monde::conteneur(env: $this->dossiers->env())->comptes()->creer('carreleur@exemple.ch', 'Carrelages Essai', false, null, 10, 'carreleur');
        $compte = BaseDeTest::ligne("SELECT id, demo, metier, metier_saisi FROM artisan WHERE courriel = 'carreleur@exemple.ch'");
        self::assertSame([0, 'carreleur', null], [$compte['demo'], $compte['metier'], $compte['metier_saisi']]);
        $id = (int) $compte['id'];
        self::assertSame(12, BaseDeTest::compter('produit', "artisan_id = $id"));
        self::assertSame(12, BaseDeTest::compter('produit', "artisan_id = $id AND prix_centimes = 0"));
        // Le prix de la démonstration attend à côté, pour être proposé par l'écran « Vos prix ».
        $requete = BaseDeTest::pdo()->query("SELECT designation, prix_exemple_centimes FROM produit WHERE artisan_id = $id ORDER BY designation");
        self::assertNotFalse($requete);
        $attendus = array_column(CatalogueFictif::produits('carreleur'), 'prix_centimes', 'designation');
        ksort($attendus);
        $lus = array_map('intval', $requete->fetchAll(\PDO::FETCH_KEY_PAIR));
        ksort($lus);
        self::assertSame($attendus, $lus);
        // Un essai n'a ni offre ni client d'exemple.
        self::assertSame(0, BaseDeTest::compter('offre', "artisan_id = $id"));
        self::assertSame(0, BaseDeTest::compter('client', "artisan_id = $id"));
    }

    /** Modèles du bâtiment (2026-11-20) : le compte s'ouvre sur le modèle proposé pour son métier ; sans métier, sur Sobre. */
    public function testUnCompteDePlatrierOuvreSurChantier(): void
    {
        $comptes = Monde::conteneur(env: $this->dossiers->env())->comptes();
        $comptes->creer('platrier@exemple.ch', 'Plâtrerie Essai', false, null, 10, 'platrier');
        $comptes->creer('demo-macon@exemple.ch', 'Maçonnerie Démo', true, 5, null, 'macon');
        $comptes->creer('sans@exemple.ch', 'Sans métier', false, null, 10);
        $modele = static fn (string $courriel): string => (string) BaseDeTest::ligne("SELECT modele FROM artisan WHERE courriel = '$courriel'")['modele'];
        self::assertSame(['chantier', 'bandeau', 'sobre'], [$modele('platrier@exemple.ch'), $modele('demo-macon@exemple.ch'), $modele('sans@exemple.ch')]);
        // Une démonstration remise à zéro dans un autre métier prend le modèle de ce métier, et garde sa couleur.
        BaseDeTest::pdo()->exec("UPDATE artisan SET couleur = '#2E7D32' WHERE courriel = 'demo-macon@exemple.ch'");
        $comptes->reinitialiserDemo('demo-macon@exemple.ch', 'sanitaire');
        self::assertSame(['technique', '#2E7D32'], array_values(BaseDeTest::ligne("SELECT modele, couleur FROM artisan WHERE courriel = 'demo-macon@exemple.ch'")));
    }

    public function testSansMetierLeCatalogueResteVide(): void
    {
        Monde::conteneur(env: $this->dossiers->env())->comptes()->creer('vide@exemple.ch', 'Vide', false, null, 10);
        $compte = BaseDeTest::ligne("SELECT id, metier FROM artisan WHERE courriel = 'vide@exemple.ch'");
        self::assertNull($compte['metier']);
        self::assertSame(0, BaseDeTest::compter('produit', 'artisan_id = ' . (int) $compte['id']));
    }

    public function testMetierInconnuOuTexteFauxRefuseAvantLaCreationDuCompte(): void
    {
        $comptes = Monde::conteneur(env: $this->dossiers->env())->comptes();
        foreach ([['boulanger', null], ['peintre', 'Ébéniste'], ['autre', str_repeat('x', 61)], [null, 'Ébéniste']] as [$metier, $texte]) {
            try {
                $comptes->creer('refus@exemple.ch', 'Refus', false, null, 10, $metier, $texte);
                self::fail('Compte créé avec un métier faux : ' . var_export($metier, true));
            } catch (\InvalidArgumentException) {
            }
        }
        self::assertSame(0, BaseDeTest::compter('artisan', "courriel = 'refus@exemple.ch'"));
    }

    public function testDemoCreeeAvecUnMetierEstPoseeToutDeSuite(): void
    {
        Monde::conteneur(env: $this->dossiers->env())->comptes()->creer('demo-macon@exemple.ch', 'Démo maçon', true, 10, null, 'macon');
        $compte = BaseDeTest::ligne("SELECT id, demo, metier FROM artisan WHERE courriel = 'demo-macon@exemple.ch'");
        self::assertSame([1, 'macon'], [$compte['demo'], $compte['metier']]);
        $id = (int) $compte['id'];
        self::assertSame(12, BaseDeTest::compter('produit', "artisan_id = $id AND prix_centimes > 0 AND prix_exemple_centimes = prix_centimes"));
        self::assertSame(2, BaseDeTest::compter('offre', "artisan_id = $id AND exemple = 1"));
    }

    public function testLePlafondTotalSeChangeApresCoup(): void
    {
        $comptes = Monde::conteneur(env: $this->dossiers->env())->comptes();
        $comptes->creer('plus@exemple.ch', 'Plus', false, null, 20);
        $comptes->changerOffresMax('plus@exemple.ch', 30);
        self::assertSame(30, BaseDeTest::ligne("SELECT offres_max FROM artisan WHERE courriel = 'plus@exemple.ch'")['offres_max']);
        $comptes->changerOffresMax('plus@exemple.ch', null);
        self::assertNull(BaseDeTest::ligne("SELECT offres_max FROM artisan WHERE courriel = 'plus@exemple.ch'")['offres_max']);
    }
}
