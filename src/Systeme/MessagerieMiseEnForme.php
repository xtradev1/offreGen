<?php

declare(strict_types=1);

namespace OffreGen\Systeme;

/**
 * Une messagerie qui sait envoyer un courriel en deux versions, texte et HTML (maquette v8, 2026-11-23). À part de
 * Messagerie : une messagerie qui ne connaît que le texte continue de recevoir le texte (voir Courriel::envoyer).
 */
interface MessagerieMiseEnForme extends Messagerie
{
    public function envoyerMisEnForme(string $destinataire, string $sujet, string $texte, string $html): void;
}
