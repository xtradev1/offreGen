<?php

declare(strict_types=1);

namespace OffreGen\Http\Controleur;

use OffreGen\Catalogue\CatalogueFictif;
use OffreGen\Compte\Activite;
use OffreGen\Compte\Demos;
use OffreGen\Compte\LiensAcces;
use OffreGen\Donnees\Dates;
use OffreGen\Http\Conteneur;
use OffreGen\Http\Flash;
use OffreGen\Http\PageIntrouvable;
use OffreGen\Systeme\Courriel;
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
            // « visite » ou « offre » : le visiteur n'a pas encore de session, l'activité vient du métier de la démo.
            'premiere' => Activite::duMetier($demo['metier'])->mots()['premiere'],
            'courriel' => $saisi ?? $demo['courriel_saisi'] ?? '',
            'par_courriel' => $this->c->messagerieReelle(),
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
        // Un membre connecté qui ouvre SA démo d'entraînement garde de quoi revenir dans son espace (lot 8, O12 B). La
        // session de la démo est neuve : ce qu'il faut en garder se lit avant, et se pose après.
        $membre = null;
        $connecte = $this->c->authentification()->artisanConnecte($requete);
        if ($connecte !== null && $demo['entrainement'] === 1 && $demo['cree_par'] === $connecte) {
            $compte = $this->c->artisans()->parId($connecte);
            $membre = $compte === null ? null : ['id' => $compte['id'], 'version' => $compte['version_session'], 'guide' => $requete->getSession()->get(AdminControleur::GUIDE_EQUIPE) === true];
        }
        $this->c->authentification()->ouvrirSession($requete, $ouverte['artisan'], $ouverte['version']);
        if ($membre !== null) {
            $this->c->retourEquipe()->poser($requete, $membre['id'], $membre['version'], $ouverte['artisan']);
            $this->c->journalAdmin()->noter($membre['id'], $ouverte['artisan'], 'entree_entrainement', null, $this->c->horloge()->now());
            if ($membre['guide']) {
                $requete->getSession()->set(AdminControleur::GUIDE_EQUIPE, true);
            }
        }
        // Le courriel « revenir dans votre démo » : à la première entrée, et quand le courriel est corrigé. Jamais pour
        // une démo d'entraînement : le membre revient par son espace.
        $liens = $this->c->liensAcces();
        $fin = $demo['demo_fin'];
        $premiere = $ouverte['motif'] !== LiensAcces::DEMO_RETOUR && !$liens->retourDemoEnvoye($ouverte['artisan']);
        if ($fin !== null && $demo['entrainement'] !== 1 && ($premiere || $courriel !== $demo['courriel_saisi'])) {
            $id = $ouverte['artisan'];
            $presentePar = $demo['presente_par'];
            $this->c->apresReponse()->ajouter(static fn () => $liens->envoyerRetourDemo($id, $courriel, Dates::depuisBase($fin), $presentePar));
        }
        return new RedirectResponse('/', 303);
    }

    /**
     * « Revenir à mon espace » depuis une démo d'entraînement (lot 8) : le compte du membre se rouvre si la marque de la
     * session concorde encore (RetourEquipe) ; 404 sinon, comme pour toute page qui n'existe pas pour ce compte.
     */
    public function retourEspace(Request $requete): RedirectResponse
    {
        $demoId = $requete->attributes->getInt('_artisan');
        $membre = $this->c->retourEquipe()->membreARouvrir($requete, $demoId) ?? throw new PageIntrouvable();
        $guide = $requete->getSession()->get(AdminControleur::GUIDE_EQUIPE) === true;
        $this->c->authentification()->ouvrirSession($requete, $membre['id'], $membre['version']);
        if ($guide) {
            // La session est neuve : le guide en cours se repose, pour la carte de fin du suivi.
            $requete->getSession()->set(AdminControleur::GUIDE_EQUIPE, true);
        }
        $this->c->journalAdmin()->noter($membre['id'], $demoId, 'retour_entrainement', null, $this->c->horloge()->now());
        return new RedirectResponse($guide ? '/admin/suivi?guide=fin' : '/admin/suivi', 303);
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
        $this->c->apresReponse()->ajouter(fn () => $this->prevenirPresentateur($demo, $courriel));
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
     * Le membre de l'équipe qui a présenté la démo apprend qu'elle devient un essai (Xavier, 2026-11-24). Rien pour une
     * démo d'entraînement : il l'a ouverte lui-même.
     *
     * @param array{id: int, entreprise: string, cree_par: int|null, entrainement: int} $demo
     */
    private function prevenirPresentateur(array $demo, string $courriel): void
    {
        $membre = $demo['cree_par'] === null || $demo['entrainement'] === 1 ? null : $this->c->artisans()->parId($demo['cree_par']);
        if ($membre === null || $membre['demo'] === 1) {
            return;
        }
        $adresse = rtrim($this->c->parametre('APP_URL', 'http://localhost:8000'), '/');
        Courriel::passageEssai($demo['entreprise'], $courriel, $this->c->horloge()->now(), Demos::OFFRES_ESSAI, "$adresse/admin/artisans/{$demo['id']}")
            ->envoyer($this->c->messagerie(), $membre['courriel'], $this->c->nomApplication(), $adresse);
    }

    /**
     * La démo du compte connecté, si l'équipe l'a créée ; 404 pour tout autre compte.
     *
     * @return array{id: int, entreprise: string, metier: string|null, metier_saisi: string|null, courriel_saisi: string|null, demo_fin: string|null, presente_par: string|null, cree_par: int|null, entrainement: int}
     */
    private function demoCourante(Request $requete): array
    {
        $demo = $this->c->depotEquipe()->demo($requete->attributes->getInt('_artisan'));
        if ($demo === null || $demo['demo_fin'] === null) {
            throw new PageIntrouvable();
        }
        return $demo;
    }

    /**
     * L'écran de fin d'une démo (K1 de l'administration, chantier onboarding, lot 2) : à la première ouverture
     * après la date de fin, puis depuis le micro. Avant la fin, retour aux offres.
     */
    public function fin(Request $requete): Response
    {
        $demo = $this->demoCourante($requete);
        if (!Demos::terminee($demo['demo_fin'], $this->c->horloge()->now())) {
            return new RedirectResponse('/', 303);
        }
        return $this->page('demo_terminee.html.twig', ['entreprise' => $demo['entreprise']], 200);
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
