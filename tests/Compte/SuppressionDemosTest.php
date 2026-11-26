<?php

declare(strict_types=1);

namespace OffreGen\Tests\Compte;

use OffreGen\Tests\Outils\BaseDeTest;
use OffreGen\Tests\Outils\Dossiers;
use OffreGen\Tests\Outils\Jeu;
use OffreGen\Tests\Outils\Monde;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

/**
 * Une démo finie est supprimée 30 jours après sa fin (écran de fin K1 ; chantier onboarding, E1 du 2026-11-23), avec
 * tout ce qui lui appartient. L'horloge est au 2026-11-07 10:00 UTC.
 */
final class SuppressionDemosTest extends TestCase
{
    private Jeu $jeu;
    private Dossiers $dossiers;

    protected function setUp(): void
    {
        $this->jeu = new Jeu();
        $this->dossiers = new Dossiers();
    }

    protected function tearDown(): void
    {
        $this->dossiers->effacer();
    }

    /** @return array{supprimees: int, erreurs: int} */
    private function supprimer(): array
    {
        return Monde::conteneur(new MockClock('2026-11-07 10:00:00', 'UTC'), $this->dossiers->env())->suppressionDemos()->supprimer();
    }

    private function demo(int $id, ?string $fin): void
    {
        BaseDeTest::pdo()->exec("UPDATE artisan SET demo = 1, demo_fin = " . ($fin === null ? 'NULL' : "'$fin'") . " WHERE id = $id");
    }

    /**
     * Les tables qui portent un compte, hors journaux (gardés, ou remis à NULL par leur clé).
     *
     * @return array<string, int> les tables où il reste des lignes du compte, et combien
     */
    private function restes(int $id): array
    {
        $liste = BaseDeTest::pdo()->query("SELECT TABLE_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND COLUMN_NAME = 'artisan_id' AND TABLE_NAME NOT IN ('journal_admin', 'journal_connexion')");
        self::assertNotFalse($liste);
        $tables = $liste->fetchAll(\PDO::FETCH_COLUMN);
        self::assertNotEmpty($tables);
        $restes = [];
        foreach ($tables as $t) {
            $n = (int) self::valeur("SELECT COUNT(*) FROM `$t` WHERE artisan_id = $id");
            if ($n > 0) {
                $restes[$t] = $n;
            }
        }
        return $restes;
    }

    public function testUneDemoFinieDepuisTrenteJoursPartAvecToutCeQuiLuiAppartient(): void
    {
        $a = $this->jeu->a;
        $this->demo($a, '2026-10-08 09:59:00');
        $pdo = BaseDeTest::pdo();
        // Son audio, son logo, une demande d'appel, un lien, une connexion, un geste de l'équipe sur elle.
        mkdir($this->dossiers->racine . "/audio/$a", 0700, true);
        file_put_contents($this->dossiers->racine . "/audio/$a/{$this->jeu->uuid['a']}.m4a", 'audio');
        file_put_contents($this->dossiers->racine . '/logos/logo-a.png', 'png');
        $pdo->exec("UPDATE artisan SET logo_fichier = 'logo-a.png' WHERE id = $a");
        $pdo->exec("INSERT INTO demande_appel (artisan_id, telephone, moment, cree_le) VALUES ($a, '079 123 45 67', 'soir', '2026-10-08 10:00:00')");
        $pdo->exec("INSERT INTO jeton_acces (artisan_id, empreinte, motif, cree_le, expire_le) VALUES ($a, '" . hash('sha256', 'x') . "', 'demo_qr', '2026-10-01 00:00:00', '2026-10-02 00:00:00')");
        $pdo->exec("INSERT INTO journal_connexion (artisan_id, courriel_essaye, ip, reussi, quand) VALUES ($a, 'a@exemple.ch', '127.0.0.1', 1, '2026-10-01 00:00:00')");
        $pdo->exec("INSERT INTO journal_admin (acteur_id, artisan_id, geste, quand) VALUES ({$this->jeu->b}, $a, 'demo_creee', '2026-10-01 00:00:00')");

        self::assertSame(['supprimees' => 1, 'erreurs' => 0], $this->supprimer());

        self::assertFalse(self::valeur("SELECT 1 FROM artisan WHERE id = $a"));
        self::assertSame([], $this->restes($a));
        self::assertFileDoesNotExist($this->dossiers->racine . "/audio/$a/{$this->jeu->uuid['a']}.m4a");
        self::assertSame([], $this->dossiers->logos());
        // Le journal de l'équipe reste : une démo supprimée compte encore dans les limites du commercial.
        self::assertSame(1, (int) self::valeur("SELECT COUNT(*) FROM journal_admin WHERE artisan_id = $a AND geste = 'demo_creee'"));
        self::assertSame(1, (int) self::valeur("SELECT COUNT(*) FROM journal_connexion WHERE artisan_id IS NULL AND courriel_essaye = 'a@exemple.ch'"));
        // L'autre compte n'a rien perdu.
        self::assertNotSame([], $this->restes($this->jeu->b));
    }

    public function testRienAvantTrenteJoursNiPourUnEssaiNiPourUneDemonstrationPartagee(): void
    {
        $this->demo($this->jeu->a, '2026-10-08 10:01:00');
        self::assertSame(['supprimees' => 0, 'erreurs' => 0], $this->supprimer());
        // Démonstration partagée : pas de date de fin.
        $this->demo($this->jeu->a, null);
        // Essai : plus une démo, même avec une ancienne date.
        BaseDeTest::pdo()->exec("UPDATE artisan SET demo = 0, demo_fin = '2026-09-01 00:00:00', offres_max = 10 WHERE id = {$this->jeu->b}");
        self::assertSame(['supprimees' => 0, 'erreurs' => 0], $this->supprimer());
        self::assertSame(2, (int) self::valeur("SELECT COUNT(*) FROM artisan WHERE id IN ({$this->jeu->a}, {$this->jeu->b})"));
    }

    public function testUnMembreDeLEquipeNEstJamaisSupprime(): void
    {
        $this->demo($this->jeu->a, '2026-09-01 00:00:00');
        BaseDeTest::pdo()->exec("UPDATE artisan SET role = 'commercial' WHERE id = {$this->jeu->a}");
        self::assertSame(['supprimees' => 0, 'erreurs' => 0], $this->supprimer());
    }

    private static function valeur(string $sql): mixed
    {
        $s = BaseDeTest::pdo()->query($sql);
        self::assertNotFalse($s);
        return $s->fetchColumn();
    }
}
