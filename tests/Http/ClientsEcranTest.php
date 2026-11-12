<?php

declare(strict_types=1);

namespace OffreGen\Tests\Http;

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
        return $this->clients->creer(['nom' => $nom, 'adresse' => $adresse, 'telephone' => null, 'courriel' => null], $this->jeu->maintenant);
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

        $ok = $this->a->post("/clients/$id", ['nom' => ' Mme  Rossi ', 'adresse' => "Rue du Stand 3\n2800 Delémont", 'telephone' => '079 555 44 10', 'courriel' => ''], json: true);
        self::assertSame(['ok' => true], json_decode((string) $ok->getContent(), true));
        self::assertSame('Mme Rossi', $this->clients->parId($id)['nom'] ?? null);

        $refus = $this->a->post("/clients/$id", ['nom' => 'Mme Rossi', 'courriel' => 'pas-un-courriel'], json: true);
        self::assertSame(422, $refus->getStatusCode());
        self::assertSame(['courriel'], array_keys((array) (json_decode((string) $refus->getContent(), true)['erreurs'] ?? [])));
        self::assertSame('079 555 44 10', $this->clients->parId($id)['telephone'] ?? null, 'rien ne change');
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

        // Ou confirmer le nom dicté : la fiche naît à ce moment-là, avec le lieu dicté pour adresse.
        BaseDeTest::pdo()->exec("UPDATE offre SET client_id = NULL WHERE id = {$this->jeu->offre['a']}");
        $avant = BaseDeTest::compter('client', "artisan_id = {$this->jeu->a}");
        $this->page($this->offreA() . '/client');
        $this->a->post($this->offreA() . '/client', ['creer' => '1']);
        self::assertSame($avant + 1, BaseDeTest::compter('client', "artisan_id = {$this->jeu->a}"));
        $nouvelle = BaseDeTest::ligne("SELECT c.nom, c.adresse FROM client c JOIN offre o ON o.client_id = c.id WHERE o.id = {$this->jeu->offre['a']}");
        self::assertSame(['nom' => 'Madame Rossi', 'adresse' => 'Delémont'], $nouvelle);
    }

    public function testLaFicheDUnAutreArtisanNeSeRattachePas(): void
    {
        $this->page($this->offreA() . '/client');
        self::assertSame(404, $this->a->post($this->offreA() . '/client', ['client' => (string) $this->jeu->client['b']])->getStatusCode());
        self::assertNull($this->clientDeLOffre()['client_id']);
    }
}
