<?php

declare(strict_types=1);

namespace OffreGen\Systeme;

use OffreGen\Donnees\DepotEtapes;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * Une étape en cours, rendue par Chronometre::commencer. Se ferme une seule fois : finir ou échouer.
 */
final class EtapeChrono
{
    private bool $close = false;

    public function __construct(private readonly ?Chronometre $chrono, private readonly int $id, private readonly \DateTimeImmutable $debut)
    {
    }

    public static function rien(): self
    {
        return new self(null, 0, new \DateTimeImmutable('@0'));
    }

    /**
     * @param array<mixed>|null $usage « usage » de la réponse du modèle : jetons envoyés et reçus déclarés par le service
     * @param ResponseInterface|null $reponse pour le temps jusqu'au premier octet (curl : starttransfer_time)
     */
    public function finir(?array $usage = null, ?ResponseInterface $reponse = null, ?string $modele = null): void
    {
        if ($this->chrono === null || $this->close) {
            return;
        }
        $this->close = true;
        $envoyes = is_int($usage['prompt_tokens'] ?? null) ? $usage['prompt_tokens'] : null;
        $recus = is_int($usage['completion_tokens'] ?? null) ? $usage['completion_tokens'] : null;
        $premier = null;
        if ($reponse !== null) {
            try {
                $t = $reponse->getInfo('starttransfer_time');
                $premier = is_float($t) || is_int($t) ? (int) round($t * 1000) : null;
            } catch (\Throwable) {
                $premier = null;
            }
        }
        $this->chrono->terminer($this->id, $this->debut, DepotEtapes::OK, null, $modele, $envoyes, $recus, $premier);
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
