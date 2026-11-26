<?php

declare(strict_types=1);

namespace OffreGen\Systeme;

use Psr\Clock\ClockInterface;

/**
 * Messagerie de développement : chaque courriel devient un fichier .eml, rien ne part.
 */
final class MessagerieFichier implements MessagerieMiseEnForme
{
    public function __construct(private readonly string $dossier, private readonly ClockInterface $horloge)
    {
    }

    public function envoyer(string $destinataire, string $sujet, string $texte): void
    {
        $this->ecrire($destinataire, $sujet, "Content-Type: text/plain; charset=UTF-8\r\n\r\n" . $texte . "\r\n");
    }

    /** Les deux versions dans un même fichier, comme elles partent : le texte d'abord, le HTML ensuite. */
    public function envoyerMisEnForme(string $destinataire, string $sujet, string $texte, string $html): void
    {
        $limite = '=_dicta_' . bin2hex(random_bytes(8));
        $this->ecrire(
            $destinataire,
            $sujet,
            "MIME-Version: 1.0\r\nContent-Type: multipart/alternative; boundary=\"$limite\"\r\n\r\n"
            . "--$limite\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: 8bit\r\n\r\n$texte\r\n"
            . "--$limite\r\nContent-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: 8bit\r\n\r\n$html\r\n"
            . "--$limite--\r\n",
        );
    }

    /** @param string $suite les en-têtes de contenu, une ligne vide, puis le corps */
    private function ecrire(string $destinataire, string $sujet, string $suite): void
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
            . $suite;
        $nom = $maintenant->format('Ymd-His') . '-' . bin2hex(random_bytes(4)) . '.eml';
        if (file_put_contents($this->dossier . '/' . $nom, $contenu) === false) {
            throw new \RuntimeException("Courriel impossible à écrire : $nom");
        }
    }
}
