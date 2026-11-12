<?php

declare(strict_types=1);

namespace OffreGen\Http;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\FlashBagAwareSessionInterface;

/**
 * Message à montrer sur la page qui suit une redirection, dans le bandeau du bas.
 * Types : « info », « erreur », « annuler » (ligne supprimée que l'artisan peut remettre), « focus » (ligne ajoutée).
 */
final class Flash
{
    public static function dire(Request $requete, string $type, mixed $valeur): void
    {
        $session = $requete->getSession();
        if ($session instanceof FlashBagAwareSessionInterface) {
            $session->getFlashBag()->add($type, $valeur);
        }
    }

    /** « texte », avec les espaces insécables de la typographie française. */
    public static function cite(string $texte): string
    {
        return "«\u{00A0}" . $texte . "\u{00A0}»";
    }
}
