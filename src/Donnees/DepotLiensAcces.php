<?php

declare(strict_types=1);

namespace OffreGen\Donnees;

/**
 * Les liens d'accès envoyés par courriel. Hors portée par nature, comme les comptes : celui qui ouvre le lien
 * n'est pas encore connecté. On n'y cherche que par l'empreinte du jeton, jamais par une donnée reçue en clair.
 */
final class DepotLiensAcces
{
    public function __construct(private readonly Base $base)
    {
    }

    public function creer(int $artisanId, string $empreinte, string $motif, \DateTimeImmutable $quand, \DateTimeImmutable $expire): void
    {
        $this->base->ecrire(
            'INSERT INTO jeton_acces (artisan_id, empreinte, motif, cree_le, expire_le) VALUES (:a, :e, :m, :q, :x)',
            ['a' => $artisanId, 'e' => $empreinte, 'm' => $motif, 'q' => Dates::versBase($quand), 'x' => Dates::versBase($expire)],
        );
    }

    /** @return array{id: int, artisan_id: int, motif: string}|null le lien ni utilisé ni expiré */
    public function valide(string $empreinte, \DateTimeImmutable $maintenant): ?array
    {
        /** @var array{id: int, artisan_id: int, motif: string}|null */
        return $this->base->un(
            'SELECT id, artisan_id, motif FROM jeton_acces WHERE empreinte = :e AND utilise_le IS NULL AND expire_le > :m',
            ['e' => $empreinte, 'm' => Dates::versBase($maintenant)],
        );
    }

    public function compterDepuis(int $artisanId, string $motif, \DateTimeImmutable $depuis): int
    {
        return (int) $this->base->valeur(
            'SELECT COUNT(*) FROM jeton_acces WHERE artisan_id = :a AND motif = :m AND cree_le > :d',
            ['a' => $artisanId, 'm' => $motif, 'd' => Dates::versBase($depuis)],
        );
    }

    /** @return int 1 si ce lien était encore libre ; 0 s'il venait d'être pris (deux clics en même temps) */
    public function marquerUtilise(int $id, \DateTimeImmutable $quand): int
    {
        return $this->base->ecrire('UPDATE jeton_acces SET utilise_le = :q WHERE id = :id AND utilise_le IS NULL', ['q' => Dates::versBase($quand), 'id' => $id]);
    }

    /** Ferme tous les liens encore ouverts d'un compte. */
    public function annulerTout(int $artisanId, \DateTimeImmutable $quand): void
    {
        $this->base->ecrire('UPDATE jeton_acces SET utilise_le = :q WHERE artisan_id = :a AND utilise_le IS NULL', ['q' => Dates::versBase($quand), 'a' => $artisanId]);
    }
}
