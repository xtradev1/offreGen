<?php

declare(strict_types=1);

namespace OffreGen\Http;

/**
 * Une route déclarée dans config/routes.php. Sans droit, elle répond 403.
 */
final readonly class Route
{
    public const PUBLIC = 'public';
    public const ARTISAN = 'artisan';
    /** Compte connecté avec le droit administrateur ; 404 pour tout autre, avec ou sans session (A1, 2026-11-18). */
    public const ADMIN = 'administrateur';
    /** Administrateur ou modérateur ; 404 pour tout autre (rôles du 2026-11-19). */
    public const MODERATION = 'moderation';
    /** Administrateur, modérateur ou commercial ; 404 pour tout autre. */
    public const EQUIPE = 'equipe';
    /** Les droits des pages d'administration : elles n'existent pour personne d'autre. */
    public const RESERVES = [self::ADMIN, self::MODERATION, self::EQUIPE];

    /**
     * @param list<string> $verbes
     * @param \Closure(\Symfony\Component\HttpFoundation\Request, array<string, string>): \Symfony\Component\HttpFoundation\Response $action
     * @param (\Closure(\Symfony\Component\HttpFoundation\Request): \Symfony\Component\HttpFoundation\Response)|null $visiteur
     *        Sur une route artisan : ce que voit un visiteur sans session, à la place du renvoi à la connexion.
     */
    public function __construct(
        public string $nom,
        public string $chemin,
        public array $verbes,
        public \Closure $action,
        public ?string $droit,
        public ?\Closure $visiteur = null,
    ) {
    }
}
