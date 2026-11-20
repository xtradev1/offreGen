<?php

declare(strict_types=1);

namespace OffreGen\Tests\Http;

use OffreGen\Compte\PhrasesLettre;
use OffreGen\Tests\Outils\BaseDeTest;
use OffreGen\Tests\Outils\Dossiers;
use OffreGen\Tests\Outils\Jeu;
use OffreGen\Tests\Outils\Monde;
use OffreGen\Tests\Outils\Navigateur;
use PHPUnit\Framework\TestCase;

/**
 * Les phrases du modèle Lettre se règlent dans « Ma marque », sous le modèle (L1 à L6, réponses de Xavier du
 * 2026-11-20 à MAQUETTE-lettre-phrases.html), et vont jusqu'au document.
 */
final class PhrasesLettreEcranTest extends TestCase
{
    private Jeu $jeu;
    private Dossiers $dossiers;
    private Navigateur $a;

    protected function setUp(): void
    {
        $this->jeu = new Jeu();
        $this->dossiers = new Dossiers();
        $this->a = new Navigateur(Monde::conteneur(env: $this->dossiers->env()));
        $this->a->connecter('a@exemple.ch');
    }

    protected function tearDown(): void
    {
        $this->dossiers->effacer();
    }

    private function page(string $chemin): string
    {
        $r = $this->a->get($chemin);
        self::assertSame(200, $r->getStatusCode(), $chemin);
        return (string) $r->getContent();
    }

    public function testLesPhrasesSeReglentSousLeModeleLettre(): void
    {
        $page = $this->page('/reglages/marque');
        self::assertStringContainsString('<fieldset class="groupe si-lettre">', $page);
        foreach (['lettre_offre_debut', 'lettre_offre_fin', 'lettre_facture_debut', 'lettre_facture_fin'] as $champ) {
            self::assertMatchesRegularExpression('#<textarea id="' . $champ . '" name="' . $champ . '"[^>]*maxlength="500"#', $page, $champ);
        }
        self::assertStringContainsString('name="lettre_appel" value="fixe" checked', $page, 'par défaut, la même pour tous');
        self::assertStringContainsString('>' . PhrasesLettre::ACTUELLES['offre_debut'] . '</textarea>', $page, 'le champ montre la phrase actuelle');
        self::assertStringContainsString('data-tournure="' . htmlspecialchars(PhrasesLettre::TOURNURES['offre_debut'][2], ENT_QUOTES) . '"', $page);
    }

    public function testLesPhrasesRegleesVontJusquAuDocument(): void
    {
        $this->page('/reglages/marque');
        $r = $this->a->post('/reglages', ['lettre_appel' => 'client', 'lettre_offre_debut' => 'Comme convenu, voici notre offre pour les travaux suivants.',
            'lettre_offre_fin' => PhrasesLettre::TOURNURES['offre_fin'][1], 'retour' => '/reglages/marque']);
        self::assertSame(303, $r->getStatusCode());
        $l = BaseDeTest::ligne("SELECT lettre_appel, lettre_offre_debut, lettre_offre_fin, lettre_facture_debut FROM artisan WHERE id = {$this->jeu->a}");
        self::assertSame(['lettre_appel' => 'client', 'lettre_offre_debut' => 'Comme convenu, voici notre offre pour les travaux suivants.',
            'lettre_offre_fin' => PhrasesLettre::TOURNURES['offre_fin'][1], 'lettre_facture_debut' => null], $l);

        BaseDeTest::pdo()->exec("UPDATE artisan SET modele = 'lettre' WHERE id = {$this->jeu->a}");
        BaseDeTest::pdo()->exec("UPDATE offre SET client_id = NULL, client_dicte = 'Madame Exemple' WHERE id = {$this->jeu->offre['a']}");
        $doc = $this->page('/offres/' . $this->jeu->offre['a'] . '/document');
        self::assertStringContainsString('<div class="doc-txt">Madame,</div>', $doc);
        self::assertStringContainsString('Comme convenu, voici notre offre pour les travaux suivants.', $doc);
        self::assertStringContainsString(htmlspecialchars("Dans l'attente de votre réponse, nous vous prions d'agréer, Madame, nos salutations distinguées.", ENT_QUOTES), $doc);
        self::assertStringNotContainsString('Nous vous remettons notre offre', $doc);

        // Une autre rubrique enregistrée ensuite garde les phrases.
        $this->page('/reglages/entreprise');
        self::assertSame(303, $this->a->post('/reglages', ['contact_nom' => 'Luc Morel', 'retour' => '/reglages/entreprise'])->getStatusCode());
        self::assertSame('client', BaseDeTest::ligne("SELECT lettre_appel FROM artisan WHERE id = {$this->jeu->a}")['lettre_appel']);
    }

    public function testUnePhraseTropLongueEstRefuseeEtRienNEstEnregistre(): void
    {
        $this->page('/reglages/marque');
        $r = $this->a->post('/reglages', ['lettre_offre_debut' => str_repeat('a', 501), 'retour' => '/reglages/marque']);
        self::assertSame(422, $r->getStatusCode());
        self::assertStringContainsString('500 caractères au plus.', (string) $r->getContent());
        self::assertNull(BaseDeTest::ligne("SELECT lettre_offre_debut FROM artisan WHERE id = {$this->jeu->a}")['lettre_offre_debut']);
    }
}
