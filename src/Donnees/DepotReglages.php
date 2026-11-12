<?php

declare(strict_types=1);

namespace OffreGen\Donnees;

use OffreGen\Compte\Reglages;

/**
 * Les réglages du compte connecté, et de lui seul : la portée désigne la ligne de la table artisan.
 */
final class DepotReglages
{
    public function __construct(private readonly Base $base, private readonly Portee $portee)
    {
    }

    public function lire(): Reglages
    {
        $l = $this->base->un(
            'SELECT entreprise, adresse, telephone, courriel_contact, ide, tva_pour_dix_mille, arrondi_cinq_centimes, validite_jours, conditions_paiement
             FROM artisan WHERE id = :a',
            ['a' => $this->portee->artisanId],
        ) ?? throw new \RuntimeException('Compte introuvable.');
        /** @var array{entreprise: string, adresse: string|null, telephone: string|null, courriel_contact: string|null, ide: string|null, tva_pour_dix_mille: int, arrondi_cinq_centimes: int, validite_jours: int, conditions_paiement: string} $l */
        return new Reglages(
            $l['entreprise'], $l['tva_pour_dix_mille'], $l['arrondi_cinq_centimes'] === 1,
            $l['adresse'] ?? '', $l['telephone'] ?? '', $l['courriel_contact'] ?? '', $l['ide'] ?? '',
            $l['validite_jours'], $l['conditions_paiement'],
        );
    }

    public function enregistrer(Reglages $r): void
    {
        $vide = static fn (string $s): ?string => $s === '' ? null : $s;
        $this->base->ecrire(
            'UPDATE artisan SET entreprise = :e, adresse = :ad, telephone = :te, courriel_contact = :co, ide = :ide,
                    tva_pour_dix_mille = :t, arrondi_cinq_centimes = :r, validite_jours = :v, conditions_paiement = :cp
             WHERE id = :a',
            ['e' => $r->entreprise, 'ad' => $vide($r->adresse), 'te' => $vide($r->telephone), 'co' => $vide($r->courrielContact),
             'ide' => $vide($r->ide), 't' => $r->tvaPourDixMille, 'r' => $r->arrondiCinqCentimes ? 1 : 0,
             'v' => $r->validiteJours, 'cp' => $r->conditionsPaiement, 'a' => $this->portee->artisanId],
        );
    }
}
