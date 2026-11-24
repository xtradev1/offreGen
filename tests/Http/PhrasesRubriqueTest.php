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
 * LC-3 (chantier documents et réglages, décisions L0, L1 A et L2 de Xavier, 2026-11-24) : la section « Les phrases du
 * document » quitte Ma marque pour sa rubrique, « Phrases du document », sous Ma marque au sommaire. Ma marque et
 * l'étape « marque » de l'assistant gardent le logo, la couleur et le modèle.
 */
final class PhrasesRubriqueTest extends TestCase
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

    public function testMaMarqueNAPlusLesPhrases(): void
    {
        $marque = $this->page('/reglages/marque');
        self::assertStringContainsString('name="modele"', $marque);
        self::assertStringContainsString('name="couleur_choix"', $marque);
        self::assertStringNotContainsString('phrases-doc', $marque);
        self::assertStringNotContainsString('name="lettre_offre_debut"', $marque);
        self::assertStringNotContainsString('name="phrase_', $marque);
    }

    public function testLAssistantNAPlusLesPhrases(): void
    {
        $etape = $this->page('/bienvenue/marque');
        self::assertStringContainsString('name="modele"', $etape);
        self::assertStringNotContainsString('phrases-doc', $etape);
        self::assertStringNotContainsString('name="phrase_', $etape);
    }

    public function testLaRubriquePortePhrasesEtFormuleDAppel(): void
    {
        $page = $this->page('/reglages/phrases');
        self::assertStringContainsString('<fieldset class="groupe phrases-doc">', $page);
        self::assertStringContainsString('Phrases du document', $page);
        foreach (['lettre_offre_debut', 'lettre_offre_fin', 'lettre_facture_debut', 'lettre_facture_fin'] as $champ) {
            self::assertStringContainsString('name="' . $champ . '"', $page, $champ);
        }
        self::assertSame(count(PhrasesLettre::DOCUMENT), preg_match_all('#<textarea id="phrase_[a-z_]+" name="phrase_#', $page));
        self::assertStringContainsString('<input type="hidden" name="retour" value="/reglages/phrases">', $page);
        self::assertStringNotContainsString('name="modele"', $page, 'la marque reste dans sa rubrique');
    }

    public function testLeSommaireLaMontreSousMaMarqueAvecSonResume(): void
    {
        $sommaire = $this->page('/reglages');
        self::assertMatchesRegularExpression('#href="/reglages/marque".*href="/reglages/phrases".*href="/reglages/conditions"#s', $sommaire);
        self::assertSame(1, preg_match('#href="/reglages/phrases".*?<small>([^<]*)</small>#s', $sommaire, $m));
        self::assertSame('Dans Lettre seulement', $m[1] ?? null);

        $this->page('/reglages/phrases');
        $this->a->post('/reglages', ['lettre_partout' => '1', 'phrase_validite' => "Prix tenus jusqu'au [date].", 'retour' => '/reglages/phrases']);
        self::assertSame(1, preg_match('#href="/reglages/phrases".*?<small>([^<]*)</small>#s', $this->page('/reglages'), $m));
        self::assertSame("Dans les douze modèles · 1 phrase réécrite", html_entity_decode($m[1] ?? '', ENT_QUOTES));
    }

    /** Une phrase réécrite survit à un enregistrement de Ma marque, qui ne l'envoie plus. */
    public function testEnregistrerMaMarqueGardeLesPhrases(): void
    {
        $this->page('/reglages/phrases');
        $r = $this->a->post('/reglages', ['phrase_validite' => "Prix tenus jusqu'au [date].", 'lettre_offre_debut' => 'Voici notre offre.', 'retour' => '/reglages/phrases']);
        self::assertSame('/reglages/phrases', $r->headers->get('Location'));
        $this->page('/reglages/marque');
        self::assertSame(303, $this->a->post('/reglages', ['modele' => 'bandeau', 'couleur_choix' => '#0C616D', 'retour' => '/reglages/marque'])->getStatusCode());
        $l = BaseDeTest::ligne("SELECT phrases_document, lettre_offre_debut, modele FROM artisan WHERE id = {$this->jeu->a}");
        self::assertSame(['phrases_document' => '{"validite":"Prix tenus jusqu\'au [date]."}', 'lettre_offre_debut' => 'Voici notre offre.', 'modele' => 'bandeau'], $l);
    }
}
