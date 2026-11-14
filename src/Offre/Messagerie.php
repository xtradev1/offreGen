<?php

declare(strict_types=1);

namespace OffreGen\Offre;

interface Messagerie
{
    public function envoyer(string $destinataire, string $sujet, string $texte): void;
}
