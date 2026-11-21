<?php

declare(strict_types=1);

namespace OffreGen\Compte;

use OffreGen\Donnees\Base;
use OffreGen\Donnees\DepotEquipe;
use OffreGen\Donnees\JournalAdmin;
use OffreGen\Donnees\PorteeEquipe;
use Psr\Clock\ClockInterface;

/**
 * Les gestes de l'équipe sur le compte d'un artisan. « Offrir des offres » (décision R1 de Xavier, 2026-11-19) :
 * un coup de pouce de 2, 5 ou 10 offres, dans la limite du rôle ; l'administrateur n'a pas de limite.
 */
final class ArtisansEquipe
{
    public const AJOUTS = [2, 5, 10];
    /** Total d'offres qu'un modérateur ou un commercial peut ajouter à un même compte. */
    public const LIMITE = 10;

    public function __construct(
        private readonly Base $base,
        private readonly DepotEquipe $depot,
        private readonly JournalAdmin $journal,
        private readonly ClockInterface $horloge,
    ) {
    }

    /**
     * Les ajouts que ce rôle peut encore faire sur ce compte ; aucun pour un compte sans plafond.
     *
     * @param array{offres_max: int|null, offres_offertes: int} $compte
     * @return list<int>
     */
    public static function ajoutsPossibles(Role $role, array $compte): array
    {
        if ($compte['offres_max'] === null) {
            return [];
        }
        $reste = $role === Role::Administrateur ? PHP_INT_MAX : self::LIMITE - $compte['offres_offertes'];
        return array_values(array_filter(self::AJOUTS, static fn (int $n): bool => $n <= $reste));
    }

    /** @throws \InvalidArgumentException si l'ajout n'est pas permis ; rien n'est alors écrit */
    public function offrir(PorteeEquipe $portee, Role $role, int $artisanId, int $ajout): void
    {
        if (!in_array($ajout, self::AJOUTS, true)) {
            throw new \InvalidArgumentException('Choisissez 2, 5 ou 10 offres.');
        }
        $this->base->transaction(function () use ($portee, $role, $artisanId, $ajout): void {
            $limite = $role === Role::Administrateur ? null : self::LIMITE;
            if (!$this->depot->ajouterOffres($portee, $artisanId, $ajout, $limite)) {
                throw new \InvalidArgumentException("Ces offres n'ont pas été ajoutées : la limite de votre rôle est atteinte, ou ce compte n'a pas de plafond.");
            }
            $this->journal->noter($portee->acteurId, $artisanId, 'offres_ajoutees', '+' . $ajout, $this->horloge->now());
        });
    }
}
