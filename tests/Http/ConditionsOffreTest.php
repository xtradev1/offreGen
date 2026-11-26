<?php

declare(strict_types=1);

namespace OffreGen\Tests\Http;

use OffreGen\Donnees\DepotOffres;
use OffreGen\Donnees\Portee;
use OffreGen\Tests\Outils\BaseDeTest;
use OffreGen\Tests\Outils\Jeu;
use OffreGen\Tests\Outils\Monde;
use OffreGen\Tests\Outils\Navigateur;
use PHPUnit\Framework\TestCase;

/**
 * Conditions et textes propres à l'offre (D5 A, D6 A, D7 B, D16 A, 2026-11-21). Horloge des tests : 7 novembre 2026.
 * Le compte du jeu : valable 30 jours, à 30 jours dès la fin des travaux, pas d'escompte, modèle Sobre.
 */
final class ConditionsOffreTest extends TestCase
{
    private Jeu $jeu;
    private Navigateur $a;
    private string $offre;

    protected function setUp(): void
    {
        $this->jeu = new Jeu();
        $this->a = new Navigateur(Monde::conteneur());
        $this->a->connecter('a@exemple.ch');
        $this->offre = '/offres/' . $this->jeu->offre['a'];
        (new DepotOffres($this->jeu->base, new Portee($this->jeu->a)))->rattacherClient($this->jeu->offre['a'], $this->jeu->client['a'], $this->jeu->maintenant);
    }

    private function page(string $chemin): string
    {
        $r = $this->a->get($chemin);
        self::assertSame(200, $r->getStatusCode(), $chemin);
        return (string) $r->getContent();
    }

    /** @param array<string, string> $champs */
    private function envoyer(string $chemin, array $champs, int $attendu = 303): string
    {
        $this->page($this->offre);
        $r = $this->a->post($chemin, $champs);
        self::assertSame($attendu, $r->getStatusCode(), (string) $r->getContent());
        return (string) $r->getContent();
    }

    /** Le texte du document, une espace entre deux cellules. */
    private function texteDuDocument(): string
    {
        return (string) preg_replace('/\s+/u', ' ', html_entity_decode((string) preg_replace('/<[^>]+>/', ' ', $this->page($this->offre . '/document'))));
    }

    /** Valable 60 jours, net à 10 jours, escompte 2 % à 10 jours. */
    private function conditionsPropres(): void
    {
        $this->envoyer($this->offre . '/conditions', ['validite_choix' => '60', 'validite_jours' => '', 'paiement_type' => 'net',
            'paiement_jours_choix' => '10', 'paiement_jours' => '', 'paiement_acomptes_choix' => '', 'paiement_acomptes' => '',
            'escompte_pourcent' => '2', 'escompte_jours' => '10']);
    }

    public function testLaFenetreEstRemplieAvecLesReglages(): void
    {
        $page = $this->page($this->offre . '/conditions');
        self::assertStringContainsString('Conditions de cette offre', $page);
        self::assertStringContainsString('<input type="radio" name="validite_choix" value="30" checked>', $page);
        self::assertStringContainsString('<input type="radio" name="paiement_type" value="jours" checked>', $page);
        self::assertStringContainsString('name="escompte_pourcent" value=""', $page);
        self::assertStringContainsString('href="' . $this->offre . '/conditions" data-en-feuille', $this->page($this->offre));
    }

    public function testDesConditionsPropresNeValentQuePourCetteOffre(): void
    {
        $this->conditionsPropres();
        $json = (string) BaseDeTest::ligne("SELECT conditions FROM offre WHERE id = {$this->jeu->offre['a']}")['conditions'];
        self::assertStringContainsString('"validite_jours":"60"', $json);
        self::assertSame(1, BaseDeTest::compter('artisan', "id = {$this->jeu->a} AND validite_jours = 30 AND paiement_type = 'jours' AND escompte_pour_dix_mille = 0"), 'D7 B : les réglages du compte ne changent pas');
        $texte = $this->texteDuDocument();
        self::assertStringContainsString('Paiement : net à 10 jours dès réception de la facture, ou dans les 10 jours avec un escompte de 2 %.', $texte);
        // Enregistrée le 7 novembre 2026 : valable 60 jours, jusqu'au 6 janvier 2027.
        self::assertStringContainsString("Offre valable jusqu'au 06.01.2027", $texte);
        $ecran = (string) preg_replace('/\s+/u', ' ', $this->page($this->offre));
        self::assertStringContainsString('Valable 60 jours, paiement net à 10 jours', $ecran);
        self::assertStringContainsString('Escompte 2 % à 10 jours</small>', $ecran, 'insécables ramenées à des espaces par \\s');
        // La fenêtre se rouvre sur les conditions de l'offre.
        self::assertStringContainsString('<input type="radio" name="validite_choix" value="60" checked>', $this->page($this->offre . '/conditions'));
    }

    public function testUneOffreSansConditionsSuitLesReglagesDuCompte(): void
    {
        BaseDeTest::pdo()->exec("UPDATE artisan SET escompte_pour_dix_mille = 300, escompte_jours = 8 WHERE id = {$this->jeu->a}");
        self::assertStringContainsString('Paiement : à 30 jours dès la fin des travaux, ou dans les 8 jours avec un escompte de 3 %.', $this->texteDuDocument());
    }

    public function testUneSaisieFausseEstRefuseeSansRienEcrire(): void
    {
        $page = $this->envoyer($this->offre . '/conditions', ['validite_choix' => 'autre', 'validite_jours' => '0', 'paiement_type' => 'jours',
            'paiement_jours_choix' => '30', 'escompte_pourcent' => '2', 'escompte_jours' => ''], 422);
        $page = html_entity_decode($page, ENT_QUOTES);
        self::assertStringContainsString("L'offre doit avoir une durée de validité", $page);
        self::assertStringContainsString("Le délai de l'escompte", $page);
        self::assertNull(BaseDeTest::ligne("SELECT conditions FROM offre WHERE id = {$this->jeu->offre['a']}")['conditions']);
    }

    public function testLaFactureSuitLesConditionsDeLOffre(): void
    {
        $this->conditionsPropres();
        // Net à 10 jours : l'échéance proposée de la finale est au 17 novembre.
        self::assertStringContainsString('name="echeance" type="date" value="2026-11-17"', $this->page($this->offre . '/facturer'));
        // Acomptes 50 / 50 propres à l'offre : le premier acompte est proposé.
        $this->envoyer($this->offre . '/conditions', ['validite_choix' => '30', 'paiement_type' => 'acomptes', 'paiement_acomptes_choix' => '50,50',
            'escompte_pourcent' => '', 'escompte_jours' => '']);
        self::assertStringContainsString('Acompte 1 sur 2', $this->page($this->offre . '/facturer'));
    }

    public function testLeTexteAvantEtLeTexteApresParaissentSurLeDocument(): void
    {
        $this->envoyer($this->offre . '/textes', ['ou' => 'avant', 'texte' => "Suite à notre visite du 3 novembre.\r\nVoici notre offre."]);
        $this->envoyer($this->offre . '/textes', ['ou' => 'apres', 'texte' => 'Travaux possibles dès janvier.']);
        self::assertSame("Suite à notre visite du 3 novembre.\nVoici notre offre.", BaseDeTest::ligne("SELECT texte_avant FROM offre WHERE id = {$this->jeu->offre['a']}")['texte_avant']);
        $doc = $this->page($this->offre . '/document');
        self::assertStringContainsString('<div class="doc-texte doc-texte-avant">Suite à notre visite du 3 novembre.<br />', $doc);
        $texte = $this->texteDuDocument();
        $avant = mb_strpos($texte, 'Voici notre offre.');
        $ligne = mb_strpos($texte, 'Peinture murale a');
        $apres = mb_strpos($texte, 'Travaux possibles dès janvier.');
        $conditions = mb_strpos($texte, 'Conditions');
        self::assertTrue($avant !== false && $ligne !== false && $apres !== false && $conditions !== false && $avant < $ligne && $ligne < $apres && $apres < $conditions, $texte);
        self::assertStringContainsString('Suite à notre visite du 3 novembre.', $this->page($this->offre), 'le champ montre le texte');
        // Vider le champ retire le texte.
        $this->envoyer($this->offre . '/textes', ['ou' => 'apres', 'texte' => '  ']);
        self::assertNull(BaseDeTest::ligne("SELECT texte_apres FROM offre WHERE id = {$this->jeu->offre['a']}")['texte_apres']);
    }

    public function testDansLettreLeTexteAvantRemplaceLaPhraseEtLeTexteApresPrecedeLaPolitesse(): void
    {
        BaseDeTest::pdo()->exec("UPDATE artisan SET modele = 'lettre' WHERE id = {$this->jeu->a}");
        self::assertStringContainsString('placeholder="Nous vous remettons notre offre pour les travaux suivants."', $this->page($this->offre));
        self::assertStringContainsString('Nous vous remettons notre offre pour les travaux suivants.', $this->texteDuDocument());
        $this->envoyer($this->offre . '/textes', ['ou' => 'avant', 'texte' => 'Comme promis au téléphone, voici notre offre.']);
        $this->envoyer($this->offre . '/textes', ['ou' => 'apres', 'texte' => 'Travaux possibles dès janvier.']);
        $texte = $this->texteDuDocument();
        self::assertStringContainsString('Comme promis au téléphone, voici notre offre.', $texte);
        self::assertStringNotContainsString('Nous vous remettons notre offre', $texte, 'D6 A : le texte avant remplace la phrase de Ma marque');
        $apres = mb_strpos($texte, 'Travaux possibles dès janvier.');
        $politesse = mb_strpos($texte, 'Nous restons à votre disposition');
        self::assertTrue($apres !== false && $politesse !== false && $apres < $politesse, 'D16 A : avant la formule de politesse, qui reste');
    }

    public function testUnTexteTropLongNEstPasEnregistre(): void
    {
        $this->envoyer($this->offre . '/textes', ['ou' => 'avant', 'texte' => str_repeat('a', 1001)], 422);
        self::assertNull(BaseDeTest::ligne("SELECT texte_avant FROM offre WHERE id = {$this->jeu->offre['a']}")['texte_avant']);
        $this->page($this->offre);
        self::assertSame(404, $this->a->post($this->offre . '/textes', ['ou' => 'ailleurs', 'texte' => 'x'])->getStatusCode());
    }

    /** Revue du 2026-11-22 : enregistrer sans rien changer ne fige pas l'offre et ne la date pas. */
    public function testDesConditionsInchangeesNeSEcriventPas(): void
    {
        BaseDeTest::pdo()->exec("UPDATE offre SET modifiee_le = '2026-11-01 08:00:00' WHERE id = {$this->jeu->offre['a']}");
        $this->envoyer($this->offre . '/conditions', ['validite_choix' => '30', 'paiement_type' => 'jours', 'paiement_jours_choix' => '30',
            'escompte_pourcent' => '', 'escompte_jours' => '']);
        self::assertSame(1, BaseDeTest::compter('offre', "id = {$this->jeu->offre['a']} AND conditions IS NULL AND modifiee_le = '2026-11-01 08:00:00'"));
    }

    public function testLEscompteDuCompteSeRegleDansReglages(): void
    {
        $page = $this->page('/reglages/conditions');
        self::assertStringContainsString('name="escompte_pourcent"', $page);
        $r = $this->a->post('/reglages', ['retour' => '/reglages/conditions', 'escompte_pourcent' => '2', 'escompte_jours' => '10']);
        self::assertContains($r->getStatusCode(), [200, 303]);
        self::assertSame(1, BaseDeTest::compter('artisan', "id = {$this->jeu->a} AND escompte_pour_dix_mille = 200 AND escompte_jours = 10"));
        self::assertStringContainsString('escompte 2', $this->page('/reglages'));
    }
}
