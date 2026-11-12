<?php

declare(strict_types=1);

namespace OffreGen\Http\Controleur;

use OffreGen\Compte\LogoRefuse;
use OffreGen\Compte\Marque;
use OffreGen\Compte\Reglages;
use OffreGen\Donnees\Portee;
use OffreGen\Http\Conteneur;
use OffreGen\Http\Flash;
use OffreGen\Http\PageIntrouvable;
use OffreGen\Offre\SaisieRefusee;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class ReglagesControleur
{
    private const CHAMPS = ['entreprise', 'adresse', 'telephone', 'courriel_contact', 'ide', 'tva', 'validite_choix', 'validite_jours',
                            'paiement_type', 'paiement_jours_choix', 'paiement_jours', 'paiement_acomptes_choix', 'paiement_acomptes',
                            'modele', 'couleur_choix', 'couleur'];

    public function __construct(private readonly Conteneur $c)
    {
    }

    public function afficher(Request $requete): Response
    {
        $portee = new Portee($requete->attributes->getInt('_artisan'));
        $r = $this->c->reglages($portee)->lire();
        $m = $this->c->reglages($portee)->lireMarque();
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
                   'paiement_acomptes' => str_replace(',', ', ', $acomptes),
                   'modele' => $m->modele,
                   'couleur_choix' => in_array($m->couleur(), self::teintes($m), true) ? $m->couleur() : 'autre',
                   'couleur' => $m->couleur()];
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
        $erreurs = [];
        $reglages = null;
        try {
            $reglages = Reglages::depuisSaisie($champs, $arrondi);
        } catch (SaisieRefusee $e) {
            $erreurs = $e->erreurs;
        }
        // La marque : sans ses champs (page ancienne, test), elle reste telle quelle.
        $marque = $this->c->reglages($portee)->lireMarque();
        $modele = $champs['modele'] === '' ? $marque->modele : Marque::modeleValide($champs['modele']);
        $couleur = $marque->couleurChoisie;
        if ($champs['couleur_choix'] !== '') {
            $couleur = Marque::couleurValide($champs['couleur_choix'] === 'autre' ? $champs['couleur'] : $champs['couleur_choix']);
            if ($couleur === null) {
                $erreurs['couleur'] = 'Une couleur de la forme #1D4E89.';
            }
        }
        if ($reglages === null || $erreurs !== []) {
            return $this->page($portee, $champs, $arrondi, $erreurs, 422, $this->c->reglages($portee)->lire()->conditionsPaiement());
        }
        $this->c->reglages($portee)->enregistrer($reglages);
        $this->c->reglages($portee)->enregistrerMarque($modele, $couleur);
        Flash::dire($requete, 'info', 'Réglages enregistrés');
        return new RedirectResponse('/reglages', 303);
    }

    /** Le logo de l'artisan connecté, et de lui seul : l'adresse ne prend aucun paramètre. */
    public function logo(Request $requete): Response
    {
        $m = $this->c->reglages(new Portee($requete->attributes->getInt('_artisan')))->lireMarque();
        $chemin = $m->logo === null ? null : $this->c->dossierLogos() . '/' . basename($m->logo);
        if ($chemin === null || !is_file($chemin)) {
            throw new PageIntrouvable();
        }
        $reponse = new BinaryFileResponse($chemin, 200, ['Content-Type' => 'image/jpeg']);
        // L'adresse change avec le fichier (?v=…) : le navigateur peut le garder.
        $reponse->headers->set('Cache-Control', 'private, max-age=86400');
        return $reponse;
    }

    /** La couleur des documents de l'artisan connecté, en CSS (voir Marque::css). */
    public function css(Request $requete): Response
    {
        $m = $this->c->reglages(new Portee($requete->attributes->getInt('_artisan')))->lireMarque();
        return new Response($m->css(), 200, ['Content-Type' => 'text/css; charset=utf-8', 'Cache-Control' => 'private, max-age=86400']);
    }

    /** Nouveau logo : contrôlé, réencodé, rangé hors du site public ; sa couleur devient celle des documents. */
    public function envoyerLogo(Request $requete): RedirectResponse
    {
        $portee = new Portee($requete->attributes->getInt('_artisan'));
        $fichier = $requete->files->get('logo');
        if (!$fichier instanceof UploadedFile || !$fichier->isValid()) {
            Flash::dire($requete, 'erreur', 'Choisissez une image PNG ou JPEG.');
            return new RedirectResponse('/reglages#marque', 303);
        }
        $dossier = $this->c->dossierLogos();
        if (!is_dir($dossier)) {
            mkdir($dossier, 0700, true);
        }
        $nom = $portee->artisanId . '-' . bin2hex(random_bytes(6)) . '.jpg';
        $logos = $this->c->traitementLogo();
        try {
            $logos->preparer($fichier->getPathname(), "$dossier/$nom");
        } catch (LogoRefuse $e) {
            Flash::dire($requete, 'erreur', $e->getMessage());
            return new RedirectResponse('/reglages#marque', 303);
        }
        chmod("$dossier/$nom", 0600);
        $ancien = $this->c->reglages($portee)->lireMarque()->logo;
        $this->c->reglages($portee)->changerLogo($nom, $logos->couleurDominante("$dossier/$nom"));
        $this->effacer($ancien);
        Flash::dire($requete, 'info', 'Logo enregistré');
        return new RedirectResponse('/reglages#marque', 303);
    }

    public function retirerLogo(Request $requete): RedirectResponse
    {
        $portee = new Portee($requete->attributes->getInt('_artisan'));
        $ancien = $this->c->reglages($portee)->lireMarque()->logo;
        $this->c->reglages($portee)->changerLogo(null, null);
        $this->effacer($ancien);
        Flash::dire($requete, 'info', 'Logo retiré');
        return new RedirectResponse('/reglages#marque', 303);
    }

    private function effacer(?string $logo): void
    {
        $chemin = $logo === null ? null : $this->c->dossierLogos() . '/' . basename($logo);
        if ($chemin !== null && is_file($chemin)) {
            unlink($chemin);
        }
    }

    /** @return list<string> la couleur du logo d'abord, puis les couleurs proposées */
    private static function teintes(Marque $m): array
    {
        return array_values(array_unique(array_filter([$m->couleurDuLogo, ...Marque::TEINTES])));
    }

    /**
     * @param array<string, string> $champs
     * @param array<string, string> $erreurs
     */
    private function page(Portee $portee, array $champs, bool $arrondi, array $erreurs, int $code, string $phrase): Response
    {
        $artisan = $this->c->artisans()->parId($portee->artisanId);
        $marque = $this->c->reglages($portee)->lireMarque();
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
            'marque' => $marque,
            'teintes' => self::teintes($marque),
            'modeles' => Marque::MODELES,
        ]), $code);
    }
}
