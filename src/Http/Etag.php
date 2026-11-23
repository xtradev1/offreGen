<?php

declare(strict_types=1);

namespace OffreGen\Http;

use Symfony\Component\HttpFoundation\Request;

/**
 * La revalidation d'un contenu par son empreinte (If-None-Match), pour les PDF (chantier sans rechargement, lot 5). Apache
 * suffixe l'empreinte d'une réponse compressée par « -gzip » et le navigateur la renvoie telle quelle : elle compte comme
 * la nôtre (l'ETag des fichiers statiques n'était jamais reconnu pour cette raison, constaté sur le test le 2026-11-23).
 */
final class Etag
{
    public static function connu(Request $requete, string $etag): bool
    {
        $gzip = substr($etag, 0, -1) . '-gzip"';
        foreach ($requete->getETags() as $recu) {
            $recu = (string) preg_replace('#^W/#', '', trim($recu));
            if ($recu === $etag || $recu === $gzip) {
                return true;
            }
        }
        return false;
    }
}
