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

    /** Revue du 2026-11-23, B1 : un processus tué laisse sa réservation vide ; passé trois minutes, le geste repart. */
    public function testUneReservationAbandonneeLaissePartirLeGeste(): void
    {
        BaseDeTest::pdo()->exec("INSERT INTO envoi (artisan_id, cle, adresse, recu_le) VALUES ({$this->jeu->a}, 'cle-abandon-01', '', '2026-11-07 09:50:00')");
        $avant = $this->lignes();
        $r = $this->envoyer($this->a, "{$this->offre}/lignes", ['produit' => (string) $this->jeu->produit['a']], 'cle-abandon-01');
        self::assertSame(200, $r->getStatusCode());
        self::assertSame($avant + 1, $this->lignes());
        self::assertNotSame('', BaseDeTest::ligne("SELECT adresse FROM envoi WHERE cle = 'cle-abandon-01'")['adresse'], 'la clé est notée');
    }

    /** Revue du 2026-11-23, I1 : la réponse s'est perdue ; rejoué, le geste retrouve ses messages (« Annuler », un refus). */
    public function testUnGesteRejoueRetrouveSesMessages(): void
    {
        $ligne = (int) BaseDeTest::ligne("SELECT id FROM ligne WHERE offre_id = {$this->jeu->offre['a']}")['id'];
        $suppression = fn (): string => (string) $this->a->post("{$this->offre}/lignes/$ligne/supprimer", [],
            entetes: [Morceaux::DEMANDE => 'totaux', Morceaux::CLE => 'cle-annuler-01'])->getContent();
        self::assertStringContainsString("/lignes/$ligne/restaurer", $suppression());
        self::assertStringContainsString("/lignes/$ligne/restaurer", $suppression(), 'rejoué : « Annuler » revient');

        $refus = fn (): string => (string) $this->a->post("{$this->offre}/lignes/$ligne/restaurer", [],
            entetes: [Morceaux::DEMANDE => 'totaux', Morceaux::CLE => 'cle-remise-01'])->getContent();
        self::assertStringContainsString('Ligne remise', $refus());
        self::assertStringContainsString('Ligne remise', $refus(), 'rejoué : le message revient');
    }

    /** Revue du 2026-11-23, sécurité I1 : un geste fait dans la page d'un compte ne part jamais dans un autre. */
    public function testUnGesteDUnAutreCompteEstRefuseSansRienFaire(): void
    {
        $avant = $this->lignes();
        $r = $this->a->post("{$this->offre}/lignes", ['produit' => (string) $this->jeu->produit['a']],
            entetes: [Morceaux::DEMANDE => 'lignes', Morceaux::CLE => 'cle-compte-001', Morceaux::COMPTE => (string) $this->jeu->b]);
        self::assertSame(409, $r->getStatusCode());
        self::assertSame('autre', $r->headers->get(Morceaux::COMPTE_AUTRE));
        self::assertSame($avant, $this->lignes());
        self::assertSame(0, BaseDeTest::compter('envoi', "cle = 'cle-compte-001'"));
        $ok = $this->a->post("{$this->offre}/lignes", ['produit' => (string) $this->jeu->produit['a']],
            entetes: [Morceaux::DEMANDE => 'lignes', Morceaux::CLE => 'cle-compte-002', Morceaux::COMPTE => (string) $this->jeu->a]);
        self::assertSame(200, $ok->getStatusCode());
        self::assertSame($avant + 1, $this->lignes());
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
