<?php

declare(strict_types=1);

namespace OffreGen\Http\Controleur;

use OffreGen\Compte\Authentification;
use OffreGen\Compte\MotDePasse;
use OffreGen\Compte\ResultatConnexion;
use OffreGen\Http\Conteneur;
use OffreGen\Http\Flash;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * « Mot de passe oublié », lien d'accès reçu par courriel, et rubrique « Mot de passe » des réglages
 * (décisions P1, P3 B, P4 et A3 A de Xavier, 2026-11-18 ; l'aspect suivra la marque).
 */
final class MotDePasseControleur
{
    public function __construct(private readonly Conteneur $c)
    {
    }

    public function oubli(Request $requete): Response
    {
        return $this->page('mot_de_passe_oubli.html.twig', ['courriel' => '', 'erreur' => null]);
    }

    /** Même page, que le compte existe ou non ; le courriel part après la réponse (son délai ne trahit rien). */
    public function demander(Request $requete): Response
    {
        $courriel = trim($requete->request->getString('courriel'));
        if ($courriel === '' || mb_strlen($courriel) > 190) {
            return $this->page('mot_de_passe_oubli.html.twig', ['courriel' => $courriel, 'erreur' => 'Indiquez le courriel de votre compte.'], 422);
        }
        $this->c->apresReponse()->ajouter(fn () => $this->c->liensAcces()->demanderOubli($courriel));
        return $this->page('mot_de_passe_envoye.html.twig', ['courriel' => $courriel]);
    }

    /** @param array<string, string> $p */
    public function lien(Request $requete, array $p): Response
    {
        if ($this->c->liensAcces()->artisanDuLien($p['jeton']) === null) {
            return $this->page('mot_de_passe_perime.html.twig', [], 410);
        }
        return $this->page('mot_de_passe_nouveau.html.twig', ['action' => '/mot-de-passe/' . $p['jeton'], 'erreur' => null]);
    }

    /** @param array<string, string> $p */
    public function choisir(Request $requete, array $p): Response
    {
        $action = '/mot-de-passe/' . $p['jeton'];
        if ($this->c->liensAcces()->artisanDuLien($p['jeton']) === null) {
            return $this->page('mot_de_passe_perime.html.twig', [], 410);
        }
        $motDePasse = $requete->request->getString('mot_de_passe');
        $erreur = MotDePasse::erreur($motDePasse, $requete->request->getString('confirmation'));
        if ($erreur !== null) {
            return $this->page('mot_de_passe_nouveau.html.twig', ['action' => $action, 'erreur' => $erreur], 422);
        }
        $resultat = $this->c->liensAcces()->utiliser($p['jeton'], $motDePasse);
        if ($resultat === null) {
            return $this->page('mot_de_passe_perime.html.twig', [], 410);
        }
        $this->c->authentification()->ouvrirSession($requete, $resultat['artisan'], $resultat['version']);
        Flash::dire($requete, 'info', 'Mot de passe enregistré.');
        return new RedirectResponse('/', 303);
    }

    public function reglages(Request $requete): Response
    {
        return $this->demonstration() ?? $this->page('reglages_mot_de_passe.html.twig', ['erreur' => null]);
    }

    public function changer(Request $requete): Response
    {
        $refus = $this->demonstration();
        if ($refus !== null) {
            return $refus;
        }
        $artisan = $this->c->garde()->artisanCourant() ?? throw new \LogicException('Route artisan sans artisan.');
        $nouveau = $requete->request->getString('mot_de_passe');
        $actuel = $this->c->authentification()->verifierMotDePasseActuel($requete, $artisan, $requete->request->getString('ancien'));
        if ($actuel === ResultatConnexion::TropDEssais) {
            return $this->page('reglages_mot_de_passe.html.twig', ['erreur' => "Trop d'essais. Réessayez dans " . Authentification::FENETRE_MINUTES . ' minutes.'], 429);
        }
        $erreur = $actuel === ResultatConnexion::Reussie
            ? MotDePasse::erreur($nouveau, $requete->request->getString('confirmation'))
            : 'Le mot de passe actuel est faux.';
        if ($erreur !== null) {
            return $this->page('reglages_mot_de_passe.html.twig', ['erreur' => $erreur], 422);
        }
        if (!$this->c->authentification()->remplacerMotDePasse($requete, $artisan, $nouveau)) {
            // Un lien d'oubli a servi pendant ce temps : celui-là l'emporte, cette session n'est plus la bonne.
            return new RedirectResponse('/connexion', 303);
        }
        $this->c->liensAcces()->annulerTout($artisan);
        Flash::dire($requete, 'info', 'Mot de passe changé. Les autres appareils sont déconnectés.');
        return new RedirectResponse('/reglages', 303);
    }

    /** Un compte de démonstration est partagé : son mot de passe ne change pas (revue du 2026-11-18). */
    private function demonstration(): ?Response
    {
        $artisan = $this->c->artisans()->parId($this->c->garde()->artisanCourant() ?? 0);
        if ($artisan === null || $artisan['demo'] !== 1) {
            return null;
        }
        return new Response($this->c->twig()->render('erreur.html.twig', ['code' => 403, 'titre' => 'Mot de passe fixe pour un compte de démonstration']), 403);
    }

    /** @param array<string, mixed> $vars */
    private function page(string $gabarit, array $vars, int $code = 200): Response
    {
        $reponse = new Response($this->c->twig()->render($gabarit, $vars + ['longueur_min' => MotDePasse::LONGUEUR_MIN]), $code);
        // Le jeton est dans l'adresse de la page : pas de cache. Vers un autre site, la règle du noyau
        // (strict-origin-when-cross-origin) ne transmet que le domaine, et la page ne charge rien d'ailleurs.
        $reponse->headers->set('Cache-Control', 'no-store');
        return $reponse;
    }
}
