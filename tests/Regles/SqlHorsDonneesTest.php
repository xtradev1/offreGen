<?php

declare(strict_types=1);

namespace OffreGen\Tests\Regles;

use PHPUnit\Framework\TestCase;

/**
 * Règle : le SQL ne vit que dans src/Donnees/ (et migrations/). Une requête ailleurs contourne
 * la portée de l'artisan, donc l'isolation entre clients.
 */
final class SqlHorsDonneesTest extends TestCase
{
    private const MOTIFS = [
        '/\bSELECT\b[\s\S]{1,400}?\bFROM\b/i',
        '/\bINSERT\s+INTO\b/i',
        '/\bUPDATE\s+`?\w+`?\s+SET\b/i',
        '/\bDELETE\s+FROM\b/i',
        '/new\s+\\\\?PDO\s*\(/',
        '/->(prepare|exec|query)\s*\(/',
    ];

    public function testAucuneRequeteHorsDeLaCoucheDonnees(): void
    {
        $racine = dirname(__DIR__, 2);
        $fautes = [];
        $vus = 0;
        foreach (['src', 'config', 'public', 'bin'] as $dossier) {
            foreach ($this->fichiers("$racine/$dossier") as $fichier) {
                $relatif = substr($fichier, strlen($racine) + 1);
                if (str_starts_with($relatif, 'src/Donnees/')) {
                    continue;
                }
                $vus++;
                $code = (string) file_get_contents($fichier);
                foreach (self::MOTIFS as $motif) {
                    if (preg_match($motif, $code, $m) === 1) {
                        $fautes[] = "$relatif : « " . trim(mb_substr($m[0], 0, 60)) . ' »';
                    }
                }
            }
        }
        self::assertGreaterThan(5, $vus, 'Le test ne lit presque aucun fichier : chemin faux ?');
        self::assertSame([], $fautes);
    }

    /** @return list<string> */
    private function fichiers(string $dossier): array
    {
        if (!is_dir($dossier)) {
            return [];
        }
        $liste = [];
        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dossier, \FilesystemIterator::SKIP_DOTS));
        foreach ($it as $f) {
            /** @var \SplFileInfo $f */
            $nom = $f->getFilename();
            if ($f->isFile() && (str_ends_with($nom, '.php') || !str_contains($nom, '.'))) {
                $liste[] = $f->getPathname();
            }
        }
        sort($liste);
        return $liste;
    }
}
