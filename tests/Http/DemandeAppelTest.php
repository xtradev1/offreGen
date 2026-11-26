<?php

declare(strict_types=1);

namespace OffreGen\Tests\Http;

use OffreGen\Tests\Outils\BaseDeTest;
use OffreGen\Tests\Outils\Dossiers;
use OffreGen\Tests\Outils\Jeu;
use OffreGen\Tests\Outils\Monde;
use OffreGen\Tests\Outils\Navigateur;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

/**
 * « Demander un appel » (K1 et S1 de la maquette v4 de l'administration ; chantier onboarding, E1 du 2026-11-23) :
 * le client laisse son numéro, le commercial qui le suit reçoit un courriel et le voit dans « À rappeler ».
 * Comptes : a = Nina Rey, commerciale ; b = Peinture Morand, sa démo, finie la veille ; x = l'administrateur.
 */
final class DemandeAppelTest extends TestCase
{
    private Jeu $jeu;
    private Dossiers $dossiers;

    protected function setUp(): void
    {
        $this->jeu = new Jeu();
        $this->dossiers = new Dossiers();
        $pdo = BaseDeTest::pdo();
        $pdo->exec("UPDATE artisan SET role = 'commercial', entreprise = 'Nina Rey', offres_max = 10 WHERE id = {$this->jeu->a}");
        $pdo->exec("UPDATE artisan SET demo = 1, demo_fin = '2026-11-06 12:00:00', cree_par = {$this->jeu->a}, apporte_par = {$this->jeu->a}, metier = 'peintre', entreprise = 'Peinture Morand' WHERE id = {$this->jeu->b}");
        $this->membre('x@exemple.ch', 'Xavier', "administrateur = 1");
    }

    protected function tearDown(): void
    {
        $this->dossiers->effacer();
    }

    /** Un compte de l'équipe, avec le mot de passe des comptes du jeu. */
    private function membre(string $courriel, string $nom, string $droit): int
    {
        $pdo = BaseDeTest::pdo();
        $pdo->exec("INSERT INTO artisan (courriel, mot_de_passe, entreprise, demo, cree_le, reglages_faits_le, guide_equipe_le)
                    SELECT '$courriel', mot_de_passe, '$nom', 0, cree_le, reglages_faits_le, '2026-11-01 08:00:00' FROM artisan WHERE id = {$this->jeu->a}");
        $id = (int) $pdo->lastInsertId();
        $pdo->exec("UPDATE artisan SET $droit WHERE id = $id");
        return $id;
    }

    private function connecte(string $courriel): Navigateur
    {
        $n = new Navigateur(Monde::conteneur(new MockClock('2026-11-07 10:00:00', 'UTC'), $this->dossiers->env()));
        self::assertSame(303, $n->connecter($courriel)->getStatusCode());
        return $n;
    }

    private function demander(Navigateur $n, string $telephone = '079 123 45 67', string $moment = 'soir', string $question = "J'ai une question sur le catalogue."): \Symfony\Component\HttpFoundation\Response
    {
        $n->get('/demander-un-appel');
        $r = $n->post('/demander-un-appel', ['telephone' => $telephone, 'moment' => $moment, 'question' => $question]);
        // Le courriel part après la réponse.
        $n->c->apresReponse()->executer();
        return $r;
    }

    private function demandes(): int
    {
        return (int) self::valeur("SELECT COUNT(*) FROM demande_appel WHERE artisan_id = {$this->jeu->b}");
    }

    public function testLEcranDeFinMeneAuFormulaireQuiNommeLeCommercial(): void
    {
        $n = $this->connecte('b@exemple.ch');
        self::assertStringContainsString('href="/demander-un-appel"', (string) $n->get('/fin-de-demo')->getContent());
        $page = (string) $n->get('/demander-un-appel')->getContent();
        self::assertStringContainsString('Nina, de ' . $n->c->nomApplication() . ', vous rappelle.', $page);
        self::assertStringContainsString('href="/fin-de-demo"', $page);
        self::assertStringContainsString('En fin de journée', $page);
    }

    public function testDemandeNoteeConfirmeeSansDelaiEtCourrielAuCommercial(): void
    {
        $n = $this->connecte('b@exemple.ch');
        $r = $this->demander($n);
        self::assertSame('/demander-un-appel/note', $r->headers->get('Location'));
        $note = (string) $n->get('/demander-un-appel/note')->getContent();
        self::assertStringContainsString('Nina a reçu votre demande et vous appellera au 079 123 45 67. Vous avez choisi la fin de journée.', $note);
        self::assertSame(1, $this->demandes());
        $courriels = $this->dossiers->courriels();
        self::assertCount(1, $courriels);
        $courriel = quoted_printable_decode((string) file_get_contents($courriels[0]));
        self::assertStringContainsString('a@exemple.ch', $courriel);
        self::assertStringContainsString('Peinture Morand demande un appel', $courriel);
        self::assertStringContainsString('079 123 45 67, en fin de journée.', $courriel);
        self::assertStringContainsString('/admin/artisans/' . $this->jeu->b, $courriel);
        self::assertStringContainsString('Démo terminée le 06.11.2026, 1 offre dictée.', $courriel);
    }

    public function testNumeroIncompletOuMomentAbsentRefusesSansRienEnvoyer(): void
    {
        $n = $this->connecte('b@exemple.ch');
        $r = $this->demander($n, '079 12');
        self::assertSame(422, $r->getStatusCode());
        self::assertStringContainsString('Vérifiez ce numéro.', (string) $r->getContent());
        self::assertStringContainsString('value="079 12"', (string) $r->getContent());
        self::assertSame(422, $this->demander($n, '079 123 45 67', 'nuit')->getStatusCode());
        self::assertSame(0, $this->demandes());
        self::assertSame([], $this->dossiers->courriels());
    }

    public function testUneNouvelleDemandeRemplaceLaPremiereEtTroisParJourAuPlus(): void
    {
        $n = $this->connecte('b@exemple.ch');
        $this->demander($n);
        $this->demander($n, '+41 79 000 00 00', 'matin', '');
        self::assertSame(1, $this->demandes());
        self::assertStringContainsString('Vous avez choisi le matin.', (string) $n->get('/demander-un-appel/note')->getContent());
        $this->demander($n);
        $r = $this->demander($n);
        self::assertSame(422, $r->getStatusCode());
        self::assertStringContainsString('trois demandes en 24 heures', (string) $r->getContent());
        self::assertCount(3, $this->dossiers->courriels());
    }

    public function testSansCommercialLaDemandeVaAuxAdministrateurs(): void
    {
        BaseDeTest::pdo()->exec("UPDATE artisan SET cree_par = NULL WHERE id = {$this->jeu->b}");
        $n = $this->connecte('b@exemple.ch');
        self::assertStringContainsString('<b>' . $n->c->nomApplication() . ' vous rappelle.</b>', (string) $n->get('/demander-un-appel')->getContent());
        $this->demander($n);
        $courriels = $this->dossiers->courriels();
        self::assertCount(1, $courriels);
        self::assertStringContainsString('x@exemple.ch', (string) file_get_contents($courriels[0]));
        self::assertStringContainsString('Peinture Morand', (string) $this->connecte('x@exemple.ch')->get('/admin/tableau')->getContent());
    }

    public function testARappelerPuisCestFaitDansLaPorteeDuCommercialSeulement(): void
    {
        $this->demander($this->connecte('b@exemple.ch'));
        $id = (int) self::valeur("SELECT id FROM demande_appel WHERE artisan_id = {$this->jeu->b}");
        $nina = $this->connecte('a@exemple.ch');
        $suivi = (string) $nina->get('/admin/suivi')->getContent();
        self::assertStringContainsString('À rappeler', $suivi);
        self::assertStringContainsString('href="tel:0791234567"', $suivi);
        // Déposée à 11:00, heure de Zurich, le jour même.
        self::assertStringContainsString('Demande d&#039;aujourd&#039;hui, 11:00 · rappeler en fin de journée', $suivi);
        self::assertStringContainsString('Démo terminée le 06.11.2026, 1 offre dictée.', $suivi);
        self::assertStringContainsString('class="compte-appels"', $suivi);
        // Un autre commercial ne la voit pas et ne peut pas la fermer.
        $this->membre('marc@exemple.ch', 'Marc Favre', "role = 'commercial', offres_max = 10");
        $marc = $this->connecte('marc@exemple.ch');
        self::assertStringNotContainsString('Peinture Morand', (string) $marc->get('/admin/suivi')->getContent());
        $marc->get('/admin/plus');
        self::assertSame(404, $marc->post("/admin/appels/$id/fait", [])->getStatusCode());
        // L'administrateur la voit sur son tableau, pour toute l'équipe.
        self::assertStringContainsString('Peinture Morand', (string) $this->connecte('x@exemple.ch')->get('/admin/tableau')->getContent());
        // « C'est fait » : elle sort de la liste, le geste est au journal du compte.
        self::assertSame('/admin/suivi', $nina->post("/admin/appels/$id/fait", [])->headers->get('Location'));
        $apres = (string) $nina->get('/admin/suivi')->getContent();
        self::assertStringNotContainsString('À rappeler', $apres);
        self::assertStringNotContainsString('class="compte-appels"', $apres);
        self::assertSame(1, (int) self::valeur("SELECT COUNT(*) FROM journal_admin WHERE artisan_id = {$this->jeu->b} AND geste = 'appel_fait' AND acteur_id = {$this->jeu->a}"));
        $nina->get('/admin/plus');
        self::assertSame(404, $nina->post("/admin/appels/$id/fait", [])->getStatusCode());
    }

    public function testLeLienDansPlusPendantLaDemoEtLEssaiSeulementEt404PourLEquipe(): void
    {
        self::assertStringContainsString('href="/demander-un-appel"', (string) $this->connecte('b@exemple.ch')->get('/plus')->getContent());
        BaseDeTest::pdo()->exec("UPDATE artisan SET demo = 0, demo_fin = NULL, offres_max = 10 WHERE id = {$this->jeu->b}");
        self::assertStringContainsString('href="/demander-un-appel"', (string) $this->connecte('b@exemple.ch')->get('/plus')->getContent());
        BaseDeTest::pdo()->exec("UPDATE artisan SET offres_max = NULL WHERE id = {$this->jeu->b}");
        self::assertStringNotContainsString('href="/demander-un-appel"', (string) $this->connecte('b@exemple.ch')->get('/plus')->getContent());
        $nina = $this->connecte('a@exemple.ch');
        self::assertStringNotContainsString('href="/demander-un-appel"', (string) $nina->get('/plus')->getContent());
        self::assertSame(404, $nina->get('/demander-un-appel')->getStatusCode());
    }

    private static function valeur(string $sql): mixed
    {
        $s = BaseDeTest::pdo()->query($sql);
        self::assertNotFalse($s);
        return $s->fetchColumn();
    }
}
