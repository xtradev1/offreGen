<?php

declare(strict_types=1);

namespace OffreGen\Http\Controleur;

use OffreGen\Compte\ArtisansEquipe;
use OffreGen\Compte\Equipe;
use OffreGen\Compte\Role;
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
 */
final class AdminControleur
{
    public function __construct(private readonly Conteneur $c)
    {
    }

    public function accueil(Request $requete): Response
    {
        return new RedirectResponse($this->role()->accueil(), 303);
    }

    /** Une page de l'ossature : son titre, son menu, et rien d'autre tant que son lot n'est pas livré. */
    public function ossature(string $actif, string $titre): Response
    {
        return $this->page('admin/page.html.twig', ['actif' => $actif, 'titre' => $titre]);
    }

    public function plus(): Response
    {
        return $this->page('admin/plus.html.twig', ['actif' => 'plus', 'titre' => 'Plus']);
    }

    public function artisans(Request $requete): Response
    {
        $type = $requete->query->getString('type');
        $type = in_array($type, ['demos', 'comptes'], true) ? $type : 'tous';
        $q = mb_substr(trim($requete->query->getString('q')), 0, 80);
        return $this->page('admin/artisans.html.twig', [
            'actif' => 'artisans',
            'titre' => $this->role() === Role::Commercial ? 'Mes artisans' : 'Artisans',
            'type' => $type,
            'q' => $q,
            'comptes' => $this->c->depotEquipe()->artisans($this->portee(), $type, $q),
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

    public function equipe(): Response
    {
        return $this->page('admin/equipe.html.twig', ['actif' => 'equipe', 'titre' => 'Équipe', 'membres' => $this->c->depotEquipe()->membres()]);
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
    public function membre(array $p): Response
    {
        $membre = $this->membreModifiable((int) $p['id']);
        return $this->page('admin/membre.html.twig', ['actif' => 'equipe', 'titre' => $membre['entreprise'], 'membre' => $membre]);
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
     * @return array{id: int, courriel: string, entreprise: string, role: string, offres_max: int|null, artisans: int}
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

    /** @param array<string, mixed> $vars */
    private function page(string $gabarit, array $vars, int $code = 200): Response
    {
        $reponse = new Response($this->c->twig()->render($gabarit, $vars + ['role' => $this->role()]), $code);
        // Des comptes, des liens d'accès, des numéros de téléphone : rien ne reste dans le cache du navigateur.
        $reponse->headers->set('Cache-Control', 'no-store');
        return $reponse;
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
