<?php

declare(strict_types=1);

namespace OffreGen\Compte;

use BaconQrCode\Common\ErrorCorrectionLevel;
use BaconQrCode\Encoder\Encoder;

/**
 * Le code QR d'un lien, en SVG écrit dans la page : la politique de sécurité du site refuse une image en `data:`.
 * Noir sur blanc quel que soit le thème, avec la marge blanche de quatre modules que la norme demande. Sans la croix
 * suisse de QrFacture::svg, qui dit « paiement ».
 */
final class QrLien
{
    private const MARGE = 4;

    public static function svg(string $lien): string
    {
        $matrice = Encoder::encode($lien, ErrorCorrectionLevel::M(), 'UTF-8')->getMatrix();
        $n = $matrice->getWidth();
        $modules = '';
        for ($y = 0; $y < $n; $y++) {
            for ($x = 0; $x < $n; $x++) {
                if ($matrice->get($x, $y) === 1) {
                    // Deux centièmes de plus : pas de fente blanche entre deux modules voisins au rendu.
                    $modules .= sprintf('<rect x="%d" y="%d" width="1.02" height="1.02"/>', $x + self::MARGE, $y + self::MARGE);
                }
            }
        }
        return sprintf(
            '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 %1$d %1$d" role="img" aria-label="Code QR"><rect width="%1$d" height="%1$d" fill="#fff"/><g fill="#000">%2$s</g></svg>',
            $n + 2 * self::MARGE,
            $modules,
        );
    }
}
