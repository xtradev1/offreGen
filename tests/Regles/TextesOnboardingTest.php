<?php

declare(strict_types=1);

namespace OffreGen\Tests\Regles;

use OffreGen\Http\Textes;
use PHPUnit\Framework\TestCase;

/**
 * Les textes de l'onboarding (docs/plans/2026-11-21_onboarding/PLAN.md, lot 1bis) : chaque texte appelé par un
 * gabarit existe, la typographie française est posée dans le fichier, les variables sont échappées.
 */
final class TextesOnboardingTest extends TestCase
{
    private const FICHIER = __DIR__ . '/../../config/textes-onboarding.json';

    public function testChaqueTexteAppeleParUnGabaritExiste(): void
    {
        $textes = (new Textes(self::FICHIER))->tous();
        $appels = 0;
        // Gabarits et code PHP : un texte inconnu lève une exception, donc une page en erreur (revue du 2026-11-22). Toute
        // chaîne de la forme d'un identifiant dans un appel à texte() est lue, ternaires et tableaux compris ; un morceau
        // suivi de « ~ » (« 'k.r1.' ~ i », « 'k.o' ~ i ») se compose à l'exécution et n'est pas lu.
        foreach (['templates', 'src'] as $dossier) {
            $fichiers = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(__DIR__ . '/../../' . $dossier, \FilesystemIterator::SKIP_DOTS));
            foreach ($fichiers as $f) {
                if (!str_ends_with((string) $f, '.twig') && !str_ends_with((string) $f, '.php')) {
                    continue;
                }
                preg_match_all('/texte\(([^;\n]*)/', (string) file_get_contents((string) $f), $appelsDuFichier);
                foreach ($appelsDuFichier[1] as $suite) {
                    preg_match_all("/'([a-z]{1,4}\\.[a-z0-9.]*[a-z0-9])'(?!\\s*~)/", $suite, $m);
                    foreach ($m[1] as $id) {
                        $appels++;
                        self::assertArrayHasKey($id, $textes, "$f appelle un texte inconnu");
                    }
                }
            }
        }
        self::assertGreaterThan(50, $appels);
    }

    public function testTypographieFrancaiseSansTiretLong(): void
    {
        foreach ((new Textes(self::FICHIER))->tous() as $id => $texte) {
            self::assertDoesNotMatchRegularExpression('/[\x{2014}\x{2013}]|&mdash;/u', $texte, $id);
            self::assertDoesNotMatchRegularExpression('/ [:;!?%»]/u', $texte, "$id : espace ordinaire avant la ponctuation");
            self::assertDoesNotMatchRegularExpression('/« /u', $texte, "$id : espace ordinaire après «");
        }
    }

    public function testLesVariablesSontEchappeesEtUnTexteInconnuLeveUneException(): void
    {
        $textes = new Textes(self::FICHIER);
        self::assertSame("Bienvenue dans l'équipe, &lt;b&gt;Nina", $textes->texte('c.b1.titre', ['prenom' => '<b>Nina']));
        $this->expectException(\OutOfBoundsException::class);
        $textes->texte('inconnu');
    }
}
