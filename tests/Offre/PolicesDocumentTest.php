<?php

declare(strict_types=1);

namespace OffreGen\Tests\Offre;

use FontLib\Font;
use OffreGen\Compte\Marque;
use OffreGen\Offre\DocumentPdf;
use OffreGen\Tests\Outils\Jeu;
use OffreGen\Tests\Outils\Monde;
use OffreGen\Tests\Outils\OffreDEssai;
use PHPUnit\Framework\TestCase;

/**
 * Les polices du document (modèles du bâtiment, lot 1) : des fichiers complets, une face par graisse employée, des
 * chiffres de même largeur dans les colonnes de montants, et les mêmes faces à l'écran.
 */
final class PolicesDocumentTest extends TestCase
{
    private const DOSSIER = __DIR__ . '/../../public/fonts';

    /** Les caractères des noms et des offres d'ici : français, allemand, portugais, albanais, ex-Yougoslavie, polonais, tchèque, turc. */
    private const CARACTERES = ['é', 'œ', 'ü', 'ß', 'ã', 'ë', 'š', 'č', 'ć', 'đ', 'ž', 'ł', 'ř', 'ğ', 'ş', 'ı', '’', '−', '×', '²', '€'];

    /** @return list<string> chaque fichier TTF du document, sans extension */
    private static function fichiers(): array
    {
        return array_merge(...array_map(array_keys(...), array_values(DocumentPdf::FACES)));
    }

    public function testChaqueFaceDeclareeAUnFichier(): void
    {
        $css = Monde::conteneur()->documentPdf()->policesCss();
        foreach (DocumentPdf::FACES as $famille => $faces) {
            foreach ($faces as $fichier => [$graisse, $style]) {
                self::assertFileExists(self::DOSSIER . "/$fichier.ttf");
                self::assertMatchesRegularExpression(
                    "#@font-face \{ font-family: '$famille'; font-style: $style; font-weight: $graisse; src: url\('file://[^']+/$fichier\.ttf'\) format\('truetype'\); \}#",
                    $css,
                );
            }
        }
        self::assertCount(23, self::fichiers());
    }

    public function testLesPolicesCouvrentLesNomsDIci(): void
    {
        foreach (self::fichiers() as $fichier) {
            $police = Font::load(self::DOSSIER . "/$fichier.ttf");
            self::assertNotNull($police, $fichier);
            $police->parse();
            $carte = $police->getUnicodeCharMap();
            $manque = array_filter(self::CARACTERES, static fn (string $c): bool => !isset($carte[mb_ord($c)]));
            self::assertSame([], array_values($manque), "$fichier : caractères absents");
        }
    }

    public function testLesChiffresOntLaMemeLargeur(): void
    {
        // Les familles des colonnes de montants. Archivo et Barlow Condensed ne servent qu'aux titres.
        foreach (['Roboto', 'IBM Plex Sans', 'IBM Plex Mono', 'Source Serif 4'] as $famille) {
            foreach (array_keys(DocumentPdf::FACES[$famille]) as $fichier) {
                $police = Font::load(self::DOSSIER . "/$fichier.ttf");
                self::assertNotNull($police);
                $police->parse();
                $carte = $police->getUnicodeCharMap();
                /** @var array<int, array{int, int}> $avances */
                $avances = $police->getData('hmtx');
                $largeurs = array_map(static fn (int $chiffre): int => $avances[$carte[mb_ord((string) $chiffre)]][0], range(0, 9));
                self::assertCount(1, array_unique($largeurs), "$fichier : " . implode(' ', $largeurs));
            }
        }
    }

    public function testLesEmpreintesSontCellesDuReleve(): void
    {
        $releve = (string) file_get_contents(self::DOSSIER . '/SOURCES.md');
        preg_match_all('/^\| `([^`]+)` \| (\d+) \| `([0-9a-f]{64})` \| https:/m', $releve, $lignes, PREG_SET_ORDER);
        self::assertCount(73, $lignes, "23 TTF, 4 licences (celle d'Archivo était déjà au dépôt), 46 WOFF2");
        foreach ($lignes as [, $nom, $octets, $empreinte]) {
            self::assertSame($empreinte, hash_file('sha256', self::DOSSIER . "/$nom"), $nom);
            self::assertSame((int) $octets, filesize(self::DOSSIER . "/$nom"), $nom);
        }
        foreach (self::fichiers() as $fichier) {
            self::assertStringContainsString("| `$fichier.ttf` |", $releve);
        }
    }

    public function testLePdfNEmploieQueLesPolicesDuDocument(): void
    {
        $jeu = new Jeu();
        $c = Monde::conteneur();
        // Un cache de polices neuf : celui du poste garde les polices d'essais passés et masquerait une face oubliée.
        $cache = sys_get_temp_dir() . '/offregen-polices-' . bin2hex(random_bytes(4));
        $pdf = (new DocumentPdf($c->racine() . '/templates', $cache, $c->racine() . '/public/fonts'))
            ->rendre(OffreDEssai::html($c, $jeu->a, OffreDEssai::offre(), OffreDEssai::lignes(8), new Marque('lateral', '#B8470F')));
        foreach (glob("$cache/*/*") ?: [] as $fichier) {
            unlink($fichier);
        }
        array_map(rmdir(...), glob("$cache/*") ?: []);
        rmdir($cache);
        preg_match_all('#/BaseFont /(?:[A-Z]{6}\+)?([A-Za-z0-9]+)#', $pdf, $polices);
        $polices = array_values(array_unique($polices[1]));
        self::assertNotSame([], $polices);
        foreach ($polices as $police) {
            self::assertMatchesRegularExpression('/^(Roboto|Archivo)/', $police, 'ni police de repli (DejaVu), ni Inter : ' . implode(', ', $polices));
        }
    }

    /**
     * dompdf donne à une ligne la hauteur « interligne x corps x k », k propre à chaque police (1,1 fois sa hauteur).
     * Les interlignes du PDF (document.css, bloc « Interlignes du PDF ») sont ceux de l'écran divisés par ces k : si un
     * fichier de police change de mesures, les feuilles changent de hauteur, et ce test le dit.
     */
    public function testLInterligneDeDompdfSuitLaHauteurDeChaquePolice(): void
    {
        $k = ['Roboto' => 1.451, 'Archivo' => 1.197, 'Barlow Condensed' => 1.320, 'IBM Plex Sans' => 1.430, 'IBM Plex Mono' => 1.430, 'Source Serif 4' => 1.508];
        $pdf = Monde::conteneur()->documentPdf();
        $css = $pdf->policesCss() . 'body { margin: 0; } div { font-size: 20px; line-height: 1; }';
        $lignes = '';
        foreach (DocumentPdf::FACES as $famille => $faces) {
            foreach ($faces as $fichier => [$graisse, $style]) {
                $lignes .= "<div class=\"$fichier\" style=\"font-family: '$famille'; font-weight: $graisse; font-style: $style\">Hg</div>";
            }
        }
        $blocs = $pdf->blocs("<!doctype html><html><head><meta charset=\"utf-8\"><style>$css</style></head><body>$lignes</body></html>", self::fichiers());
        foreach (DocumentPdf::FACES as $famille => $faces) {
            foreach (array_keys($faces) as $fichier) {
                self::assertCount(1, $blocs[$fichier]);
                // En points : 20 px font 15 pt.
                self::assertEqualsWithDelta(15 * $k[$famille], $blocs[$fichier][0]['bas'] - $blocs[$fichier][0]['haut'], 0.05, $fichier);
            }
        }
    }

    public function testLEcranDeclareLesMemesFaces(): void
    {
        $css = (string) file_get_contents(__DIR__ . '/../../public/css/document-ecran.css');
        foreach (DocumentPdf::FACES as $famille => $faces) {
            foreach ($faces as $fichier => [$graisse, $style]) {
                foreach (['latin', 'latin-ext'] as $sous) {
                    self::assertFileExists(self::DOSSIER . "/$fichier-$sous.woff2");
                    self::assertMatchesRegularExpression(
                        "#@font-face \{ font-family: \"$famille\"; font-style: $style; font-weight: $graisse; font-display: swap; src: url\(\"/fonts/$fichier-$sous\.woff2\"\) format\(\"woff2\"\); unicode-range: U\+#",
                        $css,
                    );
                }
            }
        }
    }
}
