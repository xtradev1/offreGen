<?php

declare(strict_types=1);

namespace OffreGen\Donnees;

/**
 * Qui a fait quoi sur quel compte, depuis l'administration. Écrit à chaque geste de l'équipe ; jamais modifié.
 * Sans clé étrangère : une ligne survit au compte qu'elle désigne.
 */
final class JournalAdmin
{
    public function __construct(private readonly Base $base)
    {
    }

    public function noter(int $acteurId, ?int $artisanId, string $geste, ?string $detail, \DateTimeImmutable $quand): void
    {
        $this->base->ecrire(
            'INSERT INTO journal_admin (acteur_id, artisan_id, geste, detail, quand) VALUES (:a, :c, :g, :d, :q)',
            ['a' => $acteurId, 'c' => $artisanId, 'g' => $geste, 'd' => $detail, 'q' => Dates::versBase($quand)],
        );
    }
}
