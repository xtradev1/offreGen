<?php

declare(strict_types=1);

namespace OffreGen\Tests\Http;

use OffreGen\Tests\Outils\BaseDeTest;
use OffreGen\Tests\Outils\Jeu;
use OffreGen\Tests\Outils\Monde;
use OffreGen\Tests\Outils\Navigateur;
use PHPUnit\Framework\TestCase;

/**
 * TVA (Xavier, 2026-11-15 ; décision D11) : l'entreprise a un taux (8,1, 2,6, 3,8 ou 0 %) ; une ligne, ou un produit du
 * catalogue, peut en avoir un autre ; le document détaille la TVA par taux dès qu'il y en a plusieurs. La facture copie
 * les taux de l'offre ; un acompte sur une offre à plusieurs taux se répartit par taux.
 * Jeu : une ligne « Peinture murale a », 12,5 m² à 18.50 (231.25), rattachée au produit « Peinture murale a ».
 */
final class TvaTest extends TestCase
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
    }

    private function page(string $chemin): string
    {
        $reponse = $this->a->get($chemin);
        self::assertSame(200, $reponse->getStatusCode(), $chemin);
        return (string) $reponse->getContent();
    }

    /** Une ligne libre de 100.00 au taux de l'entreprise, à côté de la ligne du jeu. */
    private function ligneLibre(?int $taux = null): int
    {
        $pdo = BaseDeTest::pdo();
        $r = $pdo->prepare('INSERT INTO ligne (artisan_id, offre_id, rang, designation, quantite_milliemes, unite, prix_centimes, tva_pour_dix_mille) VALUES (?, ?, 9, ?, 1000, ?, 10000, ?)');
        $r->execute([$this->jeu->a, $this->jeu->offre['a'], 'Fourniture de papier peint', 'forfait', $taux]);
        return (int) $pdo->lastInsertId();
    }

    public function testLEntrepriseChoisitParmiLesQuatreTaux(): void
    {
        $page = $this->page('/reglages/tva');
        foreach (['810', '260', '380', '0'] as $v) {
            self::assertStringContainsString("name=\"tva\" value=\"$v\"", $page);
        }
        self::assertSame(303, $this->a->post('/reglages', ['tva' => '260', 'arrondi' => '1', 'retour' => '/reglages/tva'])->getStatusCode());
        self::assertSame(260, (int) BaseDeTest::ligne("SELECT tva_pour_dix_mille FROM artisan WHERE id = {$this->jeu->a}")['tva_pour_dix_mille']);
    }

    public function testUneLignePrendUnAutreTauxEtLeDocumentLesDetaille(): void
    {
        $libre = $this->ligneLibre();
        $fiche = $this->page("{$this->offre}/lignes/$libre");
        self::assertMatchesRegularExpression('#name="tva" value=""[^>]*checked#', $fiche, 'par défaut : le taux de l\'entreprise');
        foreach (['810', '260', '380', '0'] as $v) {
            self::assertStringContainsString("name=\"tva\" value=\"$v\"", $fiche);
        }
        $this->a->post("{$this->offre}/lignes/$libre", ['designation' => 'Fourniture de papier peint', 'quantite' => '1', 'unite' => 'forfait', 'prix' => '100', 'tva' => '260']);
        self::assertSame(260, (int) BaseDeTest::ligne("SELECT tva_pour_dix_mille FROM ligne WHERE id = $libre")['tva_pour_dix_mille']);

        // 231.25 à 8,1 % : 18.73 ; 100.00 à 2,6 % : 2.60.
        $offre = $this->page($this->offre);
        self::assertMatchesRegularExpression('#TVA 8,1\x{00A0}% sur CHF\x{00A0}231\.25</span><span>CHF\x{00A0}18\.73#u', $offre);
        self::assertMatchesRegularExpression('#TVA 2,6\x{00A0}% sur CHF\x{00A0}100\.00</span><span>CHF\x{00A0}2\.60#u', $offre);
        $document = $this->page($this->offre . '/document');
        self::assertMatchesRegularExpression('#TVA 2,6\x{00A0}% sur 100\.00#u', $document);
        // La ligne à un autre taux le porte ; celle au taux de l'entreprise, non.
        self::assertMatchesRegularExpression('#Fourniture de papier peint<span class="doc-tva"> · TVA 2,6\x{00A0}%</span>#u', $document);
        self::assertSame(1, substr_count($document, 'class="doc-tva"'));

        // Revenir au taux de l'entreprise : une seule ligne de TVA, comme avant.
        $this->page("{$this->offre}/lignes/$libre");
        $this->a->post("{$this->offre}/lignes/$libre", ['designation' => 'Fourniture de papier peint', 'quantite' => '1', 'unite' => 'forfait', 'prix' => '100', 'tva' => '']);
        self::assertNull(BaseDeTest::ligne("SELECT tva_pour_dix_mille FROM ligne WHERE id = $libre")['tva_pour_dix_mille']);
        self::assertStringNotContainsString('% sur CHF', $this->page($this->offre));
    }

    public function testLeTauxDuProduitPasseASesLignes(): void
    {
        $fiche = $this->page('/catalogue/' . $this->jeu->produit['a']);
        self::assertStringContainsString('name="tva"', $fiche);
        self::assertSame(303, $this->a->post('/catalogue/' . $this->jeu->produit['a'], ['categorie' => 'Peinture', 'nouvelle_categorie' => '', 'designation' => 'Peinture murale a',
            'unite' => 'm²', 'prix' => '18.50', 'synonymes' => '', 'tva' => '380'])->getStatusCode());
        self::assertSame(380, (int) BaseDeTest::ligne('SELECT tva_pour_dix_mille FROM produit WHERE id = ' . $this->jeu->produit['a'])['tva_pour_dix_mille']);
        // La ligne du produit prend 3,8 % ; seule, une seule ligne de TVA : 231.25 × 3,8 % = 8.7875 → 8.79.
        self::assertMatchesRegularExpression('#TVA 3,8\x{00A0}%</span><span>CHF\x{00A0}8\.79#u', $this->page($this->offre));
    }

    public function testLaFactureFinaleCopieLesTauxEtUnAcompteSeRepartit(): void
    {
        BaseDeTest::pdo()->exec("UPDATE offre SET client_id = {$this->jeu->client['a']} WHERE id = {$this->jeu->offre['a']}");
        $this->ligneLibre(260);
        $this->page('/reglages');
        $this->a->post('/reglages', ['paiement_type' => 'acomptes', 'paiement_acomptes_choix' => '30/30/40', 'retour' => '/reglages']);
        $this->page($this->offre . '/facturer');
        $this->a->post($this->offre . '/facturer', ['genre' => 'acompte', 'pourcent' => '30', 'numero' => '2026-060', 'date' => '2026-11-07', 'echeance' => '2026-12-07', 'suite' => 'creer']);
        $acompte = (int) BaseDeTest::ligne("SELECT id FROM facture WHERE numero = '2026-060'")['id'];
        // 30 % de 231.25 = 69.375 → 69.38 à 8,1 % ; 30 % de 100.00 = 30.00 à 2,6 %.
        $lignes = self::lignesDe($acompte);
        self::assertSame([[6938, 810], [3000, 260]], array_map(static fn (array $l): array => [$l['prix'], $l['tva']], $lignes));

        $this->page($this->offre . '/facturer');
        $this->a->post($this->offre . '/facturer', ['genre' => 'finale', 'numero' => '', 'date' => '2026-11-07', 'echeance' => '2026-12-07', 'suite' => 'corriger']);
        $finale = (int) BaseDeTest::ligne("SELECT id FROM facture WHERE genre = 'finale'")['id'];
                // Sans exception, la ligne garde un taux nul : celui de l'entreprise, figé à l'émission avec la facture.
        self::assertSame([null, 260], array_column(self::lignesDe($finale), 'tva'));
        self::assertMatchesRegularExpression('#TVA 2,6\x{00A0}% sur 100\.00#u', $this->page("/factures/$finale/document"));
    }

    /** @return list<array{prix: int, tva: int|null}> les lignes d'une facture, dans l'ordre */
    private static function lignesDe(int $factureId): array
    {
        $lignes = [];
        $requete = BaseDeTest::pdo()->query("SELECT prix_centimes, tva_pour_dix_mille FROM facture_ligne WHERE facture_id = $factureId ORDER BY rang");
        foreach ($requete === false ? [] : $requete->fetchAll(\PDO::FETCH_NUM) as $l) {
            $lignes[] = ['prix' => (int) $l[0], 'tva' => $l[1] === null ? null : (int) $l[1]];
        }
        return $lignes;
    }
}
