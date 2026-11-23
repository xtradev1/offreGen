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

    public function testLesVignettesSontLeVraiDocumentEtSuiventLaCouleurTouchee(): void
    {
        // 2a (2026-11-19) : chaque modèle est le document lui-même, plus une image figée ; la couleur suit au toucher.
        $page = $this->page('/reglages/marque');
        self::assertSame(count(\OffreGen\Compte\Marque::MODELES), substr_count($page, 'data-vignette="'));
        self::assertStringContainsString('class="doc m-bandeau"', $page);
        self::assertStringNotContainsString('src="/img/modeles/sobre.jpg"', $page);
        self::assertMatchesRegularExpression('#<link rel="stylesheet" href="/reglages/marque\.css\?v=[0-9a-f]+" data-marque-css>#', $page);
        $avant = $this->marqueDeA();
        $css = (string) $this->a->get('/reglages/marque.css?apercu=%23B23A2E')->getContent();
        self::assertStringContainsString('background-color: #B23A2E', $css);
        self::assertSame($avant, $this->marqueDeA(), "L'aperçu n'enregistre rien.");
        self::assertStringNotContainsString('#B23A2E', (string) $this->a->get('/reglages/marque.css?apercu=rouge')->getContent(), 'Une couleur invalide est ignorée.');
    }

    /**
     * Xavier, 2026-11-20 : « j'ai tenté de mettre du blanc […] j'ai pas de message ». 2b : une couleur tapée trop claire
     * est signalée, avec deux versions plus foncées de sa teinte ; un toucher en prend une (sans JavaScript aussi).
     */
    public function testUneCouleurTapeeTropClaireEstSignaleeAvecDeuxPropositions(): void
    {
        $cache = '#<div class="avis-couleur" data-avis-couleur[^>]* hidden>#';
        self::assertMatchesRegularExpression($cache, $this->page('/reglages/marque'), 'la couleur par défaut se lit bien');

        self::assertSame(303, $this->a->post('/reglages', ['couleur_choix' => 'autre', 'couleur' => '#FFFFFF', 'retour' => '/reglages/marque'])->getStatusCode());
        $page = $this->page('/reglages/marque');
        self::assertDoesNotMatchRegularExpression($cache, $page);
        self::assertStringContainsString('Cette couleur se lit mal sur le papier blanc', $page);
        self::assertStringContainsString('contraste <span data-avis-contraste>1</span>:1', $page);
        $p = \OffreGen\Compte\Marque::propositions('#FFFFFF');
        self::assertNotNull($p);
        foreach (['bien', 'visible'] as $niveau) {
            self::assertStringContainsString('name="couleur_prise" value="' . $p[$niveau] . '" data-niveau="' . $niveau . '"', $page);
        }

        // Sans JavaScript, le bouton envoie le formulaire : la couleur prise devient celle des documents.
        self::assertSame(303, $this->a->post('/reglages', ['couleur_prise' => $p['bien'], 'couleur_choix' => 'autre', 'couleur' => '#FFFFFF', 'retour' => '/reglages/marque'])->getStatusCode());
        self::assertSame($p['bien'], $this->marqueDeA()['couleur']);
        self::assertMatchesRegularExpression($cache, $this->page('/reglages/marque'));
        self::assertSame(422, $this->a->post('/reglages', ['couleur_prise' => 'rouge', 'retour' => '/reglages/marque'])->getStatusCode(), 'une couleur prise invalide est refusée');

        // Les six couleurs proposées ne déclenchent rien : toutes à 7:1 depuis le 5e tour du responsive (pétrole #0C616D).
        self::assertSame(303, $this->a->post('/reglages', ['couleur_choix' => '#0C616D', 'retour' => '/reglages/marque'])->getStatusCode());
        self::assertMatchesRegularExpression($cache, $this->page('/reglages/marque'));
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

    /** Modèles du bâtiment (2026-11-20) : Latéral, premier des nouveaux, de l'écran des réglages jusqu'au PDF. */
    public function testLateralSeChoisitEtSeVoitEnGrand(): void
    {
        self::assertStringContainsString('href="/reglages/modeles/lateral"', $this->page('/reglages/marque'));
        $apercu = $this->page('/reglages/modeles/lateral');
        // La feuille a la largeur du papier A4 : l'écran la réduit à sa case (app.js, papiers ; document-ecran.css).
        self::assertStringContainsString('class="doc m-lateral" data-feuille', $apercu);
        self::assertStringContainsString('class="doc-colonne c-fond"', $apercu, 'la colonne de couleur');
        self::assertMatchesRegularExpression('#href="/css/document-ecran\.css\?v=[0-9a-f]{12}"#', $apercu);
        self::assertFileExists(dirname(__DIR__, 2) . '/public/css/document-ecran.css');

        self::assertSame(303, $this->a->post('/reglages', ['modele' => 'lateral', 'retour' => '/reglages/marque'])->getStatusCode());
        self::assertSame('lateral', $this->marqueDeA()['modele']);
        $offre = '/offres/' . $this->jeu->offre['a'];
        self::assertStringContainsString('class="doc m-lateral"', $this->page("$offre/document"));
        $pdf = $this->a->get("$offre/pdf");
        self::assertSame(200, $pdf->getStatusCode());
        self::assertStringStartsWith('%PDF-', (string) $pdf->getContent());
    }

    /** Douze modèles rangés en quatre familles ; celui du métier du compte est marqué (Xavier, 2026-11-19). */
    public function testLesDouzeModelesSontRangesEnQuatreFamillesEtCeluiDuMetierEstPropose(): void
    {
        $page = $this->page('/reglages/marque');
        self::assertSame(12, substr_count($page, 'name="modele" value="'));
        $rangs = [];
        foreach (['Classiques', 'Couleur', 'Détaillés', 'Simples'] as $famille) {
            $rang = strpos($page, "<h3 class=\"famille\">$famille</h3>");
            self::assertNotFalse($rang, $famille);
            $rangs[] = $rang;
        }
        // Bandeau est dans « Couleur », Technique dans « Détaillés », Marge dans « Simples ».
        foreach (['bandeau' => 1, 'technique' => 2, 'marge' => 3] as $cle => $famille) {
            $rang = strpos($page, "name=\"modele\" value=\"$cle\"");
            self::assertGreaterThan($rangs[$famille], $rang, $cle);
            self::assertLessThan($rangs[$famille + 1] ?? PHP_INT_MAX, $rang, $cle);
        }
        self::assertStringNotContainsString('Proposé pour votre métier', $page, 'un compte sans métier');

        BaseDeTest::pdo()->exec("UPDATE artisan SET metier = 'platrier' WHERE id = {$this->jeu->a}");
        $page = $this->page('/reglages/marque');
        self::assertSame(1, substr_count($page, 'Proposé pour votre métier'));
        self::assertMatchesRegularExpression('#name="modele" value="chantier"(?:(?!name="modele").)*Proposé pour votre métier#s', $page, 'sur Chantier, le modèle du plâtrier');
        self::assertSame('sobre', $this->marqueDeA()['modele'], 'proposer ne change pas le modèle choisi');
    }

    /**
     * Xavier, 2026-11-15 : « voir plus grand » agrandit l'image dans la page, sans ouvrir une autre page.
     * Chaque modèle a sa grande image (tirée du vrai PDF), le lien la désigne ; sans JavaScript, il mène à l'aperçu.
     */
    public function testVoirEnGrandDesigneUneGrandeImageParModele(): void
    {
        $marque = $this->page('/reglages/marque');
        foreach (array_keys(\OffreGen\Compte\Marque::MODELES) as $cle) {
            self::assertFileExists(dirname(__DIR__, 2) . "/public/img/modeles/grand/$cle.jpg");
            self::assertMatchesRegularExpression("#<a class=\"voir\" href=\"/reglages/modeles/$cle\"[^>]*data-grand=\"/img/modeles/grand/$cle\.jpg(\?v=[0-9a-f]{12})?\"#", $marque);
        }
        $taille = getimagesize(dirname(__DIR__, 2) . '/public/img/modeles/grand/sobre.jpg');
        self::assertIsArray($taille);
        self::assertGreaterThanOrEqual(900, $taille[0], 'assez grande pour un écran de PC');
    }

    public function testSansChampsDeMarqueLesReglagesGardentLaMarque(): void
    {
        BaseDeTest::pdo()->exec("UPDATE artisan SET modele = 'atelier', couleur = '#8A5A2B' WHERE id = {$this->jeu->a}");
        $this->page('/reglages');
        self::assertSame(303, $this->a->post('/reglages', self::REGLAGES)->getStatusCode());
        self::assertSame(['atelier', '#8A5A2B'], [$this->marqueDeA()['modele'], $this->marqueDeA()['couleur']]);
    }
}
