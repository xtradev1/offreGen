<?php

declare(strict_types=1);

namespace OffreGen\Tests\Http;

use OffreGen\Donnees\DepotOffres;
use OffreGen\Donnees\Portee;
use OffreGen\Facture\QrFacture;
use OffreGen\Tests\Outils\BaseDeTest;
use OffreGen\Tests\Outils\Jeu;
use OffreGen\Tests\Outils\Monde;
use OffreGen\Tests\Outils\Navigateur;
use PHPUnit\Framework\TestCase;

/**
 * Sous-chantier 18, lot 6b : la section de paiement de la facture (QR-facture), à l'écran et au PDF. La référence
 * et l'IBAN sont figés à l'émission ; une facture courte porte la section au bas de sa page, une longue sur une page
 * à part (Xavier, 2026-11-12). Offre de A : 250.00 TTC ; client « Client a, Rue a 1, 2800 Delémont ».
 */
final class FactureQrTest extends TestCase
{
    private const IBAN = 'CH9300762011623852957';
    private const QR_IBAN = 'CH4431999123000889012';

    private Jeu $jeu;
    private Navigateur $a;

    protected function setUp(): void
    {
        $this->jeu = new Jeu();
        $this->a = new Navigateur(Monde::conteneur());
        $this->a->connecter('a@exemple.ch');
        (new DepotOffres($this->jeu->base, new Portee($this->jeu->a)))->rattacherClient($this->jeu->offre['a'], $this->jeu->client['a'], $this->jeu->maintenant);
        $this->artisan(self::IBAN);
    }

    private function artisan(string $iban, string $npa = '2800'): void
    {
        BaseDeTest::pdo()->exec("UPDATE artisan SET entreprise = 'Peinture Morel Sàrl', iban = '$iban', adresse_rue = 'Rue de l''Exemple', adresse_numero = '12',
            adresse_npa = '$npa', adresse_localite = " . ($npa === '' ? "''" : "'Delémont'") . ", adresse_pays = 'CH' WHERE id = {$this->jeu->a}");
    }

    private function page(string $chemin): string
    {
        $r = $this->a->get($chemin);
        self::assertSame(200, $r->getStatusCode(), $chemin);
        return (string) $r->getContent();
    }

    private function creer(string $numero = '2026-001'): int
    {
        $chemin = '/offres/' . $this->jeu->offre['a'] . '/facturer';
        $this->page($chemin);
        $r = $this->a->post($chemin, ['genre' => 'finale', 'numero' => $numero, 'date' => '2026-11-07', 'echeance' => '2026-12-07']);
        self::assertSame(303, $r->getStatusCode(), (string) $r->getContent());
        return (int) BaseDeTest::ligne('SELECT MAX(id) AS id FROM facture')['id'];
    }

    private function emettre(int $id): string
    {
        $this->page("/factures/$id/emettre");
        $r = $this->a->post("/factures/$id/emettre", []);
        self::assertSame("/factures/$id/document", $r->headers->get('Location'), (string) $r->getContent());
        return (string) BaseDeTest::ligne("SELECT contenu FROM facture_pdf WHERE facture_id = $id")['contenu'];
    }

    private static function pages(string $pdf): int
    {
        return (int) preg_match_all('#/Type\s*/Page[^s]#', $pdf);
    }

    public function testLApercuMontreLaSectionDePaiement(): void
    {
        $page = $this->page('/factures/' . $this->creer() . '/document');
        self::assertStringContainsString('Récépissé', $page);
        self::assertStringContainsString('Section paiement', $page);
        self::assertStringContainsString('CH93 0076 2011 6238 5295 7', $page);
        self::assertMatchesRegularExpression('#RF\d\d 2026 001#', $page, 'Référence RF bâtie sur le numéro.');
        self::assertStringContainsString('Facture n° 2026-001', $page);
        self::assertStringContainsString('<p class="qr-e">Payable par</p><p class="qr-v">Client a<br>Rue a 1<br>2800 Delémont</p>', $page, '« Payable par » : le client de la facture.');
        self::assertStringContainsString("250.00", $page);
        self::assertStringContainsString('<svg xmlns="http://www.w3.org/2000/svg" width="174"', $page, 'Le code en ligne : la CSP refuse les images en data:.');
        self::assertStringNotContainsString('data:image', $page);
        self::assertStringContainsString('avec la section de paiement ci-dessous', $page);
        self::assertStringNotContainsString(QrFacture::SANS_IBAN, $page);
    }

    public function testSansIbanOuSansAdresseLApercuDitPourquoi(): void
    {
        $this->artisan('');
        $id = $this->creer();
        $page = $this->page("/factures/$id/document");
        self::assertStringNotContainsString('Récépissé', $page);
        self::assertStringContainsString(htmlspecialchars(QrFacture::SANS_IBAN) . '&nbsp;: <a href="/reglages/entreprise">', $page);
        self::assertStringNotContainsString('avec la section de paiement', $page);
        $this->artisan(self::IBAN, '');
        $page = $this->page("/factures/$id/document");
        self::assertStringContainsString(htmlspecialchars(QrFacture::SANS_ADRESSE) . '&nbsp;: <a href="/reglages/entreprise">', $page);
    }

    public function testUnClientSansAdresseLaissePayableParAuCadreVide(): void
    {
        BaseDeTest::pdo()->exec("UPDATE client SET rue = NULL, numero = NULL, npa = NULL, localite = NULL WHERE id = {$this->jeu->client['a']}");
        $page = $this->page('/factures/' . $this->creer() . '/document');
        self::assertStringContainsString('Payable par (nom/adresse)', $page);
        self::assertStringNotContainsString('<p class="qr-v">Client a', $page, 'L\'en-tête garde le nom ; la section, non.');
    }

    public function testEmettreFigeLaReferenceEtLIban(): void
    {
        $id = $this->creer();
        $this->emettre($id);
        $f = BaseDeTest::ligne("SELECT iban, reference FROM facture WHERE id = $id");
        self::assertSame(self::IBAN, $f['iban']);
        self::assertSame(QrFacture::reference(self::IBAN, $id, '2026-001'), $f['reference']);
        $this->artisan('CH5604835012345678009');
        $page = $this->page("/factures/$id/document");
        self::assertStringContainsString('CH93 0076 2011 6238 5295 7', $page, 'L\'aperçu d\'une facture émise garde l\'IBAN de l\'émission.');
        self::assertStringNotContainsString('CH56 0483', $page);
    }

    public function testUnQrIbanDonneUneReferenceQr(): void
    {
        $this->artisan(self::QR_IBAN);
        $id = $this->creer();
        $this->emettre($id);
        self::assertMatchesRegularExpression('/^\d{27}$/', (string) BaseDeTest::ligne("SELECT reference FROM facture WHERE id = $id")['reference']);
    }

    public function testUneFactureEmiseSansIbanNAPasDeReference(): void
    {
        $this->artisan('');
        $id = $this->creer();
        $this->emettre($id);
        self::assertNull(BaseDeTest::ligne("SELECT reference FROM facture WHERE id = $id")['reference']);
    }

    public function testUneFactureCourteTientSurUnePageAvecSaSection(): void
    {
        $pdf = $this->emettre($this->creer());
        self::assertSame(1, self::pages($pdf), 'La section au bas de la page 1.');
    }

    /** Une facture de 41 lignes émise, avec ou sans IBAN ; la précédente est annulée (une seule finale par offre). */
    private function longue(string $numero, string $iban): string
    {
        $id = $this->creer($numero);
        $lignes = [];
        for ($i = 2; $i <= 41; $i++) {
            $lignes[] = "({$this->jeu->a}, $id, $i, 'Poste de travail $i', 1000, 'h', 100, 'Travaux')";
        }
        BaseDeTest::pdo()->exec('INSERT INTO facture_ligne (artisan_id, facture_id, rang, designation, quantite_milliemes, unite, prix_centimes, categorie) VALUES ' . implode(',', $lignes));
        $this->artisan($iban);
        $pdf = $this->emettre($id);
        BaseDeTest::pdo()->exec("UPDATE facture SET statut = 'annulee' WHERE id = $id");
        return $pdf;
    }

    public function testUneFactureLonguePorteSaSectionSurUnePageAPart(): void
    {
        $sans = self::pages($this->longue('2026-001', ''));
        $avec = self::pages($this->longue('2026-002', self::IBAN));
        self::assertGreaterThanOrEqual(2, $sans, 'Témoin : 41 lignes ne tiennent pas sur une page.');
        self::assertSame($sans + 1, $avec, 'La section a sa page à elle.');
    }
}
