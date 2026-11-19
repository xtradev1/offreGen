<?php

declare(strict_types=1);

namespace OffreGen\Tests\Compte;

use OffreGen\Adresse\Adresse;
use OffreGen\Compte\Comptes;
use OffreGen\Donnees\DepotClients;
use OffreGen\Donnees\DepotEnregistrements;
use OffreGen\Donnees\DepotOffres;
use OffreGen\Donnees\DepotProduits;
use OffreGen\Donnees\Portee;
use OffreGen\Offre\LigneExtraite;
use OffreGen\Tests\Outils\BaseDeTest;
use OffreGen\Tests\Outils\Dossiers;
use OffreGen\Tests\Outils\Jeu;
use OffreGen\Tests\Outils\Monde;
use PHPUnit\Framework\TestCase;

/**
 * Une démo devient un essai (SPEC, « Passage de la démo à l'essai ») : les exemples partent, ce que l'artisan
 * a dicté, créé ou modifié reste.
 */
final class PassageEssaiTest extends TestCase
{
    private Jeu $jeu;
    private Dossiers $dossiers;
    private Comptes $comptes;
    private int $offreDictee;
    private int $produitModifie;
    private int $produitCree;
    private int $clientGarde;
    private int $clientSeul;

    protected function setUp(): void
    {
        $this->jeu = new Jeu();
        $this->dossiers = new Dossiers();
        $this->comptes = Monde::conteneur(env: $this->dossiers->env())->comptes();
        $a = $this->jeu->a;
        BaseDeTest::pdo()->exec("UPDATE artisan SET demo = 1, plafond_jour = 10, demo_fin = '2026-11-30 00:00:00' WHERE id = $a");
        $this->comptes->reinitialiserDemo('a@exemple.ch', 'sols');

        // Ce que le visiteur a fait pendant la démo.
        $portee = new Portee($a);
        $produits = new DepotProduits($this->jeu->base, $portee);
        $tous = $produits->tous();
        $this->produitModifie = $tous[0]['id'];
        $produits->modifier($this->produitModifie, ['categorie' => $tous[0]['categorie'], 'designation' => $tous[0]['designation'], 'unite' => $tous[0]['unite'], 'prix_centimes' => 12345]);
        $this->produitCree = $produits->creer(['categorie' => 'Divers', 'designation' => 'Déplacement', 'unite' => 'forfait', 'prix_centimes' => 8000]);

        $clients = BaseDeTest::pdo()->query("SELECT id FROM client WHERE artisan_id = $a ORDER BY id");
        self::assertNotFalse($clients);
        [$this->clientGarde, $this->clientSeul] = array_map('intval', $clients->fetchAll(\PDO::FETCH_COLUMN));

        // Une visite dictée, pour un client d'exemple, avec une ligne du catalogue.
        $enregistrement = (new DepotEnregistrements($this->jeu->base, $portee))
            ->creer('00000000-0000-4000-8000-000000000009', 'visite.m4a', 'audio/mp4', 1000, "$a/00000000-0000-4000-8000-000000000009.m4a", $this->jeu->maintenant);
        $this->offreDictee = (new DepotOffres($this->jeu->base, $portee))->creerDepuisEnregistrement(
            $enregistrement, 'Salon dicté', [new LigneExtraite($tous[1]['designation'], 20000, $tous[1]['unite'], $tous[1]['prix_centimes'], false, $tous[1]['id'])],
            $this->jeu->maintenant, $this->clientGarde);
    }

    protected function tearDown(): void
    {
        $this->dossiers->effacer();
    }

    public function testLesExemplesPartentEtLeTravailDeLArtisanReste(): void
    {
        $a = $this->jeu->a;
        $prixDicte = (int) BaseDeTest::ligne("SELECT prix_centimes FROM ligne WHERE offre_id = {$this->offreDictee}")['prix_centimes'];
        self::assertGreaterThan(0, $prixDicte);

        $bilan = $this->comptes->passerEnEssai('a@exemple.ch', 10);

        // Onze produits remis à 0 (le douzième a été modifié), deux offres d'exemple, une fiche d'exemple sans offre.
        self::assertSame(['offres' => 2, 'clients' => 1, 'produits' => 11], $bilan);
        self::assertSame(0, BaseDeTest::compter('offre', "artisan_id = $a AND exemple = 1"));
        self::assertSame(1, BaseDeTest::compter('offre', "artisan_id = $a"));
        // L'offre dictée garde sa ligne, son prix du jour de la dictée et son lien au catalogue.
        self::assertSame(1, BaseDeTest::compter('ligne', "offre_id = {$this->offreDictee} AND prix_centimes = $prixDicte AND produit_id IS NOT NULL"));
        // La fiche que l'offre dictée désigne reste et perd sa marque ; l'autre fiche d'exemple part.
        self::assertSame(1, BaseDeTest::compter('client', "id = {$this->clientGarde} AND exemple = 0"));
        self::assertSame(0, BaseDeTest::compter('client', "id = {$this->clientSeul}"));
        self::assertSame(1, BaseDeTest::compter('client', "artisan_id = $a"));
        // Catalogue : les exemples gardent désignation et unité, prix à 0, prix de la démo gardé à côté.
        self::assertSame(13, BaseDeTest::compter('produit', "artisan_id = $a"));
        self::assertSame(11, BaseDeTest::compter('produit', "artisan_id = $a AND prix_centimes = 0 AND prix_exemple_centimes > 0"));
        self::assertSame(1, BaseDeTest::compter('produit', "id = {$this->produitModifie} AND prix_centimes = 12345 AND prix_exemple_centimes IS NULL"));
        self::assertSame(1, BaseDeTest::compter('produit', "id = {$this->produitCree} AND prix_centimes = 8000 AND prix_exemple_centimes IS NULL"));
        // La garantie de la spec : aucun produit ne porte à la fois la marque « exemple » et un prix.
        self::assertSame(0, BaseDeTest::compter('produit', "artisan_id = $a AND prix_exemple_centimes IS NOT NULL AND prix_centimes <> 0"));
        // Le compte : hors démo, dix offres de plus que la visite déjà dictée, métier et entreprise gardés.
        self::assertSame(['demo' => 0, 'plafond_jour' => null, 'offres_max' => 11, 'demo_fin' => null, 'metier' => 'sols', 'entreprise' => 'Entreprise A'],
            BaseDeTest::ligne("SELECT demo, plafond_jour, offres_max, demo_fin, metier, entreprise FROM artisan WHERE id = $a"));
        self::assertSame(1, BaseDeTest::compter('enregistrement', "artisan_id = $a"));
    }

    public function testUneFicheDExempleCorrigeeResteCelleDeLArtisan(): void
    {
        // Aucune offre ne la désigne, mais le visiteur l'a corrigée : elle a perdu sa marque, elle reste.
        (new DepotClients($this->jeu->base, new Portee($this->jeu->a)))->modifier($this->clientSeul,
            ['nom' => 'Paul Vrai', 'adresse' => new Adresse('Rue du Stand', '4', '2800', 'Delémont'), 'telephone' => null, 'courriel' => null], $this->jeu->maintenant);

        $bilan = $this->comptes->passerEnEssai('a@exemple.ch', 10);

        self::assertSame(0, $bilan['clients']);
        self::assertSame(1, BaseDeTest::compter('client', "id = {$this->clientSeul} AND nom = 'Paul Vrai' AND exemple = 0"));
    }

    public function testLeCompteVoisinNEstPasTouche(): void
    {
        $b = $this->jeu->b;
        BaseDeTest::pdo()->exec("UPDATE produit SET prix_exemple_centimes = 1850 WHERE artisan_id = $b");
        BaseDeTest::pdo()->exec("UPDATE offre SET exemple = 1 WHERE artisan_id = $b");
        BaseDeTest::pdo()->exec("UPDATE client SET exemple = 1 WHERE artisan_id = $b");

        $this->comptes->passerEnEssai('a@exemple.ch', 10);

        self::assertSame(1, BaseDeTest::compter('offre', "artisan_id = $b AND exemple = 1"));
        self::assertSame(1, BaseDeTest::compter('client', "artisan_id = $b AND exemple = 1"));
        self::assertSame(1, BaseDeTest::compter('produit', "artisan_id = $b AND prix_centimes = 1850 AND prix_exemple_centimes = 1850"));
        self::assertSame(1, BaseDeTest::compter('artisan', "id = $b AND demo = 0 AND offres_max IS NULL"));
    }

    public function testUneFactureDUneOffreDExempleNeBloqueRien(): void
    {
        $pdo = BaseDeTest::pdo();
        $a = $this->jeu->a;
        $exemple = (int) BaseDeTest::ligne("SELECT id FROM offre WHERE artisan_id = $a AND exemple = 1 ORDER BY id LIMIT 1")['id'];
        foreach ([$exemple => '2026-001', $this->offreDictee => '2026-003'] as $offre => $numero) {
            $pdo->exec("INSERT INTO facture (artisan_id, offre_id, numero, genre, statut, date_facture, echeance, titre, client_nom, cree_le)
                        VALUES ($a, $offre, '$numero', 'finale', 'annulee', '2026-11-13', '2026-12-13', 'X', 'Y', NOW())");
            $emise = (int) $pdo->lastInsertId();
            $pdo->exec("INSERT INTO facture_ligne (artisan_id, facture_id, rang, designation, quantite_milliemes, unite, prix_centimes) VALUES ($a, $emise, 1, 'L', 1000, 'pce', 1)");
            $pdo->exec("INSERT INTO facture_pdf (facture_id, artisan_id, contenu, cree_le) VALUES ($emise, $a, 'pdf', NOW())");
            if ($offre === $exemple) {
                $pdo->exec("INSERT INTO facture (artisan_id, offre_id, numero, genre, statut, date_facture, echeance, titre, client_nom, cree_le, refaite_depuis)
                            VALUES ($a, $offre, '2026-002', 'finale', 'brouillon', '2026-11-13', '2026-12-13', 'X', 'Y', NOW(), $emise)");
            }
        }

        $this->comptes->passerEnEssai('a@exemple.ch', 10);

        // Les factures des offres d'exemple partent avec elles ; celle de l'offre dictée reste entière.
        self::assertSame(1, BaseDeTest::compter('facture', "artisan_id = $a AND offre_id = {$this->offreDictee}"));
        self::assertSame(1, BaseDeTest::compter('facture', "artisan_id = $a"));
        self::assertSame(1, BaseDeTest::compter('facture_pdf', "artisan_id = $a"));
        self::assertSame(1, BaseDeTest::compter('facture_ligne', "artisan_id = $a"));
    }

    public function testUnCompteQuiNEstPasUneDemoRefuseSansRienChanger(): void
    {
        try {
            $this->comptes->passerEnEssai('b@exemple.ch', 10);
            self::fail('Un compte réel est passé par le nettoyage.');
        } catch (\InvalidArgumentException $e) {
            self::assertStringContainsString("n'est pas un compte de démonstration", $e->getMessage());
        }
        self::assertSame(1, BaseDeTest::compter('offre', "artisan_id = {$this->jeu->b}"));
        self::assertSame(1, BaseDeTest::compter('artisan', "id = {$this->jeu->b} AND offres_max IS NULL"));
    }

    public function testUnNombreDOffresNulRefuseSansRienChanger(): void
    {
        try {
            $this->comptes->passerEnEssai('a@exemple.ch', 0);
            self::fail('Un essai sans offre a été accepté.');
        } catch (\InvalidArgumentException) {
        }
        self::assertSame(2, BaseDeTest::compter('offre', "artisan_id = {$this->jeu->a} AND exemple = 1"));
        self::assertSame(1, BaseDeTest::compter('artisan', "id = {$this->jeu->a} AND demo = 1"));
    }

    public function testUneEtapeQuiEchoueLaisseLaDemoEntiere(): void
    {
        $a = $this->jeu->a;
        try {
            // Le plafond dépasse la colonne (INT UNSIGNED) : la dernière écriture de la transaction échoue.
            $this->comptes->passerEnEssai('a@exemple.ch', 9_000_000_000);
            self::fail('Le plafond hors limite a été accepté.');
        } catch (\PDOException) {
        }
        self::assertSame(2, BaseDeTest::compter('offre', "artisan_id = $a AND exemple = 1"));
        self::assertSame(2, BaseDeTest::compter('client', "artisan_id = $a"));
        self::assertSame(0, BaseDeTest::compter('produit', "artisan_id = $a AND prix_centimes = 0"));
        self::assertSame(1, BaseDeTest::compter('artisan', "id = $a AND demo = 1 AND plafond_jour = 10"));
    }
}
