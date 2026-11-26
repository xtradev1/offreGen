<?php

declare(strict_types=1);

namespace OffreGen\Http\Controleur;

use OffreGen\Donnees\EtatBase;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * GET /sante, public (docs/DEPLOIEMENT.md, « Contrôles en production ») : {"commit":"<sha>","base":"ok","migrations":"ok"},
 * sans autre information. La sonde d'exposition le lit après chaque déploiement.
 *
 * - commit : le contenu du fichier REVISION posé par le déploiement, seulement s'il a la forme d'un commit ;
 * - base : l'application se connecte et lit ;
 * - migrations : toutes celles que ce code connaît sont jouées. Une base plus récente que le code reste « ok »,
 *   sinon le retour arrière serait toujours rouge.
 *
 * Lecture seule. 503 dès que la base ou les migrations ne sont pas « ok ».
 */
final class SanteControleur
{
    /** @param \Closure(): EtatBase $base */
    public function __construct(
        private readonly \Closure $base,
        private readonly string $fichierRevision,
        private readonly string $dossierMigrations,
    ) {
    }

    public function etat(): Response
    {
        $base = 'ko';
        $migrations = 'ko';
        try {
            $etat = ($this->base)();
            $etat->joindre();
            $base = 'ok';
            $connues = $this->migrationsConnues();
            $jouees = $etat->migrationsJouees();
            if ($connues !== []) {
                $migrations = array_diff($connues, $jouees) === [] ? 'ok' : 'en_retard';
            }
        } catch (\Throwable) {
            // Rien du message : il peut contenir l'hôte ou l'utilisateur de la base.
        }
        $code = $base === 'ok' && $migrations === 'ok' ? Response::HTTP_OK : Response::HTTP_SERVICE_UNAVAILABLE;
        return new JsonResponse(['commit' => $this->commit(), 'base' => $base, 'migrations' => $migrations], $code, ['Cache-Control' => 'no-store']);
    }

    private function commit(): string
    {
        $texte = is_file($this->fichierRevision) ? trim((string) file_get_contents($this->fichierRevision)) : '';
        return preg_match('/^[0-9a-f]{7,40}$/', $texte) === 1 ? $texte : 'inconnu';
    }

    /** @return list<string> les classes de migration livrées avec ce code */
    private function migrationsConnues(): array
    {
        return array_map(
            static fn (string $f): string => 'OffreGen\\Migrations\\' . basename($f, '.php'),
            glob($this->dossierMigrations . '/Version*.php') ?: [],
        );
    }
}
