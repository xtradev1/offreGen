<?php

declare(strict_types=1);

namespace OffreGen\Http\Controleur;

use OffreGen\Donnees\Portee;
use OffreGen\Http\Conteneur;
use OffreGen\Http\Flash;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Rubrique « Courriels » des réglages (M2 de Xavier, 2026-11-23) : recevoir ou non le courriel « offre prête ».
 */
final class ReglagesCourrielsControleur
{
    public function __construct(private readonly Conteneur $c)
    {
    }

    public function afficher(Request $requete): Response
    {
        $portee = new Portee($requete->attributes->getInt('_artisan'));
        return new Response($this->c->twig()->render('reglages_courriels.html.twig', ['offre_prete' => $this->c->reglages($portee)->courrielOffrePrete()]));
    }

    public function enregistrer(Request $requete): Response
    {
        $portee = new Portee($requete->attributes->getInt('_artisan'));
        $this->c->reglages($portee)->changerCourrielOffrePrete($requete->request->getString('offre_prete') === '1');
        Flash::dire($requete, 'info', 'Enregistré.');
        return new RedirectResponse('/reglages/courriels', 303);
    }
}
