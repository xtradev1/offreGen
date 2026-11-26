<?php

declare(strict_types=1);

namespace OffreGen\Tests\Http;

use OffreGen\Tests\Outils\BaseDeTest;
use OffreGen\Tests\Outils\Jeu;
use OffreGen\Tests\Outils\Monde;
use OffreGen\Tests\Outils\Navigateur;
use PHPUnit\Framework\TestCase;

/**
 * Relecture au pouce, une ligne à la fois (décision D5 de Xavier, maquette 24, 2026-11-15). Une ligne est à relire si
 * elle est « à vérifier » ou sans prix. « C'est juste » enregistre la quantité et le prix montrés, puis passe à la
 * suivante. Le rappel de la dictée est permis ici (D5, CODING_RULES).
 * Jeu : une ligne « Peinture murale a », 12,5 m² à 18.50.
 */
final class RelectureTest extends TestCase
{
    private Jeu $jeu;
    private Navigateur $a;
    private int $ligne;
    private string $offre;

    protected function setUp(): void
    {
        $this->jeu = new Jeu();
        $this->a = new Navigateur(Monde::conteneur());
        $this->a->connecter('a@exemple.ch');
        $this->offre = '/offres/' . $this->jeu->offre['a'];
        $this->ligne = (int) BaseDeTest::ligne("SELECT id FROM ligne WHERE offre_id = {$this->jeu->offre['a']}")['id'];
    }

    private function page(string $chemin): string
    {
        $reponse = $this->a->get($chemin);
        self::assertSame(200, $reponse->getStatusCode(), $chemin);
        return (string) $reponse->getContent();
    }

    private function nouvelleLigne(string $designation, int $milliemes, int $centimes, int $aVerifier, ?string $entendu = null): int
    {
        $pdo = BaseDeTest::pdo();
        $requete = $pdo->prepare('INSERT INTO ligne (artisan_id, offre_id, rang, designation, quantite_milliemes, unite, prix_centimes, a_verifier, entendu) VALUES (?, ?, 9, ?, ?, ?, ?, ?, ?)');
        $requete->execute([$this->jeu->a, $this->jeu->offre['a'], $designation, $milliemes, 'm²', $centimes, $aVerifier, $entendu]);
        return (int) $pdo->lastInsertId();
    }

    /** @return array<string, mixed> */
    private function ligneEnBase(int $id): array
    {
        return BaseDeTest::ligne("SELECT * FROM ligne WHERE id = $id");
    }

    public function testUneLigneAVerifierSeMontreEnGrandAvecCeQuiAEteEntendu(): void
    {
        BaseDeTest::pdo()->exec("UPDATE ligne SET a_verifier = 1, entendu = 'les murs, environ douze mètres et demi' WHERE id = {$this->ligne}");
        $page = $this->page($this->offre . '/relire');
        self::assertStringContainsString('<h2 class="relu-nom">Peinture murale a</h2>', $page);
        self::assertStringContainsString('Entendu&nbsp;: «&nbsp;les murs, environ douze mètres et demi&nbsp;»', $page);
        self::assertMatchesRegularExpression("#<form id=\"relecture\" method=\"post\" action=\"{$this->offre}/relire/{$this->ligne}\">#", $page);
        self::assertMatchesRegularExpression('#name="quantite" value="12.5"[^>]*inputmode="decimal"#', $page);
        self::assertMatchesRegularExpression('#name="prix" value="18.50"[^>]*inputmode="decimal"#', $page);
        self::assertStringContainsString('Encore 1 à relire', $page);
        self::assertMatchesRegularExpression('#<button class="btn grand ok" type="submit" form="relecture" data-juste="C\'est juste" data-manque="Indiquez le prix">.*<span data-relu-libelle>C\'est juste</span></button>#s', $page);
        self::assertStringContainsString("href=\"{$this->offre}/lignes/{$this->ligne}\"", $page, '« Corriger » ouvre la ligne entière');
    }

    public function testCestJusteEnregistrePuisPasseALaSuivante(): void
    {
        BaseDeTest::pdo()->exec("UPDATE ligne SET a_verifier = 1 WHERE id = {$this->ligne}");
        $deuxieme = $this->nouvelleLigne('Plafond', 24000, 3200, 1);
        $this->page($this->offre . '/relire');
        $r = $this->a->post("{$this->offre}/relire/{$this->ligne}", ['quantite' => '14', 'prix' => '18.50']);
        self::assertSame(303, $r->getStatusCode());
        self::assertSame($this->offre . '/relire', $r->headers->get('Location'));
        $l = $this->ligneEnBase($this->ligne);
        self::assertSame([14000, 1850, 0], [(int) $l['quantite_milliemes'], (int) $l['prix_centimes'], (int) $l['a_verifier']]);
        // Toutes les lignes à relire sont dans la page (chantier sans rechargement) : la visible est la première, les
        // autres et la fin sont cachées. Revue du 2026-11-23 : sans regarder hidden, ces assertions passaient à vide.
        $suite = $this->page($this->offre . '/relire');
        self::assertMatchesRegularExpression("#<section data-relu=\"$deuxieme\" data-corriger=\"[^\"]+\">.*?<h2 class=\"relu-nom\">Plafond</h2>#s", $suite, 'Plafond est la ligne visible');
        self::assertMatchesRegularExpression('#<div class="relu-fin" hidden data-relu-fin>#', $suite, 'la fin est cachée tant qu\'une ligne reste');
        $this->a->post("{$this->offre}/relire/$deuxieme", ['quantite' => '24', 'prix' => '32']);
        $fin = $this->page($this->offre . '/relire');
        self::assertMatchesRegularExpression('#<div class="relu-fin" data-relu-fin>.*?Toutes les lignes sont relues#s', $fin, 'la fin est visible');
        self::assertStringNotContainsString('<section data-relu=', $fin, 'plus aucune ligne à relire');
        // L'offre du jeu n'a pas de client : « Générer l'offre » passe par son choix, puis le PDF (Offre\Arrivee).
        self::assertMatchesRegularExpression("#<div class=\"actionbar\" data-relu-fin>.*?href=\"{$this->offre}/client\?suite=apercu\"#s", $fin, 'le geste suivant : générer l\'offre');
    }

    /** Xavier, 2026-11-26 : « Après la dernière ligne relue : PDF directement ». */
    public function testLaDerniereLigneRelueOuvreLePdf(): void
    {
        BaseDeTest::pdo()->exec("UPDATE ligne SET a_verifier = 1 WHERE id = {$this->ligne}");
        BaseDeTest::pdo()->exec("UPDATE offre SET client_id = {$this->jeu->client['a']} WHERE id = {$this->jeu->offre['a']}");
        $this->page($this->offre . '/relire');
        $r = $this->a->post("{$this->offre}/relire/{$this->ligne}", ['quantite' => '12.5', 'prix' => '18.50']);
        self::assertSame(303, $r->getStatusCode());
        self::assertSame($this->offre . '/apercu', $r->headers->get('Location'));
        self::assertMatchesRegularExpression("#<div class=\"actionbar\" data-relu-fin>.*?href=\"{$this->offre}/apercu\"#s", $this->page($this->offre . '/relire'));
    }

    /** Le client manque encore : son choix d'abord (« Pour qui est cette offre ? »), qui mène au PDF. */
    public function testLaDerniereLigneRelueSansClientMeneAuChoixDuClient(): void
    {
        BaseDeTest::pdo()->exec("UPDATE ligne SET a_verifier = 1 WHERE id = {$this->ligne}");
        $this->page($this->offre . '/relire');
        $r = $this->a->post("{$this->offre}/relire/{$this->ligne}", ['quantite' => '12.5', 'prix' => '18.50']);
        self::assertSame($this->offre . '/client?suite=apercu', $r->headers->get('Location'));
    }

    /** Par la file de fluide.js : la réponse envoie le navigateur au PDF (Morceaux::aller), sans page à découper. */
    public function testLaDerniereLigneRelueParLaFileEnvoieLeNavigateurAuPdf(): void
    {
        BaseDeTest::pdo()->exec("UPDATE ligne SET a_verifier = 1 WHERE id = {$this->ligne}");
        BaseDeTest::pdo()->exec("UPDATE offre SET client_id = {$this->jeu->client['a']} WHERE id = {$this->jeu->offre['a']}");
        $this->page($this->offre . '/relire');
        $r = $this->a->post("{$this->offre}/relire/{$this->ligne}", ['quantite' => '12.5', 'prix' => '18.50'], [], true, false,
            ['X-Morceaux' => '', 'X-Morceaux-Ici' => $this->offre . '/relire', 'X-Cle-Envoi' => 'cle-relecture-0001', 'X-Compte' => (string) $this->jeu->a]);
        self::assertSame(200, $r->getStatusCode());
        self::assertSame('1', $r->headers->get('X-Morceaux-Aller'));
        self::assertSame($this->offre . '/apercu', $r->headers->get('X-Morceaux-Adresse'));
    }

    /** Toutes les lignes à relire sont dans la page, la première visible : la suivante paraît sans requête (fluide.js). */
    public function testLaRelecturePorteToutesSesLignesLaPremiereVisible(): void
    {
        BaseDeTest::pdo()->exec("UPDATE ligne SET a_verifier = 1 WHERE id = {$this->ligne}");
        $deuxieme = $this->nouvelleLigne('Plafond', 24000, 3200, 1);
        $page = $this->page($this->offre . '/relire');
        self::assertMatchesRegularExpression("#<section data-relu=\"{$this->ligne}\" data-corriger=\"[^\"]+\">#", $page);
        self::assertMatchesRegularExpression("#<section data-relu=\"$deuxieme\" data-corriger=\"[^\"]+\" hidden>#", $page);
        self::assertMatchesRegularExpression("#<form id=\"relecture-$deuxieme\" method=\"post\" action=\"{$this->offre}/relire/$deuxieme\">#", $page);
        self::assertStringContainsString('Encore 2 à relire', $page);
        self::assertStringContainsString('Encore 1 à relire', $page);
    }

    public function testUneLigneSansPrixDemandeUnPrix(): void
    {
        $libre = $this->nouvelleLigne('Crépi', 18000, 0, 0, 'refaire le crépi en bas');
        $page = $this->page($this->offre . '/relire');
        self::assertStringContainsString('<h2 class="relu-nom">Crépi</h2>', $page);
        self::assertMatchesRegularExpression('#name="prix" value=""#', $page);
        // Constat 1 de l'analyse des parcours (Xavier, 2026-11-26) : sans prix, le bouton ne dit pas « C'est juste » et le
        // curseur est dans le prix.
        self::assertMatchesRegularExpression('#<button class="btn grand" type="submit" form="relecture" data-juste="C\'est juste" data-manque="Indiquez le prix">.*<span data-relu-libelle>Indiquez le prix</span></button>#s', $page);
        self::assertMatchesRegularExpression('#name="prix" value=""[^>]*data-relu-prix autofocus>#', $page);
        $r = $this->a->post("{$this->offre}/relire/$libre", ['quantite' => '18', 'prix' => '']);
        self::assertSame(422, $r->getStatusCode());
        self::assertStringContainsString('Indiquez le prix', (string) $r->getContent());
        self::assertSame(0, (int) $this->ligneEnBase($libre)['prix_centimes']);
        $this->a->post("{$this->offre}/relire/$libre", ['quantite' => '18', 'prix' => '38']);
        self::assertSame(3800, (int) $this->ligneEnBase($libre)['prix_centimes']);
    }

    public function testUneQuantiteIllisibleNeChangeRien(): void
    {
        BaseDeTest::pdo()->exec("UPDATE ligne SET a_verifier = 1 WHERE id = {$this->ligne}");
        $this->page($this->offre . '/relire');
        $r = $this->a->post("{$this->offre}/relire/{$this->ligne}", ['quantite' => 'beaucoup', 'prix' => '18.50']);
        self::assertSame(422, $r->getStatusCode());
        self::assertSame([12500, 1], [(int) $this->ligneEnBase($this->ligne)['quantite_milliemes'], (int) $this->ligneEnBase($this->ligne)['a_verifier']]);
    }
}
