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
 * Sous-chantier 18, lot 3 (réponses de Xavier du 2026-11-13) : « Facturer » depuis l'offre, Nouvelle facture,
 * brouillon corrigeable comme l'offre, onglet Factures, lien depuis l'offre. Une facture émise ne se corrige plus.
 * Horloge des tests : 7 novembre 2026. Offre de A : 12,5 m² à 18.50, soit 231.25 HT.
 */
final class FacturesEcranTest extends TestCase
{
    private Jeu $jeu;
    private Navigateur $a;

    protected function setUp(): void
    {
        $this->jeu = new Jeu();
        $this->a = new Navigateur(Monde::conteneur());
        $this->a->connecter('a@exemple.ch');
        (new DepotOffres($this->jeu->base, new Portee($this->jeu->a)))->rattacherClient($this->jeu->offre['a'], $this->jeu->client['a'], $this->jeu->maintenant);
    }

    private function page(string $chemin): string
    {
        $r = $this->a->get($chemin);
        self::assertSame(200, $r->getStatusCode(), $chemin);
        return (string) $r->getContent();
    }

    private function facturer(): string
    {
        return '/offres/' . $this->jeu->offre['a'] . '/facturer';
    }

    /** @param array<string, string> $champs */
    private function creer(array $champs = []): int
    {
        $this->page($this->facturer());
        $r = $this->a->post($this->facturer(), $champs + ['genre' => 'finale', 'numero' => '2026-001', 'date' => '2026-11-07', 'echeance' => '2026-12-07']);
        self::assertSame(303, $r->getStatusCode(), (string) $r->getContent());
        $id = (int) BaseDeTest::ligne('SELECT MAX(id) AS id FROM facture')['id'];
        self::assertSame("/factures/$id", $r->headers->get('Location'));
        return $id;
    }

    private function acomptes(string $tranches): void
    {
        BaseDeTest::pdo()->exec("UPDATE artisan SET paiement_type = 'acomptes', paiement_acomptes = '$tranches' WHERE id = {$this->jeu->a}");
    }

    private function statut(int $id, string $statut): void
    {
        BaseDeTest::pdo()->exec("UPDATE facture SET statut = '$statut' WHERE id = $id");
    }

    public function testSansClientConfirmeOnNeFacturePas(): void
    {
        BaseDeTest::pdo()->exec("UPDATE offre SET client_id = NULL, client_dicte = 'Mme Dictée' WHERE id = {$this->jeu->offre['a']}");
        $page = $this->page($this->facturer());
        self::assertStringContainsString('href="/offres/' . $this->jeu->offre['a'] . '/client"', $page);
        self::assertStringNotContainsString('name="numero"', $page);
        $this->a->get('/reglages');
        self::assertSame(409, $this->a->post($this->facturer(), ['genre' => 'finale', 'numero' => '2026-001', 'date' => '2026-11-07', 'echeance' => '2026-12-07'])->getStatusCode());
        self::assertSame(0, BaseDeTest::compter('facture'));
    }

    public function testLaNouvelleFactureEstPreRemplie(): void
    {
        BaseDeTest::pdo()->exec("UPDATE artisan SET paiement_type = 'net', paiement_jours = 10 WHERE id = {$this->jeu->a}");
        $page = $this->page($this->facturer());
        self::assertStringContainsString('name="numero" value="2026-001"', $page);
        self::assertStringContainsString('name="date" type="date" value="2026-11-07"', $page);
        self::assertStringContainsString('name="echeance" type="date" value="2026-11-17"', $page, 'Net à 10 jours.');
        self::assertStringContainsString('Client a', $page);
        self::assertStringContainsString('value="finale" checked', $page);
        self::assertStringNotContainsString('value="acompte"', $page, 'Pas d\'acomptes dans les conditions.');
    }

    public function testLaFactureFinaleCopieLesLignesDeLOffre(): void
    {
        $id = $this->creer();
        $f = BaseDeTest::ligne("SELECT numero, genre, statut, client_nom, client_adresse, titre, date_facture, echeance FROM facture WHERE id = $id");
        self::assertSame(['2026-001', 'finale', 'brouillon', 'Client a', "Rue a 1\n2800 Delémont", 'Offre a', '2026-11-07', '2026-12-07'], array_values($f));
        // L'adresse du client est copiée aussi en champs, pour la section de paiement.
        self::assertSame(['client_rue' => 'Rue a', 'client_numero' => '1', 'client_npa' => '2800', 'client_localite' => 'Delémont', 'client_pays' => 'CH'],
            BaseDeTest::ligne("SELECT client_rue, client_numero, client_npa, client_localite, client_pays FROM facture WHERE id = $id"));
        $page = $this->page("/factures/$id");
        self::assertStringContainsString('Facture 2026-001', $page);
        self::assertStringContainsString('Brouillon', $page);
        self::assertStringContainsString('Peinture murale a', $page);
        self::assertStringContainsString('name="quantite" value="12.5"', $page, 'Les lignes se corrigent comme sur l\'offre.');
        self::assertStringContainsString('selon offre n°', $page);
        self::assertStringContainsString('href="/offres/' . $this->jeu->offre['a'] . '"', $page, 'La facture mène à son offre.');
    }

    public function testUnAcompteEstUneLigneAuPourcentageDuHt(): void
    {
        $this->acomptes('30,30,40');
        $page = $this->page($this->facturer());
        self::assertStringContainsString('value="acompte" checked', $page);
        self::assertStringContainsString("Acompte 1 sur 3 (30\u{00A0}%)", $page);
        self::assertStringContainsString('value="finale"', $page, 'Facturer le tout reste possible.');
        $id = $this->creer(['genre' => 'acompte']);
        $l = BaseDeTest::ligne("SELECT designation, quantite_milliemes, unite, prix_centimes FROM facture_ligne WHERE facture_id = $id");
        self::assertSame(["Acompte 1 sur 3 (30\u{00A0}%), selon offre n°\u{00A0}1", 1000, 'forfait', 6938], array_values($l), '30 % de 231.25 = 69.375, arrondi à 69.38.');
        self::assertSame(1, BaseDeTest::compter('facture', "id = $id AND genre = 'acompte' AND acompte_rang = 1 AND acompte_nombre = 3 AND acompte_pourcent = 30"));
        self::assertStringContainsString("Acompte 2 sur 3 (30\u{00A0}%)", $this->page($this->facturer()), 'Le suivant est proposé.');
        self::assertStringContainsString('name="numero" value="2026-002"', $this->page($this->facturer()));
    }

    public function testLeRangDeLAcompteVientDuServeur(): void
    {
        $this->acomptes('30,30,40');
        $id = $this->creer(['genre' => 'acompte', 'acompte_rang' => '3', 'acompte_pourcent' => '99']);
        self::assertSame(1, BaseDeTest::compter('facture', "id = $id AND acompte_rang = 1 AND acompte_pourcent = 30"));
    }

    public function testUnGenreQuiNEstPasProposeEstRefuse(): void
    {
        $this->page($this->facturer());
        $r = $this->a->post($this->facturer(), ['genre' => 'acompte', 'numero' => '2026-001', 'date' => '2026-11-07', 'echeance' => '2026-12-07']);
        self::assertSame(422, $r->getStatusCode());
        self::assertSame(0, BaseDeTest::compter('facture'));
    }

    public function testUnNumeroPrisOuMalFormeEstRefuse(): void
    {
        $this->acomptes('50,50');
        $this->creer(['genre' => 'acompte']);
        foreach (['2026-001' => 'Ce numéro est déjà pris', '2026 001' => 'Lettres, chiffres', '' => 'Lettres, chiffres'] as $numero => $message) {
            $this->page($this->facturer());
            $r = $this->a->post($this->facturer(), ['genre' => 'finale', 'numero' => $numero, 'date' => '2026-11-07', 'echeance' => '2026-12-07']);
            self::assertSame(422, $r->getStatusCode(), "« $numero »");
            self::assertStringContainsString($message, (string) $r->getContent());
        }
        $this->page($this->facturer());
        $r = $this->a->post($this->facturer(), ['genre' => 'finale', 'numero' => '2026-002', 'date' => '2026-11-07', 'echeance' => '2026-11-01']);
        self::assertSame(422, $r->getStatusCode());
        self::assertStringContainsString('L&#039;échéance suit la date de la facture', (string) $r->getContent());
        self::assertSame(1, BaseDeTest::compter('facture'));
    }

    public function testLesLignesDuBrouillonSeCorrigentSansToucherLOffre(): void
    {
        $id = $this->creer();
        $ligne = (int) BaseDeTest::ligne("SELECT id FROM facture_ligne WHERE facture_id = $id")['id'];
        $this->page("/factures/$id");
        $r = $this->a->post("/factures/$id/lignes/$ligne", ['quantite' => '14']);
        self::assertSame("/factures/$id#l$ligne", $r->headers->get('Location'));
        self::assertSame(1, BaseDeTest::compter('facture_ligne', "id = $ligne AND quantite_milliemes = 14000"));
        self::assertSame(1, BaseDeTest::compter('ligne', "offre_id = {$this->jeu->offre['a']} AND quantite_milliemes = 12500"));
        $page = $this->page("/factures/$id/ajouter");
        self::assertStringContainsString('action="/factures/' . $id . '/lignes"', $page);
        self::assertStringContainsString('Ajouter à la facture', $page);
        $r = $this->a->post("/factures/$id/lignes", ['designation' => 'Retouche porte', 'quantite' => '1', 'unite' => 'forfait', 'prix' => '95']);
        self::assertSame(303, $r->getStatusCode());
        self::assertSame(2, BaseDeTest::compter('facture_ligne', "facture_id = $id"));
        $this->page("/factures/$id");
        $this->a->post("/factures/$id/lignes/$ligne/supprimer", []);
        self::assertStringContainsString('action="/factures/' . $id . '/lignes/' . $ligne . '/restaurer"', $this->page("/factures/$id"), 'Annuler la suppression.');
    }

    public function testUneFactureEmiseNeSeCorrigePlus(): void
    {
        $id = $this->creer();
        $ligne = (int) BaseDeTest::ligne("SELECT id FROM facture_ligne WHERE facture_id = $id")['id'];
        $this->statut($id, 'emise');
        $page = $this->page("/factures/$id");
        self::assertStringNotContainsString('name="quantite"', $page, 'Plus aucun champ.');
        self::assertStringNotContainsString("/factures/$id/ajouter", $page);
        foreach (["/factures/$id/ajouter", "/factures/$id/lignes/$ligne", "/factures/$id/modifier"] as $chemin) {
            self::assertSame(409, $this->a->get($chemin)->getStatusCode(), $chemin);
        }
        foreach (["/factures/$id/lignes/$ligne" => ['quantite' => '1'], "/factures/$id/lignes" => ['designation' => 'X', 'quantite' => '1', 'unite' => 'pce', 'prix' => '1'],
                  "/factures/$id/lignes/$ligne/supprimer" => [], "/factures/$id/modifier" => ['numero' => 'X-1', 'date' => '2026-11-07', 'echeance' => '2026-12-07'],
                  "/factures/$id/supprimer" => []] as $chemin => $champs) {
            $this->page('/reglages');
            self::assertSame(409, $this->a->post($chemin, $champs)->getStatusCode(), $chemin);
        }
        self::assertSame(1, BaseDeTest::compter('facture_ligne', "id = $ligne AND quantite_milliemes = 12500 AND supprimee_le IS NULL"));
        self::assertSame(1, BaseDeTest::compter('facture', "id = $id AND numero = '2026-001'"));
    }

    public function testLEnteteDuBrouillonSeCorrige(): void
    {
        $id = $this->creer();
        $page = $this->page("/factures/$id/modifier");
        self::assertStringContainsString('name="numero" value="2026-001"', $page);
        $r = $this->a->post("/factures/$id/modifier", ['numero' => 'F-0100', 'date' => '2026-11-08', 'echeance' => '2026-12-08']);
        self::assertSame("/factures/$id", $r->headers->get('Location'));
        self::assertSame(1, BaseDeTest::compter('facture', "id = $id AND numero = 'F-0100' AND date_facture = '2026-11-08' AND echeance = '2026-12-08'"));
        $this->page("/factures/$id/modifier");
        self::assertSame(422, $this->a->post("/factures/$id/modifier", ['numero' => 'F-0100', 'date' => '2026-11-08', 'echeance' => '2026-11-01'])->getStatusCode());
    }

    public function testUnNumeroTapeFaitPartirLesSuivants(): void
    {
        $this->acomptes('50,50');
        $this->creer(['genre' => 'acompte', 'numero' => 'F-0099']);
        self::assertStringContainsString('name="numero" value="F-0100"', $this->page($this->facturer()));
    }

    public function testSupprimerLeBrouillon(): void
    {
        $id = $this->creer();
        $this->page("/factures/$id/modifier");
        $r = $this->a->post("/factures/$id/supprimer", []);
        self::assertSame('/factures', $r->headers->get('Location'));
        self::assertSame(0, BaseDeTest::compter('facture'));
        self::assertStringContainsString('name="numero" value="2026-001"', $this->page($this->facturer()), 'Le numéro se libère.');
    }

    public function testLOngletFacturesListeLesFactures(): void
    {
        $id = $this->creer();
        $page = $this->page('/factures');
        self::assertStringContainsString('href="/factures/' . $id . '"', $page);
        self::assertStringContainsString('2026-001', $page);
        self::assertStringContainsString('Client a', $page);
        self::assertStringContainsString('Brouillon', $page);
        self::assertStringContainsString('250.00', $page, '231.25 + TVA 18.73 = 249.98, arrondi à 250.00.');
        self::assertMatchesRegularExpression('#<a href="/factures" aria-current="page">#', $page);
        foreach (['/', '/clients', '/catalogue', '/reglages'] as $onglet) {
            self::assertStringContainsString('href="/factures"', $this->page($onglet), "Onglet Factures sur $onglet");
        }
    }

    public function testLOffreMeneASesFactures(): void
    {
        $offre = '/offres/' . $this->jeu->offre['a'];
        self::assertStringContainsString('href="' . $this->facturer() . '"', $this->page("$offre/document"), '« Facturer » à côté de « PDF ».');
        $id = $this->creer();
        $page = $this->page($offre);
        self::assertStringContainsString('href="/factures/' . $id . '"', $page);
        self::assertStringContainsString('2026-001', $page);
    }

    public function testLApercuDeLaFactureDitSonNumeroSonEcheanceEtSonOffre(): void
    {
        $id = $this->creer();
        $page = $this->page("/factures/$id/document");
        self::assertStringContainsString('Facture n°&nbsp;2026-001', $page);
        self::assertStringContainsString('selon offre n°&nbsp;1 du 07.11.2026', $page);
        self::assertStringContainsString("Payable jusqu'au 07.12.2026", $page);
        self::assertStringContainsString('Client a', $page);
        self::assertStringContainsString('Peinture murale a', $page);
        self::assertStringContainsString('250.00', $page);
        self::assertStringNotContainsString('Bon pour accord', $page, 'Une facture ne se signe pas.');
        self::assertStringNotContainsString('valable jusqu', $page);
        self::assertStringContainsString('href="/factures/' . $id . '"', $page, 'Retour aux lignes.');
    }

    public function testLeDocumentDeLaFinaleDeduitLesAcomptesEmis(): void
    {
        $this->acomptes('50,50');
        $acompte = $this->creer(['genre' => 'acompte']);
        BaseDeTest::pdo()->exec("UPDATE facture SET statut = 'emise', ttc = 12500, tva_pour_dix_mille = 810, arrondi_cinq = 1, deja_facture = 0 WHERE id = $acompte");
        $finale = $this->creer(['genre' => 'finale', 'numero' => '2026-002']);
        $page = $this->page("/factures/$finale/document");
        self::assertStringContainsString('Acomptes déjà facturés', $page);
        self::assertStringContainsString('125.00', $page);
        self::assertMatchesRegularExpression('#À payer</td><td class="n[^"]*">CHF\x{00A0}125\.00#u', $page, '250.00 moins 125.00.');
        self::assertStringContainsString('Acomptes déjà facturés', $this->page("/factures/$finale"), 'L\'écran de la facture aussi.');
    }

    private function emettre(int $id): void
    {
        $page = $this->page("/factures/$id/emettre");
        self::assertStringContainsString('ne se modifie plus', $page);
        $r = $this->a->post("/factures/$id/emettre", []);
        self::assertSame("/factures/$id/document", $r->headers->get('Location'), (string) $r->getContent());
    }

    public function testEmettreFigeLaFactureEtGardeSonPdf(): void
    {
        $id = $this->creer();
        $this->emettre($id);
        $f = BaseDeTest::ligne("SELECT statut, tva_pour_dix_mille, arrondi_cinq, ht, tva, arrondi, ttc, deja_facture, a_payer, emise_le IS NOT NULL AS datee FROM facture WHERE id = $id");
        self::assertSame(['emise', 810, 1, 23125, 1873, 2, 25000, 0, 25000, 1], array_values($f));
        $pdf = (string) BaseDeTest::ligne("SELECT contenu FROM facture_pdf WHERE facture_id = $id")['contenu'];
        self::assertStringStartsWith('%PDF', $pdf);
        $r = $this->a->get("/factures/$id/pdf");
        self::assertSame(200, $r->getStatusCode());
        self::assertSame('application/pdf', $r->headers->get('Content-Type'));
        self::assertStringContainsString('Facture-2026-001.pdf', (string) $r->headers->get('Content-Disposition'));
        self::assertSame($pdf, $r->getContent(), 'Le PDF servi est celui de l\'émission.');
        $page = $this->page("/factures/$id/document");
        self::assertStringContainsString("/factures/$id/pdf", $page);
        self::assertStringNotContainsString("/factures/$id/emettre", $page);
        $ecran = $this->page("/factures/$id");
        self::assertStringContainsString('href="/factures/' . $id . '/document"', $ecran, 'Depuis l\'écran d\'une facture émise, on atteint son aperçu.');
        self::assertStringContainsString('href="/factures/' . $id . '/pdf"', $ecran);
    }

    public function testLePdfEmisNeChangePasQuandLesReglagesChangent(): void
    {
        $id = $this->creer();
        $this->emettre($id);
        $avant = (string) $this->a->get("/factures/$id/pdf")->getContent();
        BaseDeTest::pdo()->exec("UPDATE artisan SET entreprise = 'Autre nom', tva_pour_dix_mille = 0, arrondi_cinq_centimes = 0, modele = 'moderne' WHERE id = {$this->jeu->a}");
        self::assertSame($avant, $this->a->get("/factures/$id/pdf")->getContent());
        $ecran = $this->page("/factures/$id");
        self::assertStringContainsString('TVA 8,1', $ecran, 'L\'écran garde le taux de l\'émission.');
        self::assertStringContainsString('250.00', $ecran);
    }

    public function testOnNEmetPasUneFactureAvecUneLigneSansPrix(): void
    {
        $id = $this->creer();
        BaseDeTest::pdo()->exec("UPDATE facture_ligne SET prix_centimes = 0 WHERE facture_id = $id");
        $page = $this->page("/factures/$id/emettre");
        self::assertStringContainsString('sans prix', $page);
        self::assertStringNotContainsString('action="/factures/' . $id . '/emettre"', $page);
        $this->page('/reglages');
        self::assertSame(409, $this->a->post("/factures/$id/emettre", [])->getStatusCode());
        self::assertSame(1, BaseDeTest::compter('facture', "id = $id AND statut = 'brouillon'"));
        self::assertSame(0, BaseDeTest::compter('facture_pdf'));
    }

    public function testUnBrouillonNAPasDePdfEtNeSAnnulePas(): void
    {
        $id = $this->creer();
        self::assertSame(409, $this->a->get("/factures/$id/pdf")->getStatusCode());
        self::assertSame(409, $this->a->get("/factures/$id/annuler")->getStatusCode());
        $this->page('/reglages');
        self::assertSame(409, $this->a->post("/factures/$id/annuler", [])->getStatusCode());
        self::assertSame(1, BaseDeTest::compter('facture', "id = $id AND statut = 'brouillon'"));
    }

    public function testAnnulerGardeLaFactureEtSonNumero(): void
    {
        $id = $this->creer();
        $this->emettre($id);
        self::assertStringContainsString('Annuler et refaire', $this->page("/factures/$id/annuler"));
        $r = $this->a->post("/factures/$id/annuler", []);
        self::assertSame("/factures/$id/document", $r->headers->get('Location'));
        self::assertSame(1, BaseDeTest::compter('facture', "id = $id AND statut = 'annulee' AND annulee_le IS NOT NULL"));
        self::assertSame(1, BaseDeTest::compter('facture_pdf', "facture_id = $id"), 'Le PDF envoyé reste gardé.');
        self::assertStringContainsString('Annulée', $this->page('/factures'));
        self::assertStringContainsString('name="numero" value="2026-002"', $this->page($this->facturer()), 'Le numéro annulé n\'est pas repris.');
        self::assertSame(409, $this->a->get("/factures/$id/annuler")->getStatusCode(), 'Une fois suffit.');
    }

    public function testAnnulerEtRefaireCopieLaFactureAuNumeroSuivant(): void
    {
        $id = $this->creer();
        $ligne = (int) BaseDeTest::ligne("SELECT id FROM facture_ligne WHERE facture_id = $id")['id'];
        $this->page("/factures/$id");
        $this->a->post("/factures/$id/lignes/$ligne", ['quantite' => '14']);
        $this->emettre($id);
        $this->page("/factures/$id/annuler");
        $r = $this->a->post("/factures/$id/annuler", ['refaire' => '1']);
        $nouvelle = (int) BaseDeTest::ligne('SELECT MAX(id) AS id FROM facture')['id'];
        self::assertSame("/factures/$nouvelle", $r->headers->get('Location'));
        self::assertSame(1, BaseDeTest::compter('facture', "id = $nouvelle AND statut = 'brouillon' AND numero = '2026-002' AND refaite_depuis = $id AND genre = 'finale' AND client_nom = 'Client a' AND client_npa = '2800' AND client_rue = 'Rue a'"));
        self::assertSame(1, BaseDeTest::compter('facture_ligne', "facture_id = $nouvelle AND quantite_milliemes = 14000"), 'Les lignes corrigées sont reprises.');
        self::assertSame(1, BaseDeTest::compter('facture', "id = $id AND statut = 'annulee'"));
    }

    public function testUnAcompteDejaDeduitNeSAnnulePasAvantLaFinale(): void
    {
        $this->acomptes('50,50');
        $acompte = $this->creer(['genre' => 'acompte']);
        $this->emettre($acompte);
        $finale = $this->creer(['genre' => 'finale', 'numero' => '2026-002']);
        $this->emettre($finale);
        self::assertSame(1, BaseDeTest::compter('facture', "id = $finale AND deja_facture = 12500 AND a_payer = 12500"), 'La finale déduit l\'acompte émis.');
        $this->page('/reglages');
        self::assertSame(409, $this->a->post("/factures/$acompte/annuler", [])->getStatusCode());
        self::assertSame(1, BaseDeTest::compter('facture', "id = $acompte AND statut = 'emise'"));
    }

    public function testUneOffreFactureeEnEntierNeSeRefacturePas(): void
    {
        $id = $this->creer();
        $page = $this->page($this->facturer());
        self::assertStringNotContainsString('name="numero"', $page);
        self::assertStringContainsString('href="/factures/' . $id . '"', $page);
        $this->a->get('/reglages');
        self::assertSame(409, $this->a->post($this->facturer(), ['genre' => 'finale', 'numero' => '2026-002', 'date' => '2026-11-07', 'echeance' => '2026-12-07'])->getStatusCode());
        $this->statut($id, 'annulee');
        self::assertStringContainsString('name="numero" value="2026-002"', $this->page($this->facturer()), 'Une finale annulée se refait, au numéro suivant.');
    }
}
