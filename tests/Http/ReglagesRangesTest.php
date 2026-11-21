<?php

declare(strict_types=1);

namespace OffreGen\Tests\Http;

use OffreGen\Tests\Outils\BaseDeTest;
use OffreGen\Tests\Outils\Jeu;
use OffreGen\Tests\Outils\Monde;
use OffreGen\Tests\Outils\Navigateur;
use PHPUnit\Framework\TestCase;

/**
 * Sous-chantier 22 (choix de Xavier du 2026-11-12) : sommaire par rubriques, rubrique seule à l'écran,
 * enregistrement automatique champ par champ, assistant au premier lancement.
 */
final class ReglagesRangesTest extends TestCase
{
    private Jeu $jeu;
    private Navigateur $a;

    protected function setUp(): void
    {
        $this->jeu = new Jeu();
        $this->a = new Navigateur(Monde::conteneur());
        $this->a->connecter('a@exemple.ch');
    }

    private function page(string $chemin): string
    {
        $r = $this->a->get($chemin);
        self::assertSame(200, $r->getStatusCode(), $chemin);
        return (string) $r->getContent();
    }

    private const ADRESSE = ['adresse_rue' => 'Rue', 'adresse_numero' => '1', 'adresse_npa' => '2800', 'adresse_localite' => 'Delémont', 'adresse_pays' => 'CH'];

    /** @return array<string, mixed> */
    private function artisan(): array
    {
        return BaseDeTest::ligne("SELECT entreprise, adresse_rue, adresse_numero, adresse_npa, adresse_localite, adresse_pays, adresse, ide, tva_pour_dix_mille, validite_jours, courriel_contact, reglages_faits_le FROM artisan WHERE id = {$this->jeu->a}");
    }

    public function testLeSommaireDitCeQuiManque(): void
    {
        $page = $this->page('/reglages');
        foreach (['entreprise' => 'Mon entreprise', 'marque' => 'Ma marque', 'conditions' => 'Conditions de l&#039;offre', 'tva' => 'TVA et arrondi'] as $cle => $titre) {
            self::assertStringContainsString("href=\"/reglages/$cle\"", $page);
        }
        self::assertStringContainsString('TVA et arrondi', $page);
        // Jeu : assujetti, sans adresse, ni IDE, ni IBAN.
        self::assertStringContainsString('3 rubriques sur 4 complètes', $page);
        self::assertMatchesRegularExpression('#Mon entreprise\s*<span class="badge manque">à compléter</span>#', $page);
        self::assertStringContainsString('adresse, IDE et IBAN manquent', $page);
        self::assertStringContainsString('action="/deconnexion"', $page);

        $this->page('/reglages/entreprise');
        $this->a->post('/reglages', self::ADRESSE + ['ide' => 'CHE-123.456.789'], json: true);
        self::assertStringContainsString('IBAN manque', $this->page('/reglages'), 'La facture le réclame (maquette 18, point réglé).');
        $this->a->post('/reglages', ['iban' => 'CH93 0076 2011 6238 5295 7'], json: true);
        self::assertStringContainsString('value="CH93 0076 2011 6238 5295 7"', $this->page('/reglages/entreprise'), 'Montré en groupes de quatre.');
        self::assertSame('CH9300762011623852957', BaseDeTest::ligne('SELECT iban FROM artisan WHERE id = ' . $this->jeu->a)['iban']);
        $page = $this->page('/reglages');
        self::assertStringContainsString('4 rubriques sur 4 complètes', $page);
        self::assertStringNotContainsString('à compléter', $page);
        self::assertStringContainsString('Entreprise A · Delémont', $page);
    }

    /** Le résumé tient sur une ligne de téléphone : la phrase entière de l'offre y serait coupée. */
    public function testLeResumeDesConditionsEstCourt(): void
    {
        $this->page('/reglages/conditions');
        $resume = function (array $champs): string {
            $this->a->post('/reglages', $champs, json: true);
            self::assertSame(1, preg_match('#href="/reglages/conditions".*?<small>([^<]*)</small>#s', $this->page('/reglages'), $m));
            return html_entity_decode($m[1] ?? '', ENT_QUOTES);
        };
        self::assertSame('Valable 30 jours · acomptes 25 / 25 / 50 %',
            $resume(['validite_choix' => '30', 'paiement_type' => 'acomptes', 'paiement_acomptes_choix' => 'autre', 'paiement_acomptes' => '25, 25, 50']));
        self::assertSame('Valable 45 jours · net à 10 jours',
            $resume(['validite_choix' => 'autre', 'validite_jours' => '45', 'paiement_type' => 'net', 'paiement_jours_choix' => '10']));
        self::assertSame('Valable 45 jours · à 20 jours',
            $resume(['paiement_type' => 'jours', 'paiement_jours_choix' => '20']));
    }

    public function testChaqueRubriqueSOuvreSeule(): void
    {
        $entreprise = $this->page('/reglages/entreprise');
        // L'adresse en champs séparés (Xavier, 2026-11-13 : « rue, NPA et ville […] c'est hyper important »).
        foreach (['adresse_rue', 'adresse_numero', 'adresse_npa', 'adresse_localite', 'adresse_pays'] as $champ) {
            self::assertStringContainsString('name="' . $champ . '"', $entreprise);
        }
        self::assertStringNotContainsString('<textarea', $entreprise);
        self::assertStringContainsString('<option value="CH" selected>Suisse</option>', $entreprise);
        self::assertStringNotContainsString('name="tva"', $entreprise);
        self::assertStringContainsString('href="/reglages"', $entreprise);
        self::assertStringContainsString('name="validite_choix"', $this->page('/reglages/conditions'));
        $tva = $this->page('/reglages/tva');
        self::assertStringContainsString('name="tva"', $tva);
        self::assertStringContainsString('name="arrondi"', $tva);
        self::assertStringContainsString('name="modele"', $this->page('/reglages/marque'));
        self::assertSame(404, $this->a->get('/reglages/inconnue')->getStatusCode());
    }

    public function testUnChampSEnregistreSeulSansToucherAuReste(): void
    {
        $avant = $this->artisan();
        $this->page('/reglages/entreprise');
        $ok = $this->a->post('/reglages', self::ADRESSE, json: true);
        self::assertSame(200, $ok->getStatusCode());
        self::assertSame(['ok' => true], json_decode((string) $ok->getContent(), true));
        $apres = $this->artisan();
        self::assertSame(['Rue', '1', '2800', 'Delémont', 'CH'], [$apres['adresse_rue'], $apres['adresse_numero'], $apres['adresse_npa'], $apres['adresse_localite'], $apres['adresse_pays']]);
        // Le texte suit les champs : l'ancienne version de l'application, qui ne lit que lui, reste juste.
        self::assertSame("Rue 1\n2800 Delémont", $apres['adresse']);
        self::assertSame([$avant['entreprise'], $avant['tva_pour_dix_mille'], $avant['validite_jours']],
            [$apres['entreprise'], $apres['tva_pour_dix_mille'], $apres['validite_jours']]);

        // Refusé : le message revient pour le champ, rien n'est écrit.
        $refus = $this->a->post('/reglages', ['courriel_contact' => 'pas un courriel'], json: true);
        self::assertSame(422, $refus->getStatusCode());
        $corps = json_decode((string) $refus->getContent(), true);
        self::assertIsArray($corps);
        self::assertFalse($corps['ok']);
        self::assertArrayHasKey('courriel_contact', $corps['erreurs']);
        self::assertNull($this->artisan()['courriel_contact']);
    }

    public function testLAdresseSeControleAuRepertoireOfficiel(): void
    {
        $this->page('/reglages/entreprise');
        $erreurs = fn (array $champs): array => (array) (json_decode((string) $this->a->post('/reglages', $champs, json: true)->getContent(), true)['erreurs'] ?? []);
        // Un champ à la fois (l'enregistrement au fil de la saisie) : la rue seule demande le NPA et la localité.
        self::assertSame(['adresse_npa' => 'Indiquez le NPA.', 'adresse_localite' => 'Indiquez la localité.'], $erreurs(['adresse_rue' => 'Rue']));
        self::assertSame(['adresse_localite' => 'Pour le NPA 2800, la localité est Delémont.'], $erreurs(['adresse_rue' => 'Rue', 'adresse_npa' => '2800', 'adresse_localite' => 'Bâle']));
        self::assertSame(['adresse_npa' => "Ce NPA n'existe pas."], $erreurs(['adresse_npa' => '2801']));
        self::assertNull($this->artisan()['adresse_npa'], 'rien n\'est écrit');
        // Le NPA seul suffit à la localité ; elle prend son nom officiel.
        self::assertSame([], $erreurs(['adresse_rue' => 'Rue de la Gare', 'adresse_numero' => '4', 'adresse_npa' => '2900']));
        self::assertSame('Porrentruy', $this->artisan()['adresse_localite']);
        self::assertStringContainsString('value="Porrentruy"', $this->page('/reglages/entreprise'));
    }

    public function testLAdresseDAvantResteAReporter(): void
    {
        // Une adresse que la migration 10 n'a pas su découper.
        BaseDeTest::pdo()->exec("UPDATE artisan SET adresse = 'Atelier du Pont\nRue 1\n2800 Delémont' WHERE id = {$this->jeu->a}");
        self::assertStringContainsString("Votre adresse d'avant, à reporter dans les champs&nbsp;: <b>Atelier du Pont, Rue 1, 2800 Delémont</b>", $this->page('/reglages/entreprise'));
        self::assertStringContainsString('adresse, IDE et IBAN manquent', $this->page('/reglages'));
        // Un autre champ s'enregistre sans l'effacer ; l'adresse en champs la remplace.
        $this->a->post('/reglages', ['telephone' => '032 422 00 00'], json: true);
        self::assertSame("Atelier du Pont\nRue 1\n2800 Delémont", $this->artisan()['adresse']);
        $this->a->post('/reglages', self::ADRESSE, json: true);
        self::assertSame("Rue 1\n2800 Delémont", $this->artisan()['adresse']);
        // Effacée ensuite volontairement : plus de texte non plus.
        $this->a->post('/reglages', ['adresse_rue' => '', 'adresse_numero' => '', 'adresse_npa' => '', 'adresse_localite' => ''], json: true);
        self::assertNull($this->artisan()['adresse']);
        self::assertStringNotContainsString("adresse d'avant", $this->page('/reglages/entreprise'));
    }

    public function testSansJavascriptLeBoutonRevientALaRubrique(): void
    {
        $this->page('/reglages/tva');
        self::assertSame('/reglages/tva', $this->a->post('/reglages', ['tva' => '0', 'retour' => '/reglages/tva'])->headers->get('Location'));
        self::assertSame(0, $this->artisan()['tva_pour_dix_mille']);
        // Retour hors de la liste des rubriques : le sommaire, jamais une autre adresse.
        $this->page('/reglages/tva');
        self::assertSame('/reglages', $this->a->post('/reglages', ['tva' => '810', 'retour' => 'https://ailleurs.example'])->headers->get('Location'));
    }

    public function testLAssistantSOuvreUneFoisAuPremierLancement(): void
    {
        BaseDeTest::pdo()->exec("UPDATE artisan SET reglages_faits_le = NULL WHERE id = {$this->jeu->a}");
        self::assertSame('/bienvenue/entreprise', $this->a->get('/')->headers->get('Location'));
        $page = $this->page('/bienvenue/entreprise');
        self::assertStringContainsString('Étape 1 sur 4', $page);
        self::assertStringContainsString('name="adresse_npa"', $page);

        $suites = ['entreprise' => '/bienvenue/marque', 'marque' => '/bienvenue/tva', 'tva' => '/bienvenue/conditions', 'conditions' => '/'];
        $champs = ['entreprise' => self::ADRESSE, 'marque' => ['modele' => 'bandeau'], 'tva' => ['tva' => '0'], 'conditions' => ['validite_choix' => '60']];
        foreach ($suites as $etape => $suite) {
            $this->page("/bienvenue/$etape");
            self::assertSame($suite, $this->a->post("/bienvenue/$etape", $champs[$etape])->headers->get('Location'), $etape);
        }
        $a = $this->artisan();
        self::assertSame(['2800', 0, 60], [$a['adresse_npa'], $a['tva_pour_dix_mille'], $a['validite_jours']]);
        self::assertNotNull($a['reglages_faits_le']);
        self::assertSame(200, $this->a->get('/')->getStatusCode());
    }

    public function testLAssistantSePasse(): void
    {
        BaseDeTest::pdo()->exec("UPDATE artisan SET reglages_faits_le = NULL WHERE id = {$this->jeu->a}");
        $this->page('/bienvenue/entreprise');
        self::assertSame('/', $this->a->post('/bienvenue/passer', [])->headers->get('Location'));
        self::assertSame(200, $this->a->get('/')->getStatusCode());
    }
}
