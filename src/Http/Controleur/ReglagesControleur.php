<?php

declare(strict_types=1);

namespace OffreGen\Http\Controleur;

use OffreGen\Compte\LogoRefuse;
use OffreGen\Compte\Marque;
use OffreGen\Compte\Reglages;
use OffreGen\Donnees\Portee;
use OffreGen\Http\Conteneur;
use OffreGen\Http\Flash;
use OffreGen\Http\Formats;
use OffreGen\Http\PageIntrouvable;
use OffreGen\Offre\SaisieRefusee;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class ReglagesControleur
{
    private const CHAMPS = ['entreprise', 'adresse', 'telephone', 'courriel_contact', 'ide', 'tva', 'validite_choix', 'validite_jours',
                            'paiement_type', 'paiement_jours_choix', 'paiement_jours', 'paiement_acomptes_choix', 'paiement_acomptes',
                            'modele', 'couleur_choix', 'couleur'];

    /** Les rubriques du sommaire, dans l'ordre de la maquette validée le 2026-11-12. */
    public const RUBRIQUES = ['entreprise' => 'Mon entreprise', 'marque' => 'Ma marque', 'conditions' => "Conditions de l'offre", 'tva' => 'TVA et arrondi'];

    /** Les étapes de l'assistant du premier lancement. */
    public const ETAPES = ['entreprise' => 'Votre entreprise', 'marque' => 'Votre marque', 'tva' => 'La TVA', 'conditions' => 'Vos conditions'];

    public function __construct(private readonly Conteneur $c)
    {
    }

    /** Le sommaire : une ligne par rubrique, son résumé, « à compléter » si un champ attendu manque. */
    public function afficher(Request $requete): Response
    {
        $portee = $this->portee($requete);
        $r = $this->c->reglages($portee)->lire();
        $m = $this->c->reglages($portee)->lireMarque();
        $manque = array_merge($r->adresse === '' ? ['adresse'] : [], $r->tvaPourDixMille > 0 && $r->ide === '' ? ['IDE'] : []);
        $rubriques = [
            'entreprise' => ['complete' => $manque === [],
                'resume' => $r->entreprise . ' · ' . ($manque === [] ? ($r->localite() !== '' ? $r->localite() : $r->telephone) : implode(' et ', $manque) . (count($manque) > 1 ? ' manquent' : ' manque'))],
            'marque' => ['complete' => true, 'resume' => ($m->logo !== null ? 'Logo' : 'Sans logo') . ' · modèle ' . Marque::MODELES[$m->modele]],
            'conditions' => ['complete' => true, 'resume' => 'Valable ' . $r->validiteJours . ' jours · ' . $r->resumePaiement()],
            'tva' => ['complete' => true,
                'resume' => ($r->tvaPourDixMille > 0 ? 'Assujetti ' . Formats::taux($r->tvaPourDixMille) : 'Non assujetti') . ' · ' . ($r->arrondiCinqCentimes ? 'arrondi aux 5 centimes' : 'au centime')],
        ];
        foreach ($rubriques as $cle => $x) {
            $rubriques[$cle]['titre'] = self::RUBRIQUES[$cle];
        }
        $artisan = $this->c->artisans()->parId($portee->artisanId);
        return new Response($this->c->twig()->render('reglages.html.twig', [
            'rubriques' => $rubriques,
            'completes' => count(array_filter($rubriques, static fn (array $x): bool => $x['complete'])),
            'courriel' => $artisan['courriel'] ?? '',
            'demo' => ($artisan['demo'] ?? 0) === 1,
        ]));
    }

    /** Une rubrique seule à l'écran ; chaque changement s'y enregistre seul (app.js). */
    public function rubrique(Request $requete, string $nom): Response
    {
        $portee = $this->portee($requete);
        $r = $this->c->reglages($portee)->lire();
        return $this->page($portee, [$nom], self::RUBRIQUES[$nom], $this->champsActuels($portee), $r->arrondiCinqCentimes, [], 200, '/reglages/' . $nom, null);
    }

    /**
     * Enregistre les champs reçus, et eux seuls : les autres gardent leur valeur. Ainsi chaque rubrique,
     * et chaque champ de l'enregistrement automatique, part sans le reste du formulaire. app.js demande une
     * réponse en JSON ; sans JavaScript, retour à la rubrique d'où vient le formulaire.
     */
    public function enregistrer(Request $requete): Response
    {
        $portee = $this->portee($requete);
        [$erreurs, $champs, $arrondi] = $this->sauver($requete, $portee);
        $retour = $requete->request->getString('retour');
        $rubrique = str_starts_with($retour, '/reglages/') ? substr($retour, strlen('/reglages/')) : '';
        $json = str_contains((string) $requete->headers->get('Accept'), 'application/json');
        if ($erreurs !== []) {
            if ($json) {
                return new JsonResponse(['ok' => false, 'erreurs' => $erreurs], 422);
            }
            $sections = array_key_exists($rubrique, self::RUBRIQUES) ? [$rubrique] : array_keys(self::RUBRIQUES);
            $titre = array_key_exists($rubrique, self::RUBRIQUES) ? self::RUBRIQUES[$rubrique] : 'Réglages';
            return $this->page($portee, $sections, $titre, $champs, $arrondi, $erreurs, 422, $retour, null);
        }
        if ($json) {
            return new JsonResponse(['ok' => true]);
        }
        Flash::dire($requete, 'info', 'Réglages enregistrés');
        if ($retour === '/bienvenue/marque') {
            // Modèle choisi depuis son aperçu, pendant l'assistant : retour à l'étape.
            return new RedirectResponse($retour, 303);
        }
        return new RedirectResponse(array_key_exists($rubrique, self::RUBRIQUES) ? '/reglages/' . $rubrique : '/reglages', 303);
    }

    /**
     * « Voir en grand » : le vrai document dans le modèle demandé, avec le logo et la couleur de l'artisan
     * et sa dernière offre (un exemple s'il n'en a pas). Rien n'est enregistré.
     */
    public function apercuModele(Request $requete, string $modele): Response
    {
        $portee = $this->portee($requete);
        $offres = $this->c->offres($portee);
        $offre = $offres->liste(1)[0] ?? null;
        $lignes = $offre === null ? [] : $offres->lignes($offre['id']);
        if ($offre === null || $lignes === []) {
            [$offre, $lignes] = self::exemple($this->c->horloge()->now());
        }
        $m = $this->c->reglages($portee)->lireMarque();
        $apercu = new Marque($modele, $m->couleurChoisie, $m->logo, $m->couleurDuLogo);
        $assistant = $requete->query->getString('depuis') === 'bienvenue';
        return new Response($this->c->twig()->render('modele_apercu.html.twig', (new OffreControleur($this->c))->vueDocument($portee, $offre, $lignes, $apercu) + [
            'modele' => $modele,
            'modeles' => Marque::MODELES,
            'choisi' => $m->modele,
            'retour' => $assistant ? '/bienvenue/marque' : '/reglages/marque',
            'depuis' => $assistant ? '?depuis=bienvenue' : '',
        ]));
    }

    /**
     * Une petite offre de peintre, pour l'aperçu d'un compte qui n'a encore rien dicté.
     *
     * @return array{array{id: int, numero: int, enregistrement_id: null, titre: string, client_nom: string, client_adresse: string, cree_le: string, modifiee_le: null}, list<array{id: int, offre_id: int, rang: int, designation: string, quantite_milliemes: int, unite: string, prix_centimes: int, a_verifier: int, entendu: null, produit_id: null, categorie: string}>}
     */
    private static function exemple(\DateTimeImmutable $maintenant): array
    {
        $offre = ['id' => 0, 'numero' => 1, 'enregistrement_id' => null, 'titre' => 'Rafraîchissement du séjour',
                  'client_nom' => 'Madame Exemple', 'client_adresse' => "Rue de l'Exemple 1\n2800 Delémont",
                  'cree_le' => $maintenant->format('Y-m-d H:i:s'), 'modifiee_le' => null];
        $lignes = [];
        foreach ([['Préparation', 'Protection des sols et des meubles', 1000, 'forfait', 18000],
                  ['Préparation', 'Ponçage et rebouchage des murs', 42000, 'm²', 900],
                  ['Peinture', 'Peinture murale, deux couches', 42000, 'm²', 1850],
                  ['Peinture', 'Peinture du plafond', 18000, 'm²', 2100]] as $i => [$categorie, $designation, $quantite, $unite, $prix]) {
            $lignes[] = ['id' => $i + 1, 'offre_id' => 0, 'rang' => $i + 1, 'designation' => $designation, 'quantite_milliemes' => $quantite,
                         'unite' => $unite, 'prix_centimes' => $prix, 'a_verifier' => 0, 'entendu' => null, 'produit_id' => null, 'categorie' => $categorie];
        }
        return [$offre, $lignes];
    }

    /** Une étape de l'assistant du premier lancement. */
    public function etape(Request $requete, string $nom): Response
    {
        $portee = $this->portee($requete);
        $r = $this->c->reglages($portee)->lire();
        return $this->page($portee, [$nom], self::ETAPES[$nom], $this->champsActuels($portee), $r->arrondiCinqCentimes, [], 200, '', $nom);
    }

    /** « Suivant » : enregistre l'étape, passe à la suivante ; la dernière termine l'assistant. */
    public function enregistrerEtape(Request $requete, string $nom): Response
    {
        $portee = $this->portee($requete);
        [$erreurs, $champs, $arrondi] = $this->sauver($requete, $portee);
        if ($erreurs !== []) {
            return $this->page($portee, [$nom], self::ETAPES[$nom], $champs, $arrondi, $erreurs, 422, '', $nom);
        }
        $etapes = array_keys(self::ETAPES);
        $suivante = $etapes[array_search($nom, $etapes, true) + 1] ?? null;
        if ($suivante !== null) {
            return new RedirectResponse('/bienvenue/' . $suivante, 303);
        }
        $this->c->reglages($portee)->marquerAssistantFait($this->c->horloge()->now());
        Flash::dire($requete, 'info', 'C\'est prêt : vos offres porteront votre en-tête, votre marque et vos conditions.');
        return new RedirectResponse('/', 303);
    }

    /** « Plus tard » : l'assistant ne revient plus ; tout reste dans Réglages. */
    public function passer(Request $requete): RedirectResponse
    {
        $this->c->reglages($this->portee($requete))->marquerAssistantFait($this->c->horloge()->now());
        return new RedirectResponse('/', 303);
    }

    /**
     * Les champs reçus par-dessus les valeurs actuelles, validés ensemble ; enregistrés s'ils sont justes.
     *
     * @return array{array<string, string>, array<string, string>, bool} erreurs, champs, arrondi
     */
    private function sauver(Request $requete, Portee $portee): array
    {
        $champs = $this->champsActuels($portee);
        $recus = $requete->request;
        foreach (self::CHAMPS as $nom) {
            if ($recus->has($nom)) {
                $champs[$nom] = $recus->getString($nom);
            }
        }
        // Un nombre tapé sans son choix (formulaire ancien) vaut « Autre » : sinon le choix actuel l'emporterait.
        foreach (['validite_jours' => 'validite_choix', 'paiement_jours' => 'paiement_jours_choix', 'paiement_acomptes' => 'paiement_acomptes_choix'] as $tape => $choix) {
            if ($recus->has($tape) && !$recus->has($choix)) {
                $champs[$choix] = 'autre';
            }
        }
        $r = $this->c->reglages($portee)->lire();
        $arrondi = $recus->has('arrondi') ? $recus->getString('arrondi') === '1' : $r->arrondiCinqCentimes;
        $erreurs = [];
        $reglages = null;
        try {
            $reglages = Reglages::depuisSaisie($champs, $arrondi);
        } catch (SaisieRefusee $e) {
            $erreurs = $e->erreurs;
        }
        $couleur = Marque::couleurValide($champs['couleur_choix'] === 'autre' ? $champs['couleur'] : $champs['couleur_choix']);
        if ($couleur === null) {
            $erreurs['couleur'] = 'Une couleur de la forme #1D4E89.';
        }
        if ($reglages !== null && $erreurs === []) {
            $this->c->reglages($portee)->enregistrer($reglages);
            $this->c->reglages($portee)->enregistrerMarque(Marque::modeleValide($champs['modele']), $couleur);
        }
        return [$erreurs, $champs, $arrondi];
    }

    /** @return array<string, string> les réglages actuels, sous la forme des champs de l'écran */
    private function champsActuels(Portee $portee): array
    {
        $r = $this->c->reglages($portee)->lire();
        $m = $this->c->reglages($portee)->lireMarque();
        $acomptes = implode(',', $r->paiementAcomptes);
        $proposes = array_map(static fn (array $a): string => implode(',', $a), Reglages::ACOMPTES_PROPOSES);
        return ['entreprise' => $r->entreprise, 'adresse' => $r->adresse, 'telephone' => $r->telephone, 'courriel_contact' => $r->courrielContact,
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
    }

    private function portee(Request $requete): Portee
    {
        return new Portee($requete->attributes->getInt('_artisan'));
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
            return new RedirectResponse(self::retourDuLogo($requete), 303);
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
            return new RedirectResponse(self::retourDuLogo($requete), 303);
        }
        chmod("$dossier/$nom", 0600);
        $ancien = $this->c->reglages($portee)->lireMarque()->logo;
        $this->c->reglages($portee)->changerLogo($nom, $logos->couleurDominante("$dossier/$nom"));
        $this->effacer($ancien);
        Flash::dire($requete, 'info', 'Logo enregistré');
        return new RedirectResponse(self::retourDuLogo($requete), 303);
    }

    public function retirerLogo(Request $requete): RedirectResponse
    {
        $portee = new Portee($requete->attributes->getInt('_artisan'));
        $ancien = $this->c->reglages($portee)->lireMarque()->logo;
        $this->c->reglages($portee)->changerLogo(null, null);
        $this->effacer($ancien);
        Flash::dire($requete, 'info', 'Logo retiré');
        return new RedirectResponse(self::retourDuLogo($requete), 303);
    }

    /** Seules deux adresses : la rubrique Marque, ou l'étape Marque de l'assistant. */
    private static function retourDuLogo(Request $requete): string
    {
        return $requete->request->getString('retour') === '/bienvenue/marque' ? '/bienvenue/marque' : '/reglages/marque';
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
     * La rubrique (ou l'étape de l'assistant) avec ses champs.
     *
     * @param list<string> $sections rubriques montrées (_reglages_<rubrique>.html.twig)
     * @param array<string, string> $champs
     * @param array<string, string> $erreurs
     * @param string|null $etape l'étape de l'assistant, null dans les réglages
     */
    private function page(Portee $portee, array $sections, string $titre, array $champs, bool $arrondi, array $erreurs, int $code, string $retour, ?string $etape): Response
    {
        $marque = $this->c->reglages($portee)->lireMarque();
        $assistant = null;
        if ($etape !== null) {
            $etapes = array_keys(self::ETAPES);
            $assistant = ['etapes' => $etapes, 'numero' => (int) array_search($etape, $etapes, true) + 1];
        }
        return new Response($this->c->twig()->render('reglages_rubrique.html.twig', [
            'sections' => $sections,
            'titre' => $titre,
            'retour' => $retour,
            'assistant' => $assistant,
            'champs' => $champs,
            'arrondi' => $arrondi,
            'erreurs' => $erreurs,
            'validites' => Reglages::VALIDITES_PROPOSEES,
            'types' => Reglages::TYPES_PAIEMENT,
            'delais' => Reglages::DELAIS_PROPOSES,
            'acomptes' => array_map(static fn (array $a): string => implode(',', $a), Reglages::ACOMPTES_PROPOSES),
            'phrase' => $this->c->reglages($portee)->lire()->conditionsPaiement(),
            'marque' => $marque,
            'teintes' => self::teintes($marque),
            'modeles' => Marque::MODELES,
        ]), $code);
    }
}
