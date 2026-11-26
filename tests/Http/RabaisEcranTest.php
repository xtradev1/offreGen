<?php

declare(strict_types=1);

namespace OffreGen\Tests\Http;

use OffreGen\Donnees\DepotOffres;
use OffreGen\Donnees\Portee;
use OffreGen\Tests\Outils\BaseDeTest;
use OffreGen\Tests\Outils\Jeu;
use OffreGen\Tests\Outils\Monde;
use OffreGen\Tests\Outils\Navigateur;
use PHPUnit\Framework\TestCase;

/**
 * Rabais sous le sous-total, de l'écran à la facture (D4 B, D15 A, 2026-11-21).
 * Offre de A : 12,5 m² à 18.50, soit 231.25 HT. Avec 10 % : rabais 23.13 (23.125 arrondi), HT 208.12, TVA 16.86,
 * 224.98, arrondi aux 5 centimes : 225.00.
 */
final class RabaisEcranTest extends TestCase
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
        (new DepotOffres($this->jeu->base, new Portee($this->jeu->a)))->rattacherClient($this->jeu->offre['a'], $this->jeu->client['a'], $this->jeu->maintenant);
    }

    private function page(string $chemin): string
    {
        $r = $this->a->get($chemin);
        self::assertSame(200, $r->getStatusCode(), $chemin);
        return (string) $r->getContent();
    }

    /** @param array<string, string> $champs */
    private function envoyer(string $chemin, array $champs, int $attendu = 303): string
    {
        $this->page($this->offre);
        $r = $this->a->post($chemin, $champs);
        self::assertSame($attendu, $r->getStatusCode(), (string) $r->getContent());
        return (string) $r->getContent();
    }

    private function rabaisEnBase(): ?string
    {
        $v = BaseDeTest::ligne("SELECT rabais FROM offre WHERE id = {$this->jeu->offre['a']}")['rabais'];
        return $v === null ? null : (string) $v;
    }

    private static function chf(string $montant): string
    {
        return "CHF\u{00A0}" . $montant;
    }

    public function testLOffreProposePlusRabaisSousLeSousTotal(): void
    {
        $page = $this->page($this->offre);
        self::assertStringContainsString('<a class="lien plus" href="' . $this->offre . '/rabais" data-en-feuille>+ Rabais</a>', $page);
        $fenetre = $this->page($this->offre . '/rabais');
        self::assertStringContainsString('Nouveau rabais', $fenetre);
        self::assertStringContainsString('name="libelle" value="Rabais"', $fenetre);
        self::assertStringContainsString('<input type="radio" name="choix" value="5" checked>', $fenetre);
        self::assertStringContainsString(self::chf('231.25'), $fenetre, 'le sous-total des lignes, pour situer le rabais');
    }

    public function testAjouterDixPourCentBaisseLeTotal(): void
    {
        $this->envoyer($this->offre . '/rabais', ['libelle' => 'Rabais fidélité', 'choix' => '10', 'valeur' => '']);
        self::assertSame('[{"libelle":"Rabais fidélité","pour_dix_mille":1000,"centimes":null}]', $this->rabaisEnBase());
        $page = $this->page($this->offre);
        self::assertStringContainsString('href="' . $this->offre . '/rabais/1" data-en-feuille><span>Rabais fidélité', $page);
        self::assertStringContainsString('−&nbsp;' . self::chf('23.13'), $page);
        self::assertStringContainsString('<span>Total HT</span><span>' . self::chf('208.12') . '</span>', $page);
        self::assertMatchesRegularExpression('#data-total-fixe><span>Total TTC</span><b>CHF\x{00A0}225.00</b>#u', $page);
        // La liste des offres suit (totaux de la colonne PC et de l'accueil).
        self::assertStringContainsString(self::chf('225.00'), $this->page('/'));
    }

    public function testModifierPuisRetirerUnRabais(): void
    {
        $this->envoyer($this->offre . '/rabais', ['libelle' => 'Rabais', 'choix' => '5', 'valeur' => '']);
        $fenetre = $this->page($this->offre . '/rabais/1');
        self::assertStringContainsString('Modifier le rabais', $fenetre);
        self::assertStringContainsString('Retirer ce rabais', $fenetre);
        $this->envoyer($this->offre . '/rabais/1', ['libelle' => 'Geste commercial', 'choix' => 'chf', 'valeur' => '50']);
        self::assertSame('[{"libelle":"Geste commercial","pour_dix_mille":null,"centimes":5000}]', $this->rabaisEnBase());
        self::assertStringContainsString('name="valeur" value="50"', $this->page($this->offre . '/rabais/1'));
        $this->envoyer($this->offre . '/rabais/1/retirer', []);
        self::assertNull($this->rabaisEnBase());
        self::assertSame(404, $this->a->get($this->offre . '/rabais/1')->getStatusCode());
    }

    public function testUnAutrePourcentageSeRelitDansLaFenetre(): void
    {
        $this->envoyer($this->offre . '/rabais', ['libelle' => 'Rabais', 'choix' => 'pct', 'valeur' => '7.5']);
        $fenetre = $this->page($this->offre . '/rabais/1');
        self::assertStringContainsString('<input type="radio" name="choix" value="pct" checked>', $fenetre);
        self::assertStringContainsString('name="valeur" value="7.5"', $fenetre);
    }

    public function testUneValeurFausseEstRefuseeSansRienEcrire(): void
    {
        $page = $this->envoyer($this->offre . '/rabais', ['libelle' => 'Rabais', 'choix' => 'pct', 'valeur' => '120'], 422);
        self::assertStringContainsString('Pourcentage de plus de 0 et de moins de 100', $page);
        self::assertNull($this->rabaisEnBase());
    }

    public function testCinqRabaisAuPlus(): void
    {
        for ($i = 1; $i <= 5; $i++) {
            $this->envoyer($this->offre . '/rabais', ['libelle' => "R$i", 'choix' => 'chf', 'valeur' => '1']);
        }
        $page = $this->envoyer($this->offre . '/rabais', ['libelle' => 'R6', 'choix' => 'chf', 'valeur' => '1'], 422);
        self::assertStringContainsString('5 rabais au plus', $page);
    }

    /** Revue du 2026-11-22 : la somme des rabais ne dépasse pas le sous-total, sinon le pourcentage affiché serait faux. */
    public function testDesRabaisQuiDepassentLeSousTotalSontRefuses(): void
    {
        $this->envoyer($this->offre . '/rabais', ['libelle' => 'A', 'choix' => 'pct', 'valeur' => '60']);
        $page = $this->envoyer($this->offre . '/rabais', ['libelle' => 'B', 'choix' => 'pct', 'valeur' => '60'], 422);
        self::assertStringContainsString('Les rabais dépasseraient le sous-total des lignes', $page);
        self::assertCount(1, (array) json_decode((string) $this->rabaisEnBase(), true));
    }

    /** Revue du 2026-11-22 : une offre ne passe pas sous les acomptes déjà émis (la finale ne s'émettrait plus). */
    public function testUnRabaisNeFaitPasPasserLOffreSousLesAcomptesEmis(): void
    {
        $this->page($this->offre . '/facturer');
        $this->a->post($this->offre . '/facturer', ['genre' => 'acompte', 'pourcent' => '80', 'numero' => '', 'date' => '2026-11-07', 'echeance' => '2026-12-07', 'suite' => 'creer']);
        self::assertSame(1, BaseDeTest::compter('facture', "offre_id = {$this->jeu->offre['a']} AND statut = 'emise'"));
        self::assertStringContainsString('Des acomptes sont déjà facturés', $this->page($this->offre . '/rabais'));
        $page = $this->envoyer($this->offre . '/rabais', ['libelle' => 'Rabais', 'choix' => 'pct', 'valeur' => '30'], 422);
        self::assertStringContainsString('passerait sous les acomptes déjà facturés', html_entity_decode($page, ENT_QUOTES));
        self::assertNull($this->rabaisEnBase());
        // 10 % reste au-dessus des 80 % facturés.
        $this->envoyer($this->offre . '/rabais', ['libelle' => 'Rabais', 'choix' => '10', 'valeur' => '']);
        self::assertNotNull($this->rabaisEnBase());
    }

    /** Recette sur le test du 2026-11-22 : la finale émise garde ses montants, la fenêtre ne promet pas qu'elle suivra. */
    public function testFinaleEmiseLaFenetreDitQuElleGardeSesMontants(): void
    {
        $this->page($this->offre . '/facturer');
        $this->a->post($this->offre . '/facturer', ['genre' => 'acompte', 'pourcent' => '30', 'numero' => '', 'date' => '2026-11-07', 'echeance' => '2026-12-07', 'suite' => 'creer']);
        self::assertStringContainsString('Des acomptes sont déjà facturés', $this->page($this->offre . '/rabais'));
        $this->page($this->offre . '/facturer');
        $this->a->post($this->offre . '/facturer', ['genre' => 'finale', 'numero' => '', 'date' => '2026-11-07', 'echeance' => '2026-12-07', 'suite' => 'creer']);
        self::assertSame(1, BaseDeTest::compter('facture', "offre_id = {$this->jeu->offre['a']} AND genre = 'finale' AND statut = 'emise'"));
        $page = html_entity_decode($this->page($this->offre . '/rabais'), ENT_QUOTES);
        self::assertStringContainsString('La facture finale est déjà émise', $page);
        self::assertStringNotContainsString('suivront le nouveau total', $page);
    }

    public function testLeDocumentMontreLeRabaisAvantLaTva(): void
    {
        $this->envoyer($this->offre . '/rabais', ['libelle' => 'Rabais fidélité', 'choix' => '10', 'valeur' => '']);
        $doc = $this->page($this->offre . '/document');
        // Chaque cellule séparée par une espace, insécables comprises (\s en UTF-8) : « Sous-total 231.25 ».
        $texte = (string) preg_replace('/\s+/u', ' ', html_entity_decode((string) preg_replace('/<[^>]+>/', ' ', $doc)));
        $sousTotal = mb_strpos($texte, 'Sous-total 231.25');
        $rabais = mb_strpos($texte, 'Rabais fidélité 10 % − 23.13');
        $ht = mb_strpos($texte, 'Total HT 208.12');
        $tva = mb_strpos($texte, 'TVA 8,1');
        self::assertNotFalse($sousTotal, $texte);
        self::assertNotFalse($rabais, $texte);
        self::assertNotFalse($ht, $texte);
        self::assertNotFalse($tva, $texte);
        self::assertTrue($sousTotal < $rabais && $rabais < $ht && $ht < $tva, 'lignes, sous-total, rabais, HT, TVA (D15 A)');
    }

    public function testLaFactureFinaleCopieLesRabais(): void
    {
        $this->envoyer($this->offre . '/rabais', ['libelle' => 'Rabais fidélité', 'choix' => '10', 'valeur' => '']);
        $this->page($this->offre . '/facturer');
        $this->a->post($this->offre . '/facturer', ['genre' => 'finale', 'numero' => '2026-050', 'date' => '2026-11-07', 'echeance' => '2026-12-07', 'suite' => 'creer']);
        $f = BaseDeTest::ligne("SELECT id, rabais, ht, ttc FROM facture WHERE offre_id = {$this->jeu->offre['a']}");
        self::assertSame(['[{"libelle":"Rabais fidélité","pour_dix_mille":1000,"centimes":null}]', 20812, 22500], [$f['rabais'], (int) $f['ht'], (int) $f['ttc']]);
        // Retirer le rabais de l'offre ensuite ne change pas la facture émise : la liste la recalcule avec ses rabais à elle.
        $this->envoyer($this->offre . '/rabais/1/retirer', []);
        self::assertNotNull(BaseDeTest::ligne("SELECT rabais FROM facture WHERE id = {$f['id']}")['rabais']);
        self::assertStringContainsString('<span class="montant">225.00</span>', $this->page('/factures'));
    }

    public function testUnAcompteSeCompteSurLOffreApresRabais(): void
    {
        $this->envoyer($this->offre . '/rabais', ['libelle' => 'Rabais', 'choix' => '10', 'valeur' => '']);
        $this->page($this->offre . '/facturer');
        $this->a->post($this->offre . '/facturer', ['genre' => 'acompte', 'pourcent' => '30', 'numero' => '2026-051', 'date' => '2026-11-07', 'echeance' => '2026-12-07', 'suite' => 'creer']);
        // 30 % de 208.12 : 62.436, arrondi 62.44 HT. L'acompte ne porte pas le rabais lui-même.
        $f = BaseDeTest::ligne("SELECT f.rabais, fl.prix_centimes FROM facture f JOIN facture_ligne fl ON fl.facture_id = f.id WHERE f.offre_id = {$this->jeu->offre['a']}");
        self::assertSame([null, 6244], [$f['rabais'], (int) $f['prix_centimes']]);
    }

    /** Annuler et refaire reprend les rabais et la marque « offerte » (oubliée au lot 1, trouvée le 2026-11-22). */
    public function testAnnulerEtRefaireGardeRabaisEtLigneOfferte(): void
    {
        $this->envoyer($this->offre . '/rabais', ['libelle' => 'Rabais', 'choix' => 'chf', 'valeur' => '20']);
        BaseDeTest::pdo()->exec("INSERT INTO ligne (artisan_id, offre_id, rang, designation, quantite_milliemes, unite, prix_centimes, offerte, a_verifier)
            VALUES ({$this->jeu->a}, {$this->jeu->offre['a']}, 2, 'Retouches', 1000, 'forfait', 5000, 1, 0)");
        $this->page($this->offre . '/facturer');
        $this->a->post($this->offre . '/facturer', ['genre' => 'finale', 'numero' => '2026-052', 'date' => '2026-11-07', 'echeance' => '2026-12-07', 'suite' => 'creer']);
        $id = (int) BaseDeTest::ligne("SELECT id FROM facture WHERE offre_id = {$this->jeu->offre['a']}")['id'];
        $this->page("/factures/$id/annuler");
        self::assertSame(303, $this->a->post("/factures/$id/annuler", ['refaire' => '1'])->getStatusCode());
        $nouvelle = (int) BaseDeTest::ligne('SELECT MAX(id) AS id FROM facture')['id'];
        self::assertNotSame($id, $nouvelle);
        self::assertSame('[{"libelle":"Rabais","pour_dix_mille":null,"centimes":2000}]', BaseDeTest::ligne("SELECT rabais FROM facture WHERE id = $nouvelle")['rabais']);
        self::assertSame(1, BaseDeTest::compter('facture_ligne', "facture_id = $nouvelle AND designation = 'Retouches' AND offerte = 1"));
    }
}
