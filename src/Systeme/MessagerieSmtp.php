<?php

declare(strict_types=1);

namespace OffreGen\Systeme;

use Symfony\Component\Mailer\Transport;
use Symfony\Component\Mailer\Transport\TransportInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

/**
 * Envoi réel, en texte ou en deux versions (texte et HTML), par le serveur d'envoi désigné par MAILER_DSN (smtp://utilisateur:motdepasse@hote:587).
 */
final class MessagerieSmtp implements MessagerieMiseEnForme
{
    private readonly TransportInterface $transport;
    private readonly Address $expediteur;

    public function __construct(string $dsn, string $expediteur)
    {
        $this->transport = Transport::fromDsn($dsn);
        $this->expediteur = Address::create($expediteur);
    }

    public function envoyer(string $destinataire, string $sujet, string $texte): void
    {
        $this->transport->send($this->courriel($destinataire, $sujet, $texte));
    }

    public function envoyerMisEnForme(string $destinataire, string $sujet, string $texte, string $html): void
    {
        $this->transport->send($this->courriel($destinataire, $sujet, $texte)->html($html));
    }

    private function courriel(string $destinataire, string $sujet, string $texte): Email
    {
        if (filter_var($destinataire, FILTER_VALIDATE_EMAIL) === false) {
            throw new \InvalidArgumentException("Destinataire invalide : $destinataire");
        }
        return (new Email())->from($this->expediteur)->to($destinataire)->subject($sujet)->text($texte);
    }
}
