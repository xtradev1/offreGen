<?php

declare(strict_types=1);

namespace OffreGen\Tests\Compte;

use OffreGen\Compte\DoublonProspect;
use OffreGen\Compte\Prospects;
use OffreGen\Compte\Role;
use OffreGen\Tests\Outils\BaseDeTest;
use OffreGen\Tests\Outils\Jeu;
use OffreGen\Tests\Outils\Monde;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

/**
 * Les défauts trouvés à la revue du 2026-11-24 (docs/plans/2026-11-24_prospects/REVUE.md), chacun reproduit ici avant
 * d'être corrigé.
 */
final class ProspectsRevueTest extends TestCase
{
    private Jeu $jeu;
    private MockClock $horloge;
    private Prospects $prospects;
    private int $nina;
    private int $leo;

    private const FICHE = ['entreprise' => 'Peinture Roux Sàrl', 'localite' => '2800 Delémont', 'metier' => 'peintre', 'metier_saisi' => '', 'telephone' => '', 'courriel' => '',
        'contact' => '', 'origine' => '', 'ide' => '', 'pas_texte' => '', 'pas_date' => ''];

    protected function setUp(): void
    {
        $this->jeu = new Jeu();
        $this->nina = $this->jeu->a;
        $this->leo = $this->jeu->b;
        BaseDeTest::pdo()->exec("UPDATE artisan SET role = 'commercial', entreprise = 'Nina Rey' WHERE id = {$this->nina}");
        BaseDeTest::pdo()->exec("UPDATE artisan SET role = 'commercial', entreprise = 'Léo Favre' WHERE id = {$this->leo}");
        $this->horloge = new MockClock('2026-11-24 09:00:00', 'UTC');
        $this->prospects = Monde::conteneur($this->horloge)->prospects();
    }

    /** @param array<string, string> $champs */
    private function creer(int $acteur, array $champs = [], bool $prend = true): int
    {
        return $this->prospects->creer($acteur, $champs + self::FICHE, $prend, true);
    }

    /** @return array<string, mixed> */
    private function fiche(int $acteur, int $id): array
    {
        return $this->prospects->vue($acteur, false, $this->prospects->fiche($id))['fiche'];
    }

    private function compte(string $courriel, string $nom): int
    {
        Monde::conteneur($this->horloge)->comptes()->creer($courriel, $nom, false, null, 10);
        return (int) BaseDeTest::ligne("SELECT id FROM artisan WHERE courriel = '$courriel'")['id'];
    }

    /** S2 : trois mots d'une lettre passaient le plancher de trois lettres et listaient les comptes des collègues. */
    public function testLaRechercheExigeUnMotDeTroisLettres(): void
    {
        $this->compte('client@exemple.ch', 'Paysages du Doubs');
        $this->creer($this->nina);
        foreach (['a e i', 'pa do', 'p', 'du ou'] as $court) {
            self::assertSame(['fiches' => [], 'comptes' => []], $this->prospects->chercher($this->leo, false, $court), $court);
        }
        // Un mot de trois lettres suffit ; les mots plus courts ne servent pas à chercher un compte.
        self::assertSame(['Paysages du Doubs'], array_column($this->prospects->chercher($this->leo, false, 'pay')['comptes'], 'entreprise'));
        self::assertSame(['Paysages du Doubs'], array_column($this->prospects->chercher($this->leo, false, 'doubs du')['comptes'], 'entreprise'));
        self::assertCount(1, $this->prospects->chercher($this->leo, false, 'roux pe')['fiches']);
    }

    /** S3 : un courriel deviné faisait dire à l'avertissement de doublon quel compte le porte. */
    public function testUnCourrielNeDesignePasLeCompteDUnCollegue(): void
    {
        $this->compte('jean@roux-client.example', 'Menuiserie du Lac');
        $id = $this->prospects->creer($this->leo, ['entreprise' => 'Tout autre nom', 'localite' => 'Bienne', 'courriel' => 'jean@roux-client.example'] + self::FICHE, true, false);
        self::assertGreaterThan(0, $id);
        // Le nom, lui, signale toujours le compte.
        $this->expectException(DoublonProspect::class);
        $this->prospects->creer($this->leo, ['entreprise' => 'menuiserie du lac', 'localite' => 'Bienne'] + self::FICHE, true, false);
    }

    /** C1 et S5 : la fusion retirait une fiche à son suiveur, ou donnait le compte de l'un à l'autre. */
    public function testLaFusionNeRetireRienAPersonne(): void
    {
        $a = $this->creer($this->nina);
        $b = $this->creer($this->leo, ['entreprise' => 'Roux Peinture', 'localite' => '2900 Porrentruy']);
        try {
            $this->prospects->fusionner($this->leo, $a, $b);
            self::fail('Fusion de deux fiches suivies par deux personnes.');
        } catch (\InvalidArgumentException $e) {
            self::assertStringContainsString('Nina Rey', $e->getMessage());
            self::assertStringContainsString('Léo Favre', $e->getMessage());
        }
        self::assertSame(2, BaseDeTest::compter('prospect'));

        // La fiche gardée est libre, l'autre est tenue et liée à une démo : celui qui tient l'autre garde tout.
        $this->prospects->lacher($this->nina, $a);
        $compte = $this->prospects->creerDemo($this->leo, Role::Commercial, $b);
        $this->prospects->fusionner($this->nina, $a, $b);
        $fiche = $this->prospects->fiche($a);
        self::assertSame([$this->leo, $compte, 1], [$fiche['suivi_par'], $fiche['compte_id'], $fiche['tenue']]);
        // Le lieu de la fiche gardée reste entier : pas de numéro postal de l'une sur la localité de l'autre.
        self::assertSame(['2800', 'Delémont'], [$fiche['npa'], $fiche['localite']]);
    }

    /** C6 : une fiche sans numéro postal recevait celui de l'autre fiche, d'une autre localité. */
    public function testLaFusionNeMelangePasLesLieux(): void
    {
        $a = $this->creer($this->nina, ['localite' => 'Delémont']);
        $b = $this->creer($this->nina, ['entreprise' => 'Roux Peinture', 'localite' => '2900 Porrentruy'], false);
        $this->prospects->fusionner($this->nina, $a, $b);
        $fiche = $this->prospects->fiche($a);
        self::assertSame([null, 'Delémont'], [$fiche['npa'], $fiche['localite']]);
    }

    /** R3 : deux noms longs dépassaient la colonne du journal et annulaient la fusion sur une erreur. */
    public function testLaFusionDeDeuxNomsLongs(): void
    {
        $a = $this->creer($this->nina, ['entreprise' => str_repeat('Entreprise générale du bâtiment ', 3) . 'A']);
        $b = $this->creer($this->nina, ['entreprise' => str_repeat('Entreprise générale du bâtiment ', 3) . 'B', 'localite' => 'Alle'], false);
        $this->prospects->fusionner($this->nina, $a, $b);
        self::assertSame(1, BaseDeTest::compter('prospect'));
    }

    /** C2 et S7 : deux envois rapprochés de « Créer une démo » laissaient une démo liée à rien. */
    public function testUnSeulCompteSeLieAUneFiche(): void
    {
        $id = $this->creer($this->nina);
        $depot = Monde::conteneur($this->horloge)->depotProspects();
        $quand = new \DateTimeImmutable('2026-11-24 09:00:00', new \DateTimeZone('UTC'));
        self::assertTrue($depot->lierCompte($id, $this->compte('un@exemple.ch', 'Démo une'), $quand));
        self::assertFalse($depot->lierCompte($id, $this->compte('deux@exemple.ch', 'Démo deux'), $quand));
    }

    /** C4 : l'échéance se comptait sur le jour UTC ; une action après minuit à Zurich perdait un jour. */
    public function testUneActionApresMinuitCompteSurLeJourDeZurich(): void
    {
        // Le 24.11 à 23 h 30 UTC : le 25.11 à 0 h 30 à Zurich. Tenue jusqu'au 02.12 compris.
        $this->horloge->modify('2026-11-24 23:30:00');
        $id = $this->creer($this->nina);
        $this->horloge->modify('2026-12-02 12:00:00');
        self::assertSame(1, $this->prospects->fiche($id)['tenue']);
        $this->horloge->modify('2026-12-03 12:00:00');
        self::assertSame(0, $this->prospects->fiche($id)['tenue']);
    }

    /** C7 : le prochain pas saisi disparaissait sans un mot quand « Je m'en occupe » était décoché. */
    public function testUnPasDemandeDeSuivreLeProspect(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->prospects->creer($this->nina, ['pas_texte' => 'Rappeler', 'pas_date' => '2026-11-26'] + self::FICHE, false, false);
    }

    /** R1 : les fiches d'un membre retiré de l'équipe restaient tenues par lui, donc gelées. */
    public function testLesFichesDUnMembreRetireSontLibres(): void
    {
        $id = $this->creer($this->nina, ['telephone' => '079 555 01 42', 'pas_texte' => 'Rappeler', 'pas_date' => '2026-12-20']);
        self::assertSame(1, $this->prospects->fiche($id)['tenue']);
        BaseDeTest::pdo()->exec("UPDATE artisan SET role = NULL WHERE id = {$this->nina}");
        $fiche = $this->fiche($this->leo, $id);
        self::assertSame([0, 'Nina Rey', '079 555 01 42'], [$fiche['tenue'], $fiche['avant'], $fiche['telephone']]);
        self::assertNull($fiche['libre_le']);
        $this->prospects->prendre($this->leo, $id);
        self::assertSame($this->leo, $this->prospects->fiche($id)['suivi_par']);
    }
}
