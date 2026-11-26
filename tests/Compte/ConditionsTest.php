<?php

declare(strict_types=1);

namespace OffreGen\Tests\Compte;

use OffreGen\Compte\Conditions;
use PHPUnit\Framework\TestCase;

final class ConditionsTest extends TestCase
{
    private const BASE = ['validite_choix' => '30', 'paiement_type' => 'jours', 'paiement_jours_choix' => '30'];

    public function testSansEscompte(): void
    {
        [$c, $erreurs] = Conditions::depuisSaisie(self::BASE + ['escompte_pourcent' => '', 'escompte_jours' => '10']);
        self::assertSame([], $erreurs);
        self::assertNotNull($c);
        self::assertFalse($c->aEscompte());
        self::assertNull($c->phraseEscompte());
    }

    public function testUnEscompteADeuxDecimales(): void
    {
        [$c] = Conditions::depuisSaisie(self::BASE + ['escompte_pourcent' => '1,5', 'escompte_jours' => '10']);
        self::assertSame([150, 10], [$c?->escomptePourDixMille, $c?->escompteJours]);
        self::assertSame("Escompte 1,5\u{00A0}% à 10 jours", $c?->phraseEscompte());
        self::assertSame("Escompte 2\u{00A0}% à 8 jours", Conditions::depuisSaisie(self::BASE + ['escompte_pourcent' => '2', 'escompte_jours' => '8'])[0]?->phraseEscompte());
    }

    /** L'escompte entre dans la phrase du paiement (Xavier, 2026-11-23) ; par acomptes, il suit à part. */
    public function testLaPhraseDuPaiementPorteLEscompte(): void
    {
        self::assertSame('À 30 jours dès la fin des travaux.', (new Conditions())->paiementEtEscompte());
        self::assertSame("À 30 jours dès la fin des travaux, ou dans les 10 jours avec un escompte de 2\u{00A0}%.",
            (new Conditions(escomptePourDixMille: 200, escompteJours: 10))->paiementEtEscompte());
        self::assertSame("Net à 20 jours dès réception de la facture, ou dans les 8 jours avec un escompte de 1,5\u{00A0}%.",
            (new Conditions(paiementType: 'net', paiementJours: 20, escomptePourDixMille: 150, escompteJours: 8))->paiementEtEscompte());
        self::assertSame("30\u{00A0}% à la commande, 70\u{00A0}% à la fin des travaux. Escompte de 2\u{00A0}% sur chaque facture payée dans les 10 jours.",
            (new Conditions(paiementType: 'acomptes', paiementAcomptes: [30, 70], escomptePourDixMille: 200, escompteJours: 10))->paiementEtEscompte());
    }

    public function testUnEscompteFauxOuSansDelaiEstRefuse(): void
    {
        self::assertArrayHasKey('escompte_pourcent', Conditions::depuisSaisie(self::BASE + ['escompte_pourcent' => '100', 'escompte_jours' => '10'])[1]);
        self::assertArrayHasKey('escompte_pourcent', Conditions::depuisSaisie(self::BASE + ['escompte_pourcent' => 'x', 'escompte_jours' => '10'])[1]);
        self::assertArrayHasKey('escompte_jours', Conditions::depuisSaisie(self::BASE + ['escompte_pourcent' => '2', 'escompte_jours' => ''])[1]);
        self::assertArrayHasKey('escompte_jours', Conditions::depuisSaisie(self::BASE + ['escompte_pourcent' => '2', 'escompte_jours' => '200'])[1]);
    }

    public function testLeJsonFaitLAllerRetour(): void
    {
        foreach ([new Conditions(60, 'net', 10, [], 200, 10), new Conditions(15, 'acomptes', 30, [30, 30, 40]), new Conditions(365, 'jours', 120)] as $c) {
            self::assertEquals($c, Conditions::depuisJson($c->versJson(), new Conditions()));
        }
    }

    public function testUnJsonAbsentOuAbimeDonneLesConditionsDuCompte(): void
    {
        $compte = new Conditions(15, 'net', 20);
        self::assertSame($compte, Conditions::depuisJson(null, $compte));
        self::assertSame($compte, Conditions::depuisJson('{pas du json', $compte));
        self::assertSame($compte, Conditions::depuisJson('{"validite_jours":"0","validite_choix":"autre"}', $compte));
    }

    public function testLesChampsReprennentLesChoixProposes(): void
    {
        $champs = (new Conditions(60, 'net', 10, [], 250, 10))->versChamps();
        self::assertSame(['60', '60', 'net', '10', '10', '30,70', '', '2.5', '10'], array_values($champs));
        $autre = (new Conditions(45, 'acomptes', 30, [20, 80]))->versChamps();
        self::assertSame(['autre', 'autre', '20, 80'], [$autre['validite_choix'], $autre['paiement_acomptes_choix'], $autre['paiement_acomptes']]);
    }
}
