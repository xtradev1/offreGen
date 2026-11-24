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
 * Les phrases du modèle Lettre se règlent dans leur rubrique, « Phrases du document » (L1 à L6, réponses de Xavier du
 * 2026-11-20 à MAQUETTE-lettre-phrases.html ; sorties de Ma marque le 2026-11-24, L1 A), et vont jusqu'au document.
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

    public function testLesPhrasesSeReglentPourLesDouzeModeles(): void
    {
        $page = $this->page('/reglages/phrases');
        self::assertStringContainsString('<fieldset class="groupe phrases-doc">', $page, 'visibles quel que soit le modèle (M4 B)');
        self::assertStringContainsString('name="lettre_partout" value="0" checked', $page, 'par défaut, Lettre seulement');
        foreach (['lettre_offre_debut', 'lettre_offre_fin', 'lettre_facture_debut', 'lettre_facture_fin'] as $champ) {
            self::assertMatchesRegularExpression('#<textarea id="' . $champ . '" name="' . $champ . '"[^>]*maxlength="500"#', $page, $champ);
        }
        self::assertStringContainsString('name="lettre_appel" value="fixe" checked', $page, 'par défaut, la même pour tous');
        self::assertStringContainsString('>' . PhrasesLettre::ACTUELLES['offre_debut'] . '</textarea>', $page, 'le champ montre la phrase actuelle');
        self::assertStringContainsString('data-tournure="' . htmlspecialchars(PhrasesLettre::TOURNURES['offre_debut'][2], ENT_QUOTES) . '"', $page);
    }

    public function testLesPhrasesRegleesVontJusquAuDocument(): void
    {
        $this->page('/reglages/phrases');
        $r = $this->a->post('/reglages', ['lettre_appel' => 'client', 'lettre_offre_debut' => 'Comme convenu, voici notre offre pour les travaux suivants.',
            'lettre_offre_fin' => PhrasesLettre::TOURNURES['offre_fin'][1], 'retour' => '/reglages/phrases']);
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

    /** M1 A, M3 A : chaque phrase tirée de l'offre, repliée, avec ses pastilles ; réécrite, elle va jusqu'au document. */
    public function testLesPhrasesTireesDeLOffreSeReecrivent(): void
    {
        $page = $this->page('/reglages/phrases');
        foreach (array_keys(PhrasesLettre::DOCUMENT) as $cle) {
            self::assertMatchesRegularExpression('#<textarea id="phrase_' . $cle . '" name="phrase_' . $cle . '" rows="3" maxlength="500" data-origine="#', $page, $cle);
        }
        self::assertStringContainsString('<details class="phrase-doc">', $page);
        self::assertStringContainsString('data-pastille="[date]" data-pour="phrase_visite"', $page);
        self::assertStringContainsString('>Visite du [date].</textarea>', $page, "le champ montre la phrase d'origine");

        $r = $this->a->post('/reglages', ['phrase_validite' => 'Prix tenus jusqu\'au [date].', 'phrase_visite' => '', 'lettre_partout' => '1', 'retour' => '/reglages/phrases']);
        self::assertSame(303, $r->getStatusCode());
        $l = BaseDeTest::ligne("SELECT phrases_document, lettre_partout FROM artisan WHERE id = {$this->jeu->a}");
        self::assertSame(['phrases_document' => '{"validite":"Prix tenus jusqu\'au [date]."}', 'lettre_partout' => 1], $l);
        $page = $this->page('/reglages/phrases');
        self::assertStringContainsString('name="lettre_partout" value="1" checked', $page);
        self::assertMatchesRegularExpression('#<span class="phrase-nom">Validité</span> <span class="phrase-etat" data-etat-phrase>Réécrite</span>#', $page);

        BaseDeTest::pdo()->exec("UPDATE artisan SET modele = 'sobre' WHERE id = {$this->jeu->a}");
        $doc = $this->page('/offres/' . $this->jeu->offre['a'] . '/document');
        self::assertMatchesRegularExpression("#Prix tenus jusqu'au \d\d\.\d\d\.\d{4}\.#", $doc);
        self::assertStringContainsString('<div class="doc-txt">Madame, Monsieur,</div>', $doc, 'la formule d\'appel dans Sobre aussi');
    }

    public function testUnePastilleInconnueEstRefuseeEtSignalee(): void
    {
        $this->page('/reglages/phrases');
        $r = $this->a->post('/reglages', ['phrase_visite' => 'Venus le [date visite].', 'retour' => '/reglages/phrases']);
        self::assertSame(422, $r->getStatusCode());
        self::assertStringContainsString('Pastille inconnue&nbsp;: [date visite]. Celle de cette phrase&nbsp;: [date].', str_replace("\u{00A0}", '&nbsp;', (string) $r->getContent()));
        self::assertMatchesRegularExpression('#<details class="phrase-doc" open>\s*<summary><span class="phrase-nom">Date de la visite#', (string) $r->getContent(), 'la phrase refusée est ouverte');
        self::assertNull(BaseDeTest::ligne("SELECT phrases_document FROM artisan WHERE id = {$this->jeu->a}")['phrases_document']);
    }

    public function testUnePhraseTropLongueEstRefuseeEtRienNEstEnregistre(): void
    {
        $this->page('/reglages/phrases');
        $r = $this->a->post('/reglages', ['lettre_offre_debut' => str_repeat('a', 501), 'retour' => '/reglages/phrases']);
        self::assertSame(422, $r->getStatusCode());
        self::assertStringContainsString('500 caractères au plus.', (string) $r->getContent());
        self::assertNull(BaseDeTest::ligne("SELECT lettre_offre_debut FROM artisan WHERE id = {$this->jeu->a}")['lettre_offre_debut']);
    }
}
