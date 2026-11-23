<?php

declare(strict_types=1);

namespace OffreGen\Tests\Http;

use OffreGen\Http\Fichiers;
use OffreGen\Tests\Outils\Jeu;
use OffreGen\Tests\Outils\Monde;
use OffreGen\Tests\Outils\Navigateur;
use PHPUnit\Framework\TestCase;

/**
 * Lot 1 du chantier sans rechargement (2026-11-23) : adresses des fichiers qui suivent leur contenu (D1), un jeton
 * CSRF par page, règles de garde du .htaccess.
 */
final class SocleFluiditeTest extends TestCase
{
    private string $dossier = '';

    protected function tearDown(): void
    {
        if ($this->dossier !== '') {
            array_map('unlink', glob($this->dossier . '/css/*') ?: []);
            @rmdir($this->dossier . '/css');
            @rmdir($this->dossier);
        }
    }

    public function testUnStyleChangeChangeSonAdresse(): void
    {
        $this->dossier = sys_get_temp_dir() . '/fichiers-' . bin2hex(random_bytes(4));
        mkdir($this->dossier . '/css', 0700, true);
        file_put_contents($this->dossier . '/css/app.css', 'body { color: red; }');
        $avant = (new Fichiers($this->dossier))->adresse('/css/app.css');
        self::assertMatchesRegularExpression('#^/css/app\.css\?v=[0-9a-f]{12}$#', $avant);
        self::assertSame($avant, (new Fichiers($this->dossier))->adresse('/css/app.css'), 'même contenu, même adresse');

        // Xavier change un peu le style : la page suivante demande le nouveau fichier, sans version à tenir à la main.
        file_put_contents($this->dossier . '/css/app.css', 'body { color: blue; }');
        $apres = (new Fichiers($this->dossier))->adresse('/css/app.css');
        self::assertNotSame($avant, $apres);
        self::assertMatchesRegularExpression('#^/css/app\.css\?v=[0-9a-f]{12}$#', $apres);
    }

    public function testUnFichierAbsentGardeSonAdresseEtUnCheminDouteuxEstRefuse(): void
    {
        $f = new Fichiers(dirname(__DIR__, 2) . '/public');
        self::assertSame('/css/absent.css', $f->adresse('/css/absent.css'));
        foreach (['/css/../../.env', 'css/app.css', '/css/app.css?v=1', '/js/a b.js'] as $chemin) {
            try {
                $f->adresse($chemin);
                self::fail("accepté : $chemin");
            } catch (\InvalidArgumentException) {
                self::addToAssertionCount(1);
            }
        }
    }

    public function testLesPagesDemandentLesFichiersParLeurEmpreinte(): void
    {
        $page = (string) (new Navigateur(Monde::conteneur()))->get('/connexion')->getContent();
        self::assertMatchesRegularExpression('#href="/css/app\.css\?v=[0-9a-f]{12}"#', $page);
        self::assertMatchesRegularExpression('#src="/js/app\.js\?v=[0-9a-f]{12}"#', $page);
        self::assertDoesNotMatchRegularExpression('#(href|src)="/(css|js)/[^"?]+"#', $page);
    }

    public function testUnSeulJetonParPageValideEtNeufALaPageSuivante(): void
    {
        $jeu = new Jeu();
        $a = new Navigateur(Monde::conteneur());
        $a->connecter('a@exemple.ch');
        $offre = '/offres/' . $jeu->offre['a'];
        $page = (string) $a->get($offre)->getContent();
        preg_match_all('/name="_csrf" value="([^"]+)"/', $page, $m);
        self::assertGreaterThan(4, count($m[1]), 'une offre porte plusieurs formulaires');
        self::assertCount(1, array_unique($m[1]), 'tous les formulaires de la page portent la même valeur');
        $premier = $m[1][0];

        preg_match_all('/name="_csrf" value="([^"]+)"/', (string) $a->get($offre)->getContent(), $m2);
        self::assertCount(1, array_unique($m2[1]));
        self::assertNotSame($premier, $m2[1][0], 'la page suivante porte un autre masque');

        // L'ancienne valeur reste bonne : c'est le même jeton de session, autrement masqué.
        self::assertSame(303, $a->post($offre . '/textes', ['ou' => 'avant', 'texte' => 'Bonjour', '_csrf' => $premier])->getStatusCode());
        self::assertSame(403, $a->post($offre . '/textes', ['ou' => 'avant', 'texte' => 'Bonjour', '_csrf' => 'faux'])->getStatusCode());
    }

    public function testLeHtaccessGardeLesFichiersVersionnesEtRetireLEtag(): void
    {
        $regles = (string) file_get_contents(dirname(__DIR__, 2) . '/public/.htaccess');
        self::assertStringContainsString('FileETag None', $regles);
        self::assertMatchesRegularExpression('#QUERY_STRING\} =~ /\(\^\|&\)v=\[0-9a-f\]\{12\}#', $regles, 'la version de fichier() : 12 caractères');
        self::assertStringContainsString('Header set Cache-Control "public, max-age=31536000, immutable"', $regles);
        self::assertStringContainsString('Header set Cache-Control "no-cache"', $regles);
        self::assertStringNotContainsString('<IfModule', $regles, 'un module manquant doit casser le site, pas la protection');
    }
}
