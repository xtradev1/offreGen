<?php

declare(strict_types=1);

namespace OffreGen\Tests\Outils;

use Psr\Log\AbstractLogger;

/** Un journal qui garde ses entrées, « niveau message », pour vérifier ce qui part en alerte (niveau error). */
final class JournalDeTest extends AbstractLogger
{
    /** @var list<string> */
    public array $lignes = [];

    public function log($level, string|\Stringable $message, array $context = []): void
    {
        $this->lignes[] = (is_string($level) ? $level : 'inconnu') . ' ' . $message;
    }

    /** @return list<string> les messages de niveau error */
    public function erreurs(): array
    {
        return array_values(array_filter($this->lignes, static fn (string $l): bool => str_starts_with($l, 'error ')));
    }
}
