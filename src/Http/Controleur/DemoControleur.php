<?php

declare(strict_types=1);

namespace OffreGen\Http\Controleur;

use OffreGen\Catalogue\CatalogueFictif;
use OffreGen\Compte\Demos;
use OffreGen\Http\Conteneur;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * L'entrée du prospect dans sa démo (demande de Xavier du 2026-11-20 : « il met son mail, il a l'app, il parle »).
 * Le lien vient d'un code QR ou d'un message. Ouvrir la page ne consomme rien : les aperçus de lien des messageries
 * la chargent aussi. Le courriel tapé n'est pas confirmé : il est noté à part, et rien ne lui est envoyé.
 */
final class DemoControleur
{
    public function __construct(private readonly Conteneur $c)
    {
    }

    /** @param array<string, string> $p */
    public function entrer(Request $requete, array $p, ?string $erreur = null, int $code = 200, ?string $saisi = null): Response
    {
        $id = $this->c->liensAcces()->demoDuLien($p['jeton']);
        $demo = $id === null ? null : $this->c->depotEquipe()->demo($id);
        if ($demo === null) {
            return $this->perimee();
        }
        $metier = $demo['metier_saisi'] ?? ($demo['metier'] === null || $demo['metier'] === 'autre' ? null : CatalogueFictif::libelle($demo['metier']));
        return $this->page('demo_entrer.html.twig', [
            'action' => '/d/' . $p['jeton'],
            'metier' => $metier,
            'courriel' => $saisi ?? $demo['courriel_saisi'] ?? '',
            'erreur' => $erreur,
        ], $code);
    }

    /** @param array<string, string> $p */
    public function ouvrir(Request $requete, array $p): Response
    {
        $id = $this->c->liensAcces()->demoDuLien($p['jeton']);
        $demo = $id === null ? null : $this->c->depotEquipe()->demo($id);
        if ($demo === null) {
            return $this->perimee();
        }
        $saisi = trim($requete->request->getString('courriel'));
        try {
            // Le commercial a pu le taper à la création : le prospect n'a alors rien à écrire.
            $courriel = $saisi === '' ? ($demo['courriel_saisi'] ?? throw new \InvalidArgumentException('Indiquez votre courriel.')) : Demos::courriel($saisi);
        } catch (\InvalidArgumentException $e) {
            return $this->entrer($requete, $p, $e->getMessage(), 422, $saisi);
        }
        $ouverte = $this->c->liensAcces()->ouvrirDemo($p['jeton']);
        if ($ouverte === null) {
            return $this->perimee();
        }
        $this->c->depotEquipe()->saisirCourriel($ouverte['artisan'], $courriel);
        $this->c->authentification()->ouvrirSession($requete, $ouverte['artisan'], $ouverte['version']);
        return new RedirectResponse('/', 303);
    }

    private function perimee(): Response
    {
        return $this->page('demo_perimee.html.twig', [], 410);
    }

    /** @param array<string, mixed> $vars */
    private function page(string $gabarit, array $vars, int $code): Response
    {
        $reponse = new Response($this->c->twig()->render($gabarit, $vars), $code);
        $reponse->headers->set('Cache-Control', 'no-store');
        return $reponse;
    }
}
