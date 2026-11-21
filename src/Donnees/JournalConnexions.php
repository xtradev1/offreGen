<?php

declare(strict_types=1);

namespace OffreGen\Donnees;

/**
 * Chaque tentative de connexion, réussie ou non. Sert à freiner les essais en série
 * et à répondre à la question « qui s'est connecté, quand, d'où ».
 */
final class JournalConnexions
{
    public function __construct(private readonly Base $base)
    {
    }

    public function noter(?int $artisanId, string $courriel, string $ip, bool $reussi, \DateTimeImmutable $quand): void
    {
        $this->base->ecrire(
            'INSERT INTO journal_connexion (artisan_id, courriel_essaye, ip, reussi, quand) VALUES (:a, :c, :ip, :r, :q)',
            ['a' => $artisanId, 'c' => mb_substr(DepotArtisans::normaliser($courriel), 0, 190), 'ip' => $ip, 'r' => $reussi ? 1 : 0, 'q' => Dates::versBase($quand)],
        );
    }

    /** Échecs depuis $depuis pour ce courriel ou depuis cette adresse IP. */
    public function echecsRecents(string $courriel, string $ip, \DateTimeImmutable $depuis): int
    {
        return (int) $this->base->valeur(
            'SELECT COUNT(*) FROM journal_connexion
             WHERE reussi = 0 AND quand >= :d AND (courriel_essaye = :c OR ip = :ip)',
            ['d' => Dates::versBase($depuis), 'c' => DepotArtisans::normaliser($courriel), 'ip' => $ip],
        );
    }
}
