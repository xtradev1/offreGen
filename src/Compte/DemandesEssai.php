<?php

declare(strict_types=1);

namespace OffreGen\Compte;

use OffreGen\Systeme\Messagerie;
use Psr\Clock\ClockInterface;

/**
 * Les demandes d'essai déposées sur la page de présentation (Xavier, 2026-11-20) : tant que l'inscription
 * n'existe pas, le courriel du visiteur part à l'exploitant, qui le recontacte. Rien n'est gardé en base.
 * Un plafond par jour protège sa boîte d'un robot ; le compteur tient dans un petit fichier, lu et écrit
 * sous verrou. S'il ne peut pas s'écrire, rien ne part : sans compteur, plus de plafond.
 */
final class DemandesEssai
{
    public const MAX_PAR_JOUR = 50;
    public const RECUE = 'recue';
    public const INVALIDE = 'invalide';
    public const TROP = 'trop';
    public const ETATS = [self::RECUE, self::INVALIDE, self::TROP];

    public function __construct(
        private readonly Messagerie $messagerie,
        private readonly string $destinataire,
        private readonly string $dossier,
        private readonly ClockInterface $horloge,
    ) {
    }

    /**
     * Contrôle l'adresse et réserve une place sous le plafond du jour. À RECUE, appeler transmettre().
     *
     * @return self::RECUE|self::INVALIDE|self::TROP
     * @throws \RuntimeException si le compteur ne peut être ni lu ni écrit
     */
    public function accepter(string $courriel): string
    {
        if (strlen($courriel) > 254 || filter_var($courriel, FILTER_VALIDATE_EMAIL) === false) {
            return self::INVALIDE;
        }
        return $this->reserver() ? self::RECUE : self::TROP;
    }

    public function transmettre(string $courriel, bool $avantOuverture): void
    {
        $maintenant = $this->horloge->now();
        $this->messagerie->envoyer(
            $this->destinataire,
            "Dicta\u{00A0}: demande d'essai",
            "Une demande d'essai est arrivée par la page de présentation.\r\n\r\n"
            . "Courriel\u{00A0}: $courriel\r\n"
            . "Reçue le {$maintenant->format('d.m.Y à H:i')} (UTC)\r\n"
            . 'Page vue' . "\u{00A0}: " . ($avantOuverture ? "avant l'ouverture" : 'inscriptions ouvertes') . "\r\n\r\n"
            . "À faire\u{00A0}: lui répondre et ouvrir son compte.\r\n\r\nDicta",
        );
    }

    private function reserver(): bool
    {
        if (!is_dir($this->dossier) && !@mkdir($this->dossier, 0700, true) && !is_dir($this->dossier)) {
            throw new \RuntimeException("Demandes d'essai : dossier impossible à créer ({$this->dossier}).");
        }
        $verrou = @fopen($this->dossier . '/verrou', 'c');
        if ($verrou === false || !flock($verrou, LOCK_EX)) {
            throw new \RuntimeException("Demandes d'essai : verrou impossible dans {$this->dossier}.");
        }
        try {
            $compteur = $this->dossier . '/jour-' . $this->horloge->now()->format('Ymd');
            $recues = is_file($compteur) ? (int) file_get_contents($compteur) : 0;
            if ($recues >= self::MAX_PAR_JOUR) {
                return false;
            }
            if (@file_put_contents($compteur, (string) ($recues + 1)) === false) {
                throw new \RuntimeException("Demandes d'essai : compteur impossible à écrire dans {$this->dossier}.");
            }
            return true;
        } finally {
            flock($verrou, LOCK_UN);
            fclose($verrou);
        }
    }
}
