<?php

declare(strict_types=1);

namespace OffreGen\Tests\Http;

use OffreGen\Http\Morceaux;
use OffreGen\Tests\Outils\BaseDeTest;
use OffreGen\Tests\Outils\Jeu;
use OffreGen\Tests\Outils\Monde;
use OffreGen\Tests\Outils\Navigateur;
use PHPUnit\Framework\TestCase;

/**
 * Lot 4 du chantier sans rechargement (2026-11-23) : un geste envoyé avec sa clé (X-Cle-Envoi) n'est fait qu'une fois,
 * même rejoué ; la clé est celle d'un artisan ; un geste refusé libère sa clé.
 */
final class EnvoiCleTest extends TestCase
{
    private Jeu $jeu;
    private Navigateur $a;
    private string $offre;

    protected function setUp(): void
    {
        $this->jeu = new Jeu();
        $this->a = new Navigateur(Monde::conteneur());
        $this->a->connecter('a@exemple.ch');
        $this->offre = '/offres/' . $this->jeu->offre['a'];
        $this->a->get($this->offre);
    }

    private function lignes(): int
    {
        return BaseDeTest::compter('ligne', "offre_id = {$this->jeu->offre['a']} AND supprimee_le IS NULL");
    }

    /** @param array<string, string> $champs */
    private function envoyer(Navigateur $n, string $chemin, array $champs, string $cle): \Symfony\Component\HttpFoundation\Response
    {
        return $n->post($chemin, $champs, entetes: [Morceaux::DEMANDE => 'lignes', Morceaux::CLE => $cle]);
    }

    public function testUnAjoutRejoueNAjouteQuUneLigne(): void
    {
        $avant = $this->lignes();
        $champs = ['produit' => (string) $this->jeu->produit['a']];
        $premier = $this->envoyer($this->a, "{$this->offre}/lignes", $champs, 'cle-ajout-0001');
        self::assertSame(200, $premier->getStatusCode());
        self::assertSame($avant + 1, $this->lignes());
        // Le réseau a coupé après l'envoi : le téléphone le rejoue, avec la même clé.
        $second = $this->envoyer($this->a, "{$this->offre}/lignes", $champs, 'cle-ajout-0001');
        self::assertSame(200, $second->getStatusCode());
        self::assertSame($avant + 1, $this->lignes(), 'rejoué, il n\'ajoute rien');
        self::assertSame($premier->headers->get(Morceaux::ADRESSE), $second->headers->get(Morceaux::ADRESSE));
        self::assertStringContainsString('id="lignes"', (string) $second->getContent(), 'il reçoit les morceaux de la même page');
        // Un autre geste, une autre clé : il ajoute.
        $this->envoyer($this->a, "{$this->offre}/lignes", $champs, 'cle-ajout-0002');
        self::assertSame($avant + 2, $this->lignes());
    }

    public function testLaCleEstCelleDUnArtisan(): void
    {
        $this->envoyer($this->a, "{$this->offre}/lignes", ['produit' => (string) $this->jeu->produit['a']], 'cle-commune-01');
        $b = new Navigateur(Monde::conteneur());
        $b->connecter('b@exemple.ch');
        $offreB = '/offres/' . $this->jeu->offre['b'];
        $b->get($offreB);
        $avantB = BaseDeTest::compter('ligne', "offre_id = {$this->jeu->offre['b']} AND supprimee_le IS NULL");
        $r = $this->envoyer($b, "$offreB/lignes", ['produit' => (string) $this->jeu->produit['b']], 'cle-commune-01');
        self::assertSame(200, $r->getStatusCode());
        self::assertSame($avantB + 1, BaseDeTest::compter('ligne', "offre_id = {$this->jeu->offre['b']} AND supprimee_le IS NULL"), 'la clé de A ne bloque pas B');
        self::assertStringNotContainsString('Peinture murale a', (string) $r->getContent());
    }

    public function testUnGesteRefuseLibereSaCle(): void
    {
        // Un produit d'un autre artisan : refusé (404), rien n'est fait, la clé repart libre.
        $refus = $this->envoyer($this->a, "{$this->offre}/lignes", ['produit' => (string) $this->jeu->produit['b']], 'cle-refus-0001');
        self::assertSame(404, $refus->getStatusCode());
        self::assertSame(0, BaseDeTest::compter('envoi', "cle = 'cle-refus-0001'"));
        $avant = $this->lignes();
        $this->envoyer($this->a, "{$this->offre}/lignes", ['produit' => (string) $this->jeu->produit['a']], 'cle-refus-0001');
        self::assertSame($avant + 1, $this->lignes());
    }

    public function testUnEnvoiEncoreEnCoursSeReessaie(): void
    {
        BaseDeTest::pdo()->exec("INSERT INTO envoi (artisan_id, cle, adresse, recu_le) VALUES ({$this->jeu->a}, 'cle-en-cours-1', '', '2026-11-07 10:00:00')");
        $avant = $this->lignes();
        $r = $this->envoyer($this->a, "{$this->offre}/lignes", ['produit' => (string) $this->jeu->produit['a']], 'cle-en-cours-1');
        self::assertSame(409, $r->getStatusCode());
        self::assertStringContainsString('déjà en cours', (string) $r->getContent());
        self::assertSame($avant, $this->lignes());
    }

    public function testUneCleMalFormeeEstIgnoree(): void
    {
        $avant = $this->lignes();
        foreach (['court', str_repeat('a', 65), 'clé avec espace'] as $cle) {
            $this->envoyer($this->a, "{$this->offre}/lignes", ['produit' => (string) $this->jeu->produit['a']], $cle);
        }
        self::assertSame($avant + 3, $this->lignes(), 'sans clé valable, chaque envoi se fait');
        self::assertSame(0, BaseDeTest::compter('envoi'));
    }
}
