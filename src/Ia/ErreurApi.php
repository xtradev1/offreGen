<?php

declare(strict_types=1);

namespace OffreGen\Ia;

/**
 * Le service d'IA a répondu par une erreur ; le code HTTP est gardé dans getCode(),
 * le code d'erreur du service (« error.code » chez Infomaniak) dans codeService.
 */
final class ErreurApi extends \RuntimeException
{
    /**
     * Infomaniak refuse (trop d'appels) ou ne suit plus (service surchargé) : à savoir tout de suite, même quand l'appel
     * n'était que secondaire ou une redemande du texte (Xavier, 2026-11-25).
     */
    public const CODES_REFUS = [429, 503];

    public function __construct(string $message, int $code, public readonly ?string $codeService = null)
    {
        parent::__construct($message, $code);
    }

    /** Le code HTTP du refus, cherché aussi dans les causes (le client HTTP enveloppe parfois) ; null : pas un refus. */
    public static function refus(\Throwable $e): ?int
    {
        for ($x = $e; $x !== null; $x = $x->getPrevious()) {
            if ($x instanceof self && in_array($x->getCode(), self::CODES_REFUS, true)) {
                return $x->getCode();
            }
        }
        return null;
    }

    /** Le message d'erreur d'un refus : l'objet du courriel d'alerte devient « Infomaniak refuse des appels ». */
    public static function alerteRefus(int $code, string $quoi): string
    {
        return "offreGen, Infomaniak refuse des appels : HTTP $code ($quoi). Trop d'appels en même temps, ou service surchargé.";
    }
}
