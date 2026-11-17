<?php

declare(strict_types=1);

namespace OffreGen\Donnees;

/**
 * Les comptes. Hors portée par nature : on cherche un compte avant de savoir qui est connecté.
 * Sert la connexion et les commandes de bin/, jamais une page qui liste des comptes.
 */
final class DepotArtisans
{
    public function __construct(private readonly Base $base)
    {
    }

    /** @return array{id: int, courriel: string, mot_de_passe: string, entreprise: string, demo: int, plafond_jour: int|null, offres_max: int|null}|null */
    public function parCourriel(string $courriel): ?array
    {
        /** @var array{id: int, courriel: string, mot_de_passe: string, entreprise: string, demo: int, plafond_jour: int|null, offres_max: int|null}|null */
        return $this->base->un(
            'SELECT id, courriel, mot_de_passe, entreprise, demo, plafond_jour, offres_max FROM artisan WHERE courriel = :c',
            ['c' => self::normaliser($courriel)],
        );
    }

    /** @return array{id: int, courriel: string, mot_de_passe: string, entreprise: string, demo: int, plafond_jour: int|null, offres_max: int|null}|null */
    public function parId(int $id): ?array
    {
        /** @var array{id: int, courriel: string, mot_de_passe: string, entreprise: string, demo: int, plafond_jour: int|null, offres_max: int|null}|null */
        return $this->base->un(
            'SELECT id, courriel, mot_de_passe, entreprise, demo, plafond_jour, offres_max FROM artisan WHERE id = :id',
            ['id' => $id],
        );
    }

    public function creer(string $courriel, string $hache, string $entreprise, bool $demo, ?int $plafond, \DateTimeImmutable $quand, ?int $offresMax = null): int
    {
        $this->base->ecrire(
            'INSERT INTO artisan (courriel, mot_de_passe, entreprise, demo, plafond_jour, offres_max, cree_le)
             VALUES (:c, :h, :e, :d, :p, :m, :q)',
            ['c' => self::normaliser($courriel), 'h' => $hache, 'e' => $entreprise, 'd' => $demo ? 1 : 0, 'p' => $plafond, 'm' => $offresMax, 'q' => Dates::versBase($quand)],
        );
        return $this->base->dernierId();
    }

    /** Plafond total d'offres d'un compte d'essai ; null : aucun plafond. */
    public function changerOffresMax(int $id, ?int $offresMax): void
    {
        $this->base->ecrire('UPDATE artisan SET offres_max = :m WHERE id = :id', ['m' => $offresMax, 'id' => $id]);
    }

    public function changerMotDePasse(int $id, string $hache): void
    {
        $this->base->ecrire('UPDATE artisan SET mot_de_passe = :h WHERE id = :id', ['h' => $hache, 'id' => $id]);
    }

    public static function normaliser(string $courriel): string
    {
        return mb_strtolower(trim($courriel));
    }
}
