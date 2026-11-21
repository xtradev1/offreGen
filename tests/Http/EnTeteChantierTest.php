<?php

declare(strict_types=1);

namespace OffreGen\Tests\Http;

use OffreGen\Donnees\DepotEnregistrements;
use OffreGen\Donnees\DepotOffres;
use OffreGen\Donnees\Portee;
use OffreGen\Offre\LigneExtraite;
use OffreGen\Tests\Outils\BaseDeTest;
use OffreGen\Tests\Outils\Jeu;
use OffreGen\Tests\Outils\Monde;
use OffreGen\Tests\Outils\Navigateur;
use PHPUnit\Framework\TestCase;

/**
 * Modèles du bâtiment, lot 4 : le chantier, le début et la durée des travaux, ce qui n'est pas compris se saisissent
 * sur l'en-tête de l'offre ; le nom du contact dans « Mon entreprise ». Le document les montre, et se passe des
 * champs vides.
 */
final class EnTeteChantierTest extends TestCase
{
    private const CHANTIER = ['chantier_adresse' => 'Rue du Test 9, 2900 Porrentruy', 'chantier_contact' => 'Mme Essai, 079 000 00 00', 'chantier_acces' => 'Clé chez la concierge',
        'debut_travaux' => 'semaine 3, 2027', 'duree_travaux' => '6 jours ouvrables', 'non_compris' => "Peinture de finition\nInstallations électriques", 'visite_le' => '2026-11-12'];

    private Jeu $jeu;
    private Navigateur $a;
    private string $offre;

    protected function setUp(): void
    {
        $this->jeu = new Jeu();
        $this->a = new Navigateur(Monde::conteneur());
        $this->a->connecter('a@exemple.ch');
        $this->offre = '/offres/' . $this->jeu->offre['a'];
    }

    private function page(string $chemin): string
    {
        $r = $this->a->get($chemin);
        self::assertSame(200, $r->getStatusCode(), $chemin);
        return (string) $r->getContent();
    }

    /** @return array<string, mixed> */
    private function chantierEnBase(string $qui = 'a'): array
    {
        return BaseDeTest::ligne('SELECT chantier_adresse, chantier_contact, chantier_acces, debut_travaux, duree_travaux, non_compris, visite_le FROM offre WHERE id = ' . $this->jeu->offre[$qui]);
    }

    public function testLeChantierSaisiParaitSurLeDocument(): void
    {
        $formulaire = $this->page("$this->offre/modifier");
        foreach (array_keys(self::CHANTIER) as $champ) {
            self::assertStringContainsString("name=\"$champ\"", $formulaire);
        }
        $r = $this->a->post("$this->offre/modifier", ['titre' => 'Transformation de l\'étage'] + self::CHANTIER);
        self::assertSame(303, $r->getStatusCode(), (string) $r->getContent());
        self::assertSame(self::CHANTIER, $this->chantierEnBase());

        $doc = $this->page("$this->offre/document");
        foreach (['Chantier</div>Rue du Test 9, 2900 Porrentruy', 'Début des travaux&nbsp;: semaine 3, 2027. Durée&nbsp;: 6 jours ouvrables.', 'Non compris</div>',
                  '<li>Peinture de finition</li><li>Installations électriques</li>'] as $attendu) {
            self::assertStringContainsString($attendu, $doc);
        }
        // Le formulaire revient rempli.
        $formulaire = $this->page("$this->offre/modifier");
        self::assertStringContainsString('name="chantier_adresse" value="Rue du Test 9, 2900 Porrentruy"', $formulaire);
        self::assertStringContainsString("Peinture de finition\nInstallations électriques</textarea>", $formulaire);
        self::assertStringContainsString('name="visite_le" type="date" value="2026-11-12"', $formulaire);
        // Vidés, ils quittent la base et le document.
        self::assertSame(303, $this->a->post("$this->offre/modifier", ['titre' => 'Transformation de l\'étage'])->getStatusCode());
        self::assertSame(array_fill_keys(array_keys(self::CHANTIER), null), $this->chantierEnBase());
    }

    public function testUnChampVideNeLaisseAucunLibelle(): void
    {
        $doc = $this->page("$this->offre/document");
        foreach (['Chantier</div>', 'Votre contact', 'Début des travaux', 'Durée&nbsp;:', 'Non compris'] as $absent) {
            self::assertStringNotContainsString($absent, $doc);
        }
        // Le début seul : la phrase ne traîne pas de « Durée ».
        $this->page("$this->offre/modifier");
        $this->a->post("$this->offre/modifier", ['titre' => 'Offre a', 'debut_travaux' => 'mars 2027.']);
        $doc = $this->page("$this->offre/document");
        self::assertStringContainsString('<li>Début des travaux&nbsp;: mars 2027.</li>', $doc, 'un seul point final');
    }

    public function testUnChampTropLongEstRefuseEtRienNEstEnregistre(): void
    {
        $this->page("$this->offre/modifier");
        $r = $this->a->post("$this->offre/modifier", ['titre' => 'Autre objet', 'debut_travaux' => str_repeat('a', 81), 'chantier_adresse' => 'Rue du Test 9']);
        self::assertSame(422, $r->getStatusCode());
        self::assertStringContainsString('id="err-debut_travaux"', (string) $r->getContent());
        self::assertStringContainsString('name="chantier_adresse" value="Rue du Test 9"', (string) $r->getContent(), 'la saisie revient');
        self::assertNull($this->chantierEnBase()['chantier_adresse']);
        self::assertSame('Offre a', BaseDeTest::ligne('SELECT titre FROM offre WHERE id = ' . $this->jeu->offre['a'])['titre'], "l'objet non plus");
    }

    public function testLeChantierDUnAutreArtisanResteHorsDePortee(): void
    {
        $this->page("$this->offre/modifier");
        $r = $this->a->post('/offres/' . $this->jeu->offre['b'] . '/modifier', ['titre' => 'Pris'] + self::CHANTIER);
        self::assertSame(404, $r->getStatusCode());
        self::assertNull($this->chantierEnBase('b')['chantier_adresse']);
    }

    public function testLeLieuDicteDevientLAdresseDuChantier(): void
    {
        $portee = new Portee($this->jeu->a);
        $enregistrement = (new DepotEnregistrements($this->jeu->base, $portee))
            ->creer('00000000-0000-4000-8000-000000000009', 'visite.m4a', 'audio/mp4', 1000, "$portee->artisanId/9.m4a", $this->jeu->maintenant);
        $id = (new DepotOffres($this->jeu->base, $portee))->creerDepuisEnregistrement($enregistrement, 'Salle de bains',
            [new LigneExtraite('Carrelage', 6000, 'm²', 8500, false, null)], $this->jeu->maintenant, null, 'Madame Exemple', 'Rue du Test 4, Porrentruy');
        self::assertSame('Rue du Test 4, Porrentruy', BaseDeTest::ligne("SELECT chantier_adresse FROM offre WHERE id = $id")['chantier_adresse']);
        self::assertStringContainsString('Chantier</div>Rue du Test 4, Porrentruy', $this->page("/offres/$id/document"));
        // Sans lieu dicté : pas de chantier.
        self::assertNull($this->chantierEnBase()['chantier_adresse']);
    }

    public function testLeContactSigneLOffre(): void
    {
        self::assertStringContainsString('name="contact_nom"', $this->page('/reglages/entreprise'));
        $r = $this->a->post('/reglages', ['contact_nom' => '  Luc   Morel ', 'retour' => '/reglages/entreprise']);
        self::assertSame(303, $r->getStatusCode(), (string) $r->getContent());
        self::assertSame('Luc Morel', BaseDeTest::ligne("SELECT contact_nom FROM artisan WHERE id = {$this->jeu->a}")['contact_nom']);
        self::assertStringContainsString('name="contact_nom" value="Luc Morel"', $this->page('/reglages/entreprise'));
        $doc = $this->page("$this->offre/document");
        self::assertStringContainsString('Votre contact</div>Luc Morel', $doc);
        self::assertStringContainsString('<div class="doc-sig-nom">Luc Morel</div>', $doc, "sous « L'entreprise »");
        // Trop long : refusé.
        $this->page('/reglages/entreprise');
        self::assertSame(422, $this->a->post('/reglages', ['contact_nom' => str_repeat('a', 121), 'retour' => '/reglages/entreprise'])->getStatusCode());
        self::assertSame('Luc Morel', BaseDeTest::ligne("SELECT contact_nom FROM artisan WHERE id = {$this->jeu->a}")['contact_nom']);
    }
}
