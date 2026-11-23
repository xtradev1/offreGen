<?php

declare(strict_types=1);

namespace OffreGen\Tests\Http;

use OffreGen\Donnees\DepotProduits;
use OffreGen\Donnees\Portee;
use OffreGen\Tests\Outils\BaseDeTest;
use OffreGen\Tests\Outils\Jeu;
use OffreGen\Tests\Outils\Monde;
use OffreGen\Tests\Outils\Navigateur;
use PHPUnit\Framework\TestCase;

/**
 * L'écran « Vos prix » (docs/plans/2026-11-19_metiers-demo, lots 7 à 9) : un produit sans prix à la fois.
 * « Suivant » enregistre le prix tapé, « Reprendre » celui de la démonstration, « Passer » laisse à 0,
 * « Je ne fais pas ça » retire le produit ; « Reprendre » et le retrait s'annulent.
 */
final class VosPrixTest extends TestCase
{
    private Jeu $jeu;
    private Navigateur $a;
    private int $crepi;
    private int $lissage;
    private int $baguette;

    protected function setUp(): void
    {
        $this->jeu = new Jeu();
        // Le catalogue d'un essai : trois produits d'exemple sans prix (dont un sans prix de démonstration), et un
        // produit de l'artisan, chiffré. Ordre de l'écran, celui du catalogue : Crépi, Lissage, Baguette.
        $produits = new DepotProduits($this->jeu->base, new Portee($this->jeu->a));
        $produits->remplacerTout([
            ['categorie' => 'Finitions', 'designation' => 'Baguette', 'unite' => 'm', 'prix_centimes' => 0, 'prix_exemple_centimes' => 1200],
            ['categorie' => 'Enduits', 'designation' => 'Crépi', 'unite' => 'm²', 'prix_centimes' => 0, 'prix_exemple_centimes' => 4000],
            ['categorie' => 'Enduits', 'designation' => 'Lissage', 'unite' => 'm²', 'prix_centimes' => 0, 'prix_exemple_centimes' => 0],
            ['categorie' => 'Divers', 'designation' => 'Déplacement', 'unite' => 'forfait', 'prix_centimes' => 6000],
        ]);
        $ids = array_column($produits->tous(), 'id', 'designation');
        [$this->crepi, $this->lissage, $this->baguette] = [$ids['Crépi'], $ids['Lissage'], $ids['Baguette']];
        $this->a = new Navigateur(Monde::conteneur());
        $this->a->connecter('a@exemple.ch');
    }

    private function page(string $chemin): string
    {
        $reponse = $this->a->get($chemin);
        self::assertSame(200, $reponse->getStatusCode(), $chemin);
        return (string) $reponse->getContent();
    }

    /** @param array<string, string> $champs */
    private function agir(int $id, array $champs): string
    {
        $reponse = $this->a->post("/vos-prix/$id", $champs);
        self::assertSame(303, $reponse->getStatusCode(), (string) $reponse->getContent());
        return (string) $reponse->headers->get('Location');
    }

    /** @return array<string, mixed> */
    private function produit(int $id): array
    {
        return BaseDeTest::ligne("SELECT prix_centimes, prix_exemple_centimes FROM produit WHERE id = $id");
    }

    /** E8 de Xavier (2026-11-23) : après la mise en route, « Vos prix » en est la cinquième étape. */
    public function testDepuisLaMiseEnRouteVosPrixEstLEtapeCinq(): void
    {
        $page = $this->page('/vos-prix?mise_en_route=1');
        self::assertStringContainsString('Étape 5 sur 5', $page);
        self::assertStringContainsString('Passer le guide', $page);
        self::assertStringNotContainsString('class="vp-quitter"', $page);
        // Le produit suivant garde la tête ; un nouvel accès depuis le catalogue, non.
        self::assertStringContainsString('Étape 5 sur 5', $this->page('/vos-prix?suite=1'));
        $seul = $this->page('/vos-prix');
        self::assertStringNotContainsString('Étape 5 sur 5', $seul);
        self::assertStringContainsString('class="vp-quitter"', $seul);
    }

    public function testLaCarteMontreLePremierProduitSansPrix(): void
    {
        $page = $this->page('/vos-prix');
        self::assertStringContainsString('Crépi', $page);
        self::assertStringContainsString('<b>1</b> sur 3', $page);
        self::assertStringContainsString('action="/vos-prix/' . $this->crepi . '"', $page);
        self::assertStringContainsString('inputmode="decimal"', $page);
        self::assertStringContainsString('40.00', $page);
        self::assertStringContainsString('Reprendre', $page);
        self::assertStringContainsString('href="/css/vos-prix.css"', $page);
        self::assertStringContainsString('src="/js/vos-prix.js"', $page);
        // La touche Entrée envoie le premier bouton du formulaire : ce doit être « Suivant », jamais le retrait.
        self::assertSame(1, preg_match('/<form[^>]*data-vos-prix.*?<button[^>]*>/s', $page, $m));
        $premierBouton = $m[0] ?? '';
        self::assertStringContainsString('value="suivant"', $premierBouton);
        self::assertStringNotContainsString('value="retirer"', $premierBouton);
    }

    public function testSuivantEnregistreLePrixEtOuvreLeProduitSuivant(): void
    {
        $this->page('/vos-prix');
        $suite = $this->agir($this->crepi, ['action' => 'suivant', 'prix' => '42,50']);
        self::assertSame("/vos-prix?p={$this->lissage}&suite=1", $suite);
        // Le prix tapé est celui de l'artisan : la marque « exemple » part.
        self::assertSame(['prix_centimes' => 4250, 'prix_exemple_centimes' => null], $this->produit($this->crepi));
        $page = $this->page($suite);
        self::assertStringContainsString('Lissage', $page);
        self::assertStringContainsString('<b>2</b> sur 3', $page);
        // Aucun prix de démonstration pour ce produit : rien à reprendre.
        self::assertStringNotContainsString('value="reprendre"', $page);
    }

    public function testSuivantSansPrixRendLaMemeCarteEnErreur(): void
    {
        $this->page('/vos-prix');
        foreach (['', '0', 'cher'] as $saisie) {
            $reponse = $this->a->post("/vos-prix/{$this->crepi}", ['action' => 'suivant', 'prix' => $saisie]);
            self::assertSame(422, $reponse->getStatusCode(), $saisie);
            $page = (string) $reponse->getContent();
            self::assertStringContainsString('Tapez un prix', $page);
            self::assertStringContainsString('Crépi', $page);
            self::assertStringContainsString('<b>1</b> sur 3', $page);
        }
        self::assertSame(['prix_centimes' => 0, 'prix_exemple_centimes' => 4000], $this->produit($this->crepi));
    }

    public function testReprendreEnregistreLePrixDeLaDemoAvanceEtSAnnule(): void
    {
        $this->page('/vos-prix');
        $suite = $this->agir($this->crepi, ['action' => 'reprendre', 'prix' => '']);
        self::assertSame("/vos-prix?p={$this->lissage}&suite=1", $suite);
        self::assertSame(['prix_centimes' => 4000, 'prix_exemple_centimes' => null], $this->produit($this->crepi));
        $page = $this->page($suite);
        self::assertStringContainsString('prix repris', $page);
        self::assertStringContainsString('action="/vos-prix/annuler"', $page);

        $retour = $this->a->post('/vos-prix/annuler', []);
        self::assertSame(303, $retour->getStatusCode());
        self::assertSame("/vos-prix?p={$this->crepi}&suite=1", $retour->headers->get('Location'));
        self::assertSame(['prix_centimes' => 0, 'prix_exemple_centimes' => 4000], $this->produit($this->crepi));
        $page = $this->page("/vos-prix?p={$this->crepi}&suite=1");
        self::assertStringContainsString('Crépi', $page);
        self::assertStringContainsString('<b>1</b> sur 3', $page);
    }

    public function testReprendreSansPrixDeDemoEstRefuse(): void
    {
        $this->page("/vos-prix?p={$this->lissage}");
        $reponse = $this->a->post("/vos-prix/{$this->lissage}", ['action' => 'reprendre', 'prix' => '']);
        self::assertSame(422, $reponse->getStatusCode());
        self::assertSame(['prix_centimes' => 0, 'prix_exemple_centimes' => 0], $this->produit($this->lissage));
    }

    public function testPasserLaisseLesProduitsAZeroPuisLeBilanProposeDeLesReprendre(): void
    {
        $this->page('/vos-prix');
        self::assertSame("/vos-prix?p={$this->lissage}&suite=1", $this->agir($this->crepi, ['action' => 'passer']));
        $this->page("/vos-prix?p={$this->lissage}&suite=1");
        self::assertSame("/vos-prix?p={$this->baguette}&suite=1", $this->agir($this->lissage, ['action' => 'passer']));
        $this->page("/vos-prix?p={$this->baguette}&suite=1");
        self::assertSame('/vos-prix?suite=1', $this->agir($this->baguette, ['action' => 'passer']));
        self::assertSame(3, BaseDeTest::compter('produit', "artisan_id = {$this->jeu->a} AND prix_centimes = 0"));

        $bilan = $this->page('/vos-prix?suite=1');
        self::assertStringContainsString('<li><span>À compléter plus tard</span><b>3</b></li>', $bilan);
        self::assertStringContainsString('Reprendre les produits passés', $bilan);
        self::assertStringNotContainsString('data-vos-prix', $bilan);
        // Une nouvelle ouverture repart du premier.
        $page = $this->page('/vos-prix');
        self::assertStringContainsString('Crépi', $page);
        self::assertStringContainsString('<b>1</b> sur 3', $page);
    }

    public function testJeNeFaisPasCaRetireLeProduitEtAnnulerLeRemet(): void
    {
        $this->page('/vos-prix');
        $suite = $this->agir($this->crepi, ['action' => 'retirer']);
        self::assertSame("/vos-prix?p={$this->lissage}&suite=1", $suite);
        self::assertSame(0, BaseDeTest::compter('produit', "id = {$this->crepi}"));
        $page = $this->page($suite);
        self::assertStringContainsString('retiré du catalogue', $page);
        self::assertStringContainsString('<b>2</b> sur 3', $page);

        $retour = $this->a->post('/vos-prix/annuler', []);
        self::assertSame(303, $retour->getStatusCode());
        $remis = BaseDeTest::ligne("SELECT id, categorie, unite, prix_centimes, prix_exemple_centimes FROM produit WHERE artisan_id = {$this->jeu->a} AND designation = 'Crépi'");
        self::assertSame(['categorie' => 'Enduits', 'unite' => 'm²', 'prix_centimes' => 0, 'prix_exemple_centimes' => 4000], array_diff_key($remis, ['id' => 0]));
        self::assertSame("/vos-prix?p={$remis['id']}&suite=1", $retour->headers->get('Location'));
        self::assertStringContainsString('<b>1</b> sur 3', $this->page("/vos-prix?p={$remis['id']}&suite=1"));
        // Rien à annuler deux fois.
        $encore = $this->a->post('/vos-prix/annuler', []);
        self::assertSame('/vos-prix?suite=1', $encore->headers->get('Location'));
        self::assertSame(1, BaseDeTest::compter('produit', "artisan_id = {$this->jeu->a} AND designation = 'Crépi'"));
    }

    // Revue du 2026-11-20.

    public function testPasserEnvoyeDeuxFoisNeCompteQuUneFois(): void
    {
        $this->page('/vos-prix');
        $this->agir($this->crepi, ['action' => 'passer']);
        $this->page("/vos-prix?p={$this->lissage}&suite=1");
        $this->agir($this->crepi, ['action' => 'passer']);
        self::assertStringContainsString('<b>2</b> sur 3', $this->page("/vos-prix?p={$this->lissage}&suite=1"));
    }

    public function testUnGesteSuivantRendLAnnulationCaduque(): void
    {
        $this->page('/vos-prix');
        $this->page($this->agir($this->crepi, ['action' => 'reprendre']));
        $this->page($this->agir($this->lissage, ['action' => 'passer']));
        $retour = $this->a->post('/vos-prix/annuler', []);
        self::assertSame('/vos-prix?suite=1', $retour->headers->get('Location'));
        self::assertSame(['prix_centimes' => 4000, 'prix_exemple_centimes' => null], $this->produit($this->crepi));
    }

    public function testAnnulerLaisseUnPrixChangeEntreTemps(): void
    {
        $this->page('/vos-prix');
        $this->page($this->agir($this->crepi, ['action' => 'reprendre']));
        // L'artisan a corrigé ce prix dans son catalogue avant de toucher « Annuler ».
        BaseDeTest::pdo()->exec("UPDATE produit SET prix_centimes = 5000 WHERE id = {$this->crepi}");
        self::assertSame(303, $this->a->post('/vos-prix/annuler', [])->getStatusCode());
        self::assertSame(['prix_centimes' => 5000, 'prix_exemple_centimes' => null], $this->produit($this->crepi));
    }

    public function testLaDerniereCarteEstMarqueePourLeScript(): void
    {
        self::assertStringNotContainsString('data-dernier', $this->page('/vos-prix'));
        $this->page($this->agir($this->crepi, ['action' => 'passer']));
        $derniere = $this->page($this->agir($this->lissage, ['action' => 'passer']));
        self::assertStringContainsString('Baguette', $derniere);
        self::assertStringContainsString('data-dernier', $derniere);
    }

    public function testLeBilanCompteCeQuiAEteFait(): void
    {
        $this->page('/vos-prix');
        $this->page($this->agir($this->crepi, ['action' => 'suivant', 'prix' => '40']));
        $this->page($this->agir($this->lissage, ['action' => 'retirer']));
        $bilan = $this->page($this->agir($this->baguette, ['action' => 'passer']));
        self::assertStringContainsString('<li><span>Prix enregistrés</span><b>1</b></li>', $bilan);
        self::assertStringContainsString('<li><span>À compléter plus tard</span><b>1</b></li>', $bilan);
        self::assertStringContainsString('<li><span>Produits retirés</span><b>1</b></li>', $bilan);
    }

    public function testSansProduitACompleterLEcranLeDit(): void
    {
        BaseDeTest::pdo()->exec("UPDATE produit SET prix_centimes = 100 WHERE artisan_id = {$this->jeu->a}");
        $page = $this->page('/vos-prix');
        self::assertStringContainsString('Tous vos produits ont un prix', $page);
        self::assertStringNotContainsString('data-vos-prix', $page);
    }

    public function testUnProduitDeBEstIntrouvableEtResteIntact(): void
    {
        $this->page('/vos-prix');
        $deB = $this->jeu->produit['b'];
        BaseDeTest::pdo()->exec("UPDATE produit SET prix_centimes = 0, prix_exemple_centimes = 1850 WHERE id = $deB");
        foreach (['suivant', 'reprendre', 'passer', 'retirer'] as $action) {
            $this->page('/vos-prix');
            self::assertSame(404, $this->a->post("/vos-prix/$deB", ['action' => $action, 'prix' => '5'])->getStatusCode(), $action);
        }
        self::assertSame(['prix_centimes' => 0, 'prix_exemple_centimes' => 1850], $this->produit($deB));
    }

    public function testUneActionInconnueNeChangeRien(): void
    {
        $this->page('/vos-prix');
        self::assertSame(422, $this->a->post("/vos-prix/{$this->crepi}", ['action' => 'effacer', 'prix' => '5'])->getStatusCode());
        self::assertSame(['prix_centimes' => 0, 'prix_exemple_centimes' => 4000], $this->produit($this->crepi));
    }

    public function testLAccueilAnnonceLesProduitsDExempleSansPrixEtPlusTardReduitLeBandeau(): void
    {
        $accueil = $this->page('/');
        self::assertStringContainsString('3 produits attendent votre prix', $accueil);
        self::assertStringContainsString('href="/vos-prix"', $accueil);
        self::assertStringContainsString('href="/css/vos-prix.css"', $accueil);
        // Le script ouvre l'écran sans changer de page : sur iPhone, le clavier ne s'ouvre que pendant un toucher.
        self::assertStringContainsString('src="/js/vos-prix.js"', $accueil);

        $reponse = $this->a->post('/vos-prix/plus-tard', []);
        self::assertSame(303, $reponse->getStatusCode());
        self::assertSame('/', $reponse->headers->get('Location'));
        $accueil = $this->page('/');
        self::assertStringNotContainsString('attendent votre prix', $accueil);
        self::assertStringContainsString('3 produits sans prix', $accueil);
        self::assertStringContainsString('href="/vos-prix"', $accueil);
        self::assertStringContainsString('src="/js/vos-prix.js"', $accueil);
    }

    public function testLAccueilNeDitRienAUneDemoNiAUnCompteSansProduitDExemple(): void
    {
        // Une démonstration : le visiteur ne complète rien.
        BaseDeTest::pdo()->exec("UPDATE artisan SET demo = 1 WHERE id = {$this->jeu->a}");
        self::assertStringNotContainsString('/vos-prix', $this->page('/'));
        // Un compte dont les produits sans prix sont les siens : rien de neuf sur l'accueil.
        BaseDeTest::pdo()->exec("UPDATE artisan SET demo = 0 WHERE id = {$this->jeu->a}");
        BaseDeTest::pdo()->exec("UPDATE produit SET prix_exemple_centimes = NULL WHERE artisan_id = {$this->jeu->a}");
        self::assertStringNotContainsString('/vos-prix', $this->page('/'));
        // Un seul produit d'exemple : le singulier.
        BaseDeTest::pdo()->exec("UPDATE produit SET prix_exemple_centimes = 4000 WHERE id = {$this->crepi}");
        self::assertStringContainsString('1 produit attend votre prix', $this->page('/'));
    }

    public function testLeCatalogueRappelleLesProduitsSansPrix(): void
    {
        $catalogue = $this->page('/catalogue');
        self::assertStringContainsString('3 produits sans prix', $catalogue);
        self::assertStringContainsString('href="/vos-prix"', $catalogue);
        self::assertStringContainsString('src="/js/vos-prix.js"', $catalogue);
        BaseDeTest::pdo()->exec("UPDATE produit SET prix_centimes = 100 WHERE artisan_id = {$this->jeu->a}");
        self::assertStringNotContainsString('/vos-prix', $this->page('/catalogue'));
    }
}
