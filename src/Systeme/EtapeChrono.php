<?php

declare(strict_types=1);

namespace OffreGen\Systeme;

use OffreGen\Donnees\DepotEtapes;

/**
 * Une étape en cours, rendue par Chronometre::commencer. Se ferme une seule fois : finir ou échouer.
 */
final class EtapeChrono
{
    private bool $close = false;

    /** Départ en temps monotone (hrtime, ns), pour le premier octet. */
    private readonly int $depart;

    /** Millisecondes entre le début de l'étape et les en-têtes de la réponse, vues par on_progress. */
    private ?int $reponseMs = null;

    public function __construct(private readonly ?Chronometre $chrono, private readonly int $id, private readonly \DateTimeImmutable $debut)
    {
        $this->depart = hrtime(true);
    }

    public static function rien(): self
    {
        return new self(null, 0, new \DateTimeImmutable('@0'));
    }

    /**
     * À appeler depuis on_progress : retient l'instant où les en-têtes de la réponse arrivent (http_code connu).
     * starttransfer_time de curl ne sert pas : le curl 7.74 du serveur de test y met, pour un POST, le début de
     * l'envoi du corps (0 ms relevé sur chaque appel à l'IA, recette du 2026-11-24).
     *
     * @param array<mixed> $info troisième argument de on_progress
     */
    public function progres(array $info): void
    {
        if ($this->reponseMs === null && is_int($info['http_code'] ?? null) && $info['http_code'] > 0) {
            $this->reponseMs = intdiv(hrtime(true) - $this->depart, 1_000_000);
        }
    }

    /**
     * @param array<mixed>|null $usage « usage » de la réponse du modèle : jetons envoyés et reçus déclarés par le service
     */
    public function finir(?array $usage = null, ?string $modele = null): void
    {
        if ($this->chrono === null || $this->close) {
            return;
        }
        $this->close = true;
        $envoyes = is_int($usage['prompt_tokens'] ?? null) ? $usage['prompt_tokens'] : null;
        $recus = is_int($usage['completion_tokens'] ?? null) ? $usage['completion_tokens'] : null;
        $this->chrono->terminer($this->id, $this->debut, DepotEtapes::OK, null, $modele, $envoyes, $recus, $this->reponseMs);
    }

    public function echouer(\Throwable $e): void
    {
        if ($this->chrono === null || $this->close) {
            return;
        }
        $this->close = true;
        $this->chrono->terminer($this->id, $this->debut, DepotEtapes::ERREUR, Chronometre::resume($e), null, null, null, null);
    }
}
