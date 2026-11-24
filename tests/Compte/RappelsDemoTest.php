<?php

declare(strict_types=1);

namespace OffreGen\Tests\Compte;

use OffreGen\Tests\Outils\BaseDeTest;
use OffreGen\Tests\Outils\Dossiers;
use OffreGen\Tests\Outils\Jeu;
use OffreGen\Tests\Outils\Monde;
use OffreGen\Tests\Outils\Navigateur;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

/**
 * Rappels de fin de démo (Xavier, 2026-11-24 : « un rappel 2 jours avant », « et puis ensuite 12 heures avant ») :
 * deux courriels en tout, au courriel tapé par le prospect, signés par la personne qui a présenté la démo.
 */
final class RappelsDemoTest extends TestCase
{
    private const FIN = '2026-11-27 13:00:00';

    private Jeu $jeu;
    private Dossiers $dossiers;
    private MockClock $horloge;

    protected function setUp(): void
    {
        $this->jeu = new Jeu();
        $this->dossiers = new Dossiers();
        $this->horloge = new MockClock('2026-11-24 13:00:00', 'UTC');
        // B est la démo de Nina (A), qui l'a présentée ; le prospect a tapé son courriel.
        BaseDeTest::pdo()->exec("UPDATE artisan SET role = 'commercial', nom_affiche = 'Nina Rey' WHERE id = {$this->jeu->a}");
        BaseDeTest::pdo()->exec("UPDATE artisan SET demo = 1, demo_fin = '" . self::FIN . "', courriel_saisi = 'info@platrerie-roux.ch', cree_par = {$this->jeu->a} WHERE id = {$this->jeu->b}");
    }

    protected function tearDown(): void
    {
        $this->dossiers->effacer();
    }

    /** Un passage du cron à cette heure-là : le nombre de courriels envoyés. */
    private function passage(string $quand): int
    {
        $this->horloge->modify($quand);
        return Monde::conteneur($this->horloge, $this->dossiers->env() + ['APP_URL' => 'https://exemple.test'])->rappelsDemo()->envoyer();
    }

    /** @return list<string> le contenu des courriels écrits */
    private function contenus(): array
    {
        return array_map(static fn (string $f): string => (string) file_get_contents($f), $this->dossiers->courriels());
    }

    public function testDeuxRappelsEtPasUnDePlus(): void
    {
        // Trois jours avant la fin : trop tôt.
        self::assertSame(0, $this->passage('+0 seconds'));
        // 47 heures avant : le premier rappel, une seule fois.
        self::assertSame(1, $this->passage('2026-11-25 14:00:00'));
        self::assertSame(0, $this->passage('2026-11-25 14:10:00'));
        [$premier] = $this->contenus();
        self::assertStringContainsString("To: info@platrerie-roux.ch\r\n", $premier);
        self::assertStringContainsString("Votre démo se termine dans 2\u{00A0}jours", $premier);
        self::assertStringContainsString("vendredi 27 novembre à 14\u{00A0}h\u{00A0}00", $premier);
        self::assertStringContainsString('Nina Rey', $premier);
        // 11 heures avant : le dernier, une seule fois.
        self::assertSame(1, $this->passage('2026-11-27 02:00:00'));
        self::assertSame(0, $this->passage('2026-11-27 02:10:00'));
        // Après la fin : plus rien.
        self::assertSame(0, $this->passage('2026-11-27 13:10:00'));
        $courriels = $this->contenus();
        self::assertCount(2, $courriels);
        self::assertCount(1, array_filter($courriels, static fn (string $c): bool => str_contains($c, "C'est le dernier rappel")));
    }

    /** Une démo qui entre en retard dans la fenêtre (cron arrêté) ne reçoit que le dernier rappel. */
    public function testUneDemoEnRetardNeRecoitQueLeDernier(): void
    {
        self::assertSame(1, $this->passage('2026-11-27 08:00:00'));
        self::assertSame(0, $this->passage('2026-11-27 08:10:00'));
        [$seul] = $this->contenus();
        self::assertStringContainsString("C'est le dernier rappel", $seul);
    }

    public function testRienSansCourrielNiPourUnEssaiNiPourUneDemoDEntrainement(): void
    {
        $cas = [
            "courriel_saisi = NULL",
            "demo = 0",
            "entrainement = 1",
        ];
        foreach ($cas as $changement) {
            BaseDeTest::pdo()->exec("UPDATE artisan SET $changement WHERE id = {$this->jeu->b}");
            self::assertSame(0, $this->passage('+0 seconds'), $changement);
            BaseDeTest::pdo()->exec("UPDATE artisan SET demo = 1, courriel_saisi = 'info@platrerie-roux.ch', entrainement = 0 WHERE id = {$this->jeu->b}");
        }
        $this->horloge->modify('2026-11-25 14:00:00');
        foreach ($cas as $changement) {
            BaseDeTest::pdo()->exec("UPDATE artisan SET $changement WHERE id = {$this->jeu->b}");
            self::assertSame(0, $this->passage('+0 seconds'), $changement);
            BaseDeTest::pdo()->exec("UPDATE artisan SET demo = 1, courriel_saisi = 'info@platrerie-roux.ch', entrainement = 0 WHERE id = {$this->jeu->b}");
        }
        self::assertSame([], $this->contenus());
    }

    /** Deux passages du cron en même temps : la requête qui note le rappel ne laisse gagner que l'un des deux. */
    public function testDeuxPassagesSimultanesNEnvoientQuUnRappel(): void
    {
        $equipe = Monde::conteneur($this->horloge)->depotEquipe();
        $quand = new \DateTimeImmutable('2026-11-25 14:00:00', new \DateTimeZone('UTC'));
        // Une seconde d'écart : MariaDB ne compte pas une ligne réécrite à l'identique, le test ne prouverait rien.
        $apres = $quand->modify('+1 second');
        self::assertTrue($equipe->noterRappelDemo($this->jeu->b, false, $quand));
        self::assertFalse($equipe->noterRappelDemo($this->jeu->b, false, $apres));
        self::assertTrue($equipe->noterRappelDemo($this->jeu->b, true, $quand));
        self::assertFalse($equipe->noterRappelDemo($this->jeu->b, true, $apres));
    }

    /** Chaque rappel porte un lien qui ouvre la démo ; le lien d'un courriel précédent reste bon. */
    public function testLesLiensDesRappelsOuvrentLaDemo(): void
    {
        $this->passage('2026-11-25 14:00:00');
        $this->passage('2026-11-27 02:00:00');
        $liens = [];
        foreach ($this->contenus() as $c) {
            self::assertSame(1, preg_match('#https://exemple\.test(/d/[A-Za-z0-9_-]{43})#', $c, $m));
            $liens[] = $m[1] ?? '';
        }
        self::assertCount(2, array_unique($liens));
        foreach ($liens as $lien) {
            $n = new Navigateur(Monde::conteneur($this->horloge, $this->dossiers->env() + ['APP_URL' => 'https://exemple.test']));
            self::assertSame(200, $n->get($lien)->getStatusCode(), $lien);
        }
        // Après la fin, le lien ne vaut plus.
        $this->horloge->modify('2026-11-27 13:10:00');
        $n = new Navigateur(Monde::conteneur($this->horloge, $this->dossiers->env() + ['APP_URL' => 'https://exemple.test']));
        self::assertNotSame(200, $n->get($liens[0])->getStatusCode());
    }
}
