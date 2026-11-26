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
 * Rubrique « Courriels » des réglages : recevoir ou non le courriel « offre prête » (M2 de Xavier, 2026-11-23) et le
 * courriel « dictée en échec » (Xavier, 2026-11-24).
 */
final class ReglagesCourrielsControleur
{
    public function __construct(private readonly Conteneur $c)
    {
    }

    public function afficher(Request $requete): Response
    {
        $reglages = $this->c->reglages(new Portee($requete->attributes->getInt('_artisan')));
        return new Response($this->c->twig()->render('reglages_courriels.html.twig', [
            'offre_prete' => $reglages->courrielOffrePrete(),
            'dictee_echec' => $reglages->courrielDicteeEchec(),
        ]));
    }

    public function enregistrer(Request $requete): Response
    {
        $reglages = $this->c->reglages(new Portee($requete->attributes->getInt('_artisan')));
        $reglages->changerCourrielOffrePrete($requete->request->getString('offre_prete') === '1');
        $reglages->changerCourrielDicteeEchec($requete->request->getString('dictee_echec') === '1');
        Flash::dire($requete, 'info', 'Enregistré.');
        return new RedirectResponse('/reglages/courriels', 303);
    }
}
