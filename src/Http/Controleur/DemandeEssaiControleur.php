<?php

declare(strict_types=1);

namespace OffreGen\Http\Controleur;

use OffreGen\Compte\DemandesEssai;
use OffreGen\Http\Conteneur;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Le formulaire « Bon pour essai » de la page de présentation : le courriel du visiteur part à l'exploitant.
 * Trois demandes par heure et par visiteur ; le plafond du jour est tenu par DemandesEssai.
 */
final class DemandeEssaiControleur
{
    public const PAR_HEURE = 3;

    public function __construct(private readonly Conteneur $c)
    {
    }

    public function deposer(Request $requete): RedirectResponse
    {
        $courriel = trim($requete->request->getString('courriel'));
        $avant = $requete->request->getString('etat') === 'avant';
        $maintenant = $this->c->horloge()->now()->getTimestamp();
        $session = $requete->getSession();
        $demandes = array_values(array_filter((array) $session->get('demandes_essai', []), static fn (mixed $t): bool => is_int($t) && $t > $maintenant - 3600));
        $etat = count($demandes) >= self::PAR_HEURE ? DemandesEssai::TROP : $this->c->demandesEssai()->accepter($courriel);
        if ($etat === DemandesEssai::RECUE) {
            $demandes[] = $maintenant;
            $session->set('demandes_essai', $demandes);
            // Après la réponse : un serveur d'envoi lent ne fait pas attendre le visiteur.
            $this->c->apresReponse()->ajouter(fn () => $this->c->demandesEssai()->transmettre($courriel, $avant));
        }
        return new RedirectResponse('/?' . ($avant ? 'etat=avant&' : '') . 'demande=' . $etat . '#demande', 303);
    }
}
