<?php

declare(strict_types=1);

namespace OffreGen\Offre;

use OffreGen\Ia\ReponseApi;
use OffreGen\Systeme\EtapeChrono;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * Un appel au modèle déjà parti, dont la réponse se lit plus tard. Le client curl de Symfony fait avancer toutes les
 * requêtes ouvertes pendant qu'on en attend une : deux appels lancés l'un après l'autre se déroulent en même temps
 * (pièces et options, Xavier, 2026-11-25).
 */
final class AppelEnCours
{
    public function __construct(private readonly EtapeChrono $mesure, private readonly ResponseInterface $reponse, private readonly string $quoi)
    {
    }

    /**
     * La réponse lue ; l'étape du chronomètre se ferme avec les jetons, ou en erreur.
     *
     * @return array<string, mixed>
     */
    public function recevoir(): array
    {
        try {
            $donnees = ReponseApi::lire($this->reponse, $this->quoi);
        } catch (\Throwable $e) {
            $this->mesure->echouer($e);
            throw $e;
        }
        $this->mesure->finir(is_array($donnees['usage'] ?? null) ? $donnees['usage'] : null);
        return $donnees;
    }

    /**
     * L'appel ne sert plus (l'autre a perdu le verrou) : l'étape se ferme en erreur, puis la requête est coupée. Couper
     * rappelle on_progress, dont le pouls relève le verrou perdu : cette exception-là ne doit pas masquer la cause.
     */
    public function abandonner(\Throwable $cause): void
    {
        $this->mesure->echouer($cause);
        try {
            $this->reponse->cancel();
        } catch (\Throwable) {
        }
    }
}
