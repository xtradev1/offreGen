<?php

declare(strict_types=1);

namespace OffreGen\Tests\Http;

use OffreGen\Donnees\FileTraitement;
use OffreGen\Tests\Outils\BaseDeTest;
use OffreGen\Tests\Outils\Jeu;
use OffreGen\Tests\Outils\Monde;
use OffreGen\Tests\Outils\Navigateur;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

/**
 * Sous-chantier 31, D5 de Xavier (2026-11-24) : quand le traitement s'est arrêté en route (verrou expiré sans être
 * rendu), l'écran d'attente le dit au lieu de laisser l'artisan attendre sans rien savoir. Un traitement vivant, même
 * lent, ne déclenche pas le message : son pouls prolonge le verrou.
 */
final class DicteeInterrompueTest extends TestCase
{
    private Jeu $jeu;
    private MockClock $horloge;
    private Navigateur $n;
    private string $uuid;

    protected function setUp(): void
    {
        $this->jeu = new Jeu();
        $this->horloge = new MockClock('2026-11-07 10:00:00', 'UTC');
        $this->n = new Navigateur(Monde::conteneur($this->horloge));
        $this->n->connecter('a@exemple.ch');
        $this->uuid = $this->jeu->uuid['a'];
        BaseDeTest::pdo()->exec("UPDATE enregistrement SET statut = 'transcrit' WHERE uuid = '{$this->uuid}'");
    }

    /** @return array<string, mixed> */
    private function etat(): array
    {
        $r = $this->n->get('/enregistrements/' . $this->uuid . '/etat');
        self::assertSame(200, $r->getStatusCode());
        /** @var array<string, mixed> */
        return json_decode((string) $r->getContent(), true, 512, JSON_THROW_ON_ERROR);
    }

    private function panneVisible(): bool
    {
        $page = (string) $this->n->get('/enregistrements/' . $this->uuid)->getContent();
        self::assertSame(1, preg_match('/<p class="alerte warn" role="status" data-etapes-panne( hidden)?>/', $page, $m), 'message présent dans la page');
        return ($m[1] ?? '') === '';
    }

    public function testLeMessageApparaitQuandLeVerrouAExpireSansEtreRendu(): void
    {
        $file = new FileTraitement($this->jeu->base);
        self::assertTrue($file->prendreVerrou($this->uuid, str_repeat('m', 32), $this->horloge->now()->modify('+150 seconds'), $this->horloge->now()));

        $this->horloge->sleep(149);
        self::assertFalse($this->etat()['interrompu'], 'traitement vivant');
        self::assertFalse($this->panneVisible());

        $this->horloge->sleep(2);
        self::assertTrue($this->etat()['interrompu']);
        self::assertTrue($this->panneVisible());

        // Le cron reprend : nouveau verrou, le message disparaît.
        self::assertTrue($file->prendreVerrou($this->uuid, str_repeat('n', 32), $this->horloge->now()->modify('+150 seconds'), $this->horloge->now()));
        self::assertFalse($this->etat()['interrompu']);
    }

    public function testRienSansVerrouNiPourUneDicteeFinie(): void
    {
        self::assertFalse($this->etat()['interrompu'], 'aucun verrou : en attente du cron, pas une panne');
        BaseDeTest::pdo()->exec("UPDATE enregistrement SET statut = 'echec', verrou_jusqu_a = '2026-11-07 09:00:00' WHERE uuid = '{$this->uuid}'");
        self::assertFalse($this->etat()['interrompu']);
    }
}
