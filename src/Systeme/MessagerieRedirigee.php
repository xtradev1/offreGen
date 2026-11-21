<?php

declare(strict_types=1);

namespace OffreGen\Systeme;

/**
 * Site de test (docs/SECURITE.md, « courriels bloqués ou redirigés ») : tout courriel part vers une seule adresse,
 * le destinataire d'origine écrit dans l'objet.
 */
final class MessagerieRedirigee implements Messagerie
{
    public function __construct(private readonly Messagerie $messagerie, private readonly string $adresse)
    {
        if (filter_var($adresse, FILTER_VALIDATE_EMAIL) === false) {
            throw new \InvalidArgumentException("Adresse de redirection invalide : $adresse");
        }
    }

    public function envoyer(string $destinataire, string $sujet, string $texte): void
    {
        $this->messagerie->envoyer($this->adresse, "[pour $destinataire] $sujet", $texte);
    }
}
