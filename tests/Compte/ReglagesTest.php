<?php

declare(strict_types=1);

namespace OffreGen\Tests\Compte;

use OffreGen\Compte\Reglages;
use OffreGen\Offre\SaisieRefusee;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ReglagesTest extends TestCase
{
    /** @return iterable<string, array{string, string|null}> */
    public static function ides(): iterable
    {
        yield 'forme officielle' => ['CHE-123.456.789', 'CHE-123.456.789'];
        yield 'avec TVA' => ['CHE-123.456.789 TVA', 'CHE-123.456.789'];
        yield 'minuscules et espaces' => ['che 123 456 789 tva', 'CHE-123.456.789'];
        yield 'collé' => ['CHE123456789', 'CHE-123.456.789'];
        yield 'MWST' => ['CHE-123.456.789 MWST', 'CHE-123.456.789'];
        yield 'vide permis' => ['  ', ''];
        yield 'chiffres manquants' => ['CHE-123.456', null];
        yield 'sans CHE' => ['123.456.789', null];
    }

    #[DataProvider('ides')]
    public function testIde(string $saisie, ?string $attendu): void
    {
        self::assertSame($attendu, Reglages::ide($saisie));
    }

    private const BASE = ['entreprise' => 'Peinture Exemple', 'tva' => '810', 'validite_choix' => '30', 'paiement_type' => 'jours', 'paiement_jours_choix' => '30'];

    /** @param array<string, string> $champs */
    private static function saisie(array $champs): Reglages
    {
        return Reglages::depuisSaisie($champs + self::BASE, true);
    }

    /**
     * @param array<string, string> $champs
     * @return array<string, string> les erreurs par champ
     */
    private static function refus(array $champs): array
    {
        try {
            self::saisie($champs);
        } catch (SaisieRefusee $e) {
            return $e->erreurs;
        }
        self::fail('Saisie acceptée : ' . json_encode($champs));
    }

    public function testLaPhraseDuPaiementSeConstruitDesChoix(): void
    {
        $nb = "\u{00A0}";
        self::assertSame('À 30 jours dès la fin des travaux.', (new Reglages('X', 810, true))->conditionsPaiement());
        self::assertSame('À 20 jours dès la fin des travaux.', (new Reglages('X', 810, true, paiementJours: 20))->conditionsPaiement());
        self::assertSame('Net à 10 jours dès réception de la facture.', (new Reglages('X', 810, true, paiementType: 'net', paiementJours: 10))->conditionsPaiement());
        self::assertSame("30{$nb}% à la commande, 70{$nb}% à la fin des travaux.",
            (new Reglages('X', 810, true, paiementType: 'acomptes', paiementAcomptes: [30, 70]))->conditionsPaiement());
        self::assertSame("30{$nb}% à la commande, 30{$nb}% en cours de travaux, 40{$nb}% à la fin des travaux.",
            (new Reglages('X', 810, true, paiementType: 'acomptes', paiementAcomptes: [30, 30, 40]))->conditionsPaiement());
    }

    public function testIbanSaisiEnGroupesGardeCompact(): void
    {
        self::assertSame('CH9300762011623852957', self::saisie(['iban' => 'ch93 0076 2011 6238 5295 7'])->iban);
        self::assertSame('', self::saisie([])->iban, 'Sans IBAN, les offres se font quand même.');
        self::assertSame(['iban' => "Un IBAN suisse, par exemple CH93\u{00A0}0076\u{00A0}2011\u{00A0}6238\u{00A0}5295\u{00A0}7."], self::refus(['iban' => 'CH93 0076 2011 6238 5295 8']));
    }

    public function testValiditeProposeeOuAutre(): void
    {
        self::assertSame(60, self::saisie(['validite_choix' => '60', 'validite_jours' => '999'])->validiteJours);
        self::assertSame(45, self::saisie(['validite_choix' => 'autre', 'validite_jours' => '45'])->validiteJours);
        // Sans choix (ancien formulaire, page sans CSS) : le nombre tapé.
        self::assertSame(21, self::saisie(['validite_choix' => '', 'validite_jours' => '21'])->validiteJours);
    }

    public function testUneOffreSansValiditeEstRefusee(): void
    {
        foreach (['', '0', '366', 'trente'] as $jours) {
            self::assertArrayHasKey('validite_jours', self::refus(['validite_choix' => 'autre', 'validite_jours' => $jours]), "« $jours »");
        }
        self::assertArrayHasKey('validite_jours', self::refus(['validite_choix' => '90']));
    }

    public function testPaiementAJoursOuNetProposeOuAutre(): void
    {
        $r = self::saisie(['paiement_type' => 'net', 'paiement_jours_choix' => '15']);
        self::assertSame(['net', 15], [$r->paiementType, $r->paiementJours]);
        $r = self::saisie(['paiement_type' => 'jours', 'paiement_jours_choix' => 'autre', 'paiement_jours' => '45']);
        self::assertSame(['jours', 45], [$r->paiementType, $r->paiementJours]);
        self::assertArrayHasKey('paiement_jours', self::refus(['paiement_type' => 'jours', 'paiement_jours_choix' => 'autre', 'paiement_jours' => '0']));
        self::assertArrayHasKey('paiement_type', self::refus(['paiement_type' => 'troc']));
    }

    public function testAcomptesProposesOuAutreFontCentPourCent(): void
    {
        $r = self::saisie(['paiement_type' => 'acomptes', 'paiement_acomptes_choix' => '30,30,40']);
        self::assertSame(['acomptes', [30, 30, 40]], [$r->paiementType, $r->paiementAcomptes]);
        $r = self::saisie(['paiement_type' => 'acomptes', 'paiement_acomptes_choix' => 'autre', 'paiement_acomptes' => ' 20 % / 30 %, 50 ']);
        self::assertSame([20, 30, 50], $r->paiementAcomptes);
        foreach (['30, 30' => 'ne font pas 100', '100' => 'une seule tranche', '20,20,20,20,20' => 'cinq tranches', '0,100' => 'tranche nulle', '' => 'vide'] as $saisie => $cas) {
            self::assertArrayHasKey('paiement_acomptes', self::refus(['paiement_type' => 'acomptes', 'paiement_acomptes_choix' => 'autre', 'paiement_acomptes' => (string) $saisie]), $cas);
        }
        // Les acomptes ne restent pas collés à un paiement à jours.
        self::assertSame([], self::saisie(['paiement_type' => 'jours', 'paiement_jours_choix' => '30', 'paiement_acomptes_choix' => '50,50'])->paiementAcomptes);
    }

    public function testLocaliteSansNumeroPostal(): void
    {
        self::assertSame('Delémont', (new Reglages('X', 810, true, "Rue 1\n2800 Delémont"))->localite());
        self::assertSame('Porrentruy', (new Reglages('X', 810, true, "CH-2900 Porrentruy"))->localite());
        self::assertSame('', (new Reglages('X', 810, true))->localite());
    }
}
