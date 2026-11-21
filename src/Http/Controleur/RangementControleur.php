<?php

declare(strict_types=1);

namespace OffreGen\Http\Controleur;

use OffreGen\Audio\Uuid;
use OffreGen\Donnees\Portee;
use OffreGen\Http\Conteneur;
use OffreGen\Http\Flash;
use OffreGen\Http\PageIntrouvable;
use OffreGen\Http\Parametres;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Ranger la liste des offres (sous-chantier 21, réponses de Xavier du 2026-11-12) : archiver, supprimer,
 * restaurer une offre ; relancer ou retirer un échec. Une offre archivée ou supprimée compte toujours
 * dans les offres du mois ; un échec, qui n'a pas donné d'offre, ne compte pas.
 */
final class RangementControleur
{
    public function __construct(private readonly Conteneur $c)
    {
    }

    /** @param array<string, string> $parametres */
    public function archiver(Request $requete, array $parametres): RedirectResponse
    {
        $offres = $this->c->offres($this->portee($requete));
        $offre = $offres->parId(Parametres::id($parametres, 'id')) ?? throw new PageIntrouvable();
        $offres->archiver($offre['id'], $this->c->horloge()->now());
        Flash::dire($requete, 'annuler', ['action' => "/offres/{$offre['id']}/restaurer", 'texte' => Flash::cite($offre['titre']) . ' archivée']);
        return new RedirectResponse('/', 303);
    }

    /** @param array<string, string> $parametres */
    public function supprimer(Request $requete, array $parametres): RedirectResponse
    {
        $offres = $this->c->offres($this->portee($requete));
        $offre = $offres->parId(Parametres::id($parametres, 'id')) ?? throw new PageIntrouvable();
        $offres->supprimer($offre['id'], $this->c->horloge()->now());
        Flash::dire($requete, 'annuler', ['action' => "/offres/{$offre['id']}/restaurer", 'texte' => Flash::cite($offre['titre']) . ' supprimée']);
        return new RedirectResponse('/', 303);
    }

    /** @param array<string, string> $parametres */
    public function restaurer(Request $requete, array $parametres): RedirectResponse
    {
        if (!$this->c->offres($this->portee($requete))->restaurer(Parametres::id($parametres, 'id'))) {
            throw new PageIntrouvable();
        }
        return new RedirectResponse('/', 303);
    }

    /** @param array<string, string> $parametres */
    public function reessayer(Request $requete, array $parametres): RedirectResponse
    {
        $uuid = $this->uuid($requete, $parametres);
        if (!$this->c->enregistrements($this->portee($requete))->reessayer($uuid)) {
            Flash::dire($requete, 'erreur', "Plus de quoi relancer : l'audio a été effacé. Enregistrez à nouveau.");
            return new RedirectResponse('/', 303);
        }
        $this->c->programmerTraitement($uuid);
        return new RedirectResponse('/enregistrements/' . $uuid, 303);
    }

    /** @param array<string, string> $parametres */
    public function retirer(Request $requete, array $parametres): RedirectResponse
    {
        $uuid = $this->uuid($requete, $parametres);
        $this->c->enregistrements($this->portee($requete))->retirer($uuid, $this->c->horloge()->now());
        Flash::dire($requete, 'info', 'Échec retiré');
        return new RedirectResponse('/', 303);
    }

    private function portee(Request $requete): Portee
    {
        return new Portee($requete->attributes->getInt('_artisan'));
    }

    /** @param array<string, string> $parametres */
    private function uuid(Request $requete, array $parametres): string
    {
        $uuid = $parametres['uuid'] ?? '';
        if (!Uuid::valide($uuid) || $this->c->enregistrements($this->portee($requete))->parUuid($uuid) === null) {
            throw new PageIntrouvable();
        }
        return $uuid;
    }
}
