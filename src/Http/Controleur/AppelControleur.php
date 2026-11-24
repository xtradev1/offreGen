<?php

declare(strict_types=1);

namespace OffreGen\Http\Controleur;

use OffreGen\Compte\Demos;
use OffreGen\Http\Conteneur;
use OffreGen\Http\PageIntrouvable;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * « Demander un appel », côté client (K1 de la maquette v4 de l'administration, trois écrans : le formulaire, le
 * numéro et le moment, la confirmation sans délai promis ; chantier onboarding, E1 du 2026-11-23). Ouvert à tout
 * compte d'artisan ; un membre de l'équipe ou un administrateur reçoit 404 : il est de l'autre côté.
 */
final class AppelControleur
{
    /** « Vous avez choisi … » sur la confirmation. */
    private const DEMANDE = ['matin' => 'le matin', 'midi' => 'midi', 'soir' => 'la fin de journée'];

    public function __construct(private readonly Conteneur $c)
    {
    }

    /** @param array{telephone?: string, moment?: string, question?: string} $saisi */
    public function formulaire(Request $requete, ?string $erreur = null, int $code = 200, array $saisi = []): Response
    {
        $id = $this->compte($requete);
        $attente = $this->c->depotAppels()->enAttente($id);
        return $this->page('appel.html.twig', [
            'nom' => $this->c->depotEquipe()->destinatairesAppel($id)['nom'],
            'moments' => ['matin' => 'Le matin', 'midi' => 'À midi', 'soir' => 'En fin de journée'],
            'telephone' => $saisi['telephone'] ?? $attente['telephone'] ?? '',
            'moment' => $saisi['moment'] ?? $attente['moment'] ?? '',
            'question' => $saisi['question'] ?? $attente['question'] ?? '',
            'erreur' => $erreur,
            'retour' => $this->retour($id),
        ], $code);
    }

    public function envoyer(Request $requete): Response
    {
        $id = $this->compte($requete);
        $saisi = [
            'telephone' => mb_substr($requete->request->getString('telephone'), 0, 64),
            'moment' => $requete->request->getString('moment'),
            'question' => mb_substr($requete->request->getString('question'), 0, 2000),
        ];
        try {
            $this->c->demandesAppel()->deposer($id, $saisi['telephone'], $saisi['moment'], $saisi['question']);
        } catch (\InvalidArgumentException $e) {
            return $this->formulaire($requete, $e->getMessage(), 422, $saisi);
        }
        $appels = $this->c->demandesAppel();
        $this->c->apresReponse()->ajouter(static fn () => $appels->prevenir($id));
        return new RedirectResponse('/demander-un-appel/note', 303);
    }

    /** « C'est noté » : le numéro et le moment de la demande en attente, sans délai promis. */
    public function note(Request $requete): Response
    {
        $id = $this->compte($requete);
        $attente = $this->c->depotAppels()->enAttente($id);
        if ($attente === null) {
            return new RedirectResponse('/demander-un-appel', 303);
        }
        return $this->page('appel_note.html.twig', [
            'nom' => $this->c->depotEquipe()->destinatairesAppel($id)['nom'],
            'telephone' => $attente['telephone'],
            'moment' => self::DEMANDE[$attente['moment']] ?? '',
        ], 200);
    }

    private function compte(Request $requete): int
    {
        $id = $requete->attributes->getInt('_artisan');
        if ($this->c->depotEquipe()->compteAppel($id) === null) {
            throw new PageIntrouvable();
        }
        return $id;
    }

    /** Après une démo finie, on revient à l'écran de fin ; sinon à « Plus », où est le lien. */
    private function retour(int $id): string
    {
        $demo = $this->c->depotEquipe()->demo($id);
        return $demo !== null && Demos::terminee($demo['demo_fin'], $this->c->horloge()->now()) ? '/fin-de-demo' : '/plus';
    }

    /** @param array<string, mixed> $vars */
    private function page(string $gabarit, array $vars, int $code): Response
    {
        $reponse = new Response($this->c->twig()->render($gabarit, $vars), $code);
        // Un numéro de téléphone : rien ne reste dans le cache du navigateur.
        $reponse->headers->set('Cache-Control', 'no-store');
        return $reponse;
    }
}
