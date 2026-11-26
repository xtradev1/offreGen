<?php

declare(strict_types=1);

namespace OffreGen\Compte;

use OffreGen\Donnees\Dates;
use OffreGen\Donnees\DepotEquipe;
use OffreGen\Systeme\Courriel;
use OffreGen\Systeme\Messagerie;
use Psr\Clock\ClockInterface;
use Psr\Log\LoggerInterface;

/**
 * Rappels de fin de démo (Xavier, 2026-11-24) : un courriel 2 jours avant la fin, un autre 12 heures avant, au
 * courriel tapé par le prospect, signés par la personne qui a présenté la démo. Deux en tout, jamais plus. Lancé par
 * cron (bin/rappeler-demos) : le passage qui tombe dans la fenêtre envoie.
 */
final class RappelsDemo
{
    public const PREMIER_HEURES = 48;
    public const DERNIER_HEURES = 12;

    public function __construct(
        private readonly DepotEquipe $equipe,
        private readonly LiensAcces $liens,
        private readonly Messagerie $messagerie,
        private readonly ClockInterface $horloge,
        private readonly LoggerInterface $journal,
        private readonly string $nomApplication,
        private readonly string $adresseApplication,
    ) {
    }

    /** @return int le nombre de courriels envoyés */
    public function envoyer(): int
    {
        $maintenant = \DateTimeImmutable::createFromInterface($this->horloge->now());
        $envoyes = 0;
        foreach ($this->equipe->demosARappeler($maintenant, $maintenant->modify('+' . self::PREMIER_HEURES . ' hours')) as $demo) {
            $fin = Dates::depuisBase($demo['demo_fin']);
            $dernier = $fin <= $maintenant->modify('+' . self::DERNIER_HEURES . ' hours');
            if (!$dernier && $demo['demo_rappel_2j_le'] !== null) {
                continue;
            }
            if (!$this->equipe->noterRappelDemo($demo['id'], $dernier, $maintenant)) {
                continue;
            }
            try {
                Courriel::rappelDemo($dernier, $fin, $this->liens->lienRetourDemo($demo['id'], $fin), $demo['presente_par'])
                    ->envoyer($this->messagerie, $demo['courriel_saisi'], $this->nomApplication, $this->adresseApplication);
                $envoyes++;
            } catch (\Throwable $e) {
                // Noté avant l'envoi : une messagerie en panne ne fait pas repartir le rappel à chaque passage.
                $this->journal->error("offreGen, rappel de fin de démo {$demo['id']} : " . $e->getMessage());
            }
        }
        return $envoyes;
    }
}
