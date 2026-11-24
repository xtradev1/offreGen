<?php

declare(strict_types=1);

namespace OffreGen\Tests\Systeme;

use OffreGen\Systeme\MessagerieFichier;
use OffreGen\Systeme\MessagerieRedirigee;
use OffreGen\Systeme\MessagerieSmtp;
use OffreGen\Tests\Outils\Dossiers;
use OffreGen\Tests\Outils\Monde;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

final class MessagerieTest extends TestCase
{
    private Dossiers $dossiers;

    protected function setUp(): void
    {
        $this->dossiers = new Dossiers();
    }

    protected function tearDown(): void
    {
        $this->dossiers->effacer();
    }

    private function fichier(): MessagerieFichier
    {
        return new MessagerieFichier($this->dossiers->env()['COURRIELS_DOSSIER'], new MockClock('2026-11-18 10:00:00', 'UTC'));
    }

    /** Site de test (docs/SECURITE.md) : aucun courriel ne part chez le vrai destinataire. */
    public function testLaRedirectionEnvoieToutAUneSeuleAdresse(): void
    {
        (new MessagerieRedirigee($this->fichier(), 'recette@exemple.ch'))->envoyer('luigi@exemple.ch', 'Votre offre est prête', 'Bonjour');
        $courriels = $this->dossiers->courriels();
        self::assertCount(1, $courriels);
        $contenu = (string) file_get_contents($courriels[0]);
        self::assertStringContainsString("To: recette@exemple.ch\r\n", $contenu);
        self::assertStringNotContainsString('To: luigi@exemple.ch', $contenu);
        preg_match('/Subject: =\?UTF-8\?B\?([^?]+)\?=/', $contenu, $m);
        self::assertSame('[pour luigi@exemple.ch] Votre offre est prête', base64_decode($m[1] ?? ''));
    }

    /** @return list<string> le contenu des courriels écrits */
    private function contenus(): array
    {
        return array_map(static fn (string $f): string => (string) file_get_contents($f), $this->dossiers->courriels());
    }

    /**
     * Xavier, 2026-11-24 : « on va les limiter à une période de 1 ou 2 heures […] tester que ça envoie à tout le
     * monde […] ensuite on repasse en test en mode sécurisé ».
     */
    public function testPendantLaFenetreLeCourrielPartChezSonDestinataire(): void
    {
        $horloge = new MockClock('2026-11-25 14:00:00', 'UTC');
        $journal = new class extends \Psr\Log\AbstractLogger {
            /** @var list<string> */
            public array $lignes = [];

            public function log($level, string|\Stringable $message, array $context = []): void
            {
                $this->lignes[] = "$level $message";
            }
        };
        $m = new MessagerieRedirigee($this->fichier(), 'recette@exemple.ch', new \DateTimeImmutable('2026-11-25T15:30:00Z'), $horloge, $journal);
        self::assertTrue($m->envoiReel());
        $m->envoyerMisEnForme('luigi@exemple.ch', 'Votre offre est prête', 'Bonjour', '<p>Bonjour</p>');
        [$courriel] = $this->contenus();
        self::assertStringContainsString("To: luigi@exemple.ch\r\n", $courriel);
        self::assertStringNotContainsString('recette@exemple.ch', $courriel);
        // Chaque envoi réel laisse une trace, avec son destinataire.
        self::assertCount(1, $journal->lignes);
        self::assertStringStartsWith('info ', $journal->lignes[0]);
        self::assertStringContainsString('luigi@exemple.ch', $journal->lignes[0]);
    }

    public function testLaFenetreSeFermeSeuleALHeureDite(): void
    {
        $horloge = new MockClock('2026-11-25 15:30:00', 'UTC');
        $m = new MessagerieRedirigee($this->fichier(), 'recette@exemple.ch', new \DateTimeImmutable('2026-11-25T15:30:00Z'), $horloge);
        self::assertFalse($m->envoiReel());
        $m->envoyer('luigi@exemple.ch', 'Votre offre est prête', 'Bonjour');
        [$courriel] = $this->contenus();
        self::assertStringContainsString("To: recette@exemple.ch\r\n", $courriel);
    }

    /** Une fin à plus de 2 heures (faute de frappe sur l'année, ligne oubliée) n'ouvre rien. */
    public function testUneFenetreDePlusDeDeuxHeuresNOuvreRien(): void
    {
        $horloge = new MockClock('2026-11-25 14:00:00', 'UTC');
        foreach (['2026-11-25T16:00:01Z', '2027-11-25T15:00:00Z'] as $fin) {
            self::assertFalse((new MessagerieRedirigee($this->fichier(), 'recette@exemple.ch', new \DateTimeImmutable($fin), $horloge))->envoiReel(), $fin);
        }
        self::assertTrue((new MessagerieRedirigee($this->fichier(), 'recette@exemple.ch', new \DateTimeImmutable('2026-11-25T16:00:00Z'), $horloge))->envoiReel());
    }

    public function testLeReglageDuSiteOuvreEtFermeLaFenetre(): void
    {
        $horloge = new MockClock('2026-11-25 14:00:00', 'UTC');
        $env = $this->dossiers->env() + ['COURRIELS_REDIRIGER_VERS' => 'recette@exemple.ch'];
        $ouverte = Monde::conteneur($horloge, $env + ['COURRIELS_ENVOI_REEL_JUSQU_A' => '2026-11-25T15:00:00Z'])->messagerie();
        self::assertInstanceOf(MessagerieRedirigee::class, $ouverte);
        self::assertTrue($ouverte->envoiReel());
        foreach (['', 'demain', '2026-11-25T13:59:00Z'] as $valeur) {
            $m = Monde::conteneur($horloge, $env + ['COURRIELS_ENVOI_REEL_JUSQU_A' => $valeur])->messagerie();
            self::assertInstanceOf(MessagerieRedirigee::class, $m);
            self::assertFalse($m->envoiReel(), "« $valeur »");
        }
    }

    public function testLaRedirectionRefuseUneAdresseInvalide(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new MessagerieRedirigee($this->fichier(), 'pas une adresse');
    }

    public function testSansReglageLesCourrielsRestentDansDesFichiers(): void
    {
        self::assertInstanceOf(MessagerieFichier::class, Monde::conteneur(env: $this->dossiers->env())->messagerie());
    }

    public function testMessagerieSmtpChoisieParLeReglage(): void
    {
        $env = ['MESSAGERIE' => 'smtp', 'MAILER_DSN' => 'null://null', 'COURRIELS_EXPEDITEUR' => 'offreGen <notifications@exemple.ch>'];
        $messagerie = Monde::conteneur(env: $env)->messagerie();
        self::assertInstanceOf(MessagerieSmtp::class, $messagerie);
        // Le transport null accepte le message : construction et envoi passent sans réseau.
        $messagerie->envoyer('luigi@exemple.ch', 'Objet', 'Texte');
    }

    public function testLaRedirectionSePoseSurLeTransportChoisi(): void
    {
        $env = $this->dossiers->env() + ['COURRIELS_REDIRIGER_VERS' => 'recette@exemple.ch'];
        self::assertInstanceOf(MessagerieRedirigee::class, Monde::conteneur(env: $env)->messagerie());
    }

    public function testSmtpSansExpediteurRefuseDeDemarrer(): void
    {
        $this->expectException(\RuntimeException::class);
        Monde::conteneur(env: ['MESSAGERIE' => 'smtp', 'MAILER_DSN' => 'null://null', 'COURRIELS_EXPEDITEUR' => ''])->messagerie();
    }

    /** Revue du 2026-11-18 (exploitation, défaut 4) : « SMTP » ou une faute de frappe basculait sans bruit sur des fichiers. */
    public function testUnReglageInconnuRefuseDeDemarrer(): void
    {
        self::assertInstanceOf(MessagerieFichier::class, Monde::conteneur(env: $this->dossiers->env() + ['MESSAGERIE' => 'fichier'])->messagerie());
        $this->expectException(\RuntimeException::class);
        Monde::conteneur(env: ['MESSAGERIE' => 'SMTP'])->messagerie();
    }

    public function testSmtpRefuseUnDestinataireInvalide(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        (new MessagerieSmtp('null://null', 'offreGen <notifications@exemple.ch>'))->envoyer('pas une adresse', 'Objet', 'Texte');
    }
}
