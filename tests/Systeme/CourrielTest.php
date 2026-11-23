<?php

declare(strict_types=1);

namespace OffreGen\Tests\Systeme;

use OffreGen\Systeme\Courriel;
use OffreGen\Systeme\Messagerie;
use OffreGen\Systeme\MessagerieFichier;
use OffreGen\Systeme\MessagerieRedirigee;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

/**
 * Les courriels mis en forme (maquette v8, direction C validée par Xavier le 2026-11-23) : un bandeau qui tient sans
 * image, un bouton, le lien en clair, et toujours une version texte.
 */
final class CourrielTest extends TestCase
{
    private const LIEN = 'https://exemple.test/d/kD165X6hE6pEv90QB4qJR1n7cZZRhXZeG2mkglLtRxw';

    private function courriel(): Courriel
    {
        return new Courriel(
            'Votre démo Dicta',
            'Votre démo reste ouverte',
            'Pour y revenir, chez <Morand & fils> :',
            'Revenir dans ma démo',
            self::LIEN,
            ['Gardez ce lien pour vous.'],
            'Vous n\'avez rien demandé ? Ignorez ce courriel.',
            'Nina Rey',
        );
    }

    public function testLeBandeauTientSansImageEtLeTexteEstEchappe(): void
    {
        $html = $this->courriel()->enHtml('Dicta', 'https://exemple.test/');
        // Le bandeau est un fond de cellule, le nom du produit du texte : sans image, les deux restent.
        self::assertStringContainsString('bgcolor="#23272D"', $html);
        self::assertMatchesRegularExpression('#<span[^>]*color:\#FFFFFF[^>]*>Dicta</span>#', $html);
        self::assertStringContainsString('src="https://exemple.test/img/dicta-icone-180.png"', $html);
        self::assertStringContainsString('alt=""', $html);
        // Ce que tape un artisan n'écrit jamais de balise.
        self::assertStringContainsString('chez &lt;Morand &amp; fils&gt;', $html);
        self::assertStringNotContainsString('<Morand', $html);
        // Le bouton, puis le lien en clair pour les messageries qui n'affichent pas les boutons.
        self::assertSame(2, substr_count($html, 'href="' . self::LIEN . '"'));
        self::assertStringContainsString('>Revenir dans ma démo</a>', $html);
        self::assertStringContainsString('<b style="color:#1F2328">Nina Rey</b><br>Dicta', $html);
        foreach (['<script', '<svg', 'javascript:'] as $interdit) {
            self::assertStringNotContainsString($interdit, $html);
        }
    }

    public function testLaVersionTexteDitLaMemeChose(): void
    {
        $texte = $this->courriel()->enTexte('Dicta');
        self::assertStringStartsWith("Bonjour,\r\n\r\nVotre démo reste ouverte.\r\n\r\n", $texte);
        self::assertStringContainsString("\r\n" . self::LIEN . "\r\n", $texte);
        self::assertStringEndsWith("\r\n\r\nNina Rey\r\nDicta", $texte);
        // Sans signataire, le produit signe seul.
        $sans = (new Courriel('Sujet', 'Titre !', 'Texte :', 'Bouton', self::LIEN))->enTexte('Dicta');
        self::assertStringStartsWith("Bonjour,\r\n\r\nTitre !\r\n\r\n", $sans);
        self::assertStringEndsWith("\r\n\r\nDicta", $sans);
    }

    public function testLeMomentSeDitALHeureDeZurich(): void
    {
        self::assertSame("samedi 14 novembre à 11\u{00A0}h\u{00A0}00", Courriel::moment(new \DateTimeImmutable('2026-11-14 10:00:00', new \DateTimeZone('UTC'))));
        self::assertSame("dimanche 1er novembre à 9\u{00A0}h\u{00A0}05", Courriel::moment(new \DateTimeImmutable('2026-11-01 08:05:00', new \DateTimeZone('UTC'))));
    }

    public function testLesDeuxVersionsPartentEtLaRedirectionLesGarde(): void
    {
        $dossier = sys_get_temp_dir() . '/offregen-courriels-' . bin2hex(random_bytes(4));
        $fichier = new MessagerieFichier($dossier, new MockClock('2026-11-07 10:00:00', 'UTC'));
        try {
            $this->courriel()->envoyer(new MessagerieRedirigee($fichier, 'recette@exemple.ch'), 'info@peinture-morand.ch', 'Dicta', 'https://exemple.test');
            $ecrits = glob($dossier . '/*.eml');
            self::assertNotFalse($ecrits);
            self::assertCount(1, $ecrits);
            $eml = (string) file_get_contents($ecrits[0]);
            self::assertStringContainsString("To: recette@exemple.ch\r\n", $eml);
            self::assertStringContainsString('multipart/alternative', $eml);
            self::assertStringContainsString("Content-Type: text/plain; charset=UTF-8\r\n", $eml);
            self::assertStringContainsString("Content-Type: text/html; charset=UTF-8\r\n", $eml);
            self::assertStringContainsString('bgcolor="#23272D"', $eml);
            self::assertStringContainsString("\r\n" . self::LIEN . "\r\n", $eml);
        } finally {
            array_map(unlink(...), glob($dossier . '/*.eml') ?: []);
            @rmdir($dossier);
        }

        // Une messagerie qui ne connaît que le texte reçoit le texte.
        $simple = new class implements Messagerie {
            /** @var list<string> */
            public array $recus = [];

            public function envoyer(string $destinataire, string $sujet, string $texte): void
            {
                $this->recus[] = $texte;
            }
        };
        $this->courriel()->envoyer($simple, 'info@peinture-morand.ch', 'Dicta', 'https://exemple.test');
        self::assertCount(1, $simple->recus);
        self::assertStringNotContainsString('<table', $simple->recus[0]);
    }
}
