<?php

declare(strict_types=1);

namespace OffreGen\Tests\Offre;

use OffreGen\Compte\Marque;
use OffreGen\Donnees\Portee;
use OffreGen\Http\Conteneur;
use OffreGen\Http\Controleur\OffreControleur;
use OffreGen\Offre\DocumentPdf;
use OffreGen\Tests\Outils\BaseDeTest;
use OffreGen\Tests\Outils\Jeu;
use OffreGen\Tests\Outils\Monde;
use OffreGen\Tests\Outils\OffreDEssai;
use OffreGen\Tests\Outils\TextePdf;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Les modèles d'offre dans le PDF (chantier « Modèles d'offre du bâtiment », 2026-11-20) : dompdf donne la page et
 * la position de chaque bloc, les tests refusent un bloc mal placé sans ouvrir le PDF. Mesures en points, depuis le
 * coin haut gauche de la page (A4 : 595 x 842).
 */
final class GabaritsPdfTest extends TestCase
{
    /** Ce que l'artisan peut dire du chantier, rempli : le cas le plus chargé d'une offre courante. */
    private const CHANTIER = ['chantier_adresse' => 'Rue du Test 9, 2900 Porrentruy, 1er étage', 'chantier_contact' => 'Mme Essai, 079 000 00 00',
        'chantier_acces' => 'Clé chez la concierge, place de parc dans la cour', 'debut_travaux' => 'semaine 3, 2027', 'duree_travaux' => '6 jours ouvrables',
        'non_compris' => "Peinture de finition\nInstallations électriques dans les cloisons", 'visite_le' => '2026-11-12'];

    private Jeu $jeu;
    private Conteneur $c;

    protected function setUp(): void
    {
        $this->jeu = new Jeu();
        $this->c = Monde::conteneur();
        // Un compte réglé comme celui d'un artisan : adresse, téléphone, courriel, IDE, IBAN, contact.
        BaseDeTest::pdo()->exec("UPDATE artisan SET entreprise = 'Peinture Morel Sàrl', adresse_rue = 'Rue de l''Exemple', adresse_numero = '12', adresse_npa = '2800',
            adresse_localite = 'Delémont', adresse_pays = 'CH', telephone = '032 000 00 01', courriel_contact = 'info@peinture-morel.example', ide = 'CHE-123.456.789',
            iban = 'CH9300762011623852957', contact_nom = 'Luc Morel' WHERE id = {$this->jeu->a}");
    }

    /** @return array<string, array{string}> */
    public static function modeles(): array
    {
        return array_combine(array_keys(Marque::MODELES), array_map(static fn (string $cle): array => [$cle], array_keys(Marque::MODELES)));
    }

    private static function courante(string $modele): int
    {
        return $modele === 'lettre' ? 6 : 8;
    }

    /** @param array<string, mixed> $offre */
    private function html(string $modele, int $lignes, array $offre = self::CHANTIER, bool $parPiece = false): string
    {
        return OffreDEssai::html($this->c, $this->jeu->a, OffreDEssai::offre($offre), OffreDEssai::lignes($lignes, $parPiece), new Marque($modele, '#B8470F'));
    }

    /**
     * Le haut de ce qui se répète au bas de chaque page (pied, ou bande de Bandeau), sur la page donnée.
     *
     * @param array<string, list<array{page: int, haut: float, bas: float, gauche: float, droite: float}>> $blocs
     */
    private static function hautDuBas(array $blocs, int $page): float
    {
        $hauts = array_column(array_filter([...$blocs['doc-pied'], ...$blocs['doc-bas']], static fn (array $b): bool => $b['page'] === $page), 'haut');
        if ($hauts === []) {
            self::fail("ni pied ni bande en page $page");
        }
        return min($hauts);
    }

    /**
     * Huit lignes en deux postes, le chantier rempli. Lettre dit ses conditions en phrases et porte une formule de
     * politesse : elle est jugée sur six lignes, comme sur la maquette.
     */
    #[DataProvider('modeles')]
    public function testUneOffreCouranteTientSurUnePage(string $modele): void
    {
        self::assertSame(1, $this->c->documentPdf()->pages($this->html($modele, self::courante($modele))));
    }

    #[DataProvider('modeles')]
    public function testLeBlocDeFinResteAuDessusDuPied(string $modele): void
    {
        $blocs = $this->c->documentPdf()->blocs($this->html($modele, self::courante($modele)), ['doc-fin', 'doc-pied', 'doc-bas']);
        self::assertCount(1, $blocs['doc-fin']);
        self::assertLessThanOrEqual(self::hautDuBas($blocs, 1) - 6.0, $blocs['doc-fin'][0]['bas'], 'conditions et signatures finissent 6 points au moins au-dessus du pied');
        self::assertGreaterThan(790.0, self::hautDuBas($blocs, 1), 'le pied est dans la marge basse de la page');
    }

    /**
     * C6 (Xavier, 2026-11-19) : l'adresse du client à la même place dans tous les modèles, celle de la fenêtre d'une
     * enveloppe C5 : 118 mm du bord gauche (334,5 points) et 48 mm du haut (136,5 points), à 6 points près en hauteur.
     * Lettre vise la fenêtre de gauche : 22 mm du bord (62,25 points), même hauteur.
     */
    #[DataProvider('modeles')]
    public function testLAdresseEstDansLaFenetre(string $modele): void
    {
        foreach ([8 => self::CHANTIER, 3 => []] as $lignes => $offre) {
            $adresse = $this->c->documentPdf()->blocs($this->html($modele, $lignes, $offre), ['doc-adresse'])['doc-adresse'];
            self::assertCount(1, $adresse);
            self::assertSame(1, $adresse[0]['page']);
            self::assertEqualsWithDelta(136.5, $adresse[0]['haut'], 6.0, 'hauteur de la fenêtre');
            self::assertEqualsWithDelta($modele === 'lettre' ? 62.25 : 334.5, $adresse[0]['gauche'], 1.0, 'bord gauche de la fenêtre');
        }
        // Une entreprise sans adresse, sans téléphone ni courriel : l'adresse du client ne monte pas.
        BaseDeTest::pdo()->exec("UPDATE artisan SET adresse_rue = NULL, adresse_numero = NULL, adresse_npa = NULL, adresse_localite = NULL, telephone = NULL, courriel_contact = NULL WHERE id = {$this->jeu->a}");
        $adresse = $this->c->documentPdf()->blocs($this->html($modele, 3, []), ['doc-adresse'])['doc-adresse'];
        self::assertEqualsWithDelta(136.5, $adresse[0]['haut'], 6.0, 'sans coordonnées');
    }

    #[DataProvider('modeles')]
    public function testQuaranteLignesSansBlocCoupe(string $modele): void
    {
        $html = $this->html($modele, 40);
        $pdf = $this->c->documentPdf();
        $pages = $pdf->pages($html);
        self::assertGreaterThanOrEqual(2, $pages, 'quarante lignes ne tiennent pas sur une page');
        $blocs = $pdf->blocs($html, ['doc-totaux', 'doc-bloc-tot', 'doc-fin', 'doc-pied', 'doc-bas']);
        // Récapitulatif pose ses totaux dans un bloc à part, à côté de la liste des postes.
        self::assertCount(1, [...$blocs['doc-totaux'], ...$blocs['doc-bloc-tot']], 'un tableau coupé par un saut de page sort en deux morceaux');
        self::assertCount(1, $blocs['doc-fin']);
        $fin = $blocs['doc-fin'][0];
        self::assertLessThanOrEqual(self::hautDuBas($blocs, $fin['page']) - 6.0, $fin['bas']);
        foreach (range(1, $pages) as $page) {
            self::hautDuBas($blocs, $page);
        }
    }

    /** Une graisse ou un italique sans face retombe sur DejaVu Sans ou sur Helvetica : aucune des deux dans le PDF. */
    #[DataProvider('modeles')]
    public function testLePdfNEmploieQueLesPolicesDuDocument(string $modele): void
    {
        $pdf = $this->c->documentPdf()->rendre($this->html($modele, 8, self::CHANTIER, true));
        preg_match_all('#/BaseFont /(?:[A-Z]{6}\+)?([A-Za-z0-9]+)#', $pdf, $trouvees);
        $polices = array_values(array_unique($trouvees[1]));
        self::assertNotSame([], $polices);
        $permises = implode('|', array_map(static fn (string $f): string => str_replace(' ', '', $f), array_keys(DocumentPdf::FACES)));
        foreach ($polices as $police) {
            self::assertMatchesRegularExpression("/^($permises)/", $police, implode(', ', $polices));
        }
    }

    public function testLateralRepeteSaColonneSurChaquePage(): void
    {
        $html = $this->html('lateral', 40);
        $pdf = $this->c->documentPdf();
        $pages = $pdf->pages($html);
        $colonnes = $pdf->blocs($html, ['doc-colonne'])['doc-colonne'];
        self::assertSame(range(1, $pages), array_column($colonnes, 'page'), 'une colonne par page');
        foreach ($colonnes as $colonne) {
            self::assertEqualsWithDelta(0.0, $colonne['haut'], 0.5, 'la colonne part du haut de la feuille');
            self::assertGreaterThan(830.0, $colonne['bas'], 'et descend au bas de la feuille');
        }
    }

    public function testChaquePagePorteSonNumero(): void
    {
        $vue = (new OffreControleur($this->c))->vueDocument(new Portee($this->jeu->a), OffreDEssai::offre(), OffreDEssai::lignes(40), new Marque('lateral', '#B8470F'));
        $pages = TextePdf::pages(OffreControleur::rendrePdf($this->c, $vue));
        $total = count($pages);
        self::assertGreaterThanOrEqual(2, $total, 'quarante lignes ne tiennent pas sur une page');
        foreach ($pages as $i => $texte) {
            self::assertStringContainsString('Page ' . ($i + 1) . " / $total", $texte);
        }
        // Une page : un seul rendu suffit, le total est juste d'emblée.
        $courte = (new OffreControleur($this->c))->vueDocument(new Portee($this->jeu->a), OffreDEssai::offre(), OffreDEssai::lignes(4), new Marque('lateral', '#B8470F'));
        self::assertStringContainsString('Page 1 / 1', TextePdf::pages(OffreControleur::rendrePdf($this->c, $courte))[0]);
    }

    /** Enseigne écrit le nom officiel de l'entreprise en grand (Xavier, 2026-11-20) : un nom long se réduit et tient sur une ligne. */
    public function testEnseigneReduitUnNomLong(): void
    {
        $pdf = $this->c->documentPdf();
        self::assertStringContainsString('class="doc-enseigne c-texte t46"', $this->html('enseigne', 4), 'Peinture Morel Sàrl : 19 caractères');
        BaseDeTest::pdo()->exec("UPDATE artisan SET entreprise = 'Plâtrerie et Peinture des Trois Vallées SA' WHERE id = {$this->jeu->a}");
        $html = $this->html('enseigne', 4);
        self::assertStringContainsString('class="doc-enseigne c-texte t28"', $html);
        $nom = $pdf->blocs($html, ['doc-enseigne'])['doc-enseigne'];
        self::assertCount(1, $nom);
        self::assertLessThan(30.0, $nom[0]['bas'] - $nom[0]['haut'], 'une seule ligne de 28 px');
        self::assertLessThanOrEqual(547.5, $nom[0]['droite'], 'dans la marge de droite');
    }

    /** Marge se dessine sans cellule fusionnée : dompdf décale les colonnes quand une fusion passe d'une page à l'autre (essai du 2026-11-20). */
    public function testMargeGardeSesColonnesEnPageDeux(): void
    {
        $html = $this->html('marge', 40);
        self::assertGreaterThanOrEqual(2, $this->c->documentPdf()->pages($html));
        self::assertStringNotContainsString('rowspan', $html);
        self::assertSame(6, substr_count($html, '<td class="doc-po"><b class="c-texte">'), 'le nom de chaque poste, une fois');
        self::assertSame(40, substr_count($html, '<td class="doc-po">'), 'une cellule de marge par ligne');
    }

    /** L2 (Xavier, 2026-11-20) : la phrase de l'artisan, puis la visite et le chantier, ajoutés par l'application. */
    public function testLettreSansVisiteNeCiteAucuneDate(): void
    {
        self::assertStringContainsString('Nous vous remettons notre offre pour les travaux suivants. Visite du 12 novembre 2026. Chantier&nbsp;: Rue du Test 9', $this->html('lettre', 4));
        $sans = (string) strstr($this->html('lettre', 4, []), '<body');
        self::assertStringNotContainsString('visite', $sans);
        self::assertStringContainsString('Nous vous remettons notre offre pour les travaux suivants.', $sans);
        self::assertStringContainsString('Madame, Monsieur,', $sans);
    }

    public function testRecapitulatifListeChaquePosteAvantLeDetail(): void
    {
        $html = $this->html('recap', 16);
        $recap = strpos($html, '<table class="doc-rec">');
        $detail = strpos($html, 'Détail des travaux');
        self::assertNotFalse($recap);
        self::assertNotFalse($detail);
        self::assertLessThan($detail, $recap);
        $tableau = substr($html, $recap, $detail - $recap);
        foreach (['A' => 'Séjour', 'B' => 'Cuisine', 'C' => 'Chambre parentale'] as $lettre => $poste) {
            self::assertMatchesRegularExpression("#<td class=\"c-fond\">$lettre</td></tr></table></td><td>$poste</td><td class=\"n\">[0-9’'.]+</td>#u", $tableau);
            self::assertStringContainsString("$lettre&nbsp;·&nbsp;$poste</td>", substr($html, $detail), 'la lettre revient dans le détail');
        }
    }

    public function testChantierMontreSaFicheEtSEnPasseSansChantier(): void
    {
        $html = $this->html('chantier', 4);
        foreach (['Adresse</td><td>Rue du Test 9', 'Contact sur place</td><td>Mme Essai', 'Accès</td><td>Clé chez la concierge', 'Début des travaux</td><td>semaine 3, 2027', 'Durée estimée</td><td>6 jours ouvrables'] as $attendu) {
            self::assertStringContainsString($attendu, $html);
        }
        $sans = $this->html('chantier', 4, []);
        self::assertStringNotContainsString('Contact sur place', $sans);
        self::assertStringContainsString('<th colspan="2" class="c-fond">Références</th>', $sans, 'la fiche porte alors les références du document');
    }

    /** Épuré n'efface plus les pièces (maquette du 2026-11-19) : chaque pièce garde son titre et son total. */
    public function testEpureGardeLesPieces(): void
    {
        $html = $this->html('epure', 20, [], true);
        foreach (['Séjour', 'Cuisine', 'Chambre parentale'] as $piece) {
            self::assertStringContainsString(">$piece</td>", $html);
        }
    }
}
