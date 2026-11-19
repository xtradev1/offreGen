<?php

declare(strict_types=1);

namespace OffreGen\Compte;

use OffreGen\Donnees\Base;
use OffreGen\Donnees\DepotArtisans;
use OffreGen\Donnees\DepotEquipe;
use OffreGen\Donnees\JournalAdmin;
use Psr\Clock\ClockInterface;

/**
 * L'équipe : l'administrateur y fait entrer un commercial ou un modérateur, change son rôle, le retire
 * (décisions R1, R4 et E1 de Xavier, 2026-11-19). Le droit administrateur reste hors d'ici : il se donne par
 * bin/administrateur, pour qu'une session volée ne puisse pas se le donner.
 */
final class Equipe
{
    /** Offres de l'espace artisan d'un nouveau membre ; il ne peut pas changer ce nombre lui-même (R4). */
    public const OFFRES_PAR_DEFAUT = 10;
    public const OFFRES_MAX = 500;

    public function __construct(
        private readonly Base $base,
        private readonly DepotArtisans $artisans,
        private readonly DepotEquipe $depot,
        private readonly JournalAdmin $journal,
        private readonly Comptes $comptes,
        private readonly ClockInterface $horloge,
    ) {
    }

    /**
     * Crée le compte du membre, ou donne le rôle à un compte qui existe déjà (son mot de passe ne change pas).
     *
     * @return array{id: int, nouveau: bool}
     */
    public function inviter(int $acteurId, string $nom, string $courriel, Role $role, int $offres): array
    {
        if ($role === Role::Administrateur) {
            throw new \InvalidArgumentException('Le droit administrateur se donne par commande.');
        }
        return $this->base->transaction(function () use ($acteurId, $nom, $courriel, $role, $offres): array {
            $existant = $this->artisans->parCourriel($courriel);
            if ($existant !== null) {
                if ($existant['demo'] === 1) {
                    throw new \InvalidArgumentException("Un compte de démonstration ne rejoint pas l'équipe.");
                }
                if ($existant['administrateur'] === 1 || $existant['role'] !== null) {
                    throw new \InvalidArgumentException("Cette personne fait déjà partie de l'équipe.");
                }
                $id = $existant['id'];
            } else {
                if (trim($nom) === '') {
                    throw new \InvalidArgumentException('Indiquez le nom.');
                }
                if ($offres < 1 || $offres > self::OFFRES_MAX) {
                    throw new \InvalidArgumentException("Le nombre d'offres va de 1 à " . self::OFFRES_MAX . '.');
                }
                $this->comptes->creer($courriel, $nom, false, null, $offres);
                $id = $this->artisans->parCourriel($courriel)['id'] ?? throw new \LogicException('Compte créé introuvable.');
            }
            $this->depot->rattacher($id, $role->value, $acteurId);
            $this->journal->noter($acteurId, $id, $existant === null ? 'membre_cree' : 'membre_promu', $role->value, $this->horloge->now());
            return ['id' => $id, 'nouveau' => $existant === null];
        });
    }

    /**
     * Change le rôle d'un membre ; null le retire de l'équipe, et ses artisans passent à celui qui agit.
     * Refuse son propre compte, un administrateur, et le rôle administrateur.
     */
    public function changerRole(int $acteurId, int $membreId, ?Role $role): void
    {
        $membre = $this->depot->membre($membreId);
        if ($membre === null || $membre['role'] === Role::Administrateur->value || $membreId === $acteurId || $role === Role::Administrateur) {
            throw new \InvalidArgumentException('Ce rôle ne se change pas ici.');
        }
        $this->base->transaction(function () use ($acteurId, $membreId, $role): void {
            $this->depot->changerRole($membreId, $role?->value);
            if ($role !== null) {
                $this->journal->noter($acteurId, $membreId, 'role_change', $role->value, $this->horloge->now());
                return;
            }
            $repris = $this->depot->reattribuer($membreId, $acteurId);
            $this->journal->noter($acteurId, $membreId, 'membre_retire', $repris . ' artisan' . ($repris > 1 ? 's' : '') . ' repris', $this->horloge->now());
        });
    }
}
