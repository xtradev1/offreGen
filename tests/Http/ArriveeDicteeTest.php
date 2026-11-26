<?php

declare(strict_types=1);

namespace OffreGen\Tests\Http;

use OffreGen\Tests\Outils\BaseDeTest;
use OffreGen\Tests\Outils\Jeu;
use OffreGen\Tests\Outils\Monde;
use OffreGen\Tests\Outils\Navigateur;
use PHPUnit\Framework\TestCase;

/**
 * L'arrivée après une dictée (maquette v2 du parcours de la dictée, validée par Xavier le 2026-11-26) : l'état de
 * l'enregistrement dit où aller quand l'offre est prête. Offre propre et client rattaché : le PDF. Client à confirmer :
 * son choix, puis le PDF. Lignes à relire : la relecture. Le compte des lignes à relire est le même partout (constat 2
 * de l'analyse des parcours) : incertaines ou sans prix.
 * Jeu : l'offre de A a une ligne juste, « Peinture murale a », et aucun client.
 */
final class ArriveeDicteeTest extends TestCase
{
    private Jeu $jeu;
    private Navigateur $a;
    private int $offre;

    protected function setUp(): void
    {
        $this->jeu = new Jeu();
        $this->a = new Navigateur(Monde::conteneur());
        $this->a->connecter('a@exemple.ch');
        $this->offre = $this->jeu->offre['a'];
    }

    /** @return array<string, mixed> */
    private function etat(): array
    {
        $reponse = $this->a->get('/enregistrements/' . $this->jeu->uuid['a'] . '/etat');
        self::assertSame(200, $reponse->getStatusCode());
        return json_decode((string) $reponse->getContent(), true);
    }

    private function prete(): void
    {
        BaseDeTest::pdo()->exec("UPDATE enregistrement SET statut = 'pret' WHERE uuid = '{$this->jeu->uuid['a']}'");
    }

    private function ligne(int $centimes, int $aVerifier): void
    {
        BaseDeTest::pdo()->prepare('INSERT INTO ligne (artisan_id, offre_id, rang, designation, quantite_milliemes, unite, prix_centimes, a_verifier) VALUES (?, ?, 9, ?, 1000, ?, ?, ?)')
            ->execute([$this->jeu->a, $this->offre, 'Plafond', 'm²', $centimes, $aVerifier]);
    }

    public function testTantQueLOffreNEstPasPreteRienNeDitOuAller(): void
    {
        $etat = $this->etat();
        self::assertSame('recu', $etat['statut']);
        self::assertSame([null, null, null, null, null, null], [$etat['offre'], $etat['suite'], $etat['a_relire'], $etat['lignes'], $etat['titre'], $etat['client']]);
    }

    public function testOffrePropreSansClientLeChoixDuClientPuisLePdf(): void
    {
        $this->prete();
        $etat = $this->etat();
        self::assertSame($this->offre, $etat['offre']);
        self::assertSame("/offres/{$this->offre}/client?suite=apercu", $etat['suite']);
        self::assertSame([0, 1, 'Offre a', null], [$etat['a_relire'], $etat['lignes'], $etat['titre'], $etat['client']]);
    }

    public function testOffrePropreEtClientRattacheLePdf(): void
    {
        $this->prete();
        BaseDeTest::pdo()->exec("UPDATE offre SET client_id = {$this->jeu->client['a']} WHERE id = {$this->offre}");
        $etat = $this->etat();
        self::assertSame("/offres/{$this->offre}/apercu", $etat['suite']);
        self::assertSame([0, 'Client a'], [$etat['a_relire'], $etat['client']]);
    }

    public function testDesLignesIncertainesOuSansPrixLaRelectureDAbord(): void
    {
        $this->prete();
        BaseDeTest::pdo()->exec("UPDATE offre SET client_id = {$this->jeu->client['a']}, client_dicte = NULL WHERE id = {$this->offre}");
        $this->ligne(3200, 1);
        $this->ligne(0, 0);
        $etat = $this->etat();
        self::assertSame("/offres/{$this->offre}/relire", $etat['suite']);
        self::assertSame([2, 3], [$etat['a_relire'], $etat['lignes']], 'la ligne incertaine et la ligne sans prix comptent');
    }

    /** Revue du 2026-11-26 (I3) : la dictée n'a donné aucune ligne ; l'offre s'ouvre, elle dit « Aucun poste ». */
    public function testUneOffreSansLigneNArrivePasSurUnPdfVide(): void
    {
        $this->prete();
        BaseDeTest::pdo()->exec("UPDATE offre SET client_id = {$this->jeu->client['a']} WHERE id = {$this->offre}");
        BaseDeTest::pdo()->exec("DELETE FROM ligne WHERE offre_id = {$this->offre}");
        $etat = $this->etat();
        self::assertSame("/offres/{$this->offre}", $etat['suite']);
        self::assertSame([0, 0], [$etat['a_relire'], $etat['lignes']]);
    }

    /**
     * Revue du 2026-11-26 (I2) : le guide de la démo et de l'essai finit sur la page de l'offre (repères, « Revenir à mon
     * espace »). Tant qu'il se montre, la dictée y mène comme avant, même propre, même avec des lignes à relire.
     */
    public function testTantQueLeGuideSeMontreLaDicteeMeneALOffre(): void
    {
        $this->prete();
        BaseDeTest::pdo()->exec("UPDATE offre SET client_id = {$this->jeu->client['a']} WHERE id = {$this->offre}");
        BaseDeTest::pdo()->exec("UPDATE artisan SET demo = 0, offres_max = 5, guide_artisan_le = NULL WHERE id = {$this->jeu->a}");
        $etat = $this->etat();
        self::assertSame("/offres/{$this->offre}", $etat['suite']);
        self::assertSame(0, $etat['a_relire'], 'pas d\'écran « Offre prête » : le guide attend l\'offre');
        // Le guide fini, la règle d'arrivée reprend.
        BaseDeTest::pdo()->exec("UPDATE artisan SET guide_artisan_le = '2026-11-07 10:00:00' WHERE id = {$this->jeu->a}");
        self::assertSame("/offres/{$this->offre}/apercu", $this->etat()['suite']);
    }

    public function testLeNomDicteSeMontreTantQueLeClientEstAConfirmer(): void
    {
        $this->prete();
        BaseDeTest::pdo()->exec("UPDATE offre SET client_dicte = 'Monsieur Favre' WHERE id = {$this->offre}");
        self::assertSame('Monsieur Favre', $this->etat()['client']);
    }

    /** Constat 2 : la liste, la colonne du PC et le bouton de l'offre annoncent le même nombre, avec le même mot. */
    public function testLaListeCompteCommeLaRelecture(): void
    {
        $this->ligne(3200, 1);
        $this->ligne(0, 0);
        $liste = (string) $this->a->get('/')->getContent();
        self::assertStringContainsString('<span class="status a-verifier">2 à relire</span>', $liste);
        self::assertStringNotContainsString('à vérifier</span>', $liste);
        $offre = (string) $this->a->get("/offres/{$this->offre}")->getContent();
        self::assertStringContainsString('<span>Relire 2 lignes</span>', $offre);
        self::assertSame(1, preg_match('#<aside class="colonne-liste".*?</aside>#s', $offre, $m));
        self::assertStringContainsString('2 à relire', $m[0] ?? '');
        self::assertStringContainsString('Encore 2 à relire', (string) $this->a->get("/offres/{$this->offre}/relire")->getContent());
    }

    /**
     * Constat 5 de l'analyse des parcours (Xavier, 2026-11-26) : Mes offres a sa recherche, comme Clients, Catalogue et
     * Factures. Elle filtre à la frappe sur l'objet, le client et le numéro, sans accents ni majuscules.
     */
    public function testMesOffresOntUneRechercheSurLObjetLeClientEtLeNumero(): void
    {
        BaseDeTest::pdo()->exec("UPDATE offre SET client_id = {$this->jeu->client['a']}, titre = 'Façade côté rue' WHERE id = {$this->offre}");
        $pdo = BaseDeTest::pdo();
        $pdo->exec("INSERT INTO offre (artisan_id, numero, titre, cree_le) VALUES ({$this->jeu->a}, 2, 'Volets', '2026-11-07 09:00:00')");
        $liste = (string) $this->a->get('/')->getContent();
        self::assertStringContainsString('<input id="oq" name="q" type="search" placeholder="Chercher une offre, un client" autocomplete="off" aria-label="Chercher une offre ou un client" data-filtre="#liste-offres">', $liste);
        self::assertMatchesRegularExpression('#<div class="glisse" id="o' . $this->offre . '" data-glisse[^>]* data-texte="facade cote rue client a 1"#', $liste);
        self::assertStringContainsString('<p class="vide" data-aucun hidden>Aucune offre ne correspond.</p>', $liste);
        // Une seule offre : rien à chercher, pas de champ.
        $pdo->exec("DELETE FROM offre WHERE artisan_id = {$this->jeu->a} AND titre = 'Volets'");
        self::assertStringNotContainsString('data-filtre="#liste-offres"', (string) $this->a->get('/')->getContent());
    }

    /** L'écran « Offre prête » de la fenêtre de dictée : le compte des lignes et le geste suivant, jamais le prix. */
    public function testLaFenetreDeDicteePorteLEcranOffrePreteSansPrix(): void
    {
        $page = (string) $this->a->get('/')->getContent();
        self::assertSame(1, preg_match('#<div class="prete" data-etat="prete" hidden>(.*?)</div>\s*<div data-etat="court"#s', $page, $m), 'le bloc « Offre prête »');
        $bloc = $m[1] ?? '';
        self::assertStringContainsString('<h2 tabindex="-1" data-prete-titre>Offre prête</h2>', $bloc);
        foreach (['data-prete-client', 'data-prete-objet', 'data-prete-lignes', 'data-prete-relire', 'data-prete-suite', 'data-prete-offre'] as $attribut) {
            self::assertStringContainsString($attribut, $bloc);
        }
        self::assertStringNotContainsString('CHF', $bloc);
        self::assertStringNotContainsString('total', mb_strtolower($bloc));
    }

    /** La page de préparation, sans la fenêtre de dictée : prête avec des lignes à relire, elle mène à la relecture. */
    public function testLaPageDePreparationMeneOuLEtatLeDit(): void
    {
        $this->prete();
        $this->ligne(0, 0);
        $page = (string) $this->a->get('/enregistrements/' . $this->jeu->uuid['a'])->getContent();
        self::assertStringContainsString("<a class=\"btn primary\" href=\"/offres/{$this->offre}/relire\">Relire 1 ligne</a>", $page);
        self::assertStringContainsString("href=\"/offres/{$this->offre}\">Voir l'offre", $page);
    }
}
