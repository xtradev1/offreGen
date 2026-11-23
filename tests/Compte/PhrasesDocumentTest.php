<?php

declare(strict_types=1);

namespace OffreGen\Tests\Compte;

use OffreGen\Compte\Activite;
use OffreGen\Compte\PhrasesLettre;
use PHPUnit\Framework\TestCase;

/**
 * Les phrases que le document tire de l'offre (visite, chantier, conditions, échéance…), réécrites par l'artisan autour
 * de pastilles qui portent la donnée (MAQUETTE-lettre-phrases-auto.html, réponses de Xavier du 2026-11-23 : M1 A phrase
 * entière avec pastilles, M2 A une phrase vidée reprend l'originale, M4 B dans les douze modèles, M5 A facture annulée fixe).
 */
final class PhrasesDocumentTest extends TestCase
{
    public function testSansReglageLesPhrasesSontCellesDAujourdHui(): void
    {
        $p = new PhrasesLettre();
        self::assertSame('Visite du 12 novembre 2026.', $p->ecrire('visite', ['date' => '12 novembre 2026']));
        self::assertSame("Chantier\u{00A0}: Rue du Test 9.", $p->ecrire('chantier', ['adresse' => 'Rue du Test 9']));
        self::assertSame("Début des travaux\u{00A0}: semaine 3, 2027.", $p->ecrire('debut', ['début' => 'semaine 3, 2027']));
        self::assertSame("Paiement\u{00A0}: à 30 jours dès la fin des travaux.", $p->ecrire('paiement', ['paiement' => 'à 30 jours dès la fin des travaux']));
        self::assertSame("Offre valable jusqu'au 06.01.2027.", $p->ecrire('validite', ['date' => '06.01.2027']));
        self::assertSame("Selon notre offre n°\u{00A0}12 du 07.11.2026.", $p->ecrire('reference', ["n° de l'offre" => '12', "date de l'offre" => '07.11.2026']));
        self::assertSame("Payable jusqu'au 30.11.2026 avec la section de paiement ci-dessous.",
            $p->ecrire('payable', ['échéance' => '30.11.2026', 'section de paiement' => 'avec la section de paiement ci-dessous']));
        self::assertSame("Payable jusqu'au 30.11.2026.", $p->ecrire('payable', ['échéance' => '30.11.2026', 'section de paiement' => '']),
            'sans section de paiement, la pastille vide ne laisse pas d\'espace avant le point');
        self::assertSame('Prix en francs suisses, TVA comprise dans le total.', $p->ecrire('prix_tva'));
    }

    /** Une phrase dont la donnée manque ne paraît pas : pas de date de visite, pas de « Visite du ». */
    public function testUnePhraseSansSaDonneeNeParaitPas(): void
    {
        $p = new PhrasesLettre();
        self::assertNull($p->ecrire('visite', ['date' => '']));
        self::assertNull($p->ecrire('visite', ['date' => null]));
        self::assertNull($p->ecrire('chantier', []));
        self::assertNotNull($p->ecrire('reference', ["n° de l'offre" => '12', "date de l'offre" => '']), 'la date de l\'offre est facultative');
    }

    /** L'accord suit le nombre d'éléments non compris ; la phrase commence par une majuscule. */
    public function testNonComprisAccordeEtCommenceParUneMajuscule(): void
    {
        $p = new PhrasesLettre();
        self::assertSame("N'est pas compris\u{00A0}: peinture de finition.", $p->ecrire('non_compris', PhrasesLettre::nonCompris(['peinture de finition.'])));
        self::assertSame("Ne sont pas compris\u{00A0}: peinture\u{00A0}; nettoyage.", $p->ecrire('non_compris', PhrasesLettre::nonCompris(['peinture', 'nettoyage'])));
        self::assertNull($p->ecrire('non_compris', PhrasesLettre::nonCompris([])));
    }

    /** Les mots de l'activité : un prestataire a un « Lieu » et un « Échange du ». */
    public function testLesPhrasesDOrigineSuiventLActivite(): void
    {
        $p = new PhrasesLettre();
        self::assertSame('Échange du 12 novembre 2026.', $p->ecrire('visite', ['date' => '12 novembre 2026'], Activite::Prestations));
        self::assertSame("Lieu\u{00A0}: Rue du Test 9.", $p->ecrire('chantier', ['adresse' => 'Rue du Test 9'], Activite::Prestations));
        self::assertSame("Début\u{00A0}: lundi.", $p->ecrire('debut', ['début' => 'lundi'], Activite::Prestations));
        self::assertSame("Lieu\u{00A0}: Rue du Test 9.", $p->ecrire('chantier', ['adresse' => 'Rue du Test 9'], Activite::Exterieur));
    }

    public function testUnePhraseReecriteGardeLaDonneeAuBonEndroit(): void
    {
        [$p, $erreurs] = PhrasesLettre::depuisSaisie(['phrase_debut' => 'Nous débuterons les travaux la [début], sauf intempéries.',
            'phrase_visite' => '  Nous sommes venus le [Date] ; merci !  ', 'phrase_validite' => '', 'phrase_prix_tva' => 'Prix en francs suisses, TVA comprise dans le total.']);
        self::assertSame([], $erreurs);
        self::assertNotNull($p);
        self::assertSame('Nous débuterons les travaux la semaine 3, 2027, sauf intempéries.', $p->ecrire('debut', ['début' => 'semaine 3, 2027']));
        self::assertSame("Nous sommes venus le 12 novembre 2026\u{00A0}; merci\u{00A0}!", $p->ecrire('visite', ['date' => '12 novembre 2026']),
            'pastille sans égard aux majuscules, espaces insécables posés');
        self::assertSame("Offre valable jusqu'au 06.01.2027.", $p->ecrire('validite', ['date' => '06.01.2027']), 'M2 A : vidée, la phrase d\'origine');
        self::assertSame(['visite' => 'Nous sommes venus le [Date] ; merci !', 'debut' => 'Nous débuterons les travaux la [début], sauf intempéries.'], $p->reecrites,
            "ni la phrase vidée ni celle d'origine ne sont recopiées : elles suivront si l'application les change");
        self::assertTrue($p->estReecrite('debut'));
        self::assertFalse($p->estReecrite('validite'));
    }

    public function testUnePastilleAuDebutPrendLaMajuscule(): void
    {
        [$p] = PhrasesLettre::depuisSaisie(['phrase_paiement' => '[paiement], par virement.']);
        self::assertNotNull($p);
        self::assertSame('À 30 jours, par virement.', $p->ecrire('paiement', ['paiement' => 'à 30 jours']));
    }

    /** Une pastille inconnue, ou la pastille qui écrit la donnée retirée : refusé, la donnée de l'offre ne se perd pas. */
    public function testPastilleInconnueOuManquanteRefusee(): void
    {
        [$p, $erreurs] = PhrasesLettre::depuisSaisie(['phrase_visite' => 'Nous sommes venus le [date visite].',
            'phrase_validite' => 'Offre valable trois mois.', 'phrase_merci' => 'Merci [client].', 'phrase_debut' => str_repeat('a', 501),
            'phrase_duree' => "Durée \xC3\x28"]);
        self::assertNull($p);
        self::assertSame([
            'phrase_visite' => "Pastille inconnue\u{00A0}: [date visite]. Celle de cette phrase\u{00A0}: [date].",
            'phrase_debut' => '500 caractères au plus.',
            'phrase_duree' => 'Ce texte contient des caractères illisibles.',
            'phrase_validite' => "Gardez la pastille [date]\u{00A0}: c'est elle qui écrit la donnée de l'offre.",
            'phrase_merci' => "Pastille inconnue\u{00A0}: [client]. Cette phrase n'en a pas.",
        ], $erreurs);
    }

    /** La formule d'appel, le début et la fin : dans la Lettre seulement, ou dans les douze modèles (M4 B). */
    public function testLesPhrasesDeLettrePartoutAuChoix(): void
    {
        self::assertFalse((new PhrasesLettre())->partout, 'par défaut, la Lettre seulement : les autres modèles ne changent pas');
        [$p] = PhrasesLettre::depuisSaisie(['lettre_partout' => '1']);
        self::assertNotNull($p);
        self::assertTrue($p->partout);
        [$p] = PhrasesLettre::depuisSaisie(['lettre_partout' => 'n\'importe quoi']);
        self::assertNotNull($p);
        self::assertFalse($p->partout);
    }

    public function testLesChampsDeLEcranEtLeJson(): void
    {
        [$p] = PhrasesLettre::depuisSaisie(['lettre_partout' => '1', 'phrase_merci' => 'Grand merci.']);
        self::assertNotNull($p);
        $champs = $p->versChamps(Activite::Prestations);
        self::assertSame('1', $champs['lettre_partout']);
        self::assertSame('Grand merci.', $champs['phrase_merci']);
        self::assertSame('Échange du [date].', $champs['phrase_visite'], "le champ montre la phrase d'origine, pastilles comprises");
        self::assertSame('{"merci":"Grand merci."}', $p->reecritesJson());
        self::assertNull((new PhrasesLettre())->reecritesJson());
        self::assertSame(['merci' => 'Grand merci.'], PhrasesLettre::reecritesDepuisJson('{"merci":"Grand merci.","inconnue":"x","debut":3}'));
        self::assertSame([], PhrasesLettre::reecritesDepuisJson('pas du json'));
        self::assertSame([], PhrasesLettre::reecritesDepuisJson(null));
    }

    /** Chaque phrase a un nom, son moment, et ses pastilles sont dans sa phrase d'origine. */
    public function testChaquePhraseEstDecrite(): void
    {
        foreach (PhrasesLettre::DOCUMENT as $cle => $p) {
            $origine = PhrasesLettre::origine($cle, Activite::Batiment);
            foreach ($p['pastilles'] as $pastille) {
                self::assertStringContainsString('[' . $pastille . ']', $origine, $cle);
            }
            foreach ($p['requises'] as $requise) {
                self::assertContains($requise, $p['pastilles'], $cle);
            }
            self::assertNotSame('', $p['quand'], $cle);
        }
        // Quatorze : l'escompte de la facture est une ligne sous le total, pas une phrase (Xavier, 2026-11-23).
        self::assertCount(14, PhrasesLettre::DOCUMENT);
    }
}
