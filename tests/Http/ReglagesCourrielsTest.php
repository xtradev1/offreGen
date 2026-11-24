<?php

declare(strict_types=1);

namespace OffreGen\Tests\Http;

use OffreGen\Tests\Outils\BaseDeTest;
use OffreGen\Tests\Outils\Jeu;
use OffreGen\Tests\Outils\Monde;
use OffreGen\Tests\Outils\Navigateur;
use PHPUnit\Framework\TestCase;

/**
 * Le courriel « offre prête » (M2 de Xavier, 2026-11-23) : un réglage pour le recevoir ou non, et l'écran d'attente
 * qui dit au serveur que l'artisan suit son offre.
 */
final class ReglagesCourrielsTest extends TestCase
{
    private Jeu $jeu;

    protected function setUp(): void
    {
        $this->jeu = new Jeu();
    }

    private function navigateur(): Navigateur
    {
        $n = new Navigateur(Monde::conteneur());
        $n->connecter('a@exemple.ch');
        return $n;
    }

    private function reglage(int $artisan, string $colonne = 'courriel_offre_prete'): int
    {
        return (int) BaseDeTest::ligne("SELECT $colonne AS v FROM artisan WHERE id = $artisan")['v'];
    }

    public function testLeReglageSeLitEtSeChange(): void
    {
        $n = $this->navigateur();
        self::assertStringContainsString('href="/reglages/courriels"', (string) $n->get('/reglages')->getContent());
        $page = $n->get('/reglages/courriels');
        self::assertSame(200, $page->getStatusCode());
        // Par défaut, l'artisan est prévenu.
        self::assertStringContainsString('name="offre_prete" value="1" checked', (string) $page->getContent());
        self::assertStringContainsString('name="dictee_echec" value="1" checked', (string) $page->getContent());

        self::assertSame(303, $n->post('/reglages/courriels', [])->getStatusCode());
        self::assertSame(0, $this->reglage($this->jeu->a));
        self::assertSame(0, $this->reglage($this->jeu->a, 'courriel_dictee_echec'));
        self::assertStringNotContainsString('checked', (string) $n->get('/reglages/courriels')->getContent());
        // Le réglage d'un artisan ne touche pas celui d'un autre.
        self::assertSame(1, $this->reglage($this->jeu->b));
        self::assertSame(1, $this->reglage($this->jeu->b, 'courriel_dictee_echec'));

        // Les deux cases sont indépendantes.
        self::assertSame(303, $n->post('/reglages/courriels', ['offre_prete' => '1'])->getStatusCode());
        self::assertSame(1, $this->reglage($this->jeu->a));
        self::assertSame(0, $this->reglage($this->jeu->a, 'courriel_dictee_echec'));
        $n->get('/reglages/courriels');
        self::assertSame(303, $n->post('/reglages/courriels', ['dictee_echec' => '1'])->getStatusCode());
        self::assertSame(0, $this->reglage($this->jeu->a));
        self::assertSame(1, $this->reglage($this->jeu->a, 'courriel_dictee_echec'));
    }

    public function testLEcranDAttenteDitQueLArtisanSuitSonOffre(): void
    {
        $suivi = static fn (string $uuid): ?string => BaseDeTest::ligne("SELECT suivi_le FROM enregistrement WHERE uuid = '$uuid'")['suivi_le'];
        self::assertNull($suivi($this->jeu->uuid['a']));
        $n = $this->navigateur();
        self::assertSame(200, $n->get("/enregistrements/{$this->jeu->uuid['a']}/etat")->getStatusCode());
        self::assertSame('2026-11-07 10:00:00', $suivi($this->jeu->uuid['a']));
        // L'enregistrement d'un autre artisan n'existe pas pour lui, et n'est pas marqué.
        self::assertSame(404, $n->get("/enregistrements/{$this->jeu->uuid['b']}/etat")->getStatusCode());
        self::assertNull($suivi($this->jeu->uuid['b']));
    }
}
