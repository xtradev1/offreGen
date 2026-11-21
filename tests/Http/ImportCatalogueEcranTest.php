<?php

declare(strict_types=1);

namespace OffreGen\Tests\Http;

use OffreGen\Catalogue\Integration;
use OffreGen\Donnees\DepotProduits;
use OffreGen\Donnees\Portee;
use OffreGen\Http\Controleur\ImportCatalogueControleur;
use OffreGen\Tests\Outils\BaseDeTest;
use OffreGen\Tests\Outils\Jeu;
use OffreGen\Tests\Outils\Monde;
use OffreGen\Tests\Outils\Navigateur;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

/**
 * Import du catalogue depuis d'anciennes offres (chantier 2026-11-20_import-catalogue), par les écrans, sans modèle :
 * lecteur et comparateur factices. Le navigateur envoie le texte de chaque PDF ; le serveur lit, regroupe, montre
 * ce qu'il propose, intègre les réponses de l'artisan, et sait tout annuler. Trois imports par mois (Xavier, 2026-11-20).
 */
final class ImportCatalogueEcranTest extends TestCase
{
    private Jeu $jeu;
    private Navigateur $a;
    private MockClock $horloge;

    /** Deux offres d'un peintre : la dispersion à deux prix, le déplacement déjà au catalogue à un autre prix. */
    private const OFFRE_1 = [
        'Peinture Exemple Sàrl, rue du Modèle 4',
        'Pos. | Désignation | Qté | Unité | Prix unitaire | Total',
        '1 | Dispersion sur murs, 2 couches | 62 | m2 | 18.50 | 1\'147.00',
        '2 | Peinture des plinthes | 38 | ml | 7.50 | 285.00',
        '3 | Déplacement | 1 | forfait | 70.00 | 70.00',
        'Total | 1\'502.00',
    ];
    private const OFFRE_2 = [
        'Pos. | Désignation | Qté | Unité | Prix unitaire | Total',
        '1 | Dispersion sur murs, 2 couches | 40 | m2 | 19.00 | 760.00',
        '2 | Location d\'échafaudage roulant | 2 | j | 85.00 | 170.00',
    ];

    protected function setUp(): void
    {
        $this->jeu = new Jeu();
        (new DepotProduits($this->jeu->base, new Portee($this->jeu->a)))->remplacerTout([
            ['categorie' => 'Divers', 'designation' => 'Déplacement', 'unite' => 'forfait', 'prix_centimes' => 6000],
        ]);
        $this->horloge = new MockClock('2026-11-20 10:00:00', 'UTC');
        $this->a = new Navigateur(Monde::conteneur($this->horloge));
        $this->a->connecter('a@exemple.ch');
    }

    /**
     * @param array<string, string> $champs
     * @return array<string, mixed>
     */
    private function json(string $chemin, array $champs = [], int $attendu = 200): array
    {
        $reponse = $this->a->post($chemin, $champs, json: true);
        self::assertSame($attendu, $reponse->getStatusCode(), $chemin . ' : ' . $reponse->getContent());
        $donnees = json_decode((string) $reponse->getContent(), true);
        self::assertIsArray($donnees);
        return $donnees;
    }

    /** @param list<list<string>> $offres */
    private function importer(array $offres): int
    {
        $this->a->get('/import-catalogue');
        $id = $this->json('/import-catalogue', [], 201)['id'];
        self::assertIsInt($id);
        foreach ($offres as $texte) {
            $this->json("/import-catalogue/$id/offres", ['lignes' => (string) json_encode($texte)]);
        }
        self::assertSame("/import-catalogue/$id", $this->json("/import-catalogue/$id/regrouper")['url']);
        return $id;
    }

    /** @return array<string, array<string, mixed>> */
    private function produits(): array
    {
        $produits = [];
        foreach (BaseDeTest::pdo()->query("SELECT designation, unite, prix_centimes, categorie, synonymes FROM produit WHERE artisan_id = {$this->jeu->a}") ?: [] as $p) {
            $produits[(string) $p['designation']] = $p;
        }
        return $produits;
    }

    public function testLaPageDitCombienDImportsRestentEtChargeLaLecture(): void
    {
        $page = (string) $this->a->get('/import-catalogue')->getContent();
        self::assertStringContainsString('3 imports', $page);
        self::assertStringContainsString('src="/js/import-catalogue.js', $page);
        self::assertStringContainsString('accept="application/pdf"', $page);
        self::assertStringContainsString('data-offres-max="' . ImportCatalogueControleur::OFFRES_MAX . '"', $page);
    }

    public function testLePlusEtLeCatalogueMenentALImport(): void
    {
        self::assertStringContainsString('href="/import-catalogue"', (string) $this->a->get('/plus')->getContent());
        self::assertStringContainsString('href="/import-catalogue"', (string) $this->a->get('/catalogue')->getContent());
    }

    public function testParcoursLireRegrouperChoisirIntegrerPuisAnnuler(): void
    {
        $id = $this->importer([self::OFFRE_1, self::OFFRE_2]);
        $page = (string) $this->a->get("/import-catalogue/$id")->getContent();
        self::assertStringContainsString('Quel prix garder', $page);
        self::assertStringContainsString('Peinture des plinthes', $page);
        self::assertStringContainsString('Location d&#039;échafaudage roulant', $page);
        self::assertStringNotContainsString('Peinture Exemple Sàrl', $page, 'aucune ligne sans prix');

        // Propositions dans l'ordre de lecture : dispersion (deux prix), plinthes, déplacement (catalogue contre offre), échafaudage.
        $reponse = $this->a->post("/import-catalogue/$id", ['p' => [
            0 => ['choix' => '1'],
            1 => ['garder' => '1', 'designation' => 'Peinture des plinthes', 'unite' => 'ml', 'prix' => '8'],
            2 => ['choix' => '0'],
            3 => ['garder' => '1'],
        ]]);
        self::assertSame(303, $reponse->getStatusCode(), (string) $reponse->getContent());
        $p = $this->produits();
        self::assertSame([1900, 'm²', Integration::CATEGORIE], [$p['Dispersion sur murs, 2 couches']['prix_centimes'], $p['Dispersion sur murs, 2 couches']['unite'], $p['Dispersion sur murs, 2 couches']['categorie']]);
        self::assertSame(800, $p['Peinture des plinthes']['prix_centimes']);
        self::assertSame([8500, 'jour'], [$p['Location d\'échafaudage roulant']['prix_centimes'], $p['Location d\'échafaudage roulant']['unite']]);
        self::assertSame(6000, $p['Déplacement']['prix_centimes'], 'le prix du catalogue est gardé');
        $bilan = (string) $this->a->get("/import-catalogue/$id")->getContent();
        self::assertStringContainsString('3 produits intégrés', $bilan);
        self::assertSame(0, BaseDeTest::compter('import_ligne', "import_id = $id"), 'les lignes lues ne sont pas gardées');

        self::assertSame(303, $this->a->post("/import-catalogue/$id/annuler", [])->getStatusCode());
        self::assertSame(['Déplacement'], array_keys($this->produits()));
        self::assertSame(6000, $this->produits()['Déplacement']['prix_centimes']);
    }

    public function testChangerUnPrixDuCatalogueSeDefaitALAnnulation(): void
    {
        $id = $this->importer([self::OFFRE_1]);
        $this->a->get("/import-catalogue/$id");
        // Dispersion, plinthes, déplacement (70 contre 60) : on prend le prix de l'offre.
        $this->a->post("/import-catalogue/$id", ['p' => [0 => ['garder' => '1'], 1 => [], 2 => ['choix' => '1']]]);
        self::assertSame(7000, $this->produits()['Déplacement']['prix_centimes']);
        $this->a->get("/import-catalogue/$id");
        $this->a->post("/import-catalogue/$id/annuler", []);
        self::assertSame(6000, $this->produits()['Déplacement']['prix_centimes']);
    }

    public function testUnPrixAChoisirSansReponseRendLaPageSansRienEcrire(): void
    {
        $id = $this->importer([self::OFFRE_1, self::OFFRE_2]);
        $this->a->get("/import-catalogue/$id");
        $reponse = $this->a->post("/import-catalogue/$id", ['p' => [1 => ['garder' => '1'], 3 => ['garder' => '1']]]);
        self::assertSame(422, $reponse->getStatusCode());
        self::assertStringContainsString('Choisissez un prix.', (string) $reponse->getContent());
        self::assertSame(['Déplacement'], array_keys($this->produits()));
    }

    public function testTroisImportsParMoisPuisLeMoisSuivant(): void
    {
        $this->a->get('/import-catalogue');
        for ($i = 0; $i < ImportCatalogueControleur::PAR_MOIS; $i++) {
            $this->json('/import-catalogue', [], 201);
        }
        $refus = $this->json('/import-catalogue', [], 429);
        self::assertStringContainsString('3 imports', (string) $refus['erreur']);
        self::assertStringContainsString('0 import', (string) $this->a->get('/import-catalogue')->getContent());
        $this->horloge->modify('2026-12-01 09:00:00');
        $this->a->get('/import-catalogue');
        $this->json('/import-catalogue', [], 201);
    }

    public function testCinqOffresAuPlusParImportEtUnTexteIllisibleRefuse(): void
    {
        $this->a->get('/import-catalogue');
        $id = $this->json('/import-catalogue', [], 201)['id'];
        for ($i = 0; $i < ImportCatalogueControleur::OFFRES_MAX; $i++) {
            $this->json("/import-catalogue/$id/offres", ['lignes' => (string) json_encode(self::OFFRE_2)]);
        }
        $this->json("/import-catalogue/$id/offres", ['lignes' => (string) json_encode(self::OFFRE_2)], 409);
        $this->a->get('/import-catalogue');
        $autre = $this->json('/import-catalogue', [], 201)['id'];
        $this->json("/import-catalogue/$autre/offres", ['lignes' => '{"pas":"une liste"}'], 422);
        $this->json("/import-catalogue/$autre/offres", ['lignes' => (string) json_encode([1, 2])], 422);
    }

    public function testUnImportIntegreNeSeRelitPasEtNeSIntegrePasDeuxFois(): void
    {
        $id = $this->importer([self::OFFRE_2]);
        $this->a->get("/import-catalogue/$id");
        $this->a->post("/import-catalogue/$id", ['p' => [0 => ['garder' => '1'], 1 => ['garder' => '1']]]);
        $avant = $this->produits();
        $this->a->get("/import-catalogue/$id");
        $this->json("/import-catalogue/$id/offres", ['lignes' => (string) json_encode(self::OFFRE_1)], 409);
        $this->a->get("/import-catalogue/$id");
        self::assertSame(303, $this->a->post("/import-catalogue/$id", ['p' => [0 => ['garder' => '1'], 1 => ['garder' => '1']]])->getStatusCode());
        self::assertSame($avant, $this->produits());
    }
}
