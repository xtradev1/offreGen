<?php

declare(strict_types=1);

namespace OffreGen\Http\Controleur;

use OffreGen\Donnees\Portee;
use OffreGen\Http\Conteneur;
use OffreGen\Offre\Totaux;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class AccueilControleur
{
    public function __construct(private readonly Conteneur $c)
    {
    }

    public function liste(Request $requete): Response
    {
        $portee = new Portee($requete->attributes->getInt('_artisan'));
        $artisan = $this->c->artisans()->parId($portee->artisanId);
        $offresDepot = $this->c->offres($portee);
        $offres = $offresDepot->liste();
        $lignes = $offresDepot->lignesDe(array_column($offres, 'id'));
        $r = $this->c->reglages($portee)->lire();
        $totaux = [];
        foreach ($offres as $o) {
            $totaux[$o['id']] = Totaux::calculer($lignes[$o['id']] ?? [], $r->tvaPourDixMille, $r->arrondiCinqCentimes)['ttc'];
        }

        return new Response($this->c->twig()->render('accueil.html.twig', [
            'entreprise' => $artisan['entreprise'] ?? '',
            'demo' => ($artisan['demo'] ?? 0) === 1,
            'en_cours' => $this->c->enregistrements($portee)->enCours(),
            'offres' => $offres,
            'totaux' => $totaux,
        ]));
    }
}
