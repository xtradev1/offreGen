<?php

declare(strict_types=1);

namespace OffreGen\Compte;

use OffreGen\Donnees\Dates;

/**
 * Le mois gratuit d'un compte passé de la démo à l'essai (chantier onboarding, E1 de Xavier du 2026-11-23 ; lot B de
 * l'administration : T1 « l'essai gratuit c'est choisir un abonnement », P4 « plafond de l'offre choisi »). Il
 * commence au passage, avec les offres de la formule Essentiel, seule ouverte au lancement (G2).
 */
final class MoisGratuit
{
    /** Les offres du mois de la formule Essentiel (lot B, étude du 17.11). */
    public const OFFRES = 50;

    /**
     * Un mois de calendrier, à Zurich : commencé le 24 novembre, il finit le 24 décembre à 23:59:59 (le dernier jour
     * compte en entier, le ruban dit « dernier jour » sans mentir). Un jour qui n'existe pas le mois suivant (le
     * 31 janvier) devient le dernier jour de ce mois (le 28 ou le 29 février).
     */
    public static function fin(\DateTimeInterface $debut): \DateTimeImmutable
    {
        $zone = new \DateTimeZone('Europe/Zurich');
        $local = \DateTimeImmutable::createFromInterface($debut)->setTimezone($zone);
        $fin = $local->modify('+1 month');
        if ($fin->format('j') !== $local->format('j')) {
            $fin = $local->modify('last day of next month');
        }
        return $fin->setTime(23, 59, 59)->setTimezone(new \DateTimeZone('UTC'));
    }

    public static function termine(?string $essaiFin, \DateTimeInterface $maintenant): bool
    {
        return $essaiFin !== null && Dates::depuisBase($essaiFin)->getTimestamp() <= $maintenant->getTimestamp();
    }
}
