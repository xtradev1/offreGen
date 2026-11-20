<?php

declare(strict_types=1);

namespace OffreGen\Http\Controleur;

use OffreGen\Catalogue\CatalogueFictif;
use OffreGen\Compte\Demos;
use OffreGen\Http\Conteneur;
use OffreGen\Http\Flash;
use OffreGen\Http\PageIntrouvable;
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

    /** L'écran « Passer à l'essai » (Xavier, 2026-11-20), ouvert depuis le ruban d'une démo de l'équipe. */
    public function passage(Request $requete, ?string $erreur = null, int $code = 200, ?string $saisi = null): Response
    {
        $demo = $this->demoCourante($requete);
        return $this->page('essai_passer.html.twig', [
            'courriel' => $saisi ?? $demo['courriel_saisi'] ?? '',
            'offres' => Demos::OFFRES_ESSAI,
            'par_courriel' => $this->c->messagerieReelle(),
            'erreur' => $erreur,
        ], $code);
    }

    /**
     * La démo devient un essai, sur le même compte. Comptes::passerEnEssai remplace le mot de passe et ferme les
     * sessions : celle du prospect est rouverte aussitôt, il ne voit pas de coupure. Le courriel se confirme par le
     * lien du mot de passe, envoyé si le site envoie des courriels, ouvert tout de suite sinon.
     */
    public function passer(Request $requete): Response
    {
        $demo = $this->demoCourante($requete);
        $compte = $this->c->artisans()->parId($demo['id']) ?? throw new PageIntrouvable();
        $saisi = trim($requete->request->getString('courriel'));
        try {
            $courriel = $saisi === '' ? throw new \InvalidArgumentException('Indiquez votre courriel.') : Demos::courriel($saisi);
            if ($this->c->artisans()->parCourriel($courriel) !== null) {
                throw new \InvalidArgumentException('Un compte existe déjà avec ce courriel. Indiquez-en un autre ou connectez-vous à ce compte.');
            }
            $this->c->comptes()->passerEnEssai($compte['courriel'], $courriel, Demos::OFFRES_ESSAI);
        } catch (\InvalidArgumentException $e) {
            return $this->passage($requete, $e->getMessage(), 422, $saisi);
        }
        // Le compte relu : son numéro de session vient d'avancer.
        $essai = $this->c->artisans()->parId($demo['id']);
        $this->c->authentification()->ouvrirSession($requete, $essai['id'], $essai['version_session']);
        $this->c->journalAdmin()->noter($essai['id'], $essai['id'], 'passage_essai', null, $this->c->horloge()->now());
        if ($this->c->messagerieReelle()) {
            $this->c->apresReponse()->ajouter(fn () => $this->c->liensAcces()->envoyerAcces($courriel));
            Flash::dire($requete, 'info', "Vous êtes à l'essai. Un courriel vous attend pour choisir votre mot de passe.");
            return new RedirectResponse('/', 303);
        }
        // Ce site n'envoie pas de courriels : sans mot de passe choisi tout de suite, le compte serait perdu à la
        // fin de la session.
        Flash::dire($requete, 'info', "Vous êtes à l'essai. Choisissez votre mot de passe pour retrouver votre compte.");
        return new RedirectResponse((string) parse_url($this->c->liensAcces()->lienDAcces($essai['id']), PHP_URL_PATH), 303);
    }

    /**
     * La démo du compte connecté, si l'équipe l'a créée ; 404 pour tout autre compte.
     *
     * @return array{id: int, entreprise: string, metier: string|null, metier_saisi: string|null, courriel_saisi: string|null, demo_fin: string|null}
     */
    private function demoCourante(Request $requete): array
    {
        $demo = $this->c->depotEquipe()->demo($requete->attributes->getInt('_artisan'));
        if ($demo === null || $demo['demo_fin'] === null) {
            throw new PageIntrouvable();
        }
        return $demo;
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
