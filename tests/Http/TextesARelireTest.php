<?php

declare(strict_types=1);

namespace OffreGen\Tests\Http;

use OffreGen\Tests\Outils\Monde;
use OffreGen\Tests\Outils\Navigateur;
use PHPUnit\Framework\TestCase;

/**
 * La page de relecture des textes (chantier 2026-11-24_relecture-textes) : servie seulement avec RELECTURE_TEXTES=1,
 * conforme à la CSP fermée du site, et complète (chaque fichier qu'elle appelle existe).
 */
final class TextesARelireTest extends TestCase
{
    private const RACINE = __DIR__ . '/../..';

    public function testSansLeReglageLaPageNExistePas(): void
    {
        self::assertSame(404, (new Navigateur(Monde::conteneur()))->get('/relecture')->getStatusCode());
        self::assertSame(404, (new Navigateur(Monde::conteneur(null, ['RELECTURE_TEXTES' => 'oui'])))->get('/relecture')->getStatusCode());
    }

    public function testAvecLeReglageUnVisiteurSansSessionLaLit(): void
    {
        $reponse = (new Navigateur(Monde::conteneur(null, ['RELECTURE_TEXTES' => '1'])))->get('/relecture');
        self::assertSame(200, $reponse->getStatusCode());
        self::assertStringContainsString('<title>Textes de Dicta à relire</title>', (string) $reponse->getContent());
        self::assertSame('noindex, nofollow', $reponse->headers->get('X-Robots-Tag'));
    }

    public function testLaPageRespecteLaCspFermee(): void
    {
        $page = (string) (new Navigateur(Monde::conteneur(null, ['RELECTURE_TEXTES' => '1'])))->get('/relecture')->getContent();
        self::assertStringNotContainsString(' style=', $page);
        self::assertStringNotContainsString('<style', $page);
        self::assertDoesNotMatchRegularExpression('/\son[a-z]+="/', $page);
        // Seuls des scripts de ce site, et un bloc de données que le navigateur n'exécute pas.
        preg_match_all('/<script\b([^>]*)>/', $page, $scripts);
        self::assertNotEmpty($scripts[1]);
        foreach ($scripts[1] as $attributs) {
            self::assertMatchesRegularExpression('#^\s+(src="/relecture-textes/[a-z-]+\.js"|type="application/json" id="donnees")$#', $attributs);
        }
        self::assertDoesNotMatchRegularExpression('/\s(?:src|href)="(?!\/|#)/', $page, 'Une ressource vient d\'un autre site.');
    }

    /**
     * Apache sert un dossier de public/ avant de passer la main à PHP : un dossier public/relecture ferait rediriger
     * /relecture vers /relecture/ (constaté sur le test le 2026-11-24), et la page ne s'ouvrirait jamais.
     */
    public function testAucunDossierPublicNePorteLeNomDeLaRoute(): void
    {
        self::assertDirectoryDoesNotExist(self::RACINE . '/public/relecture');
    }

    public function testChaqueFichierAppeleExiste(): void
    {
        $page = (string) file_get_contents(self::RACINE . '/relecture/index.html');
        preg_match_all('#(?:src|href)="(/relecture-textes/[^"]+)"#', $page, $fichiers);
        self::assertCount(4, $fichiers[1], 'Deux feuilles de style et deux scripts.');
        if (preg_match('#<script type="application/json" id="donnees">(.*?)</script>#s', $page, $bloc) !== 1) {
            self::fail('La page n\'a pas de bloc de données.');
        }
        $donnees = self::donnees($bloc[1]);
        $images = [];
        $numeros = [];
        foreach ($donnees['parties'] as $partie) {
            foreach ($partie['ecrans'] as $ecran) {
                $images[] = $donnees['images'] . $ecran['image'];
                $images[] = $donnees['ecrans'] . $ecran['apercu']['fichier'] . '.txt';
                $images[] = $donnees['ecrans'] . $ecran['apercu']['fichier'] . '.css';
                foreach ($ecran['textes'] as $texte) {
                    $numeros[] = $texte['n'];
                }
            }
        }
        self::assertGreaterThan(30, count($images));
        foreach ([...$fichiers[1], ...$images, $donnees['ecrans'] . 'commun.css'] as $chemin) {
            self::assertFileExists(self::RACINE . '/public' . $chemin);
        }
        self::assertSame(count($numeros), count(array_unique($numeros)), 'Chaque texte a son propre numéro.');
    }

    /**
     * Les copies figées des écrans (aperçu en direct) s'écrivent dans un cadre qui hérite de la CSP de la page : ni
     * script, ni style écrit dans le HTML, ni ressource d'un autre site. Chaque texte numéroté y est relié au moins
     * une fois, sinon l'aperçu ne suivrait pas sa correction.
     */
    public function testLesCopiesDesEcransRespectentLaCspEtPortentLesTextes(): void
    {
        $page = (string) file_get_contents(self::RACINE . '/relecture/index.html');
        if (preg_match('#<script type="application/json" id="donnees">(.*?)</script>#s', $page, $bloc) !== 1) {
            self::fail('La page n\'a pas de bloc de données.');
        }
        $donnees = self::donnees($bloc[1]);
        $relies = [];
        $copies = glob(self::RACINE . '/public/relecture-textes/ecrans/*.txt') ?: [];
        self::assertNotEmpty($copies);
        foreach ($copies as $copie) {
            $html = (string) file_get_contents($copie);
            self::assertDoesNotMatchRegularExpression('/<script\b|\sstyle=|\son[a-z]+="|<style\b/i', $html, basename($copie));
            self::assertDoesNotMatchRegularExpression('/\s(?:src|href)="(?!\/|#)/', $html, basename($copie));
            preg_match_all('/data-t="(T\d+)"/', $html, $m);
            $relies = [...$relies, ...$m[1]];
        }
        $numeros = [];
        foreach ($donnees['parties'] as $partie) {
            foreach ($partie['ecrans'] as $ecran) {
                foreach ($ecran['textes'] as $texte) {
                    $numeros[] = $texte['n'];
                }
            }
        }
        $orphelins = array_values(array_diff($numeros, $relies));
        self::assertLessThan(count($numeros) / 10, count($orphelins), 'Textes sans place dans l\'aperçu : ' . implode(', ', array_slice($orphelins, 0, 10)));
    }

    /** @return array{images: string, ecrans: string, parties: list<array{ecrans: list<array{image: string, apercu: array{fichier: string}, textes: list<array{n: string, t: string}>}>}>} */
    private static function donnees(string $json): array
    {
        /** @var array{images: string, ecrans: string, parties: list<array{ecrans: list<array{image: string, apercu: array{fichier: string}, textes: list<array{n: string, t: string}>}>}>} */
        return json_decode(str_replace('<\/', '</', $json), true, 32, JSON_THROW_ON_ERROR);
    }

    public function testAucunTiretLong(): void
    {
        foreach (['/relecture/index.html', '/public/relecture-textes/relecture.js', '/public/relecture-textes/relecture.css', '/public/relecture-textes/ecrans/commun.css'] as $fichier) {
            self::assertDoesNotMatchRegularExpression('/\x{2014}|\x{2013}/u', (string) file_get_contents(self::RACINE . $fichier), $fichier);
        }
    }
}
