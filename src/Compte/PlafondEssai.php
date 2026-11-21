<?php

declare(strict_types=1);

namespace OffreGen\Compte;

/**
 * Compte d'essai (Xavier, 2026-11-17) : un nombre d'offres fixe, tous jours confondus. Chaque import reçu compte, réussi
 * ou non : c'est lui qui coûte (transcription, modèle), comme pour le plafond quotidien des comptes de démonstration.
 */
final class PlafondEssai
{
    private function __construct(public readonly int $offresMax, public readonly int $restantes)
    {
    }

    /** null : le compte n'a pas de plafond total. */
    public static function de(?int $offresMax, int $importsRecus): ?self
    {
        return $offresMax === null ? null : new self($offresMax, max(0, $offresMax - $importsRecus));
    }

    public function epuise(): bool
    {
        return $this->restantes === 0;
    }

    public function refus(): string
    {
        return 'Ce compte d\'essai a utilisé ' . ($this->offresMax === 1 ? 'sa seule offre.' : "ses {$this->offresMax} offres.")
            . ' Pour continuer, contactez-nous.';
    }

    public function reste(): string
    {
        return "Compte d'essai\u{00A0}: {$this->restantes} offre" . ($this->restantes > 1 ? 's restantes' : ' restante');
    }
}
