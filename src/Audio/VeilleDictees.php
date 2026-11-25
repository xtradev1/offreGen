<?php

declare(strict_types=1);

namespace OffreGen\Audio;

use OffreGen\Donnees\Dates;
use OffreGen\Donnees\FileTraitement;
use Psr\Log\LoggerInterface;

/**
 * Le point de vérification des dictées (Xavier, 2026-11-25 : « si demain il y a 10 ou 20 utilisateurs […] et que ça
 * commence à bloquer, il faut vraiment que je sache vite »). Lancé par cron chaque minute : une dictée qui attend son
 * offre depuis plus de ATTENTE_MAX_MINUTES devient une erreur du journal, donc un courriel d'alerte (Alertes : une fois
 * par heure tant que ça dure, les chiffres du message ne comptent pas). Une dictée normale est prête en moins d'une
 * minute ; une dictée en échec est déjà signalée par sa propre erreur.
 */
final class VeilleDictees
{
    public const ATTENTE_MAX_MINUTES = 5;

    public function __construct(private readonly FileTraitement $file, private readonly LoggerInterface $journal)
    {
    }

    /** @return int le nombre de dictées en retard (0 : rien n'est signalé) */
    public function verifier(\DateTimeImmutable $maintenant): int
    {
        $etat = $this->file->enAttente($maintenant->modify('-' . self::ATTENTE_MAX_MINUTES . ' minutes'));
        if ($etat['en_retard'] === 0 || $etat['plus_ancienne'] === null) {
            return 0;
        }
        $minutes = intdiv($maintenant->getTimestamp() - Dates::depuisBase($etat['plus_ancienne'])->getTimestamp(), 60);
        $this->journal->error(sprintf(
            'offreGen, dictées bloquées : %d dictée(s) attendent leur offre depuis plus de %d minutes, la plus ancienne depuis %d min (%d en cours en tout). '
            . 'Infomaniak lent ou saturé, ou traitement arrêté : voir /admin/dictees.',
            $etat['en_retard'], self::ATTENTE_MAX_MINUTES, $minutes, $etat['en_cours'],
        ));
        return $etat['en_retard'];
    }
}
