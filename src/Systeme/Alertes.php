<?php

declare(strict_types=1);

namespace OffreGen\Systeme;

use Psr\Clock\ClockInterface;

/**
 * Courriel d'alerte à l'exploitant pour chaque erreur de l'application (décision Q3 de Xavier, 2026-11-18).
 * Une même erreur part au plus une fois par heure, et 30 courriels par jour au plus : une panne qui se répète
 * chaque minute (cron) ne remplit pas la boîte. Deux erreurs qui ne diffèrent que par des chiffres ou un
 * identifiant comptent pour une seule. L'état tient dans de petits fichiers du dossier donné, lus et écrits sous
 * verrou (cron et pages web en même temps) ; s'il ne peut pas s'écrire, rien ne part : sans état, plus de plafond.
 */
final class Alertes
{
    public const MAX_PAR_JOUR = 30;
    private const SILENCE_SECONDES = 3600;

    public function __construct(
        private readonly Messagerie $messagerie,
        private readonly string $destinataire,
        private readonly string $dossier,
        private readonly ClockInterface $horloge,
        private readonly string $adresseApplication,
    ) {
    }

    /**
     * @return bool true si le courriel est parti
     * @throws \RuntimeException si l'état ne peut être ni lu ni écrit (rien n'est envoyé)
     */
    public function alerter(string $message): bool
    {
        $maintenant = $this->horloge->now();
        if (!$this->noter(self::signature($message), $maintenant)) {
            return false;
        }
        // Envoyé hors du verrou : un serveur d'envoi lent ne fait pas attendre les autres pages.
        $this->messagerie->envoyer(
            $this->destinataire,
            "Alerte offreGen\u{00A0}: " . self::sujet($message),
            "Erreur relevée le {$maintenant->format('d.m.Y à H:i')} (UTC) sur {$this->adresseApplication}\u{00A0}:\r\n\r\n$message\r\n\r\n"
            . "La même erreur ne repart pas avant une heure\u{00A0}; " . self::MAX_PAR_JOUR . " alertes par jour au plus.\r\n"
            . 'Détail dans var/log/app.log sur le serveur.',
        );
        return true;
    }

    /** Décide sous verrou si l'alerte part, et le note avant l'envoi : une messagerie en panne ne fait pas réessayer. */
    private function noter(string $signature, \DateTimeImmutable $maintenant): bool
    {
        if (!is_dir($this->dossier) && !@mkdir($this->dossier, 0700, true) && !is_dir($this->dossier)) {
            throw new \RuntimeException("Alertes : dossier impossible à créer ({$this->dossier}).");
        }
        $verrou = @fopen($this->dossier . '/verrou', 'c');
        if ($verrou === false || !flock($verrou, LOCK_EX)) {
            throw new \RuntimeException("Alertes : verrou impossible dans {$this->dossier}.");
        }
        try {
            $fichierSignature = $this->dossier . '/derniere-' . sha1($signature);
            $derniere = is_file($fichierSignature) ? (int) file_get_contents($fichierSignature) : 0;
            if ($maintenant->getTimestamp() - $derniere < self::SILENCE_SECONDES) {
                return false;
            }
            $compteur = $this->dossier . '/jour-' . $maintenant->format('Ymd');
            $envoyes = is_file($compteur) ? (int) file_get_contents($compteur) : 0;
            if ($envoyes >= self::MAX_PAR_JOUR) {
                return false;
            }
            if (@file_put_contents($fichierSignature, (string) $maintenant->getTimestamp()) === false
                || @file_put_contents($compteur, (string) ($envoyes + 1)) === false) {
                throw new \RuntimeException("Alertes : état impossible à écrire dans {$this->dossier}.");
            }
            return true;
        } finally {
            flock($verrou, LOCK_UN);
            fclose($verrou);
        }
    }

    /** Une panne qui touche un autre enregistrement ou dure une seconde de plus reste la même panne. */
    private static function signature(string $message): string
    {
        return (string) preg_replace(['/[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}/i', '/\d+/'], ['<id>', '<n>'], $message);
    }

    /**
     * L'objet nomme la panne (ce qui précède « : »), jamais son détail, qui peut reprendre ce qu'un artisan a dicté
     * ou une adresse : l'objet d'un courriel se lit partout, dans la liste de la boîte et sur l'écran verrouillé.
     */
    private static function sujet(string $message): string
    {
        $panne = explode(' : ', (string) preg_replace('/^offreGen, /', '', $message), 2)[0];
        return mb_substr((string) preg_replace('/[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}/i', '<id>', $panne), 0, 80);
    }
}
