<?php

declare(strict_types=1);

namespace OffreGen\Http\Controleur;

use OffreGen\Compte\Reglages;
use OffreGen\Donnees\Portee;
use OffreGen\Http\Conteneur;
use OffreGen\Http\Flash;
use OffreGen\Offre\SaisieRefusee;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class ReglagesControleur
{
    private const CHAMPS = ['entreprise', 'adresse', 'telephone', 'courriel_contact', 'ide', 'tva', 'validite_jours', 'conditions_paiement'];

    public function __construct(private readonly Conteneur $c)
    {
    }

    public function afficher(Request $requete): Response
    {
        $portee = new Portee($requete->attributes->getInt('_artisan'));
        $r = $this->c->reglages($portee)->lire();
        $champs = ['entreprise' => $r->entreprise, 'adresse' => $r->adresse, 'telephone' => $r->telephone, 'courriel_contact' => $r->courrielContact,
                   'ide' => $r->ide, 'tva' => (string) $r->tvaPourDixMille, 'validite_jours' => (string) $r->validiteJours,
                   'conditions_paiement' => $r->conditionsPaiement];
        return $this->page($portee, $champs, $r->arrondiCinqCentimes, [], 200);
    }

    public function enregistrer(Request $requete): Response
    {
        $portee = new Portee($requete->attributes->getInt('_artisan'));
        $champs = [];
        foreach (self::CHAMPS as $nom) {
            $champs[$nom] = $requete->request->getString($nom);
        }
        $arrondi = $requete->request->getString('arrondi') === '1';
        try {
            $reglages = Reglages::depuisSaisie($champs, $arrondi);
        } catch (SaisieRefusee $e) {
            return $this->page($portee, $champs, $arrondi, $e->erreurs, 422);
        }
        $this->c->reglages($portee)->enregistrer($reglages);
        Flash::dire($requete, 'info', 'Réglages enregistrés');
        return new RedirectResponse('/reglages', 303);
    }

    /**
     * @param array<string, string> $champs
     * @param array<string, string> $erreurs
     */
    private function page(Portee $portee, array $champs, bool $arrondi, array $erreurs, int $code): Response
    {
        $artisan = $this->c->artisans()->parId($portee->artisanId);
        return new Response($this->c->twig()->render('reglages.html.twig', [
            'champs' => $champs,
            'arrondi' => $arrondi,
            'erreurs' => $erreurs,
            'courriel' => $artisan['courriel'] ?? '',
            'demo' => ($artisan['demo'] ?? 0) === 1,
            'validites' => Reglages::VALIDITES_PROPOSEES,
            'paiements' => Reglages::PAIEMENTS_PROPOSES,
        ]), $code);
    }
}
