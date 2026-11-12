<?php

declare(strict_types=1);

namespace OffreGen\Client;

use OffreGen\Offre\Saisie;
use OffreGen\Offre\SaisieRefusee;

/** La fiche d'un client telle que l'artisan la tape : seul le nom est obligatoire. */
final class SaisieClient
{
    /**
     * @param array<string, string> $champs nom, adresse, telephone, courriel
     * @return array{nom: string, adresse: string|null, telephone: string|null, courriel: string|null}
     * @throws SaisieRefusee
     */
    public static function lire(array $champs): array
    {
        $c = static fn (string $cle): string => $champs[$cle] ?? '';
        $erreurs = [];
        $nom = Saisie::texte($c('nom'), 190);
        if ($nom === null) {
            $erreurs['nom'] = 'Indiquez le nom du client (190 caractères au plus).';
        }
        $adresse = Saisie::lignes($c('adresse'), 4, 255);
        if ($adresse === null) {
            $erreurs['adresse'] = 'Quatre lignes et 255 caractères au plus.';
        }
        $telephone = trim((string) preg_replace('/\s+/u', ' ', $c('telephone')));
        if ($telephone !== '' && preg_match('/^\+?[0-9 ().\/-]{6,40}$/', $telephone) !== 1) {
            $erreurs['telephone'] = 'Un numéro de téléphone, par exemple 079 555 00 00.';
        }
        $courriel = trim($c('courriel'));
        if ($courriel !== '' && (mb_strlen($courriel) > 190 || filter_var($courriel, FILTER_VALIDATE_EMAIL) === false)) {
            $erreurs['courriel'] = 'Une adresse de courriel, par exemple nom@exemple.ch.';
        }
        if ($nom === null || $adresse === null || $erreurs !== []) {
            throw new SaisieRefusee($erreurs);
        }
        return ['nom' => $nom, 'adresse' => $adresse === '' ? null : $adresse,
                'telephone' => $telephone === '' ? null : $telephone, 'courriel' => $courriel === '' ? null : $courriel];
    }
}
