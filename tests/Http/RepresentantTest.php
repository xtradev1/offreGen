<?php

declare(strict_types=1);

namespace OffreGen\Tests\Http;

use OffreGen\Adresse\Adresse;
use OffreGen\Donnees\DepotClients;
use OffreGen\Donnees\DepotOffres;
use OffreGen\Donnees\Portee;
use OffreGen\Tests\Outils\BaseDeTest;
use OffreGen\Tests\Outils\Jeu;
use OffreGen\Tests\Outils\Monde;
use OffreGen\Tests\Outils\Navigateur;
use PHPUnit\Framework\TestCase;

/**
 * « Représenté par » (D8 A, D9 C, D10 C, 2026-11-21). L'offre de A va à « Client a », Rue a 1, 2800 Delémont ; la régie
 * est une autre fiche de A.
 */
final class RepresentantTest extends TestCase
{
    private Jeu $jeu;
    private Navigateur $a;
    private string $offre;
    private int $regie;

    protected function setUp(): void
    {
        $this->jeu = new Jeu();
        $this->a = new Navigateur(Monde::conteneur());
        $this->a->connecter('a@exemple.ch');
        $this->offre = '/offres/' . $this->jeu->offre['a'];
        $portee = new Portee($this->jeu->a);
        (new DepotOffres($this->jeu->base, $portee))->rattacherClient($this->jeu->offre['a'], $this->jeu->client['a'], $this->jeu->maintenant);
        $this->regie = (new DepotClients($this->jeu->base, $portee))->creer(
            ['nom' => 'Régie Dupont SA', 'adresse' => new Adresse('Rue du Marché', '5', '2900', 'Porrentruy'), 'telephone' => null, 'courriel' => null], $this->jeu->maintenant);
    }

    private function page(string $chemin): string
    {
        $r = $this->a->get($chemin);
        self::assertSame(200, $r->getStatusCode(), $chemin);
        return (string) $r->getContent();
    }

    /** @param array<string, string> $champs */
    private function choisir(array $champs, int $attendu = 303): void
    {
        $this->page($this->offre . '/representant');
        self::assertSame($attendu, $this->a->post($this->offre . '/representant', $champs)->getStatusCode());
    }

    private function texteDuDocument(string $chemin): string
    {
        return (string) preg_replace('/\s+/u', ' ', html_entity_decode((string) preg_replace('/<[^>]+>/', ' ', $this->page($chemin)), ENT_QUOTES));
    }

    public function testLaFenetreProposeLesAutresFiches(): void
    {
        // Sans représentant, l'offre porte un lien court, plus une carte vide (analyse des parcours, Xavier, 2026-11-26).
        self::assertStringContainsString('data-en-feuille>+ Représentant</a>', $this->page($this->offre));
        $page = $this->page($this->offre . '/representant');
        self::assertStringContainsString('Régie Dupont SA', $page);
        self::assertStringNotContainsString('<b>Client a</b>', $page, 'le client ne se représente pas lui-même');
    }

    public function testLOffreVaAuRepresentantPourLeCompteDuClient(): void
    {
        $this->choisir(['representant' => (string) $this->regie]);
        self::assertSame(1, BaseDeTest::compter('offre', "id = {$this->jeu->offre['a']} AND representant_id = {$this->regie} AND offre_adressee = 'representant' AND factures_adressees = 'pa'"));
        self::assertStringContainsString('<b>Régie Dupont SA</b>', $this->page($this->offre));
        $texte = $this->texteDuDocument($this->offre . '/document');
        self::assertStringContainsString('Régie Dupont SA Rue du Marché 5 2900 Porrentruy', $texte);
        self::assertStringContainsString('Pour le compte de Client a, Rue a 1, 2800 Delémont', $texte);
    }

    public function testLOffrePeutAllerAuClient(): void
    {
        $this->choisir(['representant' => (string) $this->regie]);
        $this->choisir(['offre_adressee' => 'client', 'factures_adressees' => 'client']);
        $texte = $this->texteDuDocument($this->offre . '/document');
        self::assertStringContainsString('Client a Rue a 1 2800 Delémont', $texte);
        self::assertStringContainsString("Par l'intermédiaire de Régie Dupont SA", $texte);
    }

    public function testLaFactureVaAuClientPaLeRepresentantEtIlPaie(): void
    {
        $this->choisir(['representant' => (string) $this->regie]);
        $this->page($this->offre . '/facturer');
        $this->a->post($this->offre . '/facturer', ['genre' => 'finale', 'numero' => '2026-060', 'date' => '2026-11-07', 'echeance' => '2026-12-07', 'suite' => 'corriger']);
        $f = BaseDeTest::ligne("SELECT id, client_nom, client_adresse, client_rue, client_localite, representant_nom FROM facture WHERE offre_id = {$this->jeu->offre['a']}");
        self::assertSame(['Client a', "p.a. Régie Dupont SA\nRue du Marché 5\n2900 Porrentruy", 'Rue a', 'Delémont', 'Régie Dupont SA'],
            [$f['client_nom'], $f['client_adresse'], $f['client_rue'], $f['client_localite'], $f['representant_nom']], 'les champs de paiement restent ceux du client');
        $texte = $this->texteDuDocument('/factures/' . $f['id'] . '/document');
        self::assertStringContainsString('Client a p.a. Régie Dupont SA Rue du Marché 5 2900 Porrentruy', $texte);
        self::assertStringContainsString('Représenté par Régie Dupont SA', $texte);
        self::assertStringContainsString('Delémont', $this->page('/factures'), 'la liste montre la localité du client');
    }

    public function testLaFactureChezLeClient(): void
    {
        $this->choisir(['representant' => (string) $this->regie]);
        $this->choisir(['factures_adressees' => 'client']);
        $this->page($this->offre . '/facturer');
        $this->a->post($this->offre . '/facturer', ['genre' => 'finale', 'numero' => '2026-061', 'date' => '2026-11-07', 'echeance' => '2026-12-07', 'suite' => 'corriger']);
        self::assertSame("Rue a 1\n2800 Delémont", BaseDeTest::ligne("SELECT client_adresse FROM facture WHERE offre_id = {$this->jeu->offre['a']}")['client_adresse']);
    }

    /** Revue du 2026-11-22 : la fiche choisie comme client cesse d'être le représentant de l'offre. */
    public function testLeRepresentantDevenuClientNeSeRepresentePasLuiMeme(): void
    {
        $this->choisir(['representant' => (string) $this->regie]);
        $this->page($this->offre . '/client');
        self::assertSame(303, $this->a->post($this->offre . '/client', ['client' => (string) $this->regie])->getStatusCode());
        self::assertSame(1, BaseDeTest::compter('offre', "id = {$this->jeu->offre['a']} AND client_id = {$this->regie} AND representant_id IS NULL"));
    }

    public function testSansNomDeClientPasDePourLeCompteDeVide(): void
    {
        $this->choisir(['representant' => (string) $this->regie]);
        BaseDeTest::pdo()->exec("UPDATE offre SET client_id = NULL, client_dicte = NULL WHERE id = {$this->jeu->offre['a']}");
        $texte = $this->texteDuDocument($this->offre . '/document');
        self::assertStringNotContainsString('Pour le compte de', $texte);
        self::assertStringContainsString('Régie Dupont SA Rue du Marché 5', $texte);
    }

    public function testRetirerLeRepresentant(): void
    {
        $this->choisir(['representant' => (string) $this->regie]);
        $this->choisir(['retirer' => '1']);
        self::assertSame(1, BaseDeTest::compter('offre', "id = {$this->jeu->offre['a']} AND representant_id IS NULL"));
        self::assertStringNotContainsString('Pour le compte de', $this->texteDuDocument($this->offre . '/document'));
    }

    public function testLeClientOuUneFicheDeBNeSontPasRepresentants(): void
    {
        $this->choisir(['representant' => (string) $this->jeu->client['a']], 404);
        $this->choisir(['representant' => (string) $this->jeu->client['b']], 404);
        $this->choisir(['representant' => (string) $this->regie, 'offre_adressee' => 'ailleurs'], 404);
        self::assertSame(1, BaseDeTest::compter('offre', "id = {$this->jeu->offre['a']} AND representant_id IS NULL"));
    }

    public function testSupprimerLaFicheDuRepresentantLeRetireDeLOffre(): void
    {
        $this->choisir(['representant' => (string) $this->regie]);
        $this->page('/clients/' . $this->regie);
        self::assertSame(303, $this->a->post('/clients/' . $this->regie . '/supprimer', [])->getStatusCode());
        self::assertSame(0, BaseDeTest::compter('client', "id = {$this->regie}"));
        self::assertSame(1, BaseDeTest::compter('offre', "id = {$this->jeu->offre['a']} AND representant_id IS NULL"));
    }
}
