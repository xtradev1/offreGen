<?php

declare(strict_types=1);

namespace OffreGen\Tests\Http;

use OffreGen\Http\Noyau;
use OffreGen\Tests\Outils\Monde;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

final class EntetesTest extends TestCase
{
    public function testChaqueReponsePorteLesEntetesDeSecurite(): void
    {
        $noyau = Monde::conteneur()->noyau();
        foreach (['/connexion', '/nulle-part', '/'] as $chemin) {
            $reponse = $noyau->traiter(Request::create($chemin));
            foreach (Noyau::ENTETES as $nom => $valeur) {
                self::assertSame($valeur, $reponse->headers->get($nom), "$chemin : $nom");
            }
            self::assertFalse($reponse->headers->has('Strict-Transport-Security'), "$chemin : HSTS hors HTTPS");
        }
    }

    public function testHstsSeulementEnHttps(): void
    {
        $reponse = Monde::conteneur()->noyau()->traiter(Request::create('https://exemple.test/connexion'));
        self::assertSame('max-age=31536000', $reponse->headers->get('Strict-Transport-Security'));
    }

    public function testLesValeursSontCellesDuHtaccess(): void
    {
        $htaccess = (string) file_get_contents(dirname(__DIR__, 2) . '/public/.htaccess');
        foreach (Noyau::ENTETES as $nom => $valeur) {
            self::assertStringContainsString("Header always set $nom \"$valeur\"", $htaccess, "$nom diffère entre Noyau et public/.htaccess");
        }
    }
}
