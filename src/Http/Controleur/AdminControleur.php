<?php

declare(strict_types=1);

namespace OffreGen\Http\Controleur;

use OffreGen\Catalogue\CatalogueFictif;
use OffreGen\Compte\Activite;
use OffreGen\Compte\ArtisansEquipe;
use OffreGen\Compte\Demos;
use OffreGen\Compte\Equipe;
use OffreGen\Compte\LiensAcces;
use OffreGen\Compte\QrLien;
use OffreGen\Compte\Role;
use OffreGen\Donnees\Dates;
use OffreGen\Donnees\DepotEquipe;
use OffreGen\Donnees\PorteeEquipe;
use OffreGen\Http\Conteneur;
use OffreGen\Http\Flash;
use OffreGen\Http\PageIntrouvable;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * La partie administration (G1, Xavier, 2026-11-17 ; rôles et menus du 2026-11-19) : sa mise en page, son menu par
 * rôle. Le noyau a déjà vérifié le rôle : ici, chaque page n'a plus qu'à le lire.
 *
 * @phpstan-import-type Membre from DepotEquipe
 * @phpstan-import-type Compte from DepotEquipe
 */
final class AdminControleur
{
    /** La session suit le guide du commercial, de la bienvenue au retour de sa démo d'entraînement (lot 8). */
    public const GUIDE_EQUIPE = 'guide_equipe';
    /** La démo d'entraînement du guide reste à créer : à usage unique (entrainementACreer). */
    public const ENTRAINEMENT_A_CREER = 'entrainement_a_creer';
    /** Les trois écrans de bienvenue d'un commercial (O11 de Xavier). */
    public const ECRANS_BIENVENUE = 3;

    public function __construct(private readonly Conteneur $c)
    {
    }

    public function accueil(Request $requete): Response
    {
        // Un commercial qui arrive : la bienvenue d'abord, une seule fois (chantier onboarding, lot 8).
        if ($this->role() === Role::Commercial && $this->c->depotEquipe()->bienvenueAMontrer($this->acteur())) {
            return new RedirectResponse('/admin/bienvenue', 303);
        }
        return new RedirectResponse($this->role()->accueil(), 303);
    }

    /** Un des trois écrans de bienvenue (O11) ; ?ecran=1 à 3. Toujours rouvrable depuis l'aide à la vente. */
    public function bienvenue(Request $requete): Response
    {
        $ecran = max(1, min(self::ECRANS_BIENVENUE, $requete->query->getInt('ecran', 1)));
        $nom = (string) ($this->c->artisans()->parId($this->acteur())['entreprise'] ?? '');
        return $this->page('admin/bienvenue.html.twig', [
            'actif' => 'suivi',
            'titre' => 'Bienvenue',
            'ecran' => $ecran,
            'total' => self::ECRANS_BIENVENUE,
            'prenom' => explode(' ', trim($nom))[0],
            'jours' => Demos::JOURS,
            'offres' => Demos::OFFRES,
        ]);
    }

    /**
     * La bienvenue est vue. « Faire ma démo d'entraînement » lance le guide (suite=guide) ; « Plus tard » ramène à la
     * page du rôle.
     */
    public function bienvenueVue(Request $requete): RedirectResponse
    {
        $this->c->depotEquipe()->bienvenueVue($this->acteur(), \DateTimeImmutable::createFromInterface($this->c->horloge()->now()));
        if ($requete->request->getString('suite') === 'guide') {
            $requete->getSession()->set(self::GUIDE_EQUIPE, true);
            $requete->getSession()->set(self::ENTRAINEMENT_A_CREER, true);
            return new RedirectResponse('/admin/suivi?guide=depart', 303);
        }
        $requete->getSession()->remove(self::GUIDE_EQUIPE);
        $requete->getSession()->remove(self::ENTRAINEMENT_A_CREER);
        return new RedirectResponse($this->role()->accueil(), 303);
    }

    /** « Quitter le guide » dans l'administration : le guide du commercial s'arrête. */
    public function guideFini(Request $requete): Response
    {
        $requete->getSession()->remove(self::GUIDE_EQUIPE);
        $requete->getSession()->remove(self::ENTRAINEMENT_A_CREER);
        return new Response('', 204);
    }

    /** Le guide du commercial est-il en cours dans cette session ? */
    private function guideEquipe(): bool
    {
        return $this->c->garde()->requeteCourante()?->getSession()->get(self::GUIDE_EQUIPE) === true;
    }

    /**
     * La démo d'entraînement reste à créer (revue du 2026-11-22) : marque à usage unique, posée par « Faire ma démo
     * d'entraînement » et consommée à sa création. Sans elle, une démo suivante, pour un vrai prospect, serait devenue
     * une démo d'entraînement sans que rien ne le montre.
     */
    private function entrainementACreer(): bool
    {
        return $this->c->garde()->requeteCourante()?->getSession()->get(self::ENTRAINEMENT_A_CREER) === true;
    }

    /** Une page de l'ossature : son titre, son menu, et rien d'autre tant que son lot n'est pas livré. */
    public function ossature(string $actif, string $titre): Response
    {
        return $this->page('admin/page.html.twig', ['actif' => $actif, 'titre' => $titre]);
    }

    public function plus(): Response
    {
        return $this->page('admin/plus.html.twig', [
            'actif' => 'plus',
            'titre' => 'Plus',
            'nom_affiche' => $this->c->depotEquipe()->nomAffiche($this->acteur()) ?? '',
            'nom_compte' => \OffreGen\Donnees\DepotEquipe::prenom($this->c->depotEquipe()->nomDuCompte($this->acteur()) ?? ''),
        ]);
    }

    public function plusNom(Request $requete): Response
    {
        // Le nom qui signe le courriel d'une démo (M3 de Xavier, 2026-11-23). Vide : le nom du compte.
        $nom = mb_substr(trim($requete->request->getString('nom')), 0, 120);
        $this->c->depotEquipe()->changerNomAffiche($this->acteur(), $nom === '' ? null : $nom);
        Flash::dire($requete, 'info', 'Enregistré.');
        return new RedirectResponse('/admin/plus', 303);
    }

    /** Le message qui accompagne le lien d'une démo ; repris par l'aide à la vente, lien en clair. */
    public static function messageDemo(string $application, string $lien, string $premiere = 'visite'): string
    {
        return "Bonjour, voici votre démo $application\u{00A0}: $lien\nLe lien est valable 24 heures et sert une seule fois. Ouvrez-le sur votre téléphone, indiquez votre courriel et dictez votre première $premiere.";
    }

    /**
     * Le suivi (chantier onboarding, lot 7 ; O10 et O19 de Xavier) : les démos encore ouvertes et leur activité, puis
     * les artisans amenés par le membre. « Pas encore dicté » : rien dicté 48 heures après l'ouverture de la démo.
     */
    public function suivi(): Response
    {
        $maintenant = \DateTimeImmutable::createFromInterface($this->c->horloge()->now());
        $demos = [];
        foreach ($this->c->depotEquipe()->suivi($this->portee(), $maintenant) as $d) {
            $ouverte = $d['ouverte_le'] === null ? null : Dates::depuisBase($d['ouverte_le']);
            $demos[] = $d + [
                // Jours de calendrier, comme le ruban et la fenêtre de la démo (revue du 2026-11-22) ; 0 : le dernier jour.
                'jours' => Demos::joursAvantLaFin($d['demo_fin'], $maintenant),
                'pas_encore_dicte' => $d['dictees'] === 0 && $ouverte !== null && $maintenant->getTimestamp() - $ouverte->getTimestamp() >= 48 * 3600,
                'metier_nom' => self::metier($d),
            ];
        }
        // Le guide du commercial : son départ (?guide=depart) et sa fin, au retour de la démo d'entraînement (?guide=fin).
        $requete = $this->c->garde()->requeteCourante();
        $finGuide = $requete !== null && $requete->query->getString('guide') === 'fin' && $this->guideEquipe();
        if ($finGuide) {
            $requete->getSession()->remove(self::GUIDE_EQUIPE);
        }
        $mois = new \IntlDateFormatter('fr_CH', \IntlDateFormatter::NONE, \IntlDateFormatter::NONE, 'Europe/Zurich', null, 'LLLL');
        return $this->page('admin/suivi.html.twig', [
            'actif' => 'suivi',
            'titre' => $this->role() === Role::Commercial ? 'Mon suivi' : 'Suivi',
            'mois' => mb_convert_case((string) $mois->format($maintenant), MB_CASE_TITLE),
            'demos' => $demos,
            'chiffres' => $this->c->depotEquipe()->chiffres($this->acteur()),
            'guide' => $this->entrainementACreer(),
            'fin_guide' => $finGuide,
        ]);
    }

    /** L'aide à la vente (chantier onboarding, lot 6) : la même pour les trois rôles de l'équipe. */
    public function aideVente(): Response
    {
        return $this->page('admin/aide_vente.html.twig', [
            'actif' => 'plus',
            'titre' => $this->c->textes()->texte('k.titre'),
            'jours' => Demos::JOURS,
            'offres' => Demos::OFFRES,
            'message' => self::messageDemo($this->c->nomApplication(), '[le lien de la démo]'),
        ]);
    }

    public function artisans(Request $requete): Response
    {
        $type = $requete->query->getString('type');
        $type = in_array($type, ['demos', 'essais', 'comptes'], true) ? $type : 'tous';
        $q = mb_substr(trim($requete->query->getString('q')), 0, 80);
        // « Ses artisans », depuis la fiche d'un membre : ceux qu'il a amenés. Le commercial reste dans les siens.
        $brut = $requete->query->getString('par');
        $par = $this->portee()->toutVoir && preg_match('/^[1-9]\d{0,8}$/', $brut) === 1 ? (int) $brut : null;
        $apporteur = $par === null ? null : $this->c->depotEquipe()->nomDuCompte($par);
        if ($apporteur === null) {
            $par = null;
        }
        return $this->page('admin/artisans.html.twig', [
            'actif' => 'artisans',
            'titre' => $this->role() === Role::Commercial ? 'Mes clients' : 'Clients',
            'type' => $type,
            'q' => $q,
            'par' => $par,
            'apporteur' => $apporteur,
            'metiers' => CatalogueFictif::libelles(),
            'comptes' => $this->c->depotEquipe()->artisans($this->portee(), $type, $q, $par),
            // Le guide du commercial passe par ici (E3 de Xavier, 2026-11-23).
            'guide' => $this->entrainementACreer(),
        ]);
    }

    /** @param array<string, string> $p */
    public function artisan(array $p, ?string $erreur = null, int $code = 200): Response
    {
        $compte = $this->c->depotEquipe()->artisan($this->portee(), (int) $p['id']) ?? throw new PageIntrouvable();
        return $this->page('admin/artisan.html.twig', [
            'actif' => 'artisans',
            'titre' => $compte['entreprise'],
            'compte' => $compte,
            'evenements' => $this->c->depotEquipe()->evenements($compte['id']),
            'ajouts' => ArtisansEquipe::ajoutsPossibles($this->role(), $compte),
            'metier' => self::metier($compte),
            'erreur' => $erreur,
        ], $code);
    }

    /** @param array<string, string> $p */
    public function artisanOffres(Request $requete, array $p): Response
    {
        $id = (int) $p['id'];
        // Hors de la portée, le compte n'existe pas : 404 avant tout autre contrôle.
        if ($this->c->depotEquipe()->artisan($this->portee(), $id) === null) {
            throw new PageIntrouvable();
        }
        $ajout = $requete->request->getInt('ajout');
        try {
            $this->c->artisansEquipe()->offrir($this->portee(), $this->role(), $id, $ajout);
        } catch (\InvalidArgumentException $e) {
            return $this->artisan($p, $e->getMessage(), 422);
        }
        Flash::dire($requete, 'info', "$ajout offres ajoutées.");
        return new RedirectResponse("/admin/artisans/$id", 303);
    }

    /** @param array{metier: string, metier_saisi: string, entreprise: string, courriel: string}|null $saisie */
    public function demoNouvelle(?array $saisie = null, ?string $erreur = null, int $code = 200): Response
    {
        return $this->page('admin/demo_nouvelle.html.twig', [
            'actif' => 'artisans',
            'titre' => 'Nouvelle démo',
            // Les métiers sous leur activité : Bâtiment, Extérieur, Prestations (Compte\Activite, 2026-11-22).
            'groupes' => Activite::grouper(CatalogueFictif::libelles()),
            'saisie' => $saisie ?? ['metier' => '', 'metier_saisi' => '', 'entreprise' => '', 'courriel' => ''],
            'erreur' => $erreur,
            // Le guide du commercial en cours : la démo créée sera sa démo d'entraînement (lot 8).
            'guide' => $this->entrainementACreer(),
        ], $code);
    }

    public function demoCreer(Request $requete): Response
    {
        $saisie = [
            'metier' => $requete->request->getString('metier'),
            'metier_saisi' => trim($requete->request->getString('metier_saisi')),
            'entreprise' => trim($requete->request->getString('entreprise')),
            'courriel' => trim($requete->request->getString('courriel')),
        ];
        // Une démo d'entraînement seulement quand le guide l'attend : le champ posté seul ne suffit pas (revue du 2026-11-22).
        $entrainement = $requete->request->getString('entrainement') === '1' && $this->entrainementACreer();
        try {
            $id = $this->demos()->creer($this->portee(), $this->role(), $saisie['metier'], $saisie['metier_saisi'], $saisie['entreprise'], $saisie['courriel'], $entrainement);
            if ($entrainement) {
                $requete->getSession()->remove(self::ENTRAINEMENT_A_CREER);
            }
        } catch (\OverflowException $e) {
            return $this->demoNouvelle($saisie, $e->getMessage(), 429);
        } catch (\InvalidArgumentException $e) {
            return $this->demoNouvelle($saisie, $e->getMessage(), 422);
        }
        return new RedirectResponse("/admin/artisans/$id/qr", 303);
    }

    /**
     * Le code QR d'une démo : chaque affichage émet un lien neuf, bon une heure et une seule fois.
     *
     * @param array<string, string> $p
     */
    public function demoQr(array $p): Response
    {
        $demo = $this->demo((int) $p['id']);
        $lien = $this->c->liensAcces()->emettreDemo($demo['id'], LiensAcces::DEMO_QR);
        // Sa démo d'entraînement : « Ouvrir la démo sur cet appareil » devient un formulaire POST (le lien en GET
        // n'écrit rien ; revue du 2026-11-22), et le guide y montre son étape 4.
        $entrainement = ($this->c->depotEquipe()->demo($demo['id'])['entrainement'] ?? 0) === 1;
        return $this->page('admin/demo_prete.html.twig', ['actif' => 'artisans', 'titre' => $demo['entreprise'], 'compte' => $demo, 'metier' => self::metier($demo), 'lien' => $lien, 'qr' => QrLien::svg($lien),
            'entrainement' => $entrainement, 'lien_chemin' => (string) parse_url($lien, PHP_URL_PATH), 'guide' => $entrainement && $this->guideEquipe()]);
    }

    /**
     * Le message à envoyer au prospect, avec un lien bon 24 heures et une seule fois.
     *
     * @param array<string, string> $p
     */
    public function demoMessage(array $p): Response
    {
        $demo = $this->demo((int) $p['id']);
        $lien = $this->c->liensAcces()->emettreDemo($demo['id'], LiensAcces::DEMO_MSG);
        // « première visite » ou « première offre » : le prospect lit ce message, dans les mots de l'activité de son métier.
        $message = self::messageDemo($this->c->nomApplication(), $lien, Activite::duMetier($demo['metier'])->mots()['premiere']);
        return $this->page('admin/demo_message.html.twig', ['actif' => 'artisans', 'titre' => $demo['entreprise'], 'compte' => $demo, 'metier' => self::metier($demo), 'message' => $message]);
    }

    public function equipe(): Response
    {
        return $this->pageEquipe(null);
    }

    public function membreNouveau(): Response
    {
        return $this->formulaireMembre(['nom' => '', 'courriel' => '', 'role' => Role::Commercial->value, 'offres' => Equipe::OFFRES_PAR_DEFAUT], null);
    }

    public function membreCreer(Request $requete): Response
    {
        $saisie = [
            'nom' => trim($requete->request->getString('nom')),
            'courriel' => trim($requete->request->getString('courriel')),
            'role' => $requete->request->getString('role'),
            'offres' => $requete->request->getInt('offres'),
        ];
        try {
            $role = Role::tryFrom($saisie['role']) ?? throw new \InvalidArgumentException('Choisissez un rôle.');
            $resultat = $this->c->equipe()->inviter($this->acteur(), $saisie['nom'], $saisie['courriel'], $role, $saisie['offres']);
        } catch (\InvalidArgumentException $e) {
            return $this->formulaireMembre($saisie, $e->getMessage(), 422);
        }
        $membre = $this->c->depotEquipe()->membre($resultat['id']) ?? throw new \LogicException('Membre créé introuvable.');
        $lien = null;
        if ($resultat['nouveau']) {
            if ($this->c->messagerieReelle()) {
                $courriel = $membre['courriel'];
                $this->c->apresReponse()->ajouter(fn () => $this->c->liensAcces()->envoyerAcces($courriel));
            } else {
                // Ce site n'envoie pas de courriels : le lien se montre ici, une fois, pour être transmis (D6, 2026-11-19).
                $lien = $this->c->liensAcces()->lienDAcces($membre['id']);
            }
        }
        return $this->page('admin/membre_cree.html.twig', ['actif' => 'equipe', 'titre' => $membre['entreprise'], 'membre' => $membre, 'nouveau' => $resultat['nouveau'], 'lien' => $lien]);
    }

    /** @param array<string, string> $p */
    public function membre(array $p, ?string $erreur = null, int $code = 200): Response
    {
        return $this->pageEquipe($this->membreModifiable((int) $p['id']), $erreur, $code);
    }

    /** « Enregistrer » sur la fiche d'un membre : son rôle et les offres de son espace artisan.
     *
     * @param array<string, string> $p
     */
    public function membreEnregistrer(Request $requete, array $p): Response
    {
        $membre = $this->membreModifiable((int) $p['id']);
        $role = Role::tryFrom($requete->request->getString('role'));
        if ($role === null || $role === Role::Administrateur) {
            throw new PageIntrouvable();
        }
        $brut = trim($requete->request->getString('offres'));
        // Un nombre illisible vaut zéro : il sort des bornes et se refuse comme les autres.
        $offres = $brut === '' ? null : (preg_match('/^\d{1,4}$/', $brut) === 1 ? (int) $brut : 0);
        try {
            $this->c->equipe()->modifier($this->acteur(), $membre['id'], $role, $offres);
        } catch (\InvalidArgumentException $e) {
            return $this->membre($p, $e->getMessage(), 422);
        } catch (\DomainException) {
            throw new PageIntrouvable();
        }
        Flash::dire($requete, 'info', 'Enregistré.');
        return new RedirectResponse("/admin/equipe/{$membre['id']}", 303);
    }

    /**
     * « Renvoyer un lien d'accès » : par courriel si le site en envoie, sinon affiché une fois pour être transmis (D6).
     *
     * @param array<string, string> $p
     */
    public function membreAcces(Request $requete, array $p): Response
    {
        $membre = $this->membreModifiable((int) $p['id']);
        $this->c->equipe()->noterAccesRenvoye($this->acteur(), $membre['id']);
        if ($this->c->messagerieReelle()) {
            $courriel = $membre['courriel'];
            $this->c->apresReponse()->ajouter(fn () => $this->c->liensAcces()->envoyerAcces($courriel));
            Flash::dire($requete, 'info', "Lien d'accès envoyé à $courriel.");
            return new RedirectResponse("/admin/equipe/{$membre['id']}", 303);
        }
        return $this->page('admin/membre_cree.html.twig', ['actif' => 'equipe', 'titre' => $membre['entreprise'], 'membre' => $membre, 'nouveau' => true, 'lien' => $this->c->liensAcces()->lienDAcces($membre['id'])]);
    }

    /** @param array<string, string> $p */
    public function membreRole(Request $requete, array $p): Response
    {
        $membre = $this->membreModifiable((int) $p['id']);
        $voulu = $requete->request->getString('role');
        $role = $voulu === '' ? null : (Role::tryFrom($voulu) ?? throw new PageIntrouvable());
        try {
            $this->c->equipe()->changerRole($this->acteur(), $membre['id'], $role);
        } catch (\InvalidArgumentException) {
            throw new PageIntrouvable();
        }
        Flash::dire($requete, 'info', $role === null
            ? "{$membre['entreprise']} ne fait plus partie de l'équipe."
            : "{$membre['entreprise']} : {$role->libelle()}.");
        return new RedirectResponse('/admin/equipe', 303);
    }

    /**
     * Un membre que l'administrateur peut modifier : ni lui-même, ni un autre administrateur.
     *
     * @return Membre
     */
    private function membreModifiable(int $id): array
    {
        $membre = $this->c->depotEquipe()->membre($id);
        if ($membre === null || $membre['role'] === Role::Administrateur->value || $id === $this->acteur()) {
            throw new PageIntrouvable();
        }
        return $membre;
    }

    /** @param array{nom: string, courriel: string, role: string, offres: int} $saisie */
    private function formulaireMembre(array $saisie, ?string $erreur, int $code = 200): Response
    {
        return $this->page('admin/membre_nouveau.html.twig', ['actif' => 'equipe', 'titre' => 'Nouveau membre', 'saisie' => $saisie, 'erreur' => $erreur], $code);
    }

    /**
     * La liste de l'équipe, avec la fiche d'un membre quand il y en a un (à côté sur PC, sur son écran au téléphone).
     *
     * @param Membre|null $membre
     */
    private function pageEquipe(?array $membre, ?string $erreur = null, int $code = 200): Response
    {
        return $this->page('admin/equipe.html.twig', [
            'actif' => 'equipe',
            'titre' => $membre['entreprise'] ?? 'Équipe',
            'membres' => $this->c->depotEquipe()->membres(),
            'membre' => $membre,
            'moi' => $this->acteur(),
            'erreur' => $erreur,
        ], $code);
    }

    /** @param array<string, mixed> $vars */
    private function page(string $gabarit, array $vars, int $code = 200): Response
    {
        $reponse = new Response($this->c->twig()->render($gabarit, $vars + ['role' => $this->role()]), $code);
        // Des comptes, des liens d'accès, des numéros de téléphone : rien ne reste dans le cache du navigateur.
        $reponse->headers->set('Cache-Control', 'no-store');
        return $reponse;
    }

    /**
     * Une démo de la portée ; tout autre compte n'a ni code ni lien de démo.
     *
     * @return Compte
     */
    private function demo(int $id): array
    {
        $compte = $this->c->depotEquipe()->artisan($this->portee(), $id);
        // Une démonstration partagée (sans date de fin) ne s'ouvre pas par un lien : son mot de passe circule déjà.
        if ($compte === null || $compte['demo'] !== 1 || $compte['demo_fin'] === null) {
            throw new PageIntrouvable();
        }
        return $compte;
    }

    /**
     * Le métier d'un compte, tel qu'on le lit : le texte tapé pour « Autre métier », sinon le libellé de la liste.
     *
     * @param array{metier: string|null, metier_saisi: string|null} $compte
     */
    private static function metier(array $compte): ?string
    {
        if ($compte['metier_saisi'] !== null) {
            return $compte['metier_saisi'];
        }
        return $compte['metier'] === null || $compte['metier'] === 'autre' ? null : (CatalogueFictif::libelles()[$compte['metier']] ?? $compte['metier']);
    }

    /** Sans passer par le conteneur, qu'une autre session modifie en ce moment (2026-11-20). */
    private function demos(): Demos
    {
        return new Demos($this->c->base(), $this->c->comptes(), $this->c->artisans(), $this->c->depotEquipe(), $this->c->journalAdmin(), $this->c->horloge());
    }

    /** Le commercial n'atteint que les artisans qu'il a créés ; le modérateur et l'administrateur, tous. */
    private function portee(): PorteeEquipe
    {
        return new PorteeEquipe($this->acteur(), $this->role() !== Role::Commercial);
    }

    private function role(): Role
    {
        return $this->c->garde()->roleCourant() ?? throw new \LogicException("Route d'administration sans rôle.");
    }

    private function acteur(): int
    {
        return $this->c->garde()->artisanCourant() ?? throw new \LogicException("Route d'administration sans compte.");
    }
}
