<?php

declare(strict_types=1);

namespace OffreGen\Http\Controleur;

use OffreGen\Donnees\DepotOffres;
use OffreGen\Donnees\Portee;
use OffreGen\Http\Conteneur;
use OffreGen\Http\Flash;
use OffreGen\Http\Formats;
use OffreGen\Http\PageIntrouvable;
use OffreGen\Http\Parametres;
use OffreGen\Offre\Rabais;
use OffreGen\Offre\Totaux;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Les rabais d'une offre, sous le sous-total (D4 B, 2026-11-21) : « + Rabais » en ouvre un nouveau, toucher un rabais le
 * modifie ou le retire. Un rabais se désigne par sa place dans la liste, à partir de 1.
 *
 * @phpstan-import-type Offre from DepotOffres
 */
final class RabaisControleur
{
    public function __construct(private readonly Conteneur $c)
    {
    }

    /** @param array<string, string> $parametres */
    public function fenetre(Request $requete, array $parametres): Response
    {
        $offre = $this->offre($requete, $parametres);
        $rang = $this->rang($offre, $parametres);
        $r = $rang === null ? null : Rabais::liste($offre['rabais'])[$rang - 1];
        $valeur = '';
        if ($r !== null && $r->choix() === 'pct') {
            // 750 dix-millièmes : « 7.5 », comme une quantité.
            $valeur = Formats::saisieQuantite((int) $r->pourDixMille * 10);
        } elseif ($r !== null && $r->choix() === 'chf') {
            $valeur = Formats::saisiePrix((int) $r->centimes);
        }
        $champs = ['libelle' => $r === null ? Rabais::LIBELLE_PAR_DEFAUT : $r->libelle, 'choix' => $r === null ? '5' : $r->choix(), 'valeur' => $valeur];
        return $this->formulaire($requete, $offre, $rang, $champs, [], 200);
    }

    /** @param array<string, string> $parametres */
    public function enregistrer(Request $requete, array $parametres): Response
    {
        $offre = $this->offre($requete, $parametres);
        $rang = $this->rang($offre, $parametres);
        $champs = ['libelle' => $requete->request->getString('libelle'), 'choix' => $requete->request->getString('choix'),
                   'valeur' => $requete->request->getString('valeur')];
        [$rabais, $erreurs] = Rabais::depuisSaisie($champs['libelle'], $champs['choix'], $champs['valeur']);
        $liste = Rabais::liste($offre['rabais']);
        if ($rang === null && count($liste) >= Rabais::MAX) {
            $erreurs['choix'] = 'Une offre porte ' . Rabais::MAX . ' rabais au plus.';
        }
        if ($rabais === null || $erreurs !== []) {
            return $this->formulaire($requete, $offre, $rang, $champs, $erreurs, 422);
        }
        if ($rang === null) {
            $liste[] = $rabais;
        } else {
            $liste[$rang - 1] = $rabais;
        }
        $liste = array_values($liste);
        // Revue du 2026-11-22 : des rabais qui dépassent le sous-total seraient plafonnés en silence, et le document
        // montrerait un pourcentage qui ne correspond plus au montant ; une offre qui passerait sous les acomptes déjà
        // émis ne se facturerait plus (finale négative). Les deux se refusent ici.
        $portee = $this->portee($requete);
        $r = $this->c->reglages($portee)->lire();
        $lignes = $this->c->offres($portee)->lignes($offre['id']);
        $sousTotal = Totaux::calculer($lignes, $r->tvaPourDixMille, $r->arrondiCinqCentimes)['sous_total'];
        $demande = array_sum(array_map(static fn (Rabais $x): int => $x->sur($sousTotal), $liste));
        $deja = $this->c->factures($portee)->dejaFacture($offre['id']);
        $ttc = Totaux::calculer($lignes, $r->tvaPourDixMille, $r->arrondiCinqCentimes, $liste)['ttc'];
        if ($demande > $sousTotal) {
            $erreurs['valeur'] = 'Les rabais dépasseraient le sous-total des lignes (' . Formats::chf($sousTotal) . ').';
        } elseif ($ttc < $deja) {
            $erreurs['valeur'] = "Avec ce rabais, l'offre (" . Formats::chf($ttc) . ') passerait sous les acomptes déjà facturés ('
                . Formats::chf($deja) . ').';
        }
        if ($erreurs !== []) {
            return $this->formulaire($requete, $offre, $rang, $champs, $erreurs, 422);
        }
        $this->offres($requete)->changerRabais($offre['id'], $liste, $this->c->horloge()->now());
        Flash::dire($requete, 'info', $rang === null ? 'Rabais ajouté' : 'Rabais modifié');
        return new RedirectResponse('/offres/' . $offre['id'], 303);
    }

    /** @param array<string, string> $parametres */
    public function retirer(Request $requete, array $parametres): Response
    {
        $offre = $this->offre($requete, $parametres);
        $rang = $this->rang($offre, $parametres) ?? throw new PageIntrouvable();
        $liste = Rabais::liste($offre['rabais']);
        array_splice($liste, $rang - 1, 1);
        $this->offres($requete)->changerRabais($offre['id'], $liste, $this->c->horloge()->now());
        Flash::dire($requete, 'info', 'Rabais retiré');
        return new RedirectResponse('/offres/' . $offre['id'], 303);
    }

    /**
     * La place du rabais dans le chemin, null pour un nouveau ; 404 si l'offre n'en a pas à cette place.
     *
     * @param Offre $offre
     * @param array<string, string> $parametres
     */
    private function rang(array $offre, array $parametres): ?int
    {
        if (!isset($parametres['rang'])) {
            return null;
        }
        $rang = Parametres::id($parametres, 'rang');
        if ($rang > count(Rabais::liste($offre['rabais']))) {
            throw new PageIntrouvable();
        }
        return $rang;
    }

    /**
     * @param Offre $offre
     * @param array{libelle: string, choix: string, valeur: string} $champs
     * @param array<string, string> $erreurs
     */
    private function formulaire(Request $requete, array $offre, ?int $rang, array $champs, array $erreurs, int $code): Response
    {
        $portee = $this->portee($requete);
        $r = $this->c->reglages($portee)->lire();
        $sousTotal = Totaux::calculer($this->c->offres($portee)->lignes($offre['id']), $r->tvaPourDixMille, $r->arrondiCinqCentimes)['sous_total'];
        return new Response($this->c->twig()->render('rabais.html.twig', ['offre' => $offre, 'rang' => $rang, 'champs' => $champs,
            'erreurs' => $erreurs, 'sous_total' => $sousTotal, 'deja' => $this->c->factures($portee)->dejaFacture($offre['id']),
            'finale' => $this->c->factures($portee)->finaleEmise($offre['id'])]), $code);
    }

    private function portee(Request $requete): Portee
    {
        return new Portee($requete->attributes->getInt('_artisan'));
    }

    private function offres(Request $requete): DepotOffres
    {
        return $this->c->offres($this->portee($requete));
    }

    /**
     * @param array<string, string> $parametres
     * @return Offre
     */
    private function offre(Request $requete, array $parametres): array
    {
        return $this->offres($requete)->parId(Parametres::id($parametres, 'id')) ?? throw new PageIntrouvable();
    }
}
