<?php

declare(strict_types=1);

namespace OffreGen\Systeme;

use Psr\Clock\ClockInterface;
use Psr\Log\LoggerInterface;

/**
 * Site de test (docs/SECURITE.md, « courriels bloqués ou redirigés ») : tout courriel part vers une seule adresse,
 * le destinataire d'origine écrit dans l'objet.
 *
 * Fenêtre d'envoi réel (Xavier, 2026-11-24 : « on va les limiter à une période de 1 ou 2 heures […] tester que ça
 * envoie à tout le monde […] ensuite on repasse en test en mode sécurisé ») : jusqu'à $reelJusqua, les courriels
 * partent chez leur vrai destinataire, chacun noté au journal. La fenêtre se ferme seule à l'heure dite ; une fin
 * à plus de 2 heures n'ouvre rien, même écrite à la main : une faute de frappe sur l'année ne laisse pas le test
 * envoyer pour toujours.
 */
final class MessagerieRedirigee implements MessagerieMiseEnForme
{
    public const FENETRE_MAX_SECONDES = 7200;

    public function __construct(
        private readonly Messagerie $messagerie,
        private readonly string $adresse,
        private readonly ?\DateTimeImmutable $reelJusqua = null,
        private readonly ?ClockInterface $horloge = null,
        private readonly ?LoggerInterface $journal = null,
    ) {
        if (filter_var($adresse, FILTER_VALIDATE_EMAIL) === false) {
            throw new \InvalidArgumentException("Adresse de redirection invalide : $adresse");
        }
    }

    /** Vrai pendant une fenêtre d'envoi réel ouverte, qui finit dans 2 heures au plus. */
    public function envoiReel(): bool
    {
        if ($this->reelJusqua === null || $this->horloge === null) {
            return false;
        }
        $reste = $this->reelJusqua->getTimestamp() - $this->horloge->now()->getTimestamp();
        return $reste > 0 && $reste <= self::FENETRE_MAX_SECONDES;
    }

    public function envoyer(string $destinataire, string $sujet, string $texte): void
    {
        if ($this->envoiReel()) {
            $this->noter($destinataire, $sujet);
            $this->messagerie->envoyer($destinataire, $sujet, $texte);
            return;
        }
        $this->messagerie->envoyer($this->adresse, "[pour $destinataire] $sujet", $texte);
    }

    public function envoyerMisEnForme(string $destinataire, string $sujet, string $texte, string $html): void
    {
        if (!$this->messagerie instanceof MessagerieMiseEnForme) {
            $this->envoyer($destinataire, $sujet, $texte);
            return;
        }
        if ($this->envoiReel()) {
            $this->noter($destinataire, $sujet);
            $this->messagerie->envoyerMisEnForme($destinataire, $sujet, $texte, $html);
            return;
        }
        $this->messagerie->envoyerMisEnForme($this->adresse, "[pour $destinataire] $sujet", $texte, $html);
    }

    private function noter(string $destinataire, string $sujet): void
    {
        $fin = $this->reelJusqua?->format('Y-m-d H:i') ?? '';
        $this->journal?->info("offreGen, fenêtre d'envoi réel (jusqu'à $fin UTC) : « $sujet » envoyé à $destinataire.");
    }
}
