<?php

declare(strict_types=1);

namespace OffreGen\Tests\Offre;

use OffreGen\Compte\Activite;
use OffreGen\Compte\Marque;
use OffreGen\Http\Conteneur;
use OffreGen\Tests\Outils\BaseDeTest;
use OffreGen\Tests\Outils\FactureDEssai;
use OffreGen\Tests\Outils\Jeu;
use OffreGen\Tests\Outils\Monde;
use OffreGen\Tests\Outils\OffreDEssai;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Les douze modèles prennent leurs mots dans l'activité du compte (docs/plans/2026-11-22_familles-activite, SPEC point 5
 * et critères de succès) : le document d'une prestation ne parle ni de chantier, ni de travaux, ni de visite.
 */
final class GabaritsActiviteTest extends TestCase
{
    /** Tout ce que l'en-tête de l'offre peut porter, rempli : aucun libellé ne se cache derrière un champ vide. */
    private const CHAMPS = ['chantier_adresse' => 'Rue du Test 9, 2900 Porrentruy', 'chantier_contact' => 'Mme Essai, 079 000 00 00',
        'chantier_acces' => 'Badge à la réception', 'debut_travaux' => 'semaine 3, 2027', 'duree_travaux' => '6 jours ouvrables',
        'non_compris' => "Licences\nHébergement", 'visite_le' => '2026-11-12'];

    private Jeu $jeu;
    private Conteneur $c;

    protected function setUp(): void
    {
        $this->jeu = new Jeu();
        $this->c = Monde::conteneur();
    }

    /** @return array<string, array{string}> */
    public static function modeles(): array
    {
        return array_combine(array_keys(Marque::MODELES), array_map(static fn (string $cle): array => [$cle], array_keys(Marque::MODELES)));
    }

    /** Le texte que lit le client : sans la feuille de style ni les balises (la classe « m-chantier » n'est pas un mot du document). */
    private function texte(string $modele, string $activite): string
    {
        BaseDeTest::pdo()->prepare('UPDATE artisan SET activite = ? WHERE id = ?')->execute([$activite, $this->jeu->a]);
        $html = OffreDEssai::html($this->c, $this->jeu->a, OffreDEssai::offre(self::CHAMPS), OffreDEssai::lignes(8), new Marque($modele, '#1D4E89'));
        $sansStyle = (string) preg_replace('~<style\b.*?</style>~su', '', $html);
        return html_entity_decode(strip_tags($sansStyle), ENT_QUOTES | ENT_HTML5);
    }

    #[DataProvider('modeles')]
    public function testUnePrestationSeLitSansChantierNiTravauxNiVisite(string $modele): void
    {
        $texte = $this->texte($modele, 'prestations');
        self::assertDoesNotMatchRegularExpression('/chantier|travaux|visite/iu', $texte, $modele);
        self::assertStringContainsString('dès la fin de la prestation', $texte, "$modele : la phrase de paiement suit l'activité");
        self::assertStringContainsString('semaine 3, 2027', $texte, "$modele : le début reste sur le document");
    }

    #[DataProvider('modeles')]
    public function testLeBatimentGardeSesMots(string $modele): void
    {
        $texte = $this->texte($modele, 'batiment');
        self::assertStringContainsString('dès la fin des travaux', $texte, $modele);
        self::assertStringContainsString('Début des travaux', $texte, $modele);
        self::assertMatchesRegularExpression('/chantier/iu', $texte, "$modele : l'adresse du chantier garde son nom");
    }

    #[DataProvider('modeles')]
    public function testLExterieurParleDeLieuEtGardeLesTravaux(string $modele): void
    {
        $texte = $this->texte($modele, 'exterieur');
        self::assertStringContainsString('Début des travaux', $texte, $modele);
        self::assertStringContainsString('dès la fin des travaux', $texte, $modele);
        self::assertDoesNotMatchRegularExpression('/chantier/iu', $texte, "$modele : « Lieu » à la place de « Chantier »");
    }

    public function testLaLettreDUnePrestationParleDePrestationsEtDEchange(): void
    {
        $texte = $this->texte('lettre', 'prestations');
        self::assertStringContainsString('Nous vous remettons notre offre pour les prestations suivantes.', $texte);
        self::assertStringContainsString('Échange du 12 novembre 2026.', $texte);
        self::assertStringContainsString("Lieu\u{00A0}: Rue du Test 9, 2900 Porrentruy.", $texte);

        $texte = $this->texte('lettre', 'batiment');
        self::assertStringContainsString('Nous vous remettons notre offre pour les travaux suivants.', $texte);
        self::assertStringContainsString('Visite du 12 novembre 2026.', $texte);
        self::assertStringContainsString("Chantier\u{00A0}: Rue du Test 9, 2900 Porrentruy.", $texte);
    }

    /** Revue du 2026-11-22 : une facture aussi. Sa vue ne porte pas l'activité, le gabarit prend celle qu'on lui donne. */
    #[DataProvider('modeles')]
    public function testLaFactureDUnePrestationNeParlePasDeTravaux(string $modele): void
    {
        $html = FactureDEssai::html($this->c, $this->jeu, new Marque($modele, '#1D4E89'), 8, null,
            ['activite' => Activite::Prestations, 'mots' => Activite::Prestations->mots()]);
        $texte = html_entity_decode(strip_tags((string) preg_replace('~<style\b.*?</style>~su', '', $html)), ENT_QUOTES | ENT_HTML5);
        self::assertDoesNotMatchRegularExpression('/chantier|travaux|visite/iu', $texte, $modele);
        if ($modele === 'lettre') {
            self::assertStringContainsString('Nous vous adressons notre facture pour les prestations suivantes.', $texte);
        }
    }

    public function testLeRecapitulatifDetailleLesPrestations(): void
    {
        self::assertStringContainsString('Détail des prestations', $this->texte('recap', 'prestations'));
        self::assertStringContainsString('Détail des travaux', $this->texte('recap', 'batiment'));
    }
}
