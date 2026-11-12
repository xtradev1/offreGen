<?php

declare(strict_types=1);

namespace OffreGen\Ia;

use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * Lecture prudente d'une réponse d'Infomaniak (ou du serveur local qui l'imite).
 * Leur API v1 enveloppe souvent la réponse dans {"result": "success", "data": {...}} ; la documentation
 * des routes d'IA montre le contenu seul. Les deux formes sont acceptées. Un message d'erreur ne reprend
 * jamais le corps de la réponse : seulement le code HTTP et le code d'erreur du service.
 */
final class ReponseApi
{
    /** @return array<string, mixed> */
    public static function lire(ResponseInterface $reponse, string $quoi): array
    {
        $code = $reponse->getStatusCode();
        $corps = $reponse->getContent(false);
        $json = json_decode($corps, true);
        if ($code >= 400 || !is_array($json)) {
            $codeService = is_array($json) && is_array($json['error'] ?? null) && is_string($json['error']['code'] ?? null)
                ? ' (' . $json['error']['code'] . ')' : '';
            throw new ErreurApi("$quoi : réponse HTTP $code$codeService", $code);
        }
        if (($json['result'] ?? null) === 'error') {
            throw new ErreurApi("$quoi : erreur du service", $code);
        }
        if (($json['result'] ?? null) === 'success' && is_array($json['data'] ?? null)) {
            $json = $json['data'];
        }
        /** @var array<string, mixed> $json */
        return $json;
    }
}
