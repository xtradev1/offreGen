<?php

declare(strict_types=1);

namespace OffreGen\Tests\Http;

use OffreGen\Compte\DemandesEssai;
use OffreGen\Tests\Outils\Dossiers;
use OffreGen\Tests\Outils\Monde;
use OffreGen\Tests\Outils\Navigateur;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

/**
 * Le formulaire « Bon pour essai » de la page de présentation (Xavier, 2026-11-20) : tant que l'inscription
 * n'existe pas, le courriel du visiteur part à l'exploitant, qui le recontacte. Rien n'est gardé en base.
 */
final class DemandeEssaiTest extends TestCase
{
    private Dossiers $dossiers;
    private MockClock $horloge;

    protected function setUp(): void
    {
        $this->dossiers = new Dossiers();
        $this->horloge = new MockClock('2026-11-20 10:00:00', 'UTC');
    }

    protected function tearDown(): void
    {
        $this->dossiers->effacer();
    }

    private function visiteur(): Navigateur
    {
        return new Navigateur(Monde::conteneur($this->horloge, $this->dossiers->env() + ['DEMANDES_ESSAI_DOSSIER' => $this->dossiers->racine . '/demandes']));
    }

    /** La demande, puis le courriel, qui part après la réponse. */
    private function demander(Navigateur $n, string $courriel, string $page = '/'): string
    {
        $n->get($page);
        $reponse = $n->post('/essai', ['courriel' => $courriel] + (str_contains($page, 'etat=avant') ? ['etat' => 'avant'] : []));
        $n->c->apresReponse()->executer();
        self::assertSame(303, $reponse->getStatusCode());
        return (string) $reponse->headers->get('Location');
    }

    public function testLaPagePorteLeFormulaireAvecSonJeton(): void
    {
        $page = (string) $this->visiteur()->get('/')->getContent();
        self::assertMatchesRegularExpression('/<form class="demande" method="post" action="\/essai"><input type="hidden" name="_csrf" value="[^"]{20,}">/', $page);
        self::assertStringContainsString('<input type="email" name="courriel" required', $page);
        self::assertStringContainsString('Demander mon essai', $page);
    }

    public function testUneDemandeValideEcritALExploitant(): void
    {
        $n = $this->visiteur();
        self::assertSame('/?demande=recue#demande', $this->demander($n, ' Peintre@Exemple.ch '));
        $courriels = $this->dossiers->courriels();
        self::assertCount(1, $courriels);
        $courriel = (string) file_get_contents($courriels[0]);
        self::assertStringContainsString('To: info@xtradev.ch', $courriel);
        self::assertStringContainsString('Peintre@Exemple.ch', $courriel);
        self::assertStringContainsString('inscriptions ouvertes', $courriel);

        $page = (string) $n->get('/?demande=recue')->getContent();
        self::assertStringContainsString('role="status"', $page);
        self::assertStringNotContainsString('action="/essai"', $page, 'La demande reçue, le formulaire laisse la place au message.');
    }

    public function testLEtatAvantLOuvertureSuitLaDemande(): void
    {
        self::assertSame('/?etat=avant&demande=recue#demande', $this->demander($this->visiteur(), 'a@exemple.ch', '/?etat=avant'));
        self::assertStringContainsString("avant l'ouverture", (string) file_get_contents($this->dossiers->courriels()[0]));
    }

    public function testUneAdresseInvalideNEnvoieRien(): void
    {
        $n = $this->visiteur();
        foreach (['', 'pas-une-adresse', "a@exemple.ch\r\nBcc: tiers@exemple.ch", str_repeat('a', 250) . '@exemple.ch'] as $faux) {
            self::assertSame('/?demande=invalide#demande', $this->demander($n, $faux));
        }
        self::assertSame([], $this->dossiers->courriels());
        self::assertStringContainsString('role="alert"', (string) $n->get('/?demande=invalide')->getContent());
    }

    public function testSansJetonLaDemandeEstRefusee(): void
    {
        $n = $this->visiteur();
        $n->get('/');
        self::assertSame(403, $n->post('/essai', ['courriel' => 'a@exemple.ch'], avecJeton: false)->getStatusCode());
        $n->c->apresReponse()->executer();
        self::assertSame([], $this->dossiers->courriels());
    }

    public function testUnVisiteurNeDemandePasPlusDeTroisFoisParHeure(): void
    {
        $n = $this->visiteur();
        for ($i = 1; $i <= 3; $i++) {
            self::assertSame('/?demande=recue#demande', $this->demander($n, "a$i@exemple.ch"));
        }
        self::assertSame('/?demande=trop#demande', $this->demander($n, 'a4@exemple.ch'));
        self::assertCount(3, $this->dossiers->courriels());
        $this->horloge->modify('+61 minutes');
        self::assertSame('/?demande=recue#demande', $this->demander($n, 'a5@exemple.ch'));
    }

    public function testLePlafondDuJourProtegeLaBoiteDeLExploitant(): void
    {
        for ($i = 1; $i <= DemandesEssai::MAX_PAR_JOUR; $i++) {
            self::assertSame('/?demande=recue#demande', $this->demander($this->visiteur(), "v$i@exemple.ch"));
        }
        self::assertSame('/?demande=trop#demande', $this->demander($this->visiteur(), 'de-trop@exemple.ch'));
        self::assertCount(DemandesEssai::MAX_PAR_JOUR, $this->dossiers->courriels());
        $this->horloge->modify('+1 day');
        self::assertSame('/?demande=recue#demande', $this->demander($this->visiteur(), 'lendemain@exemple.ch'));
    }
}
