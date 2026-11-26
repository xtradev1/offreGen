<?php

declare(strict_types=1);

namespace OffreGen\Http;

use Psr\Log\LoggerInterface;

/**
 * Travail à faire une fois la réponse envoyée, pour rendre la main à l'artisan tout de suite.
 * Une tâche qui échoue n'empêche pas les suivantes : cron reprend ce qui n'a pas abouti.
 */
final class ApresReponse
{
    /** @var list<callable(): void> */
    private array $taches = [];

    public function __construct(private readonly LoggerInterface $journal)
    {
    }

    /** @param callable(): void $tache */
    public function ajouter(callable $tache): void
    {
        $this->taches[] = $tache;
    }

    public function nombre(): int
    {
        return count($this->taches);
    }

    public function executer(): void
    {
        $taches = $this->taches;
        $this->taches = [];
        foreach ($taches as $tache) {
            try {
                $tache();
            } catch (\Throwable $e) {
                $this->journal->error('offreGen, tâche après réponse : ' . $e->getMessage());
            }
        }
    }
}
