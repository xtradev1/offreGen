<?php

declare(strict_types=1);

namespace OffreGen\Adresse;

/**
 * Une adresse tapée en champs séparés (réglages de l'entreprise, fiche d'un client). Toute vide est permise ;
 * sinon le NPA et la localité sont obligatoires (norme de la QR-facture). En Suisse et au Liechtenstein, le NPA
 * doit exister au répertoire officiel des localités, et la localité y correspondre : vide, elle est remplie ;
 * écrite autrement (casse, accents), elle prend le nom officiel.
 */
final class SaisieAdresse
{
    /** Un numéro de bâtiment au bout de la rue : « 12 », « 4a », « 4 b », « 12/3 ». */
    public const NUMERO_AU_BOUT = '/^(.*\S)\s+(\d+\s?[a-zA-Z]?(?:[\/.-]\d+[a-zA-Z]?)?)$/u';

    /**
     * @param array<string, string> $champs rue, numero, npa, localite, pays, chacun précédé de $prefixe
     * @return array{Adresse|null, array<string, string>} l'adresse, ou null avec les erreurs (clés préfixées)
     */
    public static function lire(array $champs, string $prefixe): array
    {
        $v = static fn (string $cle): string => trim((string) preg_replace('/\s+/u', ' ', $champs[$prefixe . $cle] ?? ''));
        [$rue, $numero, $npa, $localite] = [$v('rue'), $v('numero'), $v('npa'), $v('localite')];
        $pays = strtoupper($v('pays')) === '' ? 'CH' : strtoupper($v('pays'));
        // « Rue de la Gare 4 » tapé dans la rue, numéro vide : le numéro va dans son champ (norme de la QR-facture).
        if ($numero === '' && preg_match(self::NUMERO_AU_BOUT, $rue, $m) === 1) {
            [$rue, $numero] = [$m[1], str_replace(' ', '', $m[2])];
        }
        $erreurs = [];
        if (!array_key_exists($pays, Adresse::PAYS)) {
            $erreurs[$prefixe . 'pays'] = 'Choisissez un pays de la liste.';
        }
        foreach (['rue' => $rue, 'numero' => $numero, 'npa' => $npa, 'localite' => $localite] as $cle => $valeur) {
            if (mb_strlen($valeur) > Adresse::MAX[$cle]) {
                $erreurs[$prefixe . $cle] = Adresse::MAX[$cle] . ' caractères au plus.';
            }
        }
        if ($rue === '' && $numero === '' && $npa === '' && $localite === '') {
            return $erreurs === [] ? [new Adresse(pays: $pays), []] : [null, $erreurs];
        }
        if ($numero !== '' && $rue === '') {
            $erreurs[$prefixe . 'rue'] = 'Indiquez la rue de ce numéro.';
        }
        $suisse = $pays === 'CH' || $pays === 'LI';
        $npaInconnu = false;
        if ($npa === '') {
            $erreurs[$prefixe . 'npa'] = 'Indiquez le NPA.';
        } elseif ($suisse && preg_match('/^\d{4}$/', $npa) !== 1) {
            $erreurs[$prefixe . 'npa'] = "Un NPA suisse a quatre chiffres, par exemple 2800.";
        } elseif ($suisse && Localites::de($npa) === []) {
            // Court : le message tient sous un champ étroit. La localité ne se contrôle pas sans NPA connu.
            $erreurs[$prefixe . 'npa'] = "Ce NPA n'existe pas.";
            $npaInconnu = true;
        } elseif ($suisse) {
            $officielle = $localite === '' ? Localites::seule($npa) : Localites::officielle($npa, $localite);
            if ($officielle !== null) {
                $localite = $officielle;
            } else {
                $noms = Localites::de($npa);
                $erreurs[$prefixe . 'localite'] = $localite === ''
                    ? 'Choisissez la localité' . "\u{00A0}: " . self::liste($noms) . '.'
                    : "Pour le NPA $npa, la localité est " . self::liste($noms) . '.';
            }
        }
        if ($localite === '' && !isset($erreurs[$prefixe . 'localite']) && !$npaInconnu) {
            $erreurs[$prefixe . 'localite'] = 'Indiquez la localité.';
        }
        if ($erreurs !== []) {
            return [null, $erreurs];
        }
        return [new Adresse($rue, $numero, $npa, $localite, $pays), []];
    }

    /** @param list<string> $noms « A », « A ou B », « A, B ou C » */
    private static function liste(array $noms): string
    {
        $dernier = array_pop($noms);
        return $noms === [] ? (string) $dernier : implode(', ', $noms) . ' ou ' . $dernier;
    }
}
