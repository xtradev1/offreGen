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
 * Sous-chantier 17 (maquette validée par Xavier le 2026-11-12) : onglet Clients, fiche qui monte, « Client de
 * l'offre » (variante B). Une fiche ne naît que lorsque l'artisan la crée ou confirme le nom dicté.
 */
final class ClientsEcranTest extends TestCase
{
    private Jeu $jeu;
    private Navigateur $a;
    private DepotClients $clients;

    protected function setUp(): void
    {
        $this->jeu = new Jeu();
        $this->a = new Navigateur(Monde::conteneur());
        $this->a->connecter('a@exemple.ch');
        $this->clients = new DepotClients($this->jeu->base, new Portee($this->jeu->a));
    }

    private function page(string $chemin): string
    {
        $r = $this->a->get($chemin);
        self::assertSame(200, $r->getStatusCode(), $chemin);
        return (string) $r->getContent();
    }

    private function fiche(string $nom, ?string $adresse = null): int
    {
        $a = $adresse === null ? new Adresse() : (Adresse::depuisTexte($adresse) ?? throw new \LogicException("adresse illisible : $adresse"));
        return $this->clients->creer(['nom' => $nom, 'adresse' => $a, 'telephone' => null, 'courriel' => null], $this->jeu->maintenant);
    }

    private function offreA(): string
    {
        return '/offres/' . $this->jeu->offre['a'];
    }

    /** @return array<string, mixed> */
    private function clientDeLOffre(): array
    {
        return BaseDeTest::ligne("SELECT client_id, client_dicte, lieu_dicte FROM offre WHERE id = {$this->jeu->offre['a']}");
    }

    public function testLaListeSeRangeSansCiviliteEtDitLesOffres(): void
    {
        $rossi = $this->fiche('Mme Rossi', "Rue du Stand 3\n2800 Delémont");
        $this->fiche('M. Schaller', "Route de Glovelier 21\n2854 Bassecourt");
        $this->fiche('Famille Chappuis');
        (new DepotOffres($this->jeu->base, new Portee($this->jeu->a)))->rattacherClient($this->jeu->offre['a'], $rossi, $this->jeu->maintenant);

        $page = $this->page('/clients');
        self::assertStringContainsString('aria-current="page">', $page);
        self::assertStringContainsString('href="/clients" aria-current="page"', $page);
        // Chappuis, Client a, Rossi, Schaller : « Mme », « M. » et « Famille » ne comptent pas.
        $positions = array_map(static fn (string $n): int|false => strpos($page, "<b>$n</b>"), ['Famille Chappuis', 'Client a', 'Mme Rossi', 'M. Schaller']);
        self::assertNotContains(false, $positions);
        $tries = $positions;
        sort($tries);
        self::assertSame($tries, $positions);
        self::assertMatchesRegularExpression('#Delémont · 1 offre, la dernière le \d\d\.\d\d\.\d{4}#', $page);
        self::assertStringContainsString('Bassecourt · aucune offre', $page);
        self::assertStringContainsString('data-texte="mme rossi delemont"', $page, 'la recherche ignore accents et majuscules');
        self::assertStringContainsString('4 clients', $page);
    }

    public function testLaFicheSEnregistreAuFilDeLaSaisie(): void
    {
        $id = $this->jeu->client['a'];
        $fiche = $this->page("/clients/$id");
        self::assertStringContainsString('data-auto', $fiche);
        self::assertStringContainsString('Aucune offre pour ce client.', $fiche);

        // Adresse en champs séparés : rue, numéro, NPA, localité, pays ; la localité prend son nom officiel.
        foreach (['adresse_rue', 'adresse_numero', 'adresse_npa', 'adresse_localite', 'adresse_pays'] as $champ) {
            self::assertStringContainsString('name="' . $champ . '"', $fiche);
        }
        self::assertStringNotContainsString('<textarea', $fiche);
        $ok = $this->a->post("/clients/$id", ['nom' => ' Mme  Rossi ', 'adresse_rue' => 'Rue du Stand', 'adresse_numero' => '3', 'adresse_npa' => '2800',
            'adresse_localite' => 'delemont', 'adresse_pays' => 'CH', 'telephone' => '079 555 44 10', 'courriel' => ''], json: true);
        self::assertSame(['ok' => true], json_decode((string) $ok->getContent(), true));
        self::assertSame(['nom' => 'Mme Rossi', 'rue' => 'Rue du Stand', 'numero' => '3', 'npa' => '2800', 'localite' => 'Delémont', 'pays' => 'CH', 'adresse' => "Rue du Stand 3\n2800 Delémont"],
            BaseDeTest::ligne("SELECT nom, rue, numero, npa, localite, pays, adresse FROM client WHERE id = $id"));

        $refus = $this->a->post("/clients/$id", ['nom' => 'Mme Rossi', 'courriel' => 'pas-un-courriel', 'adresse_rue' => 'Rue du Stand', 'adresse_npa' => '2801'], json: true);
        self::assertSame(422, $refus->getStatusCode());
        self::assertSame(['adresse_npa', 'courriel'], array_keys((array) (json_decode((string) $refus->getContent(), true)['erreurs'] ?? [])));
        self::assertSame('079 555 44 10', $this->clients->parId($id)['telephone'] ?? null, 'rien ne change');

        // Adresse effacée : plus de texte non plus.
        $this->a->post("/clients/$id", ['nom' => 'Mme Rossi'], json: true);
        self::assertSame(['npa' => null, 'adresse' => null], BaseDeTest::ligne("SELECT npa, adresse FROM client WHERE id = $id"));
    }

    public function testUneAdresseDAvantLesChampsResteAReporter(): void
    {
        // Une fiche que la migration n'a pas su découper : son texte reste, montré pour être reporté.
        $id = $this->fiche('Mme Rossi');
        BaseDeTest::pdo()->exec("UPDATE client SET adresse = 'c/o Dupont\nRue du Stand 3\n2800 Delémont' WHERE id = $id");
        self::assertStringContainsString("Votre adresse d'avant, à reporter dans les champs&nbsp;: <b>c/o Dupont, Rue du Stand 3, 2800 Delémont</b>", $this->page("/clients/$id"));
        // Enregistrer le téléphone sans toucher à l'adresse ne l'efface pas.
        $this->a->post("/clients/$id", ['nom' => 'Mme Rossi', 'telephone' => '079 555 44 10'], json: true);
        self::assertSame("c/o Dupont\nRue du Stand 3\n2800 Delémont", BaseDeTest::ligne("SELECT adresse FROM client WHERE id = $id")['adresse']);
        // Reportée dans les champs : le texte d'avant laisse la place.
        $this->a->post("/clients/$id", ['nom' => 'Mme Rossi', 'adresse_rue' => 'Rue du Stand', 'adresse_numero' => '3', 'adresse_npa' => '2800'], json: true);
        self::assertSame("Rue du Stand 3\n2800 Delémont", BaseDeTest::ligne("SELECT adresse FROM client WHERE id = $id")['adresse']);
        self::assertStringNotContainsString("adresse d'avant", $this->page("/clients/$id"));
    }

    public function testUneNouvelleFichePeutDevenirLeClientDeLOffre(): void
    {
        $page = $this->page('/clients/nouveau?offre=' . $this->jeu->offre['a']);
        self::assertStringContainsString('name="offre" value="' . $this->jeu->offre['a'] . '"', $page);
        self::assertStringNotContainsString('data-auto', $page, 'une fiche neuve attend « Ajouter »');
        $r = $this->a->post('/clients', ['nom' => 'Famille Chappuis', 'adresse' => '', 'telephone' => '', 'courriel' => '', 'offre' => (string) $this->jeu->offre['a']]);
        self::assertSame($this->offreA(), $r->headers->get('Location'));
        $id = (int) BaseDeTest::ligne("SELECT id FROM client WHERE nom = 'Famille Chappuis'")['id'];
        self::assertSame($id, $this->clientDeLOffre()['client_id']);
        self::assertStringContainsString('<b>Famille Chappuis</b>', $this->page($this->offreA()));

        // Sans offre, retour à la liste ; un nom vide est refusé.
        $this->page('/clients/nouveau');
        self::assertSame(422, $this->a->post('/clients', ['nom' => ' '])->getStatusCode());
        self::assertSame('/clients#k' . ($id + 1), $this->a->post('/clients', ['nom' => 'M. Schaller'])->headers->get('Location'));
    }

    public function testSeuleLaFicheDUnClientSansOffreSeSupprime(): void
    {
        $avecOffre = $this->fiche('Mme Rossi');
        (new DepotOffres($this->jeu->base, new Portee($this->jeu->a)))->rattacherClient($this->jeu->offre['a'], $avecOffre, $this->jeu->maintenant);
        self::assertStringNotContainsString('Supprimer ce client', $this->page("/clients/$avecOffre"));
        $this->a->post("/clients/$avecOffre/supprimer", []);
        self::assertNotNull($this->clients->parId($avecOffre));

        // Une offre supprimée ne retient pas la fiche ; elle garde le nom et l'adresse en texte.
        BaseDeTest::pdo()->exec("UPDATE client SET adresse = 'Delémont' WHERE id = $avecOffre");
        BaseDeTest::pdo()->exec("UPDATE offre SET supprimee_le = '2026-11-08 10:00:00' WHERE id = {$this->jeu->offre['a']}");
        $this->page("/clients/$avecOffre");
        self::assertSame('/clients', $this->a->post("/clients/$avecOffre/supprimer", [])->headers->get('Location'));
        self::assertNull($this->clients->parId($avecOffre));
        self::assertSame(['client_id' => null, 'client_dicte' => 'Mme Rossi', 'lieu_dicte' => 'Delémont'], $this->clientDeLOffre());
    }

    public function testLeNomDicteSeConfirmeOuRejointUneFiche(): void
    {
        BaseDeTest::pdo()->exec("UPDATE offre SET client_dicte = 'Madame Rossi', lieu_dicte = 'Delémont' WHERE id = {$this->jeu->offre['a']}");
        $rossi = $this->fiche('Mme Rossi', "Rue du Stand 3\n2800 Delémont");
        self::assertStringContainsString('<b>Madame Rossi</b>, Delémont <span class="etiquette nouveau">nouveau client, à confirmer</span>', $this->page($this->offreA()));

        $choix = $this->page($this->offreA() . '/client');
        self::assertStringContainsString("Créer la fiche «&nbsp;Madame Rossi&nbsp;»", $choix);
        self::assertMatchesRegularExpression('#Ou une fiche qui ressemble</h3>\s*<form[^>]*>.*?<b>Mme Rossi</b><small>Delémont · 100\x{00A0}% de ressemblance#su', $choix);

        // Rejoindre la fiche existante.
        self::assertSame($this->offreA(), $this->a->post($this->offreA() . '/client', ['client' => (string) $rossi])->headers->get('Location'));
        self::assertSame($rossi, $this->clientDeLOffre()['client_id']);
        $offre = $this->page($this->offreA());
        self::assertStringContainsString('<b>Mme Rossi</b>, Rue du Stand 3, 2800 Delémont', $offre);
        self::assertStringNotContainsString('à confirmer', $offre);

        // Ou confirmer le nom dicté : la fiche naît à ce moment-là, avec le lieu dicté pour adresse, découpé
        // (« Delémont » prend son NPA, 2800, seul au répertoire).
        BaseDeTest::pdo()->exec("UPDATE offre SET client_id = NULL WHERE id = {$this->jeu->offre['a']}");
        $avant = BaseDeTest::compter('client', "artisan_id = {$this->jeu->a}");
        $this->page($this->offreA() . '/client');
        $this->a->post($this->offreA() . '/client', ['creer' => '1']);
        self::assertSame($avant + 1, BaseDeTest::compter('client', "artisan_id = {$this->jeu->a}"));
        $nouvelle = BaseDeTest::ligne("SELECT c.nom, c.npa, c.localite, c.adresse FROM client c JOIN offre o ON o.client_id = c.id WHERE o.id = {$this->jeu->offre['a']}");
        self::assertSame(['nom' => 'Madame Rossi', 'npa' => '2800', 'localite' => 'Delémont', 'adresse' => '2800 Delémont'], $nouvelle);

        // Un lieu dicté qui ne se découpe pas reste en texte, à reporter.
        BaseDeTest::pdo()->exec("UPDATE offre SET client_id = NULL, client_dicte = 'Monsieur Tardy', lieu_dicte = 'près de la gare' WHERE id = {$this->jeu->offre['a']}");
        $this->page($this->offreA() . '/client');
        $this->a->post($this->offreA() . '/client', ['creer' => '1']);
        self::assertSame(['npa' => null, 'adresse' => 'près de la gare'],
            BaseDeTest::ligne("SELECT c.npa, c.adresse FROM client c JOIN offre o ON o.client_id = c.id WHERE o.id = {$this->jeu->offre['a']}"));
    }

    public function testLaFicheDUnAutreArtisanNeSeRattachePas(): void
    {
        $this->page($this->offreA() . '/client');
        self::assertSame(404, $this->a->post($this->offreA() . '/client', ['client' => (string) $this->jeu->client['b']])->getStatusCode());
        self::assertNull($this->clientDeLOffre()['client_id']);
    }
}
