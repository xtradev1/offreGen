<?php

declare(strict_types=1);

namespace OffreGen\Http\Controleur;

use OffreGen\Donnees\DepotReglages;
use OffreGen\Donnees\Portee;
use OffreGen\Http\Conteneur;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Le guide à anneau de l'artisan (chantier 2026-11-21_onboarding, PLAN.md, lots 0 et 3).
 * POST /guide/fini : fini (« Compris ») ou quitté (« Quitter le guide ») ; il ne se montre plus.
 * POST /guide/revoir : « Revoir le guide », depuis « Plus » ; il repart de sa première étape.
 */
final class GuideControleur
{
    public const ETATS = ['fini', 'quitte'];

    public function __construct(private readonly Conteneur $c)
    {
    }

    public function fini(Request $requete): Response
    {
        if (!in_array($requete->request->getString('etat'), self::ETATS, true)) {
            return new Response('', 422);
        }
        $this->depot($requete)->guideFini(\DateTimeImmutable::createFromInterface($this->c->horloge()->now()));
        // Quitté dans la démo d'entraînement : le guide du commercial s'arrête aussi, sans carte de fin au retour.
        if ($requete->request->getString('etat') === 'quitte') {
            $requete->getSession()->remove(AdminControleur::GUIDE_EQUIPE);
        }
        return new Response('', 204);
    }

    public function revoir(Request $requete): RedirectResponse
    {
        $this->depot($requete)->revoirGuide();
        return new RedirectResponse('/?guide=depart', 303);
    }

    private function depot(Request $requete): DepotReglages
    {
        return $this->c->reglages(new Portee($requete->attributes->getInt('_artisan')));
    }
}
