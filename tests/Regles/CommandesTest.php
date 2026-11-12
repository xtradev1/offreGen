<?php

declare(strict_types=1);

namespace OffreGen\Tests\Regles;

use PHPUnit\Framework\TestCase;

/**
 * Les commandes de bin/ se lancent sans avertissement PHP, et aucune variable n'est collée à un
 * caractère non ASCII dans une chaîne : PHP l'avalerait dans le nom de la variable (défaut du 2026-11-07,
 * « $uuid » suivi d'une espace insécable lu comme une variable inconnue).
 */
final class CommandesTest extends TestCase
{
    public function testAucuneVariableColleeAUnCaractereNonAscii(): void
    {
        $racine = dirname(__DIR__, 2);
        $fautes = [];
        $fichiers = array_merge(glob("$racine/bin/*") ?: [], $this->php("$racine/src"), $this->php("$racine/config"), $this->php("$racine/public"));
        foreach ($fichiers as $f) {
            foreach (file($f) ?: [] as $n => $ligne) {
                if (preg_match('/\$[A-Za-z_][A-Za-z0-9_]*[\x80-\xff]/', $ligne) === 1) {
                    $fautes[] = substr($f, strlen($racine) + 1) . ':' . ($n + 1);
                }
            }
        }
        self::assertGreaterThan(20, count($fichiers));
        self::assertSame([], $fautes);
    }

    public function testLesCommandesSansArgumentRepondentSansAvertissement(): void
    {
        $racine = dirname(__DIR__, 2);
        foreach (['creer-compte' => 2, 'reinitialiser-demo' => 2] as $commande => $codeAttendu) {
            $sortie = [];
            exec(PHP_BINARY . ' ' . escapeshellarg("$racine/bin/$commande") . ' 2>&1', $sortie, $code);
            $texte = implode("\n", $sortie);
            self::assertSame($codeAttendu, $code, "$commande : $texte");
            self::assertStringContainsString('Usage', $texte);
            self::assertStringNotContainsString('Warning', $texte, $commande);
        }
    }

    /** @return list<string> */
    private function php(string $dossier): array
    {
        $liste = [];
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dossier, \FilesystemIterator::SKIP_DOTS)) as $f) {
            /** @var \SplFileInfo $f */
            if ($f->isFile() && str_ends_with($f->getFilename(), '.php')) {
                $liste[] = $f->getPathname();
            }
        }
        return $liste;
    }
}
