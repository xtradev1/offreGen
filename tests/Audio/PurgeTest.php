<?php

declare(strict_types=1);

namespace OffreGen\Tests\Audio;

use OffreGen\Audio\Traiteur;
use OffreGen\Donnees\DepotEnregistrements;
use OffreGen\Donnees\FileTraitement;
use OffreGen\Donnees\Portee;
use OffreGen\Tests\Outils\BaseDeTest;
use OffreGen\Tests\Outils\Dossiers;
use OffreGen\Tests\Outils\Jeu;
use OffreGen\Tests\Outils\Monde;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

final class PurgeTest extends TestCase
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

    private function audio(string $uuid): string
    {
        $chemin = $this->dossiers->env()['AUDIO_DOSSIER'] . "/{$this->jeu->a}/$uuid.m4a";
        if (!is_dir(dirname($chemin))) {
            mkdir(dirname($chemin), 0700, true);
        }
        file_put_contents($chemin, 'audio');
        return $chemin;
    }

    public function testSeulLAudioEchuEstSupprimeLOffreReste(): void
    {
        $echu = $this->jeu->uuid['a'];
        $recent = '22222222-2222-4222-8222-222222222222';
        (new DepotEnregistrements($this->jeu->base, new Portee($this->jeu->a)))
            ->creer($recent, 'r.m4a', 'audio/x-m4a', 5, "{$this->jeu->a}/$recent.m4a", $this->jeu->maintenant);
        BaseDeTest::pdo()->exec("UPDATE enregistrement SET statut = 'pret', supprimer_apres = '2026-12-07 10:00:00' WHERE uuid = '$echu'");
        BaseDeTest::pdo()->exec("UPDATE enregistrement SET statut = 'pret', supprimer_apres = '2026-12-07 10:00:01' WHERE uuid = '$recent'");
        $fEchu = $this->audio($echu);
        $fRecent = $this->audio($recent);

        $horloge = new MockClock('2026-12-07 10:00:01', 'UTC');
        $resultat = Monde::conteneur($horloge, $this->dossiers->env())->purge()->purger();

        self::assertSame(['supprimes' => 1, 'erreurs' => 0], $resultat);
        self::assertFileDoesNotExist($fEchu);
        self::assertFileExists($fRecent);
        self::assertSame(1, BaseDeTest::compter('enregistrement', "uuid = '$echu' AND fichier IS NULL"));
        self::assertSame(1, BaseDeTest::compter('offre', "id = {$this->jeu->offre['a']}"));
        // Deuxième passage : rien de plus.
        self::assertSame(['supprimes' => 0, 'erreurs' => 0], Monde::conteneur($horloge, $this->dossiers->env())->purge()->purger());
    }

    public function testFichierDejaAbsentNeBloquePas(): void
    {
        BaseDeTest::pdo()->exec("UPDATE enregistrement SET statut = 'pret', supprimer_apres = '2026-11-01 00:00:00' WHERE uuid = '{$this->jeu->uuid['a']}'");
        $resultat = Monde::conteneur(env: $this->dossiers->env())->purge()->purger();
        self::assertSame(['supprimes' => 1, 'erreurs' => 0], $resultat);
    }

    public function testUnEchecRecoitAussiUneDateDeSuppression(): void
    {
        $file = new FileTraitement($this->jeu->base);
        $uuid = $this->jeu->uuid['a'];
        $maintenant = $this->jeu->maintenant;
        for ($i = 0; $i < Traiteur::TENTATIVES_MAX; $i++) {
            self::assertTrue($file->prendreVerrou($uuid, str_repeat('d', 32), $maintenant->modify('+1 minute'), $maintenant));
            $file->noterEchec($uuid, str_repeat('d', 32), 'panne', Traiteur::TENTATIVES_MAX, $maintenant->modify('+30 days'));
            $file->rendreVerrou($uuid, str_repeat('d', 32));
        }
        self::assertSame(1, BaseDeTest::compter('enregistrement', "uuid = '$uuid' AND statut = 'echec' AND supprimer_apres = '2026-12-07 10:00:00'"));
    }

    public function testAvantLEchecPasDeDateDeSuppression(): void
    {
        $file = new FileTraitement($this->jeu->base);
        $uuid = $this->jeu->uuid['a'];
        $m = $this->jeu->maintenant;
        $file->prendreVerrou($uuid, str_repeat('d', 32), $m->modify('+1 minute'), $m);
        $file->noterEchec($uuid, str_repeat('d', 32), 'panne', Traiteur::TENTATIVES_MAX, $m->modify('+30 days'));
        self::assertSame(1, BaseDeTest::compter('enregistrement', "uuid = '$uuid' AND statut = 'recu' AND supprimer_apres IS NULL"));
    }
}
