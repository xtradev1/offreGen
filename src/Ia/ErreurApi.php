<?php

declare(strict_types=1);

namespace OffreGen\Ia;

/**
 * Le service d'IA a répondu par une erreur ; le code HTTP est gardé dans getCode(),
 * le code d'erreur du service (« error.code » chez Infomaniak) dans codeService.
 */
final class ErreurApi extends \RuntimeException
{
    public function __construct(string $message, int $code, public readonly ?string $codeService = null)
    {
        parent::__construct($message, $code);
    }
}
