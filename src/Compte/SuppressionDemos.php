<?php

declare(strict_types=1);

namespace OffreGen\Compte;

use OffreGen\Audio\StockageAudio;
use OffreGen\Donnees\Base;
use OffreGen\Donnees\DepotDemosFinies;
use OffreGen\Donnees\DepotEnregistrements;
use OffreGen\Donnees\Portee;
use Psr\Clock\ClockInterface;
use Psr\Log\LoggerInterface;

/**
 * Une démo finie est supprimée 30 jours après sa fin (Xavier, maquette v2 de l'administration, 2026-11-19 : « gardée
 * jusqu'au 30e jour puis supprimée » ; écran de fin K1 : « Vos offres restent consultables jusqu'au …, puis elles
 * sont supprimées » ; chantier onboarding, E1 du 2026-11-23). Démos d'entraînement comprises. Le compte part avec
 * tout ce qui lui appartient, fichiers audio et logo compris. Le journal de l'équipe reste : une démo supprimée compte
 * encore dans les limites d'un commercial (dix démos par 24 heures, démos d'entraînement).
 *
 * Seules les démos de l'équipe ont une date de fin : les démonstrations partagées (sans demo_fin) et les essais ne
 * sont jamais touchés.
 */
final class SuppressionDemos
{
    private readonly DepotDemosFinies $demos;

    public function __construct(
        private readonly Base $base,
        private readonly StockageAudio $stockage,
        private readonly string $dossierLogos,
        private readonly ClockInterface $horloge,
        private readonly LoggerInterface $journal,
    ) {
        $this->demos = new DepotDemosFinies($base);
    }

    /** @return array{supprimees: int, erreurs: int} */
    public function supprimer(): array
    {
        $limite = \DateTimeImmutable::createFromInterface($this->horloge->now())->modify('-' . Demos::JOURS_GARDEE . ' days');
        $supprimees = 0;
        $erreurs = 0;
        foreach ($this->demos->finiesAvant($limite) as $d) {
            try {
                $fichiers = (new DepotEnregistrements($this->base, new Portee($d['id'])))->fichiers();
                $this->demos->effacer($d['id'], $limite);
                // Les fichiers après la validation : une erreur en base ne laisse pas des lignes sans leurs fichiers.
                foreach ($fichiers as $fichier) {
                    $this->stockage->supprimer($fichier);
                }
                $logo = $d['logo_fichier'] === null ? null : $this->dossierLogos . '/' . basename($d['logo_fichier']);
                if ($logo !== null && is_file($logo)) {
                    unlink($logo);
                }
                $supprimees++;
            } catch (\Throwable $e) {
                // Une démo récalcitrante ne bloque pas les autres ; elle sera retentée la nuit suivante.
                $erreurs++;
                $this->journal->error("offreGen, suppression de la démo {$d['id']} : " . $e->getMessage());
            }
        }
        return ['supprimees' => $supprimees, 'erreurs' => $erreurs];
    }
}
