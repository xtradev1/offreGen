<?php

declare(strict_types=1);

namespace OffreGen\Offre;

use Psr\Clock\ClockInterface;

/**
 * Messagerie de développement : chaque courriel devient un fichier .eml, rien ne part.
 */
final class MessagerieFichier implements Messagerie
{
    public function __construct(private readonly string $dossier, private readonly ClockInterface $horloge)
    {
    }

    public function envoyer(string $destinataire, string $sujet, string $texte): void
    {
        if (filter_var($destinataire, FILTER_VALIDATE_EMAIL) === false) {
            throw new \InvalidArgumentException("Destinataire invalide : $destinataire");
        }
        if (!is_dir($this->dossier) && !mkdir($this->dossier, 0700, true) && !is_dir($this->dossier)) {
            throw new \RuntimeException("Dossier des courriels impossible à créer : {$this->dossier}");
        }
        $maintenant = $this->horloge->now();
        $contenu = "To: $destinataire\r\n"
            . 'Subject: =?UTF-8?B?' . base64_encode($sujet) . "?=\r\n"
            . 'Date: ' . $maintenant->format(\DateTimeInterface::RFC2822) . "\r\n"
            . "Content-Type: text/plain; charset=UTF-8\r\n\r\n"
            . $texte . "\r\n";
        $nom = $maintenant->format('Ymd-His') . '-' . bin2hex(random_bytes(4)) . '.eml';
        if (file_put_contents($this->dossier . '/' . $nom, $contenu) === false) {
            throw new \RuntimeException("Courriel impossible à écrire : $nom");
        }
    }
}
