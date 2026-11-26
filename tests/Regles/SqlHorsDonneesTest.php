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
        // UPDATE t SET, avec alias (« t AS a », « t a ») ou jointure avant SET.
        '/\bUPDATE\s+`?\w+`?(\s+(AS\s+)?\w+)?\s+(SET|JOIN|INNER|LEFT|RIGHT|STRAIGHT_JOIN)\b/i',
        // DELETE FROM, et DELETE alias FROM (suppression avec jointure).
        '/\bDELETE\s+(\w+(\s*,\s*\w+)*\s+)?FROM\b/i',
        // En capitales seulement : « replace » et « truncate » se lisent aussi dans les commentaires.
        '/\bREPLACE\s+INTO\b/',
        '/\bTRUNCATE\s+(TABLE\s+)?`?\w+/',
        '/new\s+\\\\?PDO\s*\(/',
        '/->(prepare|exec|query)\s*\(/',
    ];

    /**
     * Revue du 2026-11-18 (M14) : les formes à alias et à jointure, REPLACE et TRUNCATE passaient sans alerte. Chaque
     * forme vient d'une requête réelle de src/Donnees (DepotClients, DepotFactures) ou du manuel de MariaDB.
     */
    public function testLesMotifsAttrapentLesFormesConnues(): void
    {
        $formes = [
            'SELECT o.id FROM offre o WHERE o.artisan_id = :a',
            'INSERT INTO ligne (artisan_id) VALUES (:a)',
            'UPDATE offre SET titre = :t',
            'UPDATE ligne l JOIN offre o ON o.id = l.offre_id SET l.piece = :p',
            'UPDATE facture AS f SET f.statut = :s',
            'DELETE FROM ligne WHERE id = :i',
            'DELETE l FROM ligne l JOIN offre o ON o.id = l.offre_id',
            'REPLACE INTO reglage (cle) VALUES (:c)',
            'TRUNCATE TABLE journal_connexion',
            'TRUNCATE journal_connexion',
        ];
        foreach ($formes as $forme) {
            $vu = false;
            foreach (self::MOTIFS as $motif) {
                $vu = $vu || preg_match($motif, $forme) === 1;
            }
            self::assertTrue($vu, "Forme non attrapée : $forme");
        }
    }

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
