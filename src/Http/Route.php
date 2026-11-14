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

    /**
     * @param list<string> $verbes
     * @param \Closure(\Symfony\Component\HttpFoundation\Request, array<string, string>): \Symfony\Component\HttpFoundation\Response $action
     */
    public function __construct(
        public string $nom,
        public string $chemin,
        public array $verbes,
        public \Closure $action,
        public ?string $droit,
    ) {
    }
}
