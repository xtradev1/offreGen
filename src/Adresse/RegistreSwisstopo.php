<?php

declare(strict_types=1);

namespace OffreGen\Adresse;

use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Le service de recherche de geo.admin.ch (swisstopo), limité aux adresses de bâtiments : gratuit, sans clé, pour un
 * usage raisonnable (https://api.geo.admin.ch/api/terms_of_use.html). Notre serveur l'appelle, jamais le navigateur :
 * swisstopo reçoit le texte tapé, sans l'adresse IP de l'artisan ni le nom du client. Rien n'est gardé ni journalisé.
 */
final class RegistreSwisstopo implements RegistreAdresses
{
    public const URL = 'https://api3.geo.admin.ch/rest/services/api/SearchServer';

    public function __construct(private readonly HttpClientInterface $http)
    {
    }

    public function chercher(string $texte, int $nombre): array
    {
        try {
            $reponse = $this->http->request('GET', self::URL, [
                'query' => ['searchText' => $texte, 'type' => 'locations', 'origins' => 'address', 'limit' => $nombre],
                'timeout' => 3,
                'max_duration' => 4,
            ]);
            $json = $reponse->toArray();
        } catch (ExceptionInterface $e) {
            throw new RegistreIndisponible('Recherche d\'adresses indisponible.', 0, $e);
        }
        $resultats = is_array($json['results'] ?? null) ? $json['results'] : [];
        $adresses = [];
        foreach ($resultats as $r) {
            $libelle = is_array($r) && is_array($r['attrs'] ?? null) && is_string($r['attrs']['label'] ?? null) ? $r['attrs']['label'] : null;
            $a = $libelle === null ? null : self::lireLibelle($libelle);
            if ($a !== null) {
                $adresses[] = $a;
            }
        }
        return array_slice($adresses, 0, $nombre);
    }

    /** « Rue de la Préfecture 1 <b>2800 Delémont</b> » donne la rue, le numéro, le NPA et la localité. */
    public static function lireLibelle(string $libelle): ?Adresse
    {
        if (preg_match('#^(.*?)\s*<b>\s*(\d{4})\s+(.+?)\s*</b>\s*$#u', $libelle, $m) !== 1) {
            return null;
        }
        $avant = trim(html_entity_decode($m[1], ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        $localite = trim(html_entity_decode($m[3], ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        [$rue, $numero] = preg_match(SaisieAdresse::NUMERO_AU_BOUT, $avant, $r) === 1
            ? [$r[1], str_replace(' ', '', $r[2])] : [$avant, ''];
        if ($rue === '' || mb_strlen($rue) > Adresse::MAX['rue'] || mb_strlen($numero) > Adresse::MAX['numero'] || mb_strlen($localite) > Adresse::MAX['localite']) {
            return null;
        }
        return new Adresse($rue, $numero, $m[2], $localite);
    }
}
