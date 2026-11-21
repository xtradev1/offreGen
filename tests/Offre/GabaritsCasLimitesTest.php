<?php

declare(strict_types=1);

namespace OffreGen\Tests\Offre;

use OffreGen\Compte\Marque;
use OffreGen\Http\Conteneur;
use OffreGen\Tests\Outils\BaseDeTest;
use OffreGen\Tests\Outils\Dossiers;
use OffreGen\Tests\Outils\Jeu;
use OffreGen\Tests\Outils\Monde;
use OffreGen\Tests\Outils\OffreDEssai;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Les douze modèles devant ce qu'un vrai compte leur donne (modèles du bâtiment, lot 9) : un logo haut ou large, une
 * couleur claire, un nom d'entreprise long, un compte et une offre sans aucun champ facultatif, une offre par pièce.
 * Mesures en points ; 1 px de la feuille vaut 0,75 point.
 *
 * @phpstan-import-type Ligne from \OffreGen\Donnees\DepotOffres
 */
final class GabaritsCasLimitesTest extends TestCase
{
    private Jeu $jeu;
    private Dossiers $dossiers;
    private Conteneur $c;

    protected function setUp(): void
    {
        $this->jeu = new Jeu();
        $this->dossiers = new Dossiers();
        $this->c = Monde::conteneur(env: $this->dossiers->env());
        BaseDeTest::pdo()->exec("UPDATE artisan SET entreprise = 'Peinture Morel Sàrl', adresse_rue = 'Rue de l''Exemple', adresse_numero = '12', adresse_npa = '2800',
            adresse_localite = 'Delémont', adresse_pays = 'CH', telephone = '032 000 00 01', courriel_contact = 'info@peinture-morel.example', ide = 'CHE-123.456.789',
            iban = 'CH9300762011623852957', contact_nom = 'Luc Morel' WHERE id = {$this->jeu->a}");
    }

    protected function tearDown(): void
    {
        $this->dossiers->effacer();
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
    private function html(Marque $m, int $lignes, array $offre = [], bool $parPiece = false): string
    {
        return OffreDEssai::html($this->c, $this->jeu->a, OffreDEssai::offre($offre), OffreDEssai::lignes($lignes, $parPiece), $m);
    }

    /** L'offre tient sur une page et l'adresse du client est dans la fenêtre de l'enveloppe. */
    private function assertTientEtAdresse(string $html, string $modele, string $cas): void
    {
        $pdf = $this->c->documentPdf();
        self::assertSame(1, $pdf->pages($html), "$cas : une page");
        $adresse = $pdf->blocs($html, ['doc-adresse'])['doc-adresse'];
        self::assertCount(1, $adresse);
        self::assertEqualsWithDelta(136.5, $adresse[0]['haut'], 6.0, "$cas : hauteur de la fenêtre");
        self::assertEqualsWithDelta($modele === 'lettre' ? 62.25 : 334.5, $adresse[0]['gauche'], 1.0, "$cas : bord gauche de la fenêtre");
    }

    private function logo(string $fixture): string
    {
        $nom = $this->jeu->a . '-' . $fixture;
        copy(dirname(__DIR__) . '/fixtures/images/' . $fixture, $this->c->dossierLogos() . '/' . $nom);
        return $nom;
    }

    /** Un logo carré et un logo cinq fois plus large que haut : entiers, à leurs proportions, dans la place du modèle. */
    #[DataProvider('modeles')]
    public function testAvecUnLogoHautOuLarge(string $modele): void
    {
        // Place du logo, en pixels de la feuille : 250 x 50 ; dans la colonne de Latéral, 170 x 70.
        [$largeurMax, $hauteurMax] = $modele === 'lateral' ? [170.0, 70.0] : [250.0, 50.0];
        foreach (['logo-haut.jpg' => 1.0, 'logo-large.jpg' => 5.0] as $fixture => $proportion) {
            $html = $this->html(new Marque($modele, '#B8470F', $this->logo($fixture)), self::courante($modele));
            $this->assertTientEtAdresse($html, $modele, $fixture);
            $logos = $this->c->documentPdf()->blocs($html, ['doc-logo', 'doc-nom'])['doc-logo'];
            if ($modele === 'enseigne') {
                self::assertSame([], $logos, 'Enseigne écrit le nom, sans logo');
                continue;
            }
            self::assertCount(1, $logos, $fixture);
            $largeur = ($logos[0]['droite'] - $logos[0]['gauche']) / 0.75;
            $hauteur = ($logos[0]['bas'] - $logos[0]['haut']) / 0.75;
            self::assertLessThanOrEqual($largeurMax + 0.5, $largeur, "$fixture : largeur");
            self::assertLessThanOrEqual($hauteurMax + 0.5, $hauteur, "$fixture : hauteur");
            self::assertEqualsWithDelta($proportion, $largeur / $hauteur, $proportion * 0.03, "$fixture : proportions gardées");
            self::assertGreaterThanOrEqual(min($largeurMax, $hauteurMax * $proportion) - 1.0, $largeur, "$fixture : le logo prend la place qu'il peut");
        }
    }

    /** Un jaune pâle tiré d'un logo : le document ne l'emploie jamais tel quel, il l'assombrit jusqu'à rester lisible. */
    #[DataProvider('modeles')]
    public function testCouleurClaire(string $modele): void
    {
        $m = new Marque($modele, '#F5E663');
        $html = $this->html($m, self::courante($modele));
        self::assertStringNotContainsStringIgnoringCase('#F5E663', $html);
        self::assertStringContainsString('color: ' . $m->principale(), $html);
        self::assertGreaterThanOrEqual(4.5, Marque::contraste($m->principale(), '#FFFFFF'));
        $this->assertTientEtAdresse($html, $modele, 'couleur claire');
    }

    #[DataProvider('modeles')]
    public function testUnNomDEntrepriseLong(string $modele): void
    {
        BaseDeTest::pdo()->exec("UPDATE artisan SET entreprise = 'Plâtrerie et Peinture des Trois Vallées SA' WHERE id = {$this->jeu->a}");
        $html = $this->html(new Marque($modele, '#B8470F'), self::courante($modele));
        $this->assertTientEtAdresse($html, $modele, 'nom long');
        foreach ($this->c->documentPdf()->blocs($html, ['doc-nom', 'doc-enseigne', 'doc-coord'])  as $blocs) {
            foreach ($blocs as $b) {
                self::assertLessThanOrEqual(595.3, $b['droite'], 'rien ne sort de la feuille à droite');
                self::assertGreaterThanOrEqual(0.0, $b['gauche']);
            }
        }
    }

    /** Un compte tout juste créé, une offre sans client ni chantier : ni libellé seul, ni séparateur qui traîne. */
    #[DataProvider('modeles')]
    public function testSansChampFacultatif(string $modele): void
    {
        BaseDeTest::pdo()->exec("UPDATE artisan SET adresse = NULL, adresse_rue = NULL, adresse_numero = NULL, adresse_npa = NULL, adresse_localite = NULL, telephone = NULL,
            courriel_contact = NULL, ide = NULL, iban = NULL, contact_nom = NULL WHERE id = {$this->jeu->a}");
        $html = $this->html(new Marque($modele, '#B8470F'), self::courante($modele), ['client_nom' => null, 'client_adresse' => null]);
        $this->assertTientEtAdresse($html, $modele, 'sans champ facultatif');
        $corps = (string) strstr($html, '<body');
        foreach (['Votre contact', 'votre contact', 'Contact</div>', 'Chantier</', 'Chantier&nbsp;:', 'chantier&nbsp;:', 'Non compris', 'Ne sont pas compris', 'Début des travaux', 'Durée', 'IBAN', 'visite', ' ·  · ', ' · <'] as $absent) {
            self::assertStringNotContainsString($absent, $corps, "« $absent » ne doit pas paraître");
        }
        self::assertDoesNotMatchRegularExpression('#<div class="doc-lab">[^<]*</div>\s*</td>#', $corps, 'aucun libellé sans valeur');
        self::assertDoesNotMatchRegularExpression('#<(div|td)[^>]*>\s*·#u', $corps, 'aucune ligne ne commence par un séparateur');
        self::assertStringContainsString('Peinture Morel Sàrl', $corps);
    }

    /** Une offre rangée par pièce, avec ses lignes générales : chaque pièce paraît, « Général » n'a pas de sous-total. */
    #[DataProvider('modeles')]
    public function testOffreParPiece(string $modele): void
    {
        /** @var list<Ligne> $lignes deux lignes générales, puis huit lignes en deux pièces */
        $lignes = array_map(static fn (array $l): array => $l['rang'] <= 2 ? ['piece' => null] + $l : $l, OffreDEssai::lignes(10, true));
        $html = OffreDEssai::html($this->c, $this->jeu->a, OffreDEssai::offre(), $lignes, new Marque($modele, '#B8470F'));
        $corps = (string) strstr($html, '<body');
        foreach (['Général', 'Séjour', 'Cuisine'] as $piece) {
            self::assertStringContainsString($piece, $corps);
        }
        self::assertStringNotContainsString('Total général', $corps);
        // « Général » ne porte pas non plus de montant sur sa ligne de titre ni dans la marge : il se lirait comme le total
        // de l'offre (sous-chantier 28). Récapitulatif fait exception : sa liste de postes doit tomber juste.
        if ($modele !== 'recap') {
            self::assertDoesNotMatchRegularExpression('#Général</(td|b)>(<br>|<td class="n[^"]*">)[0-9]#u', $corps);
        }
        self::assertMatchesRegularExpression('#Séjour#u', $corps);
        $pdf = $this->c->documentPdf();
        $blocs = $pdf->blocs($html, ['doc-fin', 'doc-pied', 'doc-bas']);
        self::assertCount(1, $blocs['doc-fin']);
        $fin = $blocs['doc-fin'][0];
        $bas = array_column(array_filter([...$blocs['doc-pied'], ...$blocs['doc-bas']], static fn (array $b): bool => $b['page'] === $fin['page']), 'haut');
        if ($bas === []) {
            self::fail('ni pied ni bande sur la page du bloc de fin');
        }
        self::assertLessThanOrEqual(min($bas) - 6.0, $fin['bas']);
    }
}
