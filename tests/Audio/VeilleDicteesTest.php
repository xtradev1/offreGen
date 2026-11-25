<?php

declare(strict_types=1);

namespace OffreGen\Tests\Audio;

use OffreGen\Audio\VeilleDictees;
use OffreGen\Donnees\DepotEnregistrements;
use OffreGen\Donnees\FileTraitement;
use OffreGen\Donnees\Portee;
use OffreGen\Donnees\Statut;
use OffreGen\Tests\Outils\BaseDeTest;
use OffreGen\Tests\Outils\Jeu;
use OffreGen\Tests\Outils\JournalDeTest;
use PHPUnit\Framework\TestCase;

final class VeilleDicteesTest extends TestCase
{
    private Jeu $jeu;
    private JournalDeTest $journal;
    private \DateTimeImmutable $maintenant;

    protected function setUp(): void
    {
        $this->jeu = new Jeu();
        $this->journal = new JournalDeTest();
        $this->maintenant = new \DateTimeImmutable('2026-11-25 10:00:00', new \DateTimeZone('UTC'));
        // Le jeu de départ porte des dictées en cours, datées du 2026-11-07 : terminées ici, chaque test pose les siennes.
        BaseDeTest::pdo()->exec("UPDATE enregistrement SET statut = 'pret'");
    }

    private function dictee(string $uuid, string $recueIlYA, string $statut): void
    {
        (new DepotEnregistrements($this->jeu->base, new Portee($this->jeu->a)))
            ->creer($uuid, 'visite.m4a', 'audio/x-m4a', 4322, "{$this->jeu->a}/$uuid.m4a", $this->maintenant->modify("-$recueIlYA"));
        BaseDeTest::pdo()->prepare('UPDATE enregistrement SET statut = ? WHERE uuid = ?')->execute([$statut, $uuid]);
    }

    private function verifier(): int
    {
        return (new VeilleDictees(new FileTraitement($this->jeu->base), $this->journal))->verifier($this->maintenant);
    }

    public function testUneDicteeQuiAttendDepuisPlusDeCinqMinutesPartEnAlerte(): void
    {
        $this->dictee('a1111111-1111-4111-8111-111111111111', '12 minutes', Statut::TRANSCRIPTION);
        $this->dictee('a2222222-2222-4222-8222-222222222222', '7 minutes', Statut::RECU);
        $this->dictee('a3333333-3333-4333-8333-333333333333', '1 minute', Statut::RECU);
        // Prête ou en échec depuis longtemps : plus rien à attendre, l'échec a déjà eu sa propre erreur.
        $this->dictee('a4444444-4444-4444-8444-444444444444', '2 hours', Statut::PRET);
        $this->dictee('a5555555-5555-4555-8555-555555555555', '2 hours', Statut::ECHEC);

        self::assertSame(2, $this->verifier());
        self::assertSame(['error offreGen, dictées bloquées : 2 dictée(s) attendent leur offre depuis plus de 5 minutes, la plus ancienne depuis 12 min (3 en cours en tout). '
            . 'Infomaniak lent ou saturé, ou traitement arrêté : voir /admin/dictees.'], $this->journal->lignes);
    }

    public function testRienNePartQuandToutVaBien(): void
    {
        $this->dictee('b1111111-1111-4111-8111-111111111111', '4 minutes', Statut::TRANSCRIPTION);
        $this->dictee('b2222222-2222-4222-8222-222222222222', '3 hours', Statut::PRET);

        self::assertSame(0, $this->verifier());
        self::assertSame([], $this->journal->lignes);
    }

    public function testRienNePartSansAucuneDictee(): void
    {
        self::assertSame(0, $this->verifier());
        self::assertSame([], $this->journal->lignes);
    }
}
