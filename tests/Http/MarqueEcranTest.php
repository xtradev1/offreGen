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
 * Sous-chantier 16 : logo, couleur et modèle dans Réglages, jusqu'au document et au PDF.
 */
final class MarqueEcranTest extends TestCase
{
    private Jeu $jeu;
    private Dossiers $dossiers;
    private Navigateur $a;

    /** Les réglages déjà valides de l'écran ; chaque test y ajoute la marque. */
    private const REGLAGES = ['entreprise' => 'Peinture Exemple', 'tva' => '810', 'arrondi' => '1', 'validite_choix' => '30',
                              'paiement_type' => 'jours', 'paiement_jours_choix' => '30'];

    protected function setUp(): void
    {
        $this->jeu = new Jeu();
        $this->dossiers = new Dossiers();
        $this->a = $this->navigateur('a@exemple.ch');
    }

    protected function tearDown(): void
    {
        $this->dossiers->effacer();
    }

    private function navigateur(string $courriel): Navigateur
    {
        $n = new Navigateur(Monde::conteneur(env: $this->dossiers->env()));
        $n->connecter($courriel);
        return $n;
    }

    private function page(string $chemin): string
    {
        $r = $this->a->get($chemin);
        self::assertSame(200, $r->getStatusCode(), $chemin);
        return (string) $r->getContent();
    }

    /** @return array{logo_fichier: string|null, couleur_logo: string|null, couleur: string|null, modele: string} */
    private function marqueDeA(): array
    {
        /** @var array{logo_fichier: string|null, couleur_logo: string|null, couleur: string|null, modele: string} */
        return BaseDeTest::ligne("SELECT logo_fichier, couleur_logo, couleur, modele FROM artisan WHERE id = {$this->jeu->a}");
    }

    private function envoyerLogo(string $fixture): void
    {
        $this->page('/reglages');
        self::assertSame(303, $this->a->post('/reglages/logo', [], ['logo' => $this->dossiers->envoi($fixture, null, 'images')])->getStatusCode());
    }

    public function testLeLogoDonneSaCouleurEtNeSertQuASonArtisan(): void
    {
        $this->envoyerLogo('logo-transparent.png');
        $m = $this->marqueDeA();
        self::assertNotNull($m['logo_fichier']);
        self::assertSame($m['couleur_logo'], $m['couleur']);
        self::assertStringStartsWith('#1', (string) $m['couleur'], 'le bleu du logo');
        self::assertCount(1, $this->dossiers->logos());

        $logo = $this->a->get('/reglages/logo');
        self::assertSame(200, $logo->getStatusCode());
        self::assertSame('image/jpeg', $logo->headers->get('Content-Type'));
        self::assertStringContainsString('private', (string) $logo->headers->get('Cache-Control'));
        $page = $this->page('/reglages/marque');
        self::assertStringContainsString('src="/reglages/logo?v=' . $m['logo_fichier'] . '"', $page);
        // La couleur du logo est la première pastille, cochée.
        self::assertStringContainsString('name="couleur_choix" value="' . $m['couleur'] . '" checked', $page);

        // Un autre artisan n'y a pas accès : il n'a pas de logo, et l'adresse ne prend aucun paramètre.
        self::assertSame(404, $this->navigateur('b@exemple.ch')->get('/reglages/logo')->getStatusCode());

        // Un nouveau logo remplace l'ancien, fichier compris.
        $this->envoyerLogo('logo-rouge.jpg');
        self::assertCount(1, $this->dossiers->logos());
        self::assertStringStartsWith('#B', (string) $this->marqueDeA()['couleur'], 'le rouge du logo');

        // Refusé : rien ne change, le message le dit.
        $avant = $this->marqueDeA();
        $this->envoyerLogo('pas-une-image.png');
        self::assertSame($avant, $this->marqueDeA());
        self::assertStringContainsString('Le logo doit être une image PNG ou JPEG.', $this->page('/reglages/marque'));

        // Retiré : plus de fichier, plus d'adresse.
        self::assertSame(303, $this->a->post('/reglages/logo/retirer', [])->getStatusCode());
        self::assertNull($this->marqueDeA()['logo_fichier']);
        self::assertSame([], $this->dossiers->logos());
        self::assertSame(404, $this->a->get('/reglages/logo')->getStatusCode());
    }

    public function testModeleEtCouleurVontAuDocumentEtAuPdf(): void
    {
        $offre = '/offres/' . $this->jeu->offre['a'];
        $this->page('/reglages');
        self::assertSame(303, $this->a->post('/reglages', self::REGLAGES + ['modele' => 'bandeau', 'couleur_choix' => '#2E7D32'])->getStatusCode());
        self::assertSame(['bandeau', '#2E7D32'], [$this->marqueDeA()['modele'], $this->marqueDeA()['couleur']]);
        $doc = $this->page("$offre/document");
        self::assertStringContainsString('class="doc m-bandeau"', $doc);
        // La CSP refuse les styles en ligne : la couleur passe par une feuille servie par le site.
        self::assertMatchesRegularExpression('#<link rel="stylesheet" href="/reglages/marque\.css\?v=[0-9a-f]+">#', $doc);
        $css = $this->a->get('/reglages/marque.css');
        self::assertStringStartsWith('text/css', (string) $css->headers->get('Content-Type'));
        self::assertStringContainsString('background-color: #2E7D32', (string) $css->getContent());

        // Couleur libre trop claire : enregistrée telle quelle, assombrie dans le document.
        $this->page('/reglages');
        $this->a->post('/reglages', self::REGLAGES + ['modele' => 'moderne', 'couleur_choix' => 'autre', 'couleur' => '#f5e663']);
        self::assertSame('#F5E663', $this->marqueDeA()['couleur']);
        self::assertStringContainsString('class="doc m-moderne"', $this->page("$offre/document"));
        self::assertStringNotContainsString('#F5E663', (string) $this->a->get('/reglages/marque.css')->getContent());

        // Couleur illisible : refusée, rien ne change.
        $this->page('/reglages');
        $refus = $this->a->post('/reglages', self::REGLAGES + ['modele' => 'sobre', 'couleur_choix' => 'autre', 'couleur' => 'rouge']);
        self::assertSame(422, $refus->getStatusCode());
        self::assertSame('moderne', $this->marqueDeA()['modele']);

        // Avec un logo, le PDF l'embarque.
        $this->envoyerLogo('logo-rouge.jpg');
        $pdf = $this->a->get("$offre/pdf");
        self::assertSame('application/pdf', $pdf->headers->get('Content-Type'));
        self::assertStringContainsString('/Subtype /Image', (string) $pdf->getContent());
    }

    /** « Voir en grand » (Xavier, 2026-11-12) : le vrai document, dans le modèle demandé, sans rien enregistrer. */
    public function testUnModeleSeVoitEnGrandAvantDeLeChoisir(): void
    {
        $marque = $this->page('/reglages/marque');
        self::assertStringContainsString('href="/reglages/modeles/bandeau"', $marque);
        self::assertStringContainsString('href="/reglages/modeles/bandeau?depuis=bienvenue"', $this->page('/bienvenue/marque'));

        $apercu = $this->page('/reglages/modeles/bandeau');
        self::assertStringContainsString('class="doc m-bandeau"', $apercu);
        self::assertStringContainsString('Peinture murale a', $apercu,'la dernière offre de l\'artisan sert d\'exemple');
        self::assertStringContainsString('href="/reglages/modeles/technique"', $apercu, 'on passe d\'un modèle à l\'autre');
        self::assertStringContainsString('href="/reglages/marque"', $apercu);
        self::assertSame('sobre', $this->marqueDeA()['modele'], 'regarder ne change rien');

        // « Choisir ce modèle » enregistre et revient à Ma marque, ou à l'étape de l'assistant.
        self::assertSame(303, $this->a->post('/reglages', ['modele' => 'bandeau', 'retour' => '/reglages/marque'])->getStatusCode());
        self::assertSame('bandeau', $this->marqueDeA()['modele']);
        $this->page('/reglages/modeles/epure?depuis=bienvenue');
        $r = $this->a->post('/reglages', ['modele' => 'epure', 'retour' => '/bienvenue/marque']);
        self::assertSame('/bienvenue/marque', $r->headers->get('Location'));
        self::assertSame('epure', $this->marqueDeA()['modele']);

        // Sans aucune offre, un exemple tient lieu de document.
        BaseDeTest::pdo()->exec("UPDATE offre SET supprimee_le = '2026-11-01 08:00:00' WHERE artisan_id = {$this->jeu->a}");
        self::assertStringContainsString('class="doc m-atelier"', $this->page('/reglages/modeles/atelier'));
        self::assertSame(404, $this->a->get('/reglages/modeles/inconnu')->getStatusCode());
    }

    public function testSansChampsDeMarqueLesReglagesGardentLaMarque(): void
    {
        BaseDeTest::pdo()->exec("UPDATE artisan SET modele = 'atelier', couleur = '#8A5A2B' WHERE id = {$this->jeu->a}");
        $this->page('/reglages');
        self::assertSame(303, $this->a->post('/reglages', self::REGLAGES)->getStatusCode());
        self::assertSame(['atelier', '#8A5A2B'], [$this->marqueDeA()['modele'], $this->marqueDeA()['couleur']]);
    }
}
