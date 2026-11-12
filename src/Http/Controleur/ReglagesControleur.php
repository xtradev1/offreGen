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
    private const CHAMPS = ['entreprise', 'adresse', 'telephone', 'courriel_contact', 'ide', 'tva', 'validite_choix', 'validite_jours',
                            'paiement_type', 'paiement_jours_choix', 'paiement_jours', 'paiement_acomptes_choix', 'paiement_acomptes'];

    public function __construct(private readonly Conteneur $c)
    {
    }

    public function afficher(Request $requete): Response
    {
        $portee = new Portee($requete->attributes->getInt('_artisan'));
        $r = $this->c->reglages($portee)->lire();
        $acomptes = implode(',', $r->paiementAcomptes);
        $proposes = array_map(static fn (array $a): string => implode(',', $a), Reglages::ACOMPTES_PROPOSES);
        $champs = ['entreprise' => $r->entreprise, 'adresse' => $r->adresse, 'telephone' => $r->telephone, 'courriel_contact' => $r->courrielContact,
                   'ide' => $r->ide, 'tva' => (string) $r->tvaPourDixMille,
                   // Une valeur proposée se montre cochée ; une autre, sous « Autre ».
                   'validite_choix' => in_array($r->validiteJours, Reglages::VALIDITES_PROPOSEES, true) ? (string) $r->validiteJours : 'autre',
                   'validite_jours' => (string) $r->validiteJours,
                   'paiement_type' => $r->paiementType,
                   'paiement_jours_choix' => in_array($r->paiementJours, Reglages::DELAIS_PROPOSES, true) ? (string) $r->paiementJours : 'autre',
                   'paiement_jours' => (string) $r->paiementJours,
                   'paiement_acomptes_choix' => $acomptes === '' ? '30,70' : (in_array($acomptes, $proposes, true) ? $acomptes : 'autre'),
                   'paiement_acomptes' => str_replace(',', ', ', $acomptes)];
        return $this->page($portee, $champs, $r->arrondiCinqCentimes, [], 200, $r->conditionsPaiement());
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
            return $this->page($portee, $champs, $arrondi, $e->erreurs, 422, $this->c->reglages($portee)->lire()->conditionsPaiement());
        }
        $this->c->reglages($portee)->enregistrer($reglages);
        Flash::dire($requete, 'info', 'Réglages enregistrés');
        return new RedirectResponse('/reglages', 303);
    }

    /**
     * @param array<string, string> $champs
     * @param array<string, string> $erreurs
     */
    private function page(Portee $portee, array $champs, bool $arrondi, array $erreurs, int $code, string $phrase): Response
    {
        $artisan = $this->c->artisans()->parId($portee->artisanId);
        return new Response($this->c->twig()->render('reglages.html.twig', [
            'champs' => $champs,
            'arrondi' => $arrondi,
            'erreurs' => $erreurs,
            'courriel' => $artisan['courriel'] ?? '',
            'demo' => ($artisan['demo'] ?? 0) === 1,
            'validites' => Reglages::VALIDITES_PROPOSEES,
            'types' => Reglages::TYPES_PAIEMENT,
            'delais' => Reglages::DELAIS_PROPOSES,
            'acomptes' => array_map(static fn (array $a): string => implode(',', $a), Reglages::ACOMPTES_PROPOSES),
            'phrase' => $phrase,
        ]), $code);
    }
}
