<?php

declare(strict_types=1);

namespace OffreGen\Http\Controleur;

use OffreGen\Adresse\Adresse;
use OffreGen\Adresse\Localites;
use OffreGen\Adresse\RegistreIndisponible;
use OffreGen\Http\Conteneur;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * L'aide à la saisie d'une adresse (app.js, champs data-adresse) : localités d'un NPA (répertoire copié chez nous),
 * suggestions et vérification par le registre officiel (swisstopo, par notre serveur). Rien n'est écrit ; un
 * registre muet ou trop sollicité répond « indisponible » et la saisie continue sans lui.
 */
final class AdresseControleur
{
    /** Appels au registre par session et par minute : un usage raisonnable du service gratuit de swisstopo. */
    public const APPELS_PAR_MINUTE = 20;

    public function __construct(private readonly Conteneur $c)
    {
    }

    /** ?npa=2800 : les localités officielles, et celle à proposer d'office s'il n'y a pas à choisir. */
    public function localites(Request $requete): JsonResponse
    {
        $npa = $requete->query->getString('npa');
        if (preg_match('/^\d{4}$/', $npa) !== 1) {
            return new JsonResponse(['localites' => [], 'seule' => null]);
        }
        return new JsonResponse(['localites' => Localites::de($npa), 'seule' => Localites::seule($npa)]);
    }

    /** ?q=rue de la pref : jusqu'à six adresses du registre. */
    public function suggestions(Request $requete): JsonResponse
    {
        $q = trim((string) preg_replace('/\s+/u', ' ', mb_substr($requete->query->getString('q'), 0, 120)));
        if (mb_strlen($q) < 3) {
            return new JsonResponse(['suggestions' => []]);
        }
        try {
            $adresses = $this->chercher($requete, $q, 6);
        } catch (RegistreIndisponible) {
            return new JsonResponse(['suggestions' => [], 'indisponible' => true]);
        }
        return new JsonResponse(['suggestions' => array_map(static fn (Adresse $a): array => [
            'rue' => $a->rue, 'numero' => $a->numero, 'npa' => $a->npa, 'localite' => $a->localite,
            'texte' => trim($a->rue . ' ' . $a->numero) . ', ' . $a->npa . ' ' . $a->localite,
        ], $adresses)]);
    }

    /** ?rue=…&numero=…&npa=…&localite=… : trouvée au registre (true), introuvable (false), ou on ne sait pas (null). */
    public function verification(Request $requete): JsonResponse
    {
        $p = static fn (string $cle): string => trim((string) preg_replace('/\s+/u', ' ', mb_substr($requete->query->getString($cle), 0, 70)));
        $tapee = new Adresse($p('rue'), $p('numero'), $p('npa'), $p('localite'));
        if ($tapee->rue === '' || preg_match('/^\d{4}$/', $tapee->npa) !== 1) {
            return new JsonResponse(['trouvee' => null]);
        }
        try {
            $adresses = $this->chercher($requete, trim("$tapee->rue $tapee->numero $tapee->npa"), 10);
        } catch (RegistreIndisponible) {
            return new JsonResponse(['trouvee' => null]);
        }
        foreach ($adresses as $a) {
            if ($a->memeQue($tapee)) {
                return new JsonResponse(['trouvee' => true]);
            }
        }
        return new JsonResponse(['trouvee' => false]);
    }

    /**
     * @return list<Adresse>
     * @throws RegistreIndisponible au-delà de APPELS_PAR_MINUTE dans la session
     */
    private function chercher(Request $requete, string $texte, int $nombre): array
    {
        $maintenant = $this->c->horloge()->now()->getTimestamp();
        $session = $requete->getSession();
        $appels = array_values(array_filter((array) $session->get('adresses_appels', []), static fn (mixed $t): bool => is_int($t) && $t > $maintenant - 60));
        if (count($appels) >= self::APPELS_PAR_MINUTE) {
            throw new RegistreIndisponible('Trop de recherches d\'adresses en une minute.');
        }
        $appels[] = $maintenant;
        $session->set('adresses_appels', $appels);
        return $this->c->registreAdresses()->chercher($texte, $nombre);
    }
}
