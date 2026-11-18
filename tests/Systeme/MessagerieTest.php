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
