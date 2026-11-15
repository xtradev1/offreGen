<?php

declare(strict_types=1);

namespace OffreGen\Tests\Http;

use OffreGen\Donnees\DepotEnregistrements;
use OffreGen\Donnees\DepotOffres;
use OffreGen\Donnees\Portee;
use OffreGen\Offre\LigneExtraite;
use OffreGen\Tests\Outils\Jeu;
use OffreGen\Tests\Outils\Monde;
use OffreGen\Tests\Outils\Navigateur;
use PHPUnit\Framework\TestCase;

/**
 * Version PC (décisions D12 et D13 de Xavier, 2026-11-15) : à partir de 1024 px, la barre du bas devient un menu à
 * gauche (avec Catalogue, Réglages, « Dicter une offre » et « Importer ») ; l'offre montre la liste des offres dans
 * une colonne ; un bouton met le document à côté des lignes. La mise en page est en CSS : le serveur rend les mêmes
 * éléments à toutes les largeurs, ce test vérifie qu'ils y sont et qu'ils ne mélangent pas les comptes.
 */
final class VersionPcTest extends TestCase
{
    private Jeu $jeu;
    private Navigateur $a;

    protected function setUp(): void
    {
        $this->jeu = new Jeu();
        $this->a = new Navigateur(Monde::conteneur());
        $this->a->connecter('a@exemple.ch');
    }

    private function page(string $chemin): string
    {
        $reponse = $this->a->get($chemin);
        self::assertSame(200, $reponse->getStatusCode(), $chemin);
        return (string) $reponse->getContent();
    }

    /** Le menu de la barre, entre <nav class="tabs" et </nav>. */
    private static function menu(string $page): string
    {
        self::assertSame(1, preg_match('#<nav class="tabs[ "].*?</nav>#s', $page, $m));
        return $m[0] ?? '';
    }

    public function testLeMenuPortaitLesRubriquesDuPc(): void
    {
        $menu = self::menu($this->page('/clients'));
        self::assertStringContainsString('<a class="pc-seul" href="/catalogue"', $menu);
        self::assertStringContainsString('<a class="pc-seul" href="/reglages"', $menu);
        self::assertMatchesRegularExpression('#<a class="mobile-seul" href="/plus"#', $menu);
        self::assertMatchesRegularExpression('#data-enregistrer.*<b class="pc-seul">Dicter une offre</b>#s', $menu);
        self::assertMatchesRegularExpression('#<a class="pc-seul importer" href="/import" data-importer>#', $menu);
        self::assertStringContainsString('<kbd>D</kbd>', $menu);
        // Catalogue allume son entrée sur PC, et « Plus » sur le téléphone.
        $catalogue = self::menu($this->page('/catalogue'));
        self::assertStringContainsString('<a class="pc-seul" href="/catalogue" aria-current="page"', $catalogue);
        self::assertStringContainsString('<a class="mobile-seul" href="/plus" aria-current="page"', $catalogue);
    }

    public function testLOffreMontreLaListeDesOffresDuCompteSeulement(): void
    {
        $portee = new Portee($this->jeu->a);
        $enr = (new DepotEnregistrements($this->jeu->base, $portee))
            ->creer('00000000-0000-4000-8000-0000000000a9', 'visite-a2.m4a', 'audio/mp4', 1000, $this->jeu->a . '/a2.m4a', $this->jeu->maintenant);
        $autre = (new DepotOffres($this->jeu->base, $portee))->creerDepuisEnregistrement($enr, 'Façade de la maison',
            [new LigneExtraite('Crépi', 1000, 'm²', 0, true, null)], $this->jeu->maintenant);

        $page = $this->page('/offres/' . $this->jeu->offre['a']);
        self::assertSame(1, preg_match('#<aside class="colonne-liste".*?</aside>#s', $page, $m));
        $liste = $m[0] ?? '';
        self::assertStringContainsString('<a class="lcard" href="/offres/' . $this->jeu->offre['a'] . '" aria-current="page"', $liste);
        self::assertStringContainsString('<a class="lcard" href="/offres/' . $autre . '"', $liste);
        self::assertStringContainsString('Façade de la maison', $liste);
        self::assertStringContainsString('1 à vérifier', $liste);
        self::assertStringContainsString('data-filtre="#liste-pc"', $liste);
        // L'autre compte n'y paraît pas.
        self::assertStringNotContainsString('Offre b', $liste);
        self::assertStringNotContainsString('/offres/' . $this->jeu->offre['b'] . '"', $liste);
    }

    public function testLOffreProposeLeDocumentACoteEtLesTouches(): void
    {
        $id = $this->jeu->offre['a'];
        $page = $this->page("/offres/$id");
        self::assertStringContainsString('data-doc-cote="/offres/' . $id . '/document"', $page);
        self::assertStringContainsString('<p class="aide-clavier pc-seul">', $page);
        foreach (['Tab', 'Entrée', 'J', 'Suppr'] as $touche) {
            self::assertStringContainsString("<kbd>$touche</kbd>", $page);
        }
        // Le document que la colonne reprend porte son article.
        self::assertStringContainsString('<article class="paper">', $this->page("/offres/$id/document"));
    }

    /**
     * E8 (maquette 25, Xavier, 2026-11-15) : sur PC, le menu reste sur tous les écrans, petits compris ; au téléphone,
     * ces écrans gardent leur seule barre d'actions (le menu y est « pc-seul »). La rubrique de l'écran est allumée.
     * Sans compte (connexion), pas de menu.
     */
    public function testLeMenuEstSurTousLesEcransDuPc(): void
    {
        $o = '/offres/' . $this->jeu->offre['a'];
        $ligne = (int) \OffreGen\Tests\Outils\BaseDeTest::ligne("SELECT id FROM ligne WHERE offre_id = {$this->jeu->offre['a']}")['id'];
        $petits = [
            "$o/ajouter" => 'offres', "$o/lignes/$ligne" => 'offres', "$o/modifier" => 'offres', "$o/client" => 'offres',
            "$o/facturer" => 'offres', "$o/apercu" => 'offres', "$o/document" => 'offres', '/clients/nouveau' => 'clients',
        ];
        foreach ($petits as $chemin => $actif) {
            $menu = self::menu($this->page($chemin));
            self::assertStringStartsWith('<nav class="tabs pc-seul"', $menu, $chemin);
            $href = $actif === 'offres' ? '/' : '/' . $actif;
            self::assertStringContainsString('<a href="' . $href . '" aria-current="page">', $menu, "$chemin : rubrique $actif");
        }
        // Les écrans à onglets les gardent au téléphone.
        self::assertStringStartsWith('<nav class="tabs" aria-label', self::menu($this->page($o)));
        // Sans compte : aucun menu.
        $anonyme = new Navigateur(Monde::conteneur());
        self::assertStringNotContainsString('<nav class="tabs', (string) $anonyme->get('/connexion')->getContent());
    }

    /**
     * Xavier, 2026-11-15 : une tablette reçoit la vue du téléphone. La largeur seule ne suffit pas (un iPad en paysage
     * fait 1180 px) : la version PC demande aussi une souris ou un pavé tactile. Chaque requête de largeur de app.css
     * et de app.js porte donc la condition de pointeur, et le téléphone prend tout ce qui n'est pas « PC ».
     */
    public function testLaVersionPcDemandeUnPointeurFin(): void
    {
        $racine = dirname(__DIR__, 2) . '/public';
        $css = (string) file_get_contents($racine . '/css/app.css');
        $js = (string) file_get_contents($racine . '/js/app.js');
        $pointeur = 'and (hover: hover) and (pointer: fine)';
        self::assertSame(1, substr_count($css, "@media (min-width: 1024px) $pointeur {"), 'bloc PC de app.css');
        self::assertSame(1, substr_count($css, "@media not all and (min-width: 1024px) $pointeur {"), 'bloc téléphone de app.css');
        self::assertDoesNotMatchRegularExpression('/@media \((?:max|min)-width: 10[0-9]{2}(?:\.98)?px\) \{/', $css, 'une largeur seule');
        preg_match_all("/matchMedia\\('([^']+)'\\)/", $js, $m);
        self::assertNotEmpty($m[1]);
        foreach ($m[1] as $requete) {
            self::assertStringEndsWith($pointeur, $requete, $requete);
        }
    }
}
