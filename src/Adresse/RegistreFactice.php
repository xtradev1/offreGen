<?php

declare(strict_types=1);

namespace OffreGen\Adresse;

/**
 * Registre de développement et de test (ADRESSES vide ou autre que « swisstopo ») : quelques adresses connues,
 * celles de la démonstration comprises ; rien ne sort de la machine. « panne » dans le texte simule un service
 * qui ne répond pas.
 */
final class RegistreFactice implements RegistreAdresses
{
    /** @var list<array{string, string, string, string}> */
    private const ADRESSES = [
        ['Rue de la Préfecture', '1', '2800', 'Delémont'],
        ['Rue de la Préfecture', '2', '2800', 'Delémont'],
        ['Rue de la Gare', '4', '2800', 'Delémont'],
        ['Chemin des Essais', '4', '1700', 'Fribourg'],
        ['Route du Test', '18', '2000', 'Neuchâtel'],
        ['Rue du Modèle', '7', '1950', 'Sion'],
        ['Bundesplatz', '3', '3011', 'Bern'],
    ];

    public function chercher(string $texte, int $nombre): array
    {
        $mots = array_filter(explode(' ', Localites::cle($texte)), static fn (string $m): bool => $m !== '');
        if (in_array('panne', $mots, true)) {
            throw new RegistreIndisponible('Panne simulée.');
        }
        $trouvees = [];
        foreach (self::ADRESSES as [$rue, $numero, $npa, $localite]) {
            $cle = Localites::cle("$rue $numero $npa $localite");
            $tous = true;
            foreach ($mots as $mot) {
                // Un mot se prend comme un début de mot (« pref ») ; un nombre, en entier (« 2 » n'est pas « 2800 »).
                $fin = ctype_digit($mot) ? '\b' : '';
                $tous = $tous && preg_match('/\b' . preg_quote($mot, '/') . $fin . '/', $cle) === 1;
            }
            if ($tous && $mots !== []) {
                $trouvees[] = new Adresse($rue, $numero, $npa, $localite);
            }
        }
        return array_slice($trouvees, 0, $nombre);
    }
}
