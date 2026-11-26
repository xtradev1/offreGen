<?php

declare(strict_types=1);

namespace OffreGen\Tests\Http;

use OffreGen\Tests\Outils\BaseDeTest;
use OffreGen\Tests\Outils\Jeu;
use OffreGen\Tests\Outils\Monde;
use OffreGen\Tests\Outils\Navigateur;
use PHPUnit\Framework\TestCase;

/**
 * Le réglage « Votre activité » (docs/plans/2026-11-22_familles-activite, SPEC point 2) : dans « Mon entreprise » et à
 * la première étape de l'assistant. Il change les mots et le rangement des prochaines offres, rien d'autre.
 */
final class ActiviteReglageTest extends TestCase
{
    private Jeu $jeu;
    private Navigateur $a;

    protected function setUp(): void
    {
        $this->jeu = new Jeu();
        $this->a = new Navigateur(Monde::conteneur());
        $this->a->connecter('a@exemple.ch');
    }

    private function activite(int $compte): string
    {
        return (string) BaseDeTest::ligne("SELECT activite FROM artisan WHERE id = $compte")['activite'];
    }

    /** @return array<string, mixed> ce que le changement d'activité ne doit pas toucher */
    private function reste(): array
    {
        return [
            'compte' => BaseDeTest::ligne("SELECT entreprise, metier, modele, tva_pour_dix_mille FROM artisan WHERE id = {$this->jeu->a}"),
            // Revue du 2026-11-22 : les phrases de Lettre laissées telles que l'écran les montrait ne sont pas recopiées
            // (elles restent NULL), sinon « travaux » resterait sur les documents d'un prestataire.
            'lettre' => BaseDeTest::ligne("SELECT lettre_offre_debut, lettre_offre_fin, lettre_facture_debut, lettre_facture_fin FROM artisan WHERE id = {$this->jeu->a}"),
            'produits' => BaseDeTest::ligne("SELECT COUNT(*) AS n, SUM(prix_centimes) AS prix FROM produit WHERE artisan_id = {$this->jeu->a}"),
            'offres' => BaseDeTest::ligne("SELECT COUNT(*) AS n, MAX(titre) AS titre FROM offre WHERE artisan_id = {$this->jeu->a}"),
            'lignes' => BaseDeTest::ligne("SELECT COUNT(*) AS n, MAX(designation) AS designation FROM ligne WHERE artisan_id = {$this->jeu->a}"),
        ];
    }

    public function testLeReglageMontreLesTroisActivitesEtCelleDuCompte(): void
    {
        $page = (string) $this->a->get('/reglages/entreprise')->getContent();
        self::assertStringContainsString('<legend>Votre activité</legend>', $page);
        self::assertStringContainsString('name="activite" value="batiment" checked', $page);
        self::assertStringContainsString('name="activite" value="exterieur">', $page);
        self::assertStringContainsString('name="activite" value="prestations">', $page);
        self::assertStringContainsString('Informatique, conseil, marketing, autres services.', $page);
        // La première étape de l'assistant du premier lancement le montre aussi.
        self::assertStringContainsString('<legend>Votre activité</legend>', (string) $this->a->get('/bienvenue/entreprise')->getContent());
    }

    public function testChoisirLesPrestationsChangeLesMotsEtRienDAutre(): void
    {
        $avant = $this->reste();
        $this->a->get('/reglages/entreprise');
        self::assertSame(303, $this->a->post('/reglages', ['activite' => 'prestations', 'retour' => '/reglages/entreprise'])->getStatusCode());
        self::assertSame('prestations', $this->activite($this->jeu->a));
        self::assertSame('batiment', $this->activite($this->jeu->b), 'le compte B ne bouge pas');
        self::assertSame($avant, $this->reste(), 'ni le catalogue, ni les offres, ni le modèle, ni le reste du compte');

        self::assertStringContainsString('name="activite" value="prestations" checked', (string) $this->a->get('/reglages/entreprise')->getContent());
        self::assertStringContainsString('Puis prestation par prestation', (string) $this->a->get('/')->getContent());
    }

    /** Revue du 2026-11-22 : après le changement, la lettre parle de prestations, et le retour au bâtiment de travaux. */
    public function testLesPhrasesDeLettreSuiventLeChangementDActivite(): void
    {
        $this->a->get('/reglages/entreprise');
        $this->a->post('/reglages', ['activite' => 'prestations', 'retour' => '/reglages/entreprise']);
        self::assertStringContainsString('>Nous vous remettons notre offre pour les prestations suivantes.</textarea>', (string) $this->a->get('/reglages/phrases')->getContent());
        // Une phrase écrite par le titulaire reste la sienne d'une activité à l'autre.
        $this->a->post('/reglages', ['lettre_offre_fin' => 'Merci pour ces travaux.', 'retour' => '/reglages/phrases']);
        $this->a->get('/reglages/entreprise');
        $this->a->post('/reglages', ['activite' => 'batiment', 'retour' => '/reglages/entreprise']);
        $page = (string) $this->a->get('/reglages/phrases')->getContent();
        self::assertStringContainsString('>Nous vous remettons notre offre pour les travaux suivants.</textarea>', $page);
        self::assertStringContainsString('>Merci pour ces travaux.</textarea>', $page);
        self::assertSame(['lettre_offre_debut' => null, 'lettre_offre_fin' => 'Merci pour ces travaux.'],
            BaseDeTest::ligne("SELECT lettre_offre_debut, lettre_offre_fin FROM artisan WHERE id = {$this->jeu->a}"));
    }

    public function testLEnregistrementAutomatiqueLePrendAussi(): void
    {
        $this->a->get('/reglages/entreprise');
        $reponse = $this->a->post('/reglages', ['activite' => 'exterieur', 'retour' => '/reglages/entreprise'], json: true);
        self::assertSame(200, $reponse->getStatusCode());
        self::assertSame('{"ok":true}', $reponse->getContent());
        self::assertSame('exterieur', $this->activite($this->jeu->a));
    }

    public function testUneActiviteInconnueEstRefuseeEtRienNEstEcrit(): void
    {
        $this->a->get('/reglages/entreprise');
        $reponse = $this->a->post('/reglages', ['activite' => 'evenement', 'entreprise' => 'Autre nom', 'retour' => '/reglages/entreprise']);
        self::assertSame(422, $reponse->getStatusCode());
        self::assertStringContainsString('Choisissez votre activité.', (string) $reponse->getContent());
        self::assertSame('batiment', $this->activite($this->jeu->a));
        self::assertSame('Entreprise A', BaseDeTest::ligne("SELECT entreprise FROM artisan WHERE id = {$this->jeu->a}")['entreprise'], 'un envoi refusé n\'écrit rien');
    }

    public function testUnAutreReglageNeToucheParLActivite(): void
    {
        BaseDeTest::pdo()->exec("UPDATE artisan SET activite = 'prestations' WHERE id = {$this->jeu->a}");
        $this->a->get('/reglages/tva');
        self::assertSame(303, $this->a->post('/reglages', ['tva' => '0', 'retour' => '/reglages/tva'])->getStatusCode());
        self::assertSame('prestations', $this->activite($this->jeu->a));
    }

    public function testSansSessionLeReglageEstRefuse(): void
    {
        $visiteur = new Navigateur(Monde::conteneur());
        $visiteur->get('/connexion');
        $reponse = $visiteur->post('/reglages', ['activite' => 'prestations']);
        self::assertNotSame(200, $reponse->getStatusCode());
        self::assertSame('batiment', $this->activite($this->jeu->a));
        self::assertSame('batiment', $this->activite($this->jeu->b));
    }
}
