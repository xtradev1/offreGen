<?php

declare(strict_types=1);

namespace OffreGen\Systeme;

use Symfony\Component\Mailer\Transport;
use Symfony\Component\Mailer\Transport\TransportInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

/**
 * Envoi réel, en texte brut, par le serveur d'envoi désigné par MAILER_DSN (smtp://utilisateur:motdepasse@hote:587).
 */
final class MessagerieSmtp implements Messagerie
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
        if (filter_var($destinataire, FILTER_VALIDATE_EMAIL) === false) {
            throw new \InvalidArgumentException("Destinataire invalide : $destinataire");
        }
        $this->transport->send((new Email())->from($this->expediteur)->to($destinataire)->subject($sujet)->text($texte));
    }
}
