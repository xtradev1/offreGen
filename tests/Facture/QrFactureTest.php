<?php

declare(strict_types=1);

namespace OffreGen\Tests\Facture;

use BaconQrCode\Common\ErrorCorrectionLevel;
use BaconQrCode\Encoder\Encoder;
use OffreGen\Adresse\Adresse;
use OffreGen\Facture\QrFacture;
use OffreGen\Facture\SectionPaiement;
use PHPUnit\Framework\TestCase;

/**
 * Sous-chantier 18, lot 6b : la section de paiement de la QR-facture (norme SIX, version 2.3), adresses structurées
 * (type S) seulement. Les clés de contrôle des références sont recalculées ici à la main, sans la bibliothèque.
 * IBAN d'exemple de la norme : CH93 0076 2011 6238 5295 7 (ordinaire), CH44 3199 9123 0008 8901 2 (QR-IBAN).
 */
final class QrFactureTest extends TestCase
{
    private const IBAN = 'CH9300762011623852957';
    private const QR_IBAN = 'CH4431999123000889012';

    private static function artisan(): Adresse
    {
        return new Adresse("Rue de l'Exemple", '12', '2800', 'Delémont');
    }

    /** @param array<string, mixed> $remplace */
    private static function section(array $remplace = []): SectionPaiement|string
    {
        $v = $remplace + [
            'entreprise' => 'Peinture Morel Sàrl', 'adresse' => self::artisan(), 'iban' => self::IBAN,
            'reference' => QrFacture::reference(self::IBAN, 7, '2026-001'), 'numero' => '2026-001', 'a_payer' => 310680,
            'client_nom' => 'Famille Exemple', 'client' => new Adresse('Chemin des Essais', '4', '1700', 'Fribourg'),
        ];
        /** @var array{entreprise: string, adresse: Adresse, iban: string, reference: string|null, numero: string, a_payer: int, client_nom: string, client: Adresse} $v */
        return QrFacture::section($v['entreprise'], $v['adresse'], $v['iban'], $v['reference'], $v['numero'], $v['a_payer'], $v['client_nom'], $v['client']);
    }

    private static function ok(SectionPaiement|string $s): SectionPaiement
    {
        self::assertInstanceOf(SectionPaiement::class, $s, is_string($s) ? $s : '');
        return $s;
    }

    /** Clé modulo 10 récursive de la référence QR (table de la norme). */
    private static function cleModulo10(string $chiffres): int
    {
        $table = [0, 9, 4, 6, 8, 2, 7, 1, 3, 5];
        $report = 0;
        foreach (str_split($chiffres) as $c) {
            $report = $table[($report + (int) $c) % 10];
        }
        return (10 - $report) % 10;
    }

    /** Clé ISO 11649 : « RF00 » à la fin, lettres en nombres (A = 10), reste de la division par 97, 98 moins ce reste. */
    private static function cleRf(string $base): int
    {
        $nombre = '';
        foreach (str_split($base . 'RF00') as $c) {
            $nombre .= ctype_digit($c) ? $c : (string) (ord($c) - 55);
        }
        $reste = 0;
        foreach (str_split($nombre, 7) as $morceau) {
            $reste = (int) ($reste . $morceau) % 97;
        }
        return 98 - $reste;
    }

    public function testUnIbanOrdinaireDonneUneReferenceRfBatieSurLeNumero(): void
    {
        $ref = QrFacture::reference(self::IBAN, 7, '2026-001');
        self::assertSame(sprintf('RF%02d2026001', self::cleRf('2026001')), $ref);
        self::assertSame('RF' . sprintf('%02d', self::cleRf('F0099A')) . 'F0099A', QrFacture::reference(self::IBAN, 7, 'f-0099.a'), 'Lettres en majuscules, signes retirés.');
        self::assertNull(QrFacture::reference(self::IBAN, 7, '-/._'), 'Rien à garder : paiement sans référence.');
    }

    public function testUnQrIbanDonneUneReferenceQrTireeDeLIdentifiantDeLaFacture(): void
    {
        $ref = (string) QrFacture::reference(self::QR_IBAN, 4242, '2026-001');
        self::assertMatchesRegularExpression('/^\d{27}$/', $ref);
        self::assertSame(str_pad('4242', 26, '0', STR_PAD_LEFT), substr($ref, 0, 26));
        self::assertSame((string) self::cleModulo10(substr($ref, 0, 26)), substr($ref, 26));
    }

    public function testLeContenuDuCodeSuitLaNormeEnTrenteEtUneLignes(): void
    {
        $s = self::ok(self::section());
        $attendu = [
            'SPC', '0200', '1', self::IBAN,
            'S', 'Peinture Morel Sàrl', "Rue de l'Exemple", '12', '2800', 'Delémont', 'CH',
            '', '', '', '', '', '', '',
            '3106.80', 'CHF',
            'S', 'Famille Exemple', 'Chemin des Essais', '4', '1700', 'Fribourg', 'CH',
            'SCOR', sprintf('RF%02d2026001', self::cleRf('2026001')),
            'Facture n° 2026-001', 'EPD',
        ];
        self::assertSame($attendu, explode("\n", $s->texte));
    }

    public function testLaReferenceQrPasseDansLeCodeEnQrr(): void
    {
        $ref = QrFacture::reference(self::QR_IBAN, 4242, '2026-001');
        $lignes = explode("\n", self::ok(self::section(['iban' => self::QR_IBAN, 'reference' => $ref]))->texte);
        self::assertSame(['QRR', $ref], [$lignes[27], $lignes[28]]);
    }

    public function testSansReferenceLePaiementEstSansReference(): void
    {
        $lignes = explode("\n", self::ok(self::section(['reference' => null]))->texte);
        self::assertSame(['NON', ''], [$lignes[27], $lignes[28]]);
    }

    public function testCeQueMontreLaSection(): void
    {
        $s = self::ok(self::section());
        self::assertSame('CH93 0076 2011 6238 5295 7', $s->compte);
        self::assertSame(['Peinture Morel Sàrl', "Rue de l'Exemple 12", '2800 Delémont'], $s->creancier);
        self::assertSame(['Famille Exemple', 'Chemin des Essais 4', '1700 Fribourg'], $s->debiteur);
        self::assertSame('RF' . sprintf('%02d', self::cleRf('2026001')) . ' 2026 001', $s->reference, 'Par groupes de quatre.');
        self::assertSame("3\u{00A0}106.80", $s->montant, 'Espace comme séparateur de milliers (norme), insécable.');
        self::assertSame('CHF', $s->monnaie);
        self::assertSame('Facture n° 2026-001', $s->informations);
        $qr = (string) QrFacture::reference(self::QR_IBAN, 4242, '2026-001');
        self::assertSame(trim((string) strrev(chunk_split(strrev($qr), 5, ' '))), self::ok(self::section(['iban' => self::QR_IBAN, 'reference' => $qr]))->reference, 'QR : groupes de cinq depuis la droite.');
    }

    public function testUneAdresseHorsDeSuissePorteLeCodeDuPays(): void
    {
        $s = self::ok(self::section(['client' => new Adresse('Rue de Rivoli', '1', '75001', 'Paris', 'FR')]));
        self::assertSame(['Famille Exemple', 'Rue de Rivoli 1', 'FR-75001 Paris'], $s->debiteur);
        self::assertSame('FR', explode("\n", $s->texte)[26]);
    }

    public function testUnClientSansAdresseLaissePayableParVide(): void
    {
        foreach ([new Adresse(), new Adresse('Rue seule', '1', '', '')] as $adresse) {
            $s = self::ok(self::section(['client' => $adresse]));
            self::assertNull($s->debiteur);
            self::assertSame(['', '', '', '', '', '', ''], array_slice(explode("\n", $s->texte), 20, 7));
        }
        self::assertNull(self::ok(self::section(['client_nom' => '']))->debiteur);
    }

    public function testUnClientQueLaNormeRefuseEstOmisSansPerdreLaSection(): void
    {
        $s = self::ok(self::section(['client_nom' => str_repeat('N', 71)]));
        self::assertNull($s->debiteur, 'Nom de plus de 70 caractères : « Payable par » reste vide.');
    }

    public function testUneRueSansNumeroEtUneAdresseSansRue(): void
    {
        $s = self::ok(self::section(['adresse' => new Adresse('Grand-Rue', '', '2800', 'Delémont'), 'client' => new Adresse('', '', '1700', 'Fribourg')]));
        self::assertSame(['Peinture Morel Sàrl', 'Grand-Rue', '2800 Delémont'], $s->creancier);
        self::assertSame(['Famille Exemple', '1700 Fribourg'], $s->debiteur);
        self::assertSame(['Grand-Rue', ''], array_slice(explode("\n", $s->texte), 6, 2));
    }

    public function testCeQuiEmpecheLaSection(): void
    {
        self::assertSame(QrFacture::SANS_IBAN, self::section(['iban' => '']));
        self::assertSame(QrFacture::SANS_ADRESSE, self::section(['adresse' => new Adresse("Rue de l'Exemple", '12', '', '')]));
        self::assertSame(QrFacture::SANS_ADRESSE, self::section(['adresse' => new Adresse()]));
        self::assertSame(QrFacture::SANS_NOM, self::section(['entreprise' => '  ']));
        self::assertSame(QrFacture::RIEN_A_PAYER, self::section(['a_payer' => 0]));
        self::assertSame(QrFacture::RIEN_A_PAYER, self::section(['a_payer' => -500]));
        self::assertSame(QrFacture::REFUSEE, self::section(['entreprise' => str_repeat('E', 71)]), 'Le nom du créancier est obligatoire : on ne l\'omet pas.');
        self::assertSame(QrFacture::REFUSEE, self::section(['iban' => 'DE89370400440532013000']), 'IBAN étranger.');
    }

    public function testLeDessinDuCodeFait46MillimetresAvecLaCroixSuisse(): void
    {
        $svg = self::ok(self::section())->svg;
        self::assertStringStartsWith('<svg xmlns="http://www.w3.org/2000/svg" width="174" height="174" viewBox="0 0 174 174">', $svg, 'dompdf lit le viewBox en pixels : 174 px font 46 mm.');
        self::assertSame(1, substr_count($svg, 'fill="#000"/>'), 'Le carré noir de la croix.');
        self::assertGreaterThan(400, substr_count($svg, '<rect'), 'Les modules du code.');
        self::assertStringStartsWith('data:image/svg+xml;base64,', self::ok(self::section())->svgEnDonnees());
    }

    public function testLeCodeEstAuNiveauDeCorrectionM(): void
    {
        $s = self::ok(self::section());
        $modules = static fn (string $niveau): int => Encoder::encode($s->texte, ErrorCorrectionLevel::forBits(['L' => 1, 'M' => 0, 'Q' => 3, 'H' => 2][$niveau]), 'UTF-8')->getMatrix()->getWidth();
        self::assertNotSame($modules('L'), $modules('M'), 'Témoin : les deux niveaux donnent des tailles différentes pour ce contenu.');
        preg_match('/<rect x="0\.0000" y="0\.0000" width="([\d.]+)"/', $s->svg, $m);
        $largeur = (float) ($m[1] ?? 0) - 0.001;
        self::assertGreaterThan(0, $largeur, 'Le module du coin haut gauche (motif de repérage).');
        self::assertSame($modules('M'), (int) round(QrFacture::COTE / $largeur), 'Taille d\'un module : 174 divisé par le nombre de modules au niveau M.');
    }

    public function testLeCadreVideDePayableParAQuatreCoins(): void
    {
        $svg = QrFacture::cadre(52, 20);
        self::assertStringContainsString('viewBox="0 0 196.5 75.6"', $svg, '52 × 20 mm à 96 dpi.');
        self::assertSame(4, substr_count($svg, '<path'));
    }
}
