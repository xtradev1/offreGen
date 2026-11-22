<?php

declare(strict_types=1);

namespace OffreGen\Tests\Offre;

use OffreGen\Compte\Marque;
use OffreGen\Donnees\Portee;
use OffreGen\Http\Conteneur;
use OffreGen\Http\Controleur\OffreControleur;
use OffreGen\Tests\Outils\BaseDeTest;
use OffreGen\Tests\Outils\Jeu;
use OffreGen\Tests\Outils\Monde;
use OffreGen\Tests\Outils\OffreDEssai;
use OffreGen\Tests\Outils\TextePdf;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Options et variantes dans les douze modèles (docs/plans/2026-11-22_options-variantes/PLAN.md, lot 5) : la variante sous
 * sa ligne avec l'écart (O2), le bloc « Options » sous le total avec le total si le client prend tout (O1, point 4),
 * puis, après le choix du client, la variante à la place de sa ligne et l'option dans les lignes (point 2).
 *
 * L'offre : les six premiers postes d'OffreDEssai (3'094.00), une variante de « Peinture des murs, 2 couches »
 * (42 m² à 34.00 au lieu de 28.00 : +252.00) et deux options (portail 420.00, radiateur 85.00).
 *
 * @phpstan-import-type Ligne from \OffreGen\Donnees\DepotOffres
 */
final class GabaritsOptionsTest extends TestCase
{
    private Jeu $jeu;
    private Conteneur $c;

    protected function setUp(): void
    {
        $this->jeu = new Jeu();
        $this->c = Monde::conteneur();
        BaseDeTest::pdo()->exec("UPDATE artisan SET entreprise = 'Peinture Morel Sàrl', adresse_rue = 'Rue de l''Exemple', adresse_numero = '12', adresse_npa = '2800',
            adresse_localite = 'Delémont', adresse_pays = 'CH', telephone = '032 000 00 01', courriel_contact = 'info@peinture-morel.example', ide = 'CHE-123.456.789',
            iban = 'CH9300762011623852957', contact_nom = 'Luc Morel' WHERE id = {$this->jeu->a}");
    }

    /** @return array<string, array{string}> */
    public static function modeles(): array
    {
        return array_combine(array_keys(Marque::MODELES), array_map(static fn (string $cle): array => [$cle], array_keys(Marque::MODELES)));
    }

    /** @return Ligne */
    private static function ligne(int $id, string $designation, int $q, string $u, int $prix, string $nature, ?int $remplace, int $retenue): array
    {
        return ['id' => $id, 'offre_id' => 0, 'rang' => $id, 'designation' => $designation, 'quantite_milliemes' => $q, 'unite' => $u, 'prix_centimes' => $prix,
            'offerte' => 0, 'a_verifier' => 0, 'entendu' => null, 'produit_id' => null, 'categorie' => 'Séjour', 'tva_pour_dix_mille' => null, 'tva_ligne' => null,
            'prix_dicte_centimes' => null, 'prix_catalogue' => null, 'piece' => null, 'nature' => $nature, 'remplace_id' => $remplace, 'retenue' => $retenue];
    }

    /** @return list<Ligne> */
    private static function lignes(bool $choix, bool $tout = false): array
    {
        return [...OffreDEssai::lignes(6),
            self::ligne(7, 'Peinture lessivable, 2 couches', 42000, 'm²', 3400, 'variante', 3, $choix ? 1 : 0),
            self::ligne(8, 'Laquage du portail', 1000, 'pce', 42000, 'option', null, $choix ? 1 : 0),
            self::ligne(9, 'Peinture du radiateur de la cave', 1000, 'pce', 8500, 'option', null, $tout ? 1 : 0)];
    }

    /** @return array<string, mixed> */
    private function vue(string $modele, bool $choix): array
    {
        return (new OffreControleur($this->c))->vueDocument(new Portee($this->jeu->a), OffreDEssai::offre(), self::lignes($choix), new Marque($modele, '#B8470F'));
    }

    /**
     * Le texte du PDF sans aucune espace : l'extraction colle les cellules d'une rangée. Et aucune police de repli : une
     * face manquante (italique d'IBM Plex) rendait la ligne illisible.
     */
    private function texte(string $modele, bool $choix): string
    {
        $pdf = OffreControleur::rendrePdf($this->c, $this->vue($modele, $choix));
        preg_match_all('#/BaseFont /(?:[A-Z]{6}\+)?([A-Za-z0-9-]+)#', $pdf, $polices);
        self::assertSame([], preg_grep('/DejaVu/', $polices[1]), "$modele : aucune police de repli (" . implode(', ', $polices[1]) . ')');
        return (string) preg_replace('/[\s\x{00A0}\x{202F}]+/u', '', implode('', TextePdf::pages($pdf)));
    }

    #[DataProvider('modeles')]
    public function testLaVarianteSuitSaLigneEtLesOptionsSuiventLeTotal(string $modele): void
    {
        $t = $this->texte($modele, false);
        $ligne = mb_strpos($t, 'Peinturedesmurs,2couches');
        $variante = mb_strpos($t, 'Variante:Peinturelessivable,2couches');
        $options = mb_stripos($t, 'OptionsHorsdutotalci-dessus');
        self::assertNotFalse($ligne, $modele);
        self::assertNotFalse($variante, "$modele : la variante, avec son mot");
        self::assertNotFalse($options, "$modele : le bloc des options");
        self::assertTrue($ligne < $variante, "$modele : la variante sous sa ligne");
        self::assertMatchesRegularExpression('/Variante:Peinturelessivable,2couches42m²34\.00\+252\.00/u', $t, "$modele : prix unitaire et écart");
        self::assertNotFalse(mb_strpos($t, 'Laquageduportail', $options), "$modele : l'option dans son bloc");
        // 3'094.00 + 420.00 + 85.00 = 3'599.00 ; TVA 291.519 → 291.52 ; 3'890.52 → 3'890.50.
        self::assertMatchesRegularExpression('/Totalavectouteslesoptions(TTC)?CHF3.890\.50/iu', $t, "$modele (Moderne met ses totaux en majuscules)");
    }

    #[DataProvider('modeles')]
    public function testApresLeChoixLaVariantePrendLaPlaceEtLOptionEntre(string $modele): void
    {
        $t = $this->texte($modele, true);
        self::assertStringNotContainsString('Peinturedesmurs,2couches', $t, "$modele : la ligne remplacée a disparu");
        self::assertStringNotContainsString('Variante', $t, $modele);
        self::assertStringContainsString('Peinturelessivable,2couches', $t, $modele);
        $bloc = mb_stripos($t, 'OptionsHorsdutotal');
        self::assertNotFalse($bloc, $modele);
        self::assertLessThan($bloc, mb_strpos($t, 'Laquageduportail'), "$modele : l'option retenue est dans les lignes, avant le bloc");
        self::assertGreaterThan($bloc, mb_strpos($t, 'Peintureduradiateurdelacave'), "$modele : l'option libre reste dans le bloc");
    }

    #[DataProvider('modeles')]
    public function testLeBlocDesOptionsNeTouchePasLePied(string $modele): void
    {
        $blocs = $this->c->documentPdf()->blocs(OffreControleur::htmlPdf($this->c, $this->vue($modele, false)), ['doc-options', 'doc-fin', 'doc-pied', 'doc-bas']);
        self::assertCount(1, $blocs['doc-options'], "$modele : un bloc, jamais coupé");
        foreach (['doc-options', 'doc-fin'] as $classe) {
            $b = $blocs[$classe][0];
            $bas = array_column(array_filter([...$blocs['doc-pied'], ...$blocs['doc-bas']], static fn (array $x): bool => $x['page'] === $b['page']), 'haut');
            if ($bas !== []) {
                self::assertLessThanOrEqual(min($bas) - 6.0, $b['bas'], "$modele : $classe finit au-dessus du pied, page {$b['page']}");
            }
        }
    }

    public function testRienQuandToutEstRetenu(): void
    {
        $lignes = self::lignes(true, true);
        $vue = (new OffreControleur($this->c))->vueDocument(new Portee($this->jeu->a), OffreDEssai::offre(), $lignes, new Marque('sobre', '#B8470F'));
        $t = implode(' ', TextePdf::pages(OffreControleur::rendrePdf($this->c, $vue)));
        self::assertStringNotContainsString('Options', $t);
        self::assertStringNotContainsString('Total avec toutes les options', $t);
    }
}
