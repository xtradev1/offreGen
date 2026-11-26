<?php

declare(strict_types=1);

namespace OffreGen\Tests\Http;

use OffreGen\Tests\Outils\BaseDeTest;
use OffreGen\Tests\Outils\Jeu;
use OffreGen\Tests\Outils\Monde;
use OffreGen\Tests\Outils\Navigateur;
use PHPUnit\Framework\TestCase;

/**
 * Les mots des écrans suivent l'activité du compte (docs/plans/2026-11-22_familles-activite, SPEC point 5).
 * Le compte A du jeu est en Bâtiment, l'activité par défaut de la colonne.
 */
final class ActiviteEcransTest extends TestCase
{
    private Jeu $jeu;
    private Navigateur $a;

    protected function setUp(): void
    {
        $this->jeu = new Jeu();
        $this->a = new Navigateur(Monde::conteneur());
        $this->a->connecter('a@exemple.ch');
    }

    private function activite(string $activite): void
    {
        BaseDeTest::pdo()->prepare('UPDATE artisan SET activite = ? WHERE id = ?')->execute([$activite, $this->jeu->a]);
    }

    private function page(string $chemin): string
    {
        $reponse = $this->a->get($chemin);
        self::assertSame(200, $reponse->getStatusCode(), $chemin);
        return (string) $reponse->getContent();
    }

    public function testLEcranDeDicteeParleLaLangueDeLActivite(): void
    {
        // Trois conseils tournent sous l'onde (maquette du parcours de la dictée, Xavier, 2026-11-26) : le premier est
        // dans la page, les trois dans l'attribut, pour app.js.
        $page = $this->page('/');
        self::assertMatchesRegularExpression('#<p data-consigne data-conseils="\[&quot;Commencez par le client et le lieu du chantier\.&quot;,&quot;Puis pièce par pièce\x{00A0}: les travaux, les surfaces, les quantités\.&quot;,&quot;Les fournitures et le déplacement, s&\#039;il y en a\.&quot;\]"[^>]*>Commencez par le client et le lieu du chantier\.</p>#u', $page);

        $this->activite('prestations');
        $page = $this->page('/');
        self::assertMatchesRegularExpression('#<p data-consigne data-conseils="[^"]*Puis prestation par prestation[^"]*"[^>]*>Commencez par le client et l&\#039;objet de l&\#039;offre\.</p>#u', $page);
        self::assertStringNotContainsString('pièce par pièce', $page);

        $this->activite('exterieur');
        self::assertMatchesRegularExpression('#<p data-consigne data-conseils="[^"]*Puis zone par zone[^"]*"[^>]*>Commencez par le client et le lieu d&\#039;intervention\.</p>#u', $this->page('/'));
    }

    public function testUneActiviteInconnueEnBaseVautLeBatiment(): void
    {
        $this->activite('evenement');
        self::assertStringContainsString('Puis pièce par pièce', $this->page('/'));
    }

    public function testLEnTeteDeLOffreDitLeChantierAuBatiment(): void
    {
        $page = $this->page('/offres/' . $this->jeu->offre['a'] . '/modifier');
        foreach (['<legend>Chantier</legend>', '>Adresse du chantier</label>', '>Contact sur place</label>', '<label for="chantier_acces">Accès</label>',
            '>Début des travaux</label>', '>Date de la visite</label>'] as $attendu) {
            self::assertStringContainsString($attendu, $page);
        }
    }

    public function testLEnTeteDUnePrestationNaPasDeTitreEtGardeLAccesReplie(): void
    {
        $this->activite('prestations');
        $page = $this->page('/offres/' . $this->jeu->offre['a'] . '/modifier');
        self::assertStringNotContainsString('<legend>', $page);
        // Xavier, 2026-11-23 : l'accès reste possible (un badge, une réception), replié tant qu'il n'est pas utile.
        self::assertMatchesRegularExpression('~<details class="libre-detail">\s*<summary>Ajouter un accès</summary>\s*<div class="champ">\s*<label for="chantier_acces">Accès</label>~u', $page);
        foreach (['<label for="chantier_adresse">Lieu</label>', '<label for="chantier_contact">Contact</label>', '<label for="debut_travaux">Début</label>',
            '<label for="visite_le">Date de l&#039;échange</label>'] as $attendu) {
            self::assertStringContainsString($attendu, $page);
        }
        self::assertStringContainsString('Par exemple&nbsp;: Licences des logiciels.', $page);
        // « Chantier » reste dans une aide : c'est le nom d'un modèle de document (Marque::MODELES).
        foreach (['Adresse du chantier', 'Contact sur place', 'Début des travaux', 'Date de la visite', 'Peinture de finition'] as $absent) {
            self::assertStringNotContainsString($absent, $page);
        }
    }

    public function testUnAccesDejaSaisiResteOuvertEnPrestations(): void
    {
        BaseDeTest::pdo()->prepare('UPDATE offre SET chantier_acces = ? WHERE id = ?')->execute(['Clé chez la concierge', $this->jeu->offre['a']]);
        $this->activite('prestations');
        $chemin = '/offres/' . $this->jeu->offre['a'] . '/modifier';
        self::assertMatchesRegularExpression('~<details class="libre-detail" open>\s*<summary>Ajouter un accès</summary>~u', $this->page($chemin));
        self::assertSame(303, $this->a->post($chemin, ['titre' => 'Offre a', 'chantier_acces' => 'Clé chez la concierge', 'chantier_adresse' => 'Rue du Test 9'])->getStatusCode());
        self::assertSame(['chantier_acces' => 'Clé chez la concierge', 'chantier_adresse' => 'Rue du Test 9'],
            BaseDeTest::ligne('SELECT chantier_acces, chantier_adresse FROM offre WHERE id = ' . $this->jeu->offre['a']));
    }

    public function testEnExterieurLaPieceDevientUneZone(): void
    {
        BaseDeTest::pdo()->prepare('UPDATE ligne SET piece = ? WHERE offre_id = ?')->execute(['Devant la maison', $this->jeu->offre['a']]);
        $ligne = (int) BaseDeTest::ligne('SELECT id FROM ligne WHERE offre_id = ' . $this->jeu->offre['a'])['id'];
        $offre = '/offres/' . $this->jeu->offre['a'];

        // Bâtiment : les mots d'avant.
        self::assertStringContainsString('Renommer la pièce', $this->page($offre . '/pieces?nom=Devant+la+maison'));
        // Fiche d'une ligne : les pièces en puces sous leur titre (recette du 2026-11-24, MP-A14).
        self::assertStringContainsString('<legend>Pièce</legend>', $this->page("$offre/lignes/$ligne"));

        $this->activite('exterieur');
        $page = $this->page($offre . '/pieces?nom=Devant+la+maison');
        foreach (['Renommer la zone', '>Nom de la zone</label>', "Le nom d'une autre zone de l'offre", 'Zone Devant la maison · Offre'] as $attendu) {
            self::assertStringContainsString($attendu, $page);
        }
        self::assertStringNotContainsString('pièce', $page);
        $fiche = $this->page("$offre/lignes/$ligne");
        self::assertStringContainsString('<legend>Zone</legend>', $fiche);
        self::assertStringContainsString('placeholder="Autre zone"', $fiche);
        $ajout = $this->page($offre . '/ajouter');
        self::assertStringContainsString('aria-label="Zone de la ligne"', $ajout);
        self::assertStringContainsString('placeholder="Autre zone"', $ajout);

        $this->page($offre . '/pieces?nom=Devant+la+maison');
        $refus = $this->a->post($offre . '/pieces', ['ancienne' => 'Devant la maison', 'nouvelle' => '']);
        self::assertSame(422, $refus->getStatusCode());
        self::assertStringContainsString('Indiquez le nom de la zone', (string) $refus->getContent());
        self::assertSame(303, $this->a->post($offre . '/pieces', ['ancienne' => 'Devant la maison', 'nouvelle' => 'Talus'])->getStatusCode());
        self::assertStringContainsString('Zone renommée', $this->page($offre));
    }

    /** Xavier, 2026-11-23 : en Prestations, une partie annoncée se renomme et se choisit, sous le mot « catégorie ». */
    public function testEnPrestationsLeGroupeDicteEstUneCategorie(): void
    {
        BaseDeTest::pdo()->prepare('UPDATE ligne SET piece = ? WHERE offre_id = ?')->execute(['Pour le site', $this->jeu->offre['a']]);
        $ligne = (int) BaseDeTest::ligne('SELECT id FROM ligne WHERE offre_id = ' . $this->jeu->offre['a'])['id'];
        $offre = '/offres/' . $this->jeu->offre['a'];
        $this->activite('prestations');
        $page = $this->page($offre . '/pieces?nom=Pour+le+site');
        foreach (['Renommer la catégorie', '>Nom de la catégorie</label>', "Le nom d'une autre catégorie de l'offre"] as $attendu) {
            self::assertStringContainsString($attendu, $page);
        }
        self::assertStringNotContainsString('pièce', $page);
        self::assertStringContainsString('<legend>Catégorie</legend>', $this->page("$offre/lignes/$ligne"));
        $ajout = $this->page($offre . '/ajouter');
        self::assertStringContainsString('aria-label="Catégorie de la ligne"', $ajout);
        self::assertStringContainsString('placeholder="Autre catégorie"', $ajout);
        self::assertSame(303, $this->a->post($offre . '/pieces', ['ancienne' => 'Pour le site', 'nouvelle' => 'Site web'])->getStatusCode());
        self::assertStringContainsString('Catégorie renommée', $this->page($offre));
    }

    /** Xavier, 2026-11-23 : « Hors catalogue » sur les offres « c'est moche et sert à rien ». */
    public function testUneLigneSansCategorieNAPasDeTitre(): void
    {
        // La catégorie d'une ligne vient de son produit : sans produit, elle n'en a pas.
        BaseDeTest::pdo()->exec("UPDATE ligne SET produit_id = NULL WHERE offre_id = {$this->jeu->offre['a']}");
        $page = $this->page('/offres/' . $this->jeu->offre['a']);
        self::assertStringNotContainsString('Hors catalogue', $page);
        // Le titre du groupe porte son id de morceau (chantier sans rechargement, 2026-11-23) ; il reste vide.
        self::assertMatchesRegularExpression('#<div class="cat" id="g-[0-9a-f]{10}"><h5></h5>#', $page);
        $doc = (string) $this->a->get('/offres/' . $this->jeu->offre['a'] . '/document')->getContent();
        self::assertStringNotContainsString('Hors catalogue', $doc);
        self::assertStringNotContainsString('doc-grp', $doc, 'aucune ligne de titre de groupe sur le document');
    }

    public function testLAccueilNommeLaDicteeSelonLActivite(): void
    {
        // Une dictée en cours de traitement, et plus aucune offre : la liste « En préparation » et la phrase de l'accueil vide.
        BaseDeTest::pdo()->exec("DELETE FROM ligne WHERE offre_id = {$this->jeu->offre['a']}");
        BaseDeTest::pdo()->exec("DELETE FROM offre WHERE id = {$this->jeu->offre['a']}");
        $page = $this->page('/');
        self::assertStringContainsString('<b>Visite du ', $page);
        self::assertStringContainsString('enregistre la visite', $page);

        $this->activite('prestations');
        $page = $this->page('/');
        self::assertStringContainsString('<b>Dictée du ', $page);
        self::assertStringContainsString('enregistre votre dictée', $page);
        self::assertStringNotContainsString('Visite du', $page);
    }

    public function testLesAidesDeLaTvaSuiventLActivite(): void
    {
        self::assertStringContainsString('La plupart des travaux et des fournitures.', $this->page('/reglages/tva'));
        $this->activite('prestations');
        self::assertStringContainsString('La plupart des prestations et des fournitures.', $this->page('/reglages/tva'));
    }
}
