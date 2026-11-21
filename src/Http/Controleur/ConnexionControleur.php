<?php

declare(strict_types=1);

namespace OffreGen\Http\Controleur;

use OffreGen\Compte\Authentification;
use OffreGen\Compte\ResultatConnexion;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Twig\Environment;

final class ConnexionControleur
{
    public function __construct(private readonly Authentification $authentification, private readonly Environment $twig)
    {
    }

    public function afficher(Request $requete): Response
    {
        if ($this->authentification->artisanConnecte($requete) !== null) {
            return new RedirectResponse('/', 303);
        }
        return $this->page('', null, 200);
    }

    public function soumettre(Request $requete): Response
    {
        $courriel = trim($requete->request->getString('courriel'));
        $motDePasse = $requete->request->getString('mot_de_passe');
        if ($courriel === '' || $motDePasse === '') {
            return $this->page($courriel, 'Indiquez votre courriel et votre mot de passe.', 200);
        }
        return match ($this->authentification->connecter($requete, $courriel, $motDePasse)) {
            ResultatConnexion::Reussie => new RedirectResponse('/', 303),
            ResultatConnexion::Refusee => $this->page($courriel, 'Courriel ou mot de passe incorrect.', 200),
            ResultatConnexion::TropDEssais => $this->page($courriel, "Trop d'essais. Réessayez dans " . Authentification::FENETRE_MINUTES . ' minutes.', 429),
        };
    }

    public function deconnecter(Request $requete): Response
    {
        $this->authentification->deconnecter($requete);
        return new RedirectResponse('/connexion', 303);
    }

    private function page(string $courriel, ?string $erreur, int $code): Response
    {
        return new Response($this->twig->render('connexion.html.twig', ['courriel' => $courriel, 'erreur' => $erreur]), $code);
    }
}
