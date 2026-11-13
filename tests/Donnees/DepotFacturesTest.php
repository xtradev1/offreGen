<?php

declare(strict_types=1);

namespace OffreGen\Tests\Donnees;

use OffreGen\Donnees\DepotFactures;
use OffreGen\Donnees\DepotOffres;
use OffreGen\Donnees\Portee;
use OffreGen\Tests\Outils\BaseDeTest;
use OffreGen\Tests\Outils\Jeu;
use PHPUnit\Framework\TestCase;

/**
 * Une facture copie les lignes de son offre ; elle ne se corrige qu'en brouillon ; son numéro est unique chez
 * l'artisan ; l'artisan A ne voit ni ne touche rien chez B.
 */
final class DepotFacturesTest extends TestCase
{
    private Jeu $jeu;
    private DepotFactures $a;
    private DepotFactures $b;

    protected function setUp(): void
    {
        $this->jeu = new Jeu();
        $this->a = new DepotFactures($this->jeu->base, new Portee($this->jeu->a));
        $this->b = new DepotFactures($this->jeu->base, new Portee($this->jeu->b));
    }

    /** @param array<string, mixed> $autre */
    private function creer(DepotFactures $depot, string $qui, string $numero = '2026-001', array $autre = []): int
    {
        $offres = new DepotOffres($this->jeu->base, new Portee($qui === 'a' ? $this->jeu->a : $this->jeu->b));
        $lignes = array_map(static fn (array $l): array => [
            'designation' => $l['designation'], 'quantite_milliemes' => $l['quantite_milliemes'], 'unite' => $l['unite'],
            'prix_centimes' => $l['prix_centimes'], 'categorie' => $l['categorie'], 'produit_id' => $l['produit_id'],
        ], $offres->lignes($this->jeu->offre[$qui]));
        return $depot->creer($autre + [
            'offre_id' => $this->jeu->offre[$qui], 'numero' => $numero, 'genre' => 'finale',
            'acompte_rang' => null, 'acompte_nombre' => null, 'acompte_pourcent' => null,
            'date_facture' => new \DateTimeImmutable('2026-11-13'), 'echeance' => new \DateTimeImmutable('2026-12-13'),
            'titre' => "Offre $qui", 'client_nom' => "Client $qui", 'client_adresse' => "Rue $qui 1\n2800 Delémont",
        ], $lignes, $this->jeu->maintenant);
    }

    private function statut(int $id, string $statut): void
    {
        BaseDeTest::pdo()->exec("UPDATE facture SET statut = '$statut' WHERE id = $id");
    }

    public function testLaFactureCopieLesLignesEtLEnteteDeLOffre(): void
    {
        $id = $this->creer($this->a, 'a');
        $f = $this->a->parId($id);
        self::assertNotNull($f);
        self::assertSame('2026-001', $f['numero']);
        self::assertSame('brouillon', $f['statut']);
        self::assertSame(1, $f['offre_numero']);
        self::assertSame('2026-11-13', $f['date_facture']);
        self::assertSame('2026-12-13', $f['echeance']);
        self::assertSame("Rue a 1\n2800 Delémont", $f['client_adresse']);
        $lignes = $this->a->lignes($id);
        self::assertCount(1, $lignes);
        self::assertSame('Peinture murale a', $lignes[0]['designation']);
        self::assertSame(12500, $lignes[0]['quantite_milliemes']);
        self::assertSame('Peinture', $lignes[0]['categorie']);
        self::assertSame($this->jeu->produit['a'], $lignes[0]['produit_id']);
    }

    public function testCorrigerLOffreEnsuiteNeChangePasLaFacture(): void
    {
        $id = $this->creer($this->a, 'a');
        $offres = new DepotOffres($this->jeu->base, new Portee($this->jeu->a));
        $ligne = $offres->lignes($this->jeu->offre['a'])[0];
        $offres->corrigerLigne($this->jeu->offre['a'], $ligne['id'], 'Autre chose', 1000, 'pce', 1);
        self::assertSame('Peinture murale a', $this->a->lignes($id)[0]['designation']);
    }

    public function testDeuxFacturesNePortentPasLeMemeNumero(): void
    {
        $this->creer($this->a, 'a');
        self::assertTrue($this->a->numeroPris('2026-001'));
        self::assertTrue($this->a->numeroPris('2026-001 '), 'Les espaces de fin ne font pas un autre numéro (comparaison MySQL).');
        self::assertFalse($this->a->numeroPris('2026-002'));
        self::assertFalse($this->b->numeroPris('2026-001'), 'B a sa propre suite.');
        $this->expectException(\PDOException::class);
        $this->creer($this->a, 'a');
    }

    public function testLeNumeroDUneFactureNeLaGenePasElleMeme(): void
    {
        $id = $this->creer($this->a, 'a');
        self::assertFalse($this->a->numeroPris('2026-001', $id));
    }

    public function testLeDernierNumeroEstCeluiDeLaDerniereCreee(): void
    {
        self::assertNull($this->a->dernierNumero());
        $this->creer($this->a, 'a', '2026-009');
        $this->creer($this->a, 'a', '2026-003');
        self::assertSame('2026-003', $this->a->dernierNumero());
    }

    public function testBNeVoitNiNeToucheLesFacturesDeA(): void
    {
        $id = $this->creer($this->a, 'a');
        $ligne = $this->a->lignes($id)[0]['id'];
        self::assertNull($this->b->parId($id));
        self::assertSame([], $this->b->liste());
        self::assertSame([], $this->b->lignes($id));
        self::assertSame([], $this->b->deOffre($this->jeu->offre['a']));
        self::assertNull($this->b->ligne($id, $ligne));
        self::assertFalse($this->b->corrigerLigne($id, $ligne, 'Intrus', 1000, 'pce', 1));
        self::assertFalse($this->b->supprimerLigne($id, $ligne, $this->jeu->maintenant));
        self::assertSame(0, $this->b->ajouterLigne($id, 'Intrus', 1000, 'pce', 1, null));
        self::assertFalse($this->b->modifierEntete($id, 'X-1', new \DateTimeImmutable('2026-01-01'), new \DateTimeImmutable('2026-01-02'), $this->jeu->maintenant));
        self::assertFalse($this->b->supprimerBrouillon($id));
        self::assertSame('Peinture murale a', $this->a->lignes($id)[0]['designation']);
        self::assertSame(1, BaseDeTest::compter('facture_ligne'));
        self::assertSame('2026-001', $this->a->parId($id)['numero'] ?? null);
    }

    public function testOnNeFacturePasLOffreDeB(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->a->creer([
            'offre_id' => $this->jeu->offre['b'], 'numero' => '2026-001', 'genre' => 'finale',
            'acompte_rang' => null, 'acompte_nombre' => null, 'acompte_pourcent' => null,
            'date_facture' => new \DateTimeImmutable('2026-11-13'), 'echeance' => new \DateTimeImmutable('2026-12-13'),
            'titre' => 'Intrus', 'client_nom' => 'Intrus', 'client_adresse' => null,
        ], [], $this->jeu->maintenant);
    }

    public function testUneLigneDeFactureNePointePasLeProduitDeB(): void
    {
        $id = $this->creer($this->a, 'a');
        $ligne = $this->a->ligne($id, $this->a->ajouterLigne($id, 'Intrus', 1000, 'pce', 100, $this->jeu->produit['b']));
        self::assertNotNull($ligne);
        self::assertNull($ligne['produit_id']);
        self::assertNull($ligne['categorie'], 'La catégorie du produit de B ne passe pas non plus.');
    }

    public function testUneLigneDuCatalogueGardeSaCategorie(): void
    {
        $id = $this->creer($this->a, 'a');
        $ligne = $this->a->ajouterLigne($id, 'Peinture murale a', 1000, 'm²', 1850, $this->jeu->produit['a']);
        self::assertSame('Peinture', $this->a->ligne($id, $ligne)['categorie'] ?? null);
        $libre = $this->a->ajouterLigne($id, 'Retouche porte', 1000, 'forfait', 9500, null);
        $l = $this->a->ligne($id, $libre);
        self::assertNotNull($l);
        self::assertNull($l['categorie']);
        $this->a->rattacherProduit($id, $libre, $this->jeu->produit['a']);
        self::assertSame('Peinture', $this->a->ligne($id, $libre)['categorie'] ?? null);
    }

    public function testUnBrouillonSeCorrige(): void
    {
        $id = $this->creer($this->a, 'a');
        $ligne = $this->a->lignes($id)[0]['id'];
        self::assertTrue($this->a->corrigerLigne($id, $ligne, 'Peinture des murs', 42000, 'm²', 2800));
        self::assertTrue($this->a->modifierEntete($id, '2026-050', new \DateTimeImmutable('2026-11-14'), new \DateTimeImmutable('2026-12-14'), $this->jeu->maintenant));
        $f = $this->a->parId($id);
        self::assertSame(['2026-050', '2026-11-14', '2026-12-14'], [$f['numero'] ?? '', $f['date_facture'] ?? '', $f['echeance'] ?? '']);
        self::assertNotNull($f['modifiee_le'] ?? null);
        self::assertSame(42000, $this->a->lignes($id)[0]['quantite_milliemes']);
        self::assertTrue($this->a->supprimerLigne($id, $ligne, $this->jeu->maintenant));
        self::assertSame([], $this->a->lignes($id));
        self::assertTrue($this->a->restaurerLigne($id, $ligne));
        self::assertCount(1, $this->a->lignes($id));
    }

    public function testUneFactureEmiseOuAnnuleeNeSeCorrigePlus(): void
    {
        foreach (['emise', 'annulee'] as $i => $statut) {
            $id = $this->creer($this->a, 'a', "2026-00$i");
            $ligne = $this->a->lignes($id)[0]['id'];
            $this->statut($id, $statut);
            self::assertFalse($this->a->corrigerLigne($id, $ligne, 'Changée', 1000, 'pce', 1), $statut);
            self::assertFalse($this->a->supprimerLigne($id, $ligne, $this->jeu->maintenant), $statut);
            self::assertSame(0, $this->a->ajouterLigne($id, 'En plus', 1000, 'pce', 1, null), $statut);
            self::assertFalse($this->a->modifierEntete($id, 'X-1', new \DateTimeImmutable('2026-01-01'), new \DateTimeImmutable('2026-01-02'), $this->jeu->maintenant), $statut);
            self::assertFalse($this->a->supprimerBrouillon($id), $statut);
            self::assertSame('Peinture murale a', $this->a->lignes($id)[0]['designation'], $statut);
            self::assertCount(1, $this->a->lignes($id), $statut);
        }
    }

    public function testUneLigneSupprimeeDUneFactureEmiseNeRevientPas(): void
    {
        $id = $this->creer($this->a, 'a');
        $ligne = $this->a->lignes($id)[0]['id'];
        $this->a->supprimerLigne($id, $ligne, $this->jeu->maintenant);
        $this->statut($id, 'emise');
        self::assertFalse($this->a->restaurerLigne($id, $ligne));
        self::assertSame([], $this->a->lignes($id));
    }

    public function testSupprimerUnBrouillonLibereSonNumero(): void
    {
        $id = $this->creer($this->a, 'a');
        self::assertTrue($this->a->supprimerBrouillon($id));
        self::assertNull($this->a->parId($id));
        self::assertSame(0, BaseDeTest::compter('facture_ligne'));
        self::assertFalse($this->a->numeroPris('2026-001'));
    }

    public function testCeQueLOffreAFacture(): void
    {
        $o = $this->jeu->offre['a'];
        self::assertSame([0, false, 0], [$this->a->acomptesFaits($o), $this->a->finaleFaite($o), $this->a->dejaFacture($o)]);
        $acompte = ['genre' => 'acompte', 'acompte_rang' => 1, 'acompte_nombre' => 3, 'acompte_pourcent' => 30];
        $brouillon = $this->creer($this->a, 'a', '2026-001', $acompte);
        $emis = $this->creer($this->a, 'a', '2026-002', $acompte);
        $annule = $this->creer($this->a, 'a', '2026-003', $acompte);
        $this->statut($emis, 'emise');
        $this->statut($annule, 'annulee');
        BaseDeTest::pdo()->exec("UPDATE facture SET ttc = 93205 WHERE id IN ($emis, $annule, $brouillon)");
        self::assertSame(2, $this->a->acomptesFaits($o), 'Brouillon et émis comptent, l\'annulé non.');
        self::assertSame(93205, $this->a->dejaFacture($o), 'Seul l\'acompte émis se déduit.');
        self::assertFalse($this->a->finaleFaite($o));
        $finale = $this->creer($this->a, 'a', '2026-004');
        self::assertTrue($this->a->finaleFaite($o));
        $this->statut($finale, 'annulee');
        self::assertFalse($this->a->finaleFaite($o), 'Une finale annulée se refait.');
        self::assertSame(['2026-001', '2026-002', '2026-003', '2026-004'], array_column($this->a->deOffre($o), 'numero'));
        self::assertSame(0, $this->b->acomptesFaits($o));
        self::assertSame(0, $this->b->dejaFacture($o));
    }
}
