<?php

declare(strict_types=1);

namespace OffreGen\Tests\Http;

use OffreGen\Http\Morceaux;
use OffreGen\Tests\Outils\BaseDeTest;
use OffreGen\Tests\Outils\Jeu;
use OffreGen\Tests\Outils\Monde;
use OffreGen\Tests\Outils\Navigateur;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

/**
 * Lot 2 du chantier sans rechargement (2026-11-23) : un formulaire envoyé par fetch avec X-Morceaux reçoit les seuls
 * éléments demandés de la page où menait l'action, avec ses messages ; les refus se lisent (401, 403, 404) ; rien ne
 * fuit d'un artisan à l'autre. Sans l'en-tête, rien ne change.
 * Jeu : une ligne « Peinture murale a », 12,5 m² à 18.50.
 */
final class MorceauxTest extends TestCase
{
    private Jeu $jeu;
    private Navigateur $a;
    private string $offre;
    private int $ligne;

    protected function setUp(): void
    {
        $this->jeu = new Jeu();
        $this->a = new Navigateur(Monde::conteneur());
        $this->a->connecter('a@exemple.ch');
        $this->offre = '/offres/' . $this->jeu->offre['a'];
        $this->ligne = (int) BaseDeTest::ligne("SELECT id FROM ligne WHERE offre_id = {$this->jeu->offre['a']}")['id'];
        $this->a->get($this->offre);
    }

    /**
     * @param array<string, string> $champs
     * @param array<string, string> $autres
     */
    private function morceaux(string $chemin, array $champs, string $ids, array $autres = []): \Symfony\Component\HttpFoundation\Response
    {
        return $this->a->post($chemin, $champs, entetes: [Morceaux::DEMANDE => $ids] + $autres);
    }

    public function testUneQuantiteCorrigeeRenvoieLaLigneEtLesTotauxSeulement(): void
    {
        $r = $this->morceaux("{$this->offre}/lignes/{$this->ligne}", ['quantite' => '20'], "l{$this->ligne} totaux total-fixe etat-offre");
        self::assertSame(200, $r->getStatusCode());
        self::assertSame("{$this->offre}#l{$this->ligne}", $r->headers->get(Morceaux::ADRESSE));
        self::assertStringContainsString('no-store', (string) $r->headers->get('Cache-Control'));
        $corps = (string) $r->getContent();
        self::assertStringNotContainsString('<html', $corps);
        self::assertStringNotContainsString('<head', $corps);
        self::assertMatchesRegularExpression('/^<div class="row[^"]*" id="l' . $this->ligne . '"/', $corps);
        self::assertStringContainsString('name="quantite" value="20"', $corps);
        // 20 m² à 18.50 = 370.00 ; avec la TVA à 8,1 % : 399.97, arrondi aux 5 centimes : 399.95.
        self::assertStringContainsString('id="totaux"', $corps);
        self::assertStringContainsString('370.00', $corps);
        self::assertMatchesRegularExpression('/id="total-fixe".*399\.95/s', $corps);
        self::assertStringContainsString('id="etat-offre"', $corps);
        self::assertLessThan(6000, strlen($corps), 'quelques morceaux, pas la page');
        self::assertSame(20000, BaseDeTest::ligne("SELECT quantite_milliemes FROM ligne WHERE id = {$this->ligne}")['quantite_milliemes']);
    }

    public function testUneLigneRetireeEstAbsenteEtSonAnnulerArriveDansLesMessages(): void
    {
        $r = $this->morceaux("{$this->offre}/lignes/{$this->ligne}/supprimer", [], "l{$this->ligne} totaux");
        self::assertSame(200, $r->getStatusCode());
        $corps = (string) $r->getContent();
        self::assertStringContainsString('<template data-absent="l' . $this->ligne . '"></template>', $corps);
        self::assertMatchesRegularExpression('#<form class="snack"[^>]*action="' . preg_quote($this->offre, '#') . '/lignes/' . $this->ligne . '/restaurer"#', $corps);
        self::assertStringContainsString('Annuler', $corps);
        self::assertNotNull(BaseDeTest::ligne("SELECT supprimee_le FROM ligne WHERE id = {$this->ligne}")['supprimee_le']);

        // « Annuler » par morceaux : la ligne revient, les messages disent « Ligne remise ».
        $retour = (string) $this->morceaux("{$this->offre}/lignes/{$this->ligne}/restaurer", [], "l{$this->ligne}")->getContent();
        self::assertMatchesRegularExpression('/id="l' . $this->ligne . '"/', $retour);
        self::assertStringContainsString('Ligne remise', $retour);
        self::assertNull(BaseDeTest::ligne("SELECT supprimee_le FROM ligne WHERE id = {$this->ligne}")['supprimee_le']);
    }

    public function testUneSaisieRefuseeRevientAvecSonMessageEtLAncienneValeur(): void
    {
        $corps = (string) $this->morceaux("{$this->offre}/lignes/{$this->ligne}", ['quantite' => 'beaucoup'], "l{$this->ligne}")->getContent();
        self::assertMatchesRegularExpression('/<div class="snack bad" role="alert"><span>Quantité plus grande que 0/', $corps);
        self::assertStringContainsString('name="quantite" value="12.5"', $corps);
        self::assertSame(12500, BaseDeTest::ligne("SELECT quantite_milliemes FROM ligne WHERE id = {$this->ligne}")['quantite_milliemes']);
    }

    public function testSessionPerdueEtJetonRefusSeLisentSansRenvoiALaConnexion(): void
    {
        $visiteur = new Navigateur(Monde::conteneur());
        $r = $visiteur->post("{$this->offre}/lignes/{$this->ligne}", ['quantite' => '3', '_csrf' => 'x'], entetes: [Morceaux::DEMANDE => "l{$this->ligne}"]);
        self::assertSame(401, $r->getStatusCode());
        self::assertSame('perdue', $r->headers->get(Morceaux::SESSION));
        self::assertNull($r->headers->get('Location'));

        $r = $this->morceaux("{$this->offre}/lignes/{$this->ligne}", ['quantite' => '3', '_csrf' => 'faux'], "l{$this->ligne}");
        self::assertSame(403, $r->getStatusCode());
        self::assertSame('refuse', $r->headers->get(Morceaux::JETON));
        self::assertSame(12500, BaseDeTest::ligne("SELECT quantite_milliemes FROM ligne WHERE id = {$this->ligne}")['quantite_milliemes']);
    }

    public function testSansLEnteteRienNeChange(): void
    {
        $r = $this->a->post("{$this->offre}/lignes/{$this->ligne}", ['quantite' => '20']);
        self::assertSame(303, $r->getStatusCode());
        self::assertSame("{$this->offre}#l{$this->ligne}", $r->headers->get('Location'));
    }

    public function testRienNeFuitDUnArtisanALAutre(): void
    {
        $b = new Navigateur(Monde::conteneur());
        $b->connecter('b@exemple.ch');
        $b->get('/offres/' . $this->jeu->offre['b']);
        // B touche la ligne de A : refusé, sans morceau de la page de A.
        $r = $b->post("{$this->offre}/lignes/{$this->ligne}", ['quantite' => '1'], entetes: [Morceaux::DEMANDE => "l{$this->ligne} totaux"]);
        self::assertSame(404, $r->getStatusCode());
        self::assertStringNotContainsString('Peinture murale a', (string) $r->getContent());
        self::assertSame(12500, BaseDeTest::ligne("SELECT quantite_milliemes FROM ligne WHERE id = {$this->ligne}")['quantite_milliemes']);

        // A demande les morceaux de l'offre de B par X-Morceaux-Page : la page de B ne se rend pas, aucun morceau.
        $r = $this->morceaux("{$this->offre}/lignes/{$this->ligne}", ['quantite' => '13'], 'lignes totaux', [Morceaux::PAGE => '/offres/' . $this->jeu->offre['b']]);
        self::assertSame(200, $r->getStatusCode());
        self::assertSame('1', $r->headers->get(Morceaux::ALLER));
        self::assertSame('', (string) $r->getContent());
        self::assertStringNotContainsString('Peinture murale b', (string) $r->getContent());
    }

    public function testUnePageDemandeeHorsDuSiteEstIgnoree(): void
    {
        foreach (['https://exemple.org/offres/1', '//exemple.org/x', '/\\exemple.org', 'javascript:alert(1)'] as $ailleurs) {
            $r = $this->morceaux("{$this->offre}/lignes/{$this->ligne}", ['quantite' => '14'], "l{$this->ligne}", [Morceaux::PAGE => $ailleurs]);
            self::assertSame(200, $r->getStatusCode(), $ailleurs);
            self::assertSame("{$this->offre}#l{$this->ligne}", $r->headers->get(Morceaux::ADRESSE), $ailleurs);
            self::assertStringContainsString('id="l' . $this->ligne . '"', (string) $r->getContent(), $ailleurs);
        }
    }

    public function testUneActionQuiMeneAUnAutreEcranYEnvoieLeNavigateur(): void
    {
        // Archiver depuis l'offre ramène à la liste : rien à découper sur l'écran de l'offre.
        $r = $this->morceaux("{$this->offre}/archiver", [], 'totaux', [Morceaux::ICI => $this->offre]);
        self::assertSame(200, $r->getStatusCode());
        self::assertSame('1', $r->headers->get(Morceaux::ALLER));
        self::assertSame('/', $r->headers->get(Morceaux::ADRESSE));
        // Même écran, autre ancre ou autre recherche : on découpe.
        $this->a->get($this->offre);
        $r = $this->morceaux("{$this->offre}/restaurer", [], 'totaux', [Morceaux::ICI => '/?tri=client']);
        self::assertNull($r->headers->get(Morceaux::ALLER));
    }

    public function testUnGetRelitDesMorceauxSansRienEcrire(): void
    {
        $r = $this->a->c->noyau()->traiter((function (): Request {
            $r = Request::create($this->offre, 'GET', server: ['HTTP_X_MORCEAUX' => 'totaux total-fixe']);
            $r->setSession($this->a->session);
            return $r;
        })());
        self::assertSame(200, $r->getStatusCode());
        $corps = (string) $r->getContent();
        self::assertStringStartsWith('<div class="totals" id="totaux">', $corps);
        self::assertStringContainsString('id="total-fixe"', $corps);
        self::assertStringNotContainsString('id="lignes"', $corps);
    }

    public function testLesIdsDemandesSontFiltres(): void
    {
        $r = Request::create('/x', 'POST', server: ['HTTP_X_MORCEAUX' => 'l12  totaux <script> l12 A Ok x' . str_repeat('y', 45)]);
        self::assertSame(['l12', 'totaux'], Morceaux::demandes($r));
        self::assertNull(Morceaux::demandes(Request::create('/x', 'POST')));
        self::assertSame([], Morceaux::demandes(Request::create('/x', 'POST', server: ['HTTP_X_MORCEAUX' => ''])));
    }
}
