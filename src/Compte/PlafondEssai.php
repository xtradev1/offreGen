<?php

declare(strict_types=1);

namespace OffreGen\Compte;

/**
 * Compte d'essai (Xavier, 2026-11-17) : un nombre d'offres fixe, tous jours confondus. Chaque import reçu compte, réussi
 * ou non : c'est lui qui coûte (transcription, modèle), comme pour le plafond quotidien des comptes de démonstration.
 *
 * Le mois gratuit (MoisGratuit, chantier onboarding, E1) est un essai daté : ses offres sont celles de la formule,
 * comptées à partir du passage ; la jauge validée dit « 12 offres ce mois sur 50 » (maquette v5 de l'administration).
 */
final class PlafondEssai
{
    private function __construct(public readonly int $offresMax, public readonly int $restantes, public readonly bool $moisGratuit)
    {
    }

    /** null : le compte n'a pas de plafond total. */
    public static function de(?int $offresMax, int $importsRecus, bool $moisGratuit = false): ?self
    {
        return $offresMax === null ? null : new self($offresMax, max(0, $offresMax - $importsRecus), $moisGratuit);
    }

    public function epuise(): bool
    {
        return $this->restantes === 0;
    }

    public function refus(): string
    {
        if ($this->moisGratuit) {
            return 'Vous avez dicté les ' . MoisGratuit::OFFRES . " offres de votre mois gratuit. Pour continuer, demandez un appel depuis «\u{00A0}Plus\u{00A0}».";
        }
        return 'Ce compte d\'essai a utilisé ' . ($this->offresMax === 1 ? 'sa seule offre.' : "ses {$this->offresMax} offres.")
            . ' Pour continuer, contactez-nous.';
    }

    public function reste(): string
    {
        if ($this->moisGratuit) {
            $faites = max(0, MoisGratuit::OFFRES - $this->restantes);
            return $faites . ' offre' . ($faites > 1 ? 's' : '') . ' ce mois sur ' . MoisGratuit::OFFRES;
        }
        return "Compte d'essai\u{00A0}: {$this->restantes} offre" . ($this->restantes > 1 ? 's restantes' : ' restante');
    }
}
