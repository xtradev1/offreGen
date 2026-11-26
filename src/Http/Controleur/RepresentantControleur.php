<?php

declare(strict_types=1);

namespace OffreGen\Http\Controleur;

use OffreGen\Catalogue\Recherche;
use OffreGen\Donnees\DepotOffres;
use OffreGen\Donnees\Portee;
use OffreGen\Http\Conteneur;
use OffreGen\Http\Flash;
use OffreGen\Http\PageIntrouvable;
use OffreGen\Http\Parametres;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * « Représenté par » (D8 A, D9 C, D10 C, 2026-11-21) : la régie, la gérance ou l'architecte qui représente le client,
 * choisi parmi les fiches de l'artisan ; à qui va l'offre (le représentant par défaut) et où vont les factures (au
 * client, « p.a. » le représentant par défaut, ou chez lui).
 *
 * @phpstan-import-type Offre from DepotOffres
 */
final class RepresentantControleur
{
    public function __construct(private readonly Conteneur $c)
    {
    }

    /** @param array<string, string> $parametres */
    public function fenetre(Request $requete, array $parametres): Response
    {
        $offre = $this->offre($requete, $parametres);
        $fiches = [];
        foreach ($this->c->clients($this->portee($requete))->tous() as $k) {
            if ($k['id'] !== $offre['client_id']) {
                $localite = $k['localite'] ?? ClientControleur::localite($k['adresse']);
                $fiches[] = $k + ['texte' => Recherche::normaliser($k['nom'] . ' ' . $localite), 'lieu' => $localite];
            }
        }
        return new Response($this->c->twig()->render('representant.html.twig', ['offre' => $offre, 'fiches' => $fiches]));
    }

    /**
     * representant=N choisit la fiche ; retirer=1 l'enlève ; offre_adressee et factures_adressees règlent les envois.
     *
     * @param array<string, string> $parametres
     */
    public function choisir(Request $requete, array $parametres): Response
    {
        $offre = $this->offre($requete, $parametres);
        $r = $requete->request;
        $representant = $offre['representant_id'];
        if ($r->getString('retirer') === '1') {
            $representant = null;
        } elseif ($r->getString('representant') !== '') {
            $representant = Parametres::id(['representant' => $r->getString('representant')], 'representant');
        }
        $offreA = $r->has('offre_adressee') ? $r->getString('offre_adressee') : $offre['offre_adressee'];
        $facturesA = $r->has('factures_adressees') ? $r->getString('factures_adressees') : $offre['factures_adressees'];
        if (!$this->offres($requete)->changerRepresentant($offre['id'], $representant, $offreA, $facturesA, $this->c->horloge()->now())) {
            throw new PageIntrouvable();
        }
        Flash::dire($requete, 'info', $representant === null ? 'Plus de représentant' : 'Représentant enregistré');
        return new RedirectResponse('/offres/' . $offre['id'], 303);
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
