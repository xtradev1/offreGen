<?php

declare(strict_types=1);

namespace OffreGen\Tests\Offre;

use OffreGen\Compte\Marque;
use OffreGen\Compte\PhrasesLettre;
use OffreGen\Http\Conteneur;
use OffreGen\Tests\Outils\BaseDeTest;
use OffreGen\Tests\Outils\FactureDEssai;
use OffreGen\Tests\Outils\Jeu;
use OffreGen\Tests\Outils\Monde;
use OffreGen\Tests\Outils\OffreDEssai;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Les phrases réécrites par l'artisan, dans les douze modèles (Xavier, 2026-11-23, M4 B : « peu importe quel design on
 * prend, quel modèle on prend, on doit pouvoir avoir les mêmes fonctionnalités »).
 */
final class PhrasesModelesTest extends TestCase
{
    private const CHANTIER = ['chantier_adresse' => 'Rue du Test 9, 2900 Porrentruy', 'debut_travaux' => 'semaine 3, 2027', 'duree_travaux' => '6 jours ouvrables',
        'non_compris' => "Peinture de finition\nInstallations électriques", 'visite_le' => '2026-11-12'];

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

    /** @param array<string, string> $phrases */
    private function regler(array $phrases, bool $partout = false): void
    {
        $json = BaseDeTest::pdo()->quote((string) json_encode($phrases, JSON_UNESCAPED_UNICODE));
        BaseDeTest::pdo()->exec("UPDATE artisan SET phrases_document = $json, lettre_partout = " . ($partout ? 1 : 0) . " WHERE id = {$this->jeu->a}");
    }

    private function offre(string $modele): string
    {
        return OffreDEssai::html($this->c, $this->jeu->a, OffreDEssai::offre(self::CHANTIER), OffreDEssai::lignes(3), new Marque($modele, '#1D4E89'));
    }

    #[DataProvider('modeles')]
    public function testLesConditionsReecritesParaissentDansChaqueModele(string $modele): void
    {
        $this->regler(['paiement' => 'Règlement [paiement], par virement.', 'validite' => 'Prix tenus jusqu\'au [date] seulement.', 'duree' => 'Comptez [durée].']);
        $html = $this->offre($modele);
        self::assertMatchesRegularExpression('#Règlement à \d+ jours[^<]*, par virement\.#u', $html);
        self::assertMatchesRegularExpression("#Prix tenus jusqu'au \d\d\.\d\d\.\d{4} seulement\.#", $html);
        self::assertStringNotContainsString('Paiement&nbsp;:', $html);
        self::assertStringNotContainsString('Offre valable jusqu', $html, 'la phrase de validité réécrite remplace celle de l\'application');
        if ($modele !== 'chantier') {
            self::assertStringContainsString('Comptez 6 jours ouvrables.', $html);
        } else {
            self::assertStringContainsString('Durée estimée', $html, 'Chantier dit la durée dans sa fiche, avec son titre');
        }
    }

    #[DataProvider('modeles')]
    public function testLaFormuleDAppelLeDebutEtLaFinPartoutAuChoix(string $modele): void
    {
        $sans = $this->offre($modele);
        if ($modele !== 'lettre') {
            self::assertStringNotContainsString('Madame, Monsieur,', $sans, 'par défaut, les autres modèles ne changent pas');
            self::assertStringNotContainsString('Visite du', $sans);
        }
        $this->regler(['visite' => 'Nous sommes passés le [date].'], true);
        $html = $this->offre($modele);
        self::assertStringContainsString('<div class="doc-txt">Madame, Monsieur,</div>', $html);
        self::assertStringContainsString(PhrasesLettre::ACTUELLES['offre_debut'] . ' Nous sommes passés le 12 novembre 2026.', $html);
        self::assertStringContainsString(PhrasesLettre::ACTUELLES['offre_fin'], $html);
    }

    /** Réécrite, « Non compris » quitte sa liste titrée pour les conditions ; dans Lettre, elle reste une phrase. */
    #[DataProvider('modeles')]
    public function testNonComprisReecrit(string $modele): void
    {
        self::assertStringContainsString($modele === 'lettre' ? 'Ne sont pas compris&nbsp;:' : 'Non compris</div>', $this->offre($modele));
        $this->regler(['non_compris' => 'Hors de cette offre : [liste].']);
        $html = $this->offre($modele);
        self::assertStringContainsString('Hors de cette offre&nbsp;: Peinture de finition&nbsp;; Installations électriques.', $html);
        self::assertStringNotContainsString('Non compris</div>', $html);
    }

    #[DataProvider('modeles')]
    public function testLaFactureEcritSesPhrasesReecrites(string $modele): void
    {
        $this->regler(['payable' => 'À régler au plus tard le [échéance] [section de paiement].', 'montants_tva' => 'Tous les montants en francs, TVA comprise.',
            'montants_sans' => 'Tous les montants en francs.', 'merci' => 'Grand merci.']);
        $html = FactureDEssai::html($this->c, $this->jeu, new Marque($modele, '#1D4E89'), 1, 'essai');
        self::assertMatchesRegularExpression('#À régler au plus tard le \d\d\.\d\d\.\d{4} avec la section de paiement (ci-dessous|de la page suivante)\.#u', $html);
        self::assertMatchesRegularExpression('#Tous les montants en francs(, TVA comprise)?\.#', $html);
        self::assertStringNotContainsString('Payable jusqu', $html);
        // Lettre remercie dans sa phrase de fin : pas de remerciement en plus.
        self::assertSame($modele !== 'lettre', str_contains($html, 'Grand merci.'), 'le remerciement');
    }

    /** M5 A : la phrase d'une facture annulée reste fixe, l'échéance réécrite ne paraît pas. */
    #[DataProvider('modeles')]
    public function testLaFactureAnnuleeGardeSaPhrase(string $modele): void
    {
        $this->regler(['payable' => 'À régler au plus tard le [échéance].']);
        $html = FactureDEssai::html($this->c, $this->jeu, new Marque($modele, '#1D4E89'), 1, null, [], true);
        self::assertStringContainsString('Facture annulée le 08.11.2026&nbsp;: elle ne doit pas être payée.', $html);
        self::assertStringNotContainsString('À régler', $html);
    }

    /** Une phrase réécrite reste du texte : une balise tapée par l'artisan ne passe pas dans le document. */
    public function testUnePhraseReecriteEstEchappee(): void
    {
        $this->regler(['duree' => 'Durée <b>[durée]</b> & plus.']);
        $html = $this->offre('sobre');
        self::assertStringContainsString('Durée &lt;b&gt;6 jours ouvrables&lt;/b&gt; &amp; plus.', $html);
    }
}
