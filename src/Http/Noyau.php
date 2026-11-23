<?php

declare(strict_types=1);

namespace OffreGen\Http;

use OffreGen\Compte\Role;
use Symfony\Component\HttpFoundation\Exception\SuspiciousOperationException;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Flash\FlashBagInterface;
use Symfony\Component\HttpFoundation\Session\FlashBagAwareSessionInterface;
use Symfony\Component\Routing\Exception\MethodNotAllowedException;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;
use Symfony\Component\Routing\Matcher\UrlMatcher;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\Route as RouteSymfony;
use Symfony\Component\Routing\RouteCollection;
use Twig\Environment;

/**
 * Reçoit chaque requête : trouve la route, applique son droit, contrôle la session et le jeton CSRF,
 * puis appelle son action. Une route sans droit déclaré répond 403 : le refus est la valeur par défaut.
 */
final class Noyau
{
    /** @var array<string, Route> */
    private array $routes = [];

    private RouteCollection $collection;

    /** Les mêmes routes sans celles d'administration : sert à ne pas trahir une page d'administration par un 405. */
    private RouteCollection $collectionHorsAdmin;

    /** @var \Closure(string): void */
    private \Closure $journal;

    /**
     * @param list<Route> $routes
     * @param (\Closure(string): void)|null $journal reçoit les erreurs ; par défaut, error_log
     * @param (\Closure(int): \OffreGen\Donnees\DepotEnvois)|null $envois les clés des gestes d'un artisan (lot 4)
     * @param (\Closure(): \DateTimeImmutable)|null $horloge
     */
    public function __construct(
        array $routes,
        private readonly Environment $twig,
        private readonly Garde $garde,
        ?\Closure $journal = null,
        private readonly ?\Closure $envois = null,
        private readonly ?\Closure $horloge = null,
    ) {
        $this->journal = $journal ?? static function (string $message): void {
            error_log($message);
        };
        $this->collection = new RouteCollection();
        $this->collectionHorsAdmin = new RouteCollection();
        foreach ($routes as $route) {
            if (isset($this->routes[$route->nom])) {
                throw new \LogicException("Route déclarée deux fois : {$route->nom}");
            }
            $this->routes[$route->nom] = $route;
            $this->collection->add($route->nom, new RouteSymfony($route->chemin, methods: $route->verbes));
            if (!in_array($route->droit, Route::RESERVES, true)) {
                $this->collectionHorsAdmin->add($route->nom, new RouteSymfony($route->chemin, methods: $route->verbes));
            }
        }
    }

    /**
     * En-têtes de sécurité posés sur chaque réponse. Chez l'hébergeur, public/.htaccess pose les mêmes
     * et les remplace ; ceux-ci servent au serveur local et de secours si le .htaccess n'est pas appliqué.
     * tests/Http/EntetesTest.php vérifie que les deux copies restent identiques.
     */
    public const ENTETES = [
        'X-Content-Type-Options' => 'nosniff',
        'X-Frame-Options' => 'SAMEORIGIN',
        'Referrer-Policy' => 'strict-origin-when-cross-origin',
        'Permissions-Policy' => 'camera=(), microphone=(self), geolocation=()',
        'Content-Security-Policy' => "default-src 'self'; base-uri 'self'; form-action 'self'; frame-ancestors 'self'; object-src 'none'",
    ];

    public function traiter(Request $requete): Response
    {
        try {
            $reponse = $this->repondre($requete);
        } catch (SuspiciousOperationException) {
            // En-tête piégé (méthode substituée invalide, hôte faux) : refusé, sans erreur 500 (revue du 2026-11-18, B1).
            $reponse = $this->erreur(400, 'Requête invalide');
        }
        foreach (self::ENTETES as $nom => $valeur) {
            $reponse->headers->set($nom, $valeur);
        }
        if ($requete->isSecure()) {
            $reponse->headers->set('Strict-Transport-Security', 'max-age=31536000');
        }
        return $reponse;
    }

    private function repondre(Request $requete): Response
    {
        try {
            $matcher = new UrlMatcher($this->collection, (new RequestContext())->fromRequest($requete));
            $parametres = $matcher->match($requete->getPathInfo());
        } catch (ResourceNotFoundException) {
            return $this->erreur(404, 'Page introuvable');
        } catch (MethodNotAllowedException) {
            // Un 405 dirait qu'une page existe à cette adresse : pour une page d'administration, 404 comme ailleurs.
            return $this->existeHorsAdmin($requete) ? $this->erreur(405, 'Action non permise') : $this->erreur(404, 'Page introuvable');
        }

        $route = $this->routes[(string) $parametres['_route']];
        if (!in_array($route->droit, [Route::PUBLIC, Route::ARTISAN, ...Route::RESERVES], true)) {
            return $this->erreur(403, 'Accès refusé');
        }

        $this->garde->entrer($requete);
        try {
            // Un envoi par morceaux (Http\Morceaux) attend une réponse qu'il peut lire : pas de renvoi à la connexion.
            // En GET, il relit des morceaux de la page sans rien écrire (après un refus, au retour du réseau).
            $morceaux = $requete->isMethod('POST') || $requete->isMethod('GET') ? Morceaux::demandes($requete) : null;
            if ($route->droit === Route::ARTISAN) {
                $artisan = $this->garde->artisan($requete);
                if ($artisan === null && $morceaux !== null) {
                    // Constaté le 2026-11-23 : la quantité tapée après la session perdue restait sur « Enregistrement… ».
                    return self::refusMorceaux(401, Morceaux::SESSION, 'perdue', 'Session expirée : reconnectez-vous.');
                }
                if ($artisan === null) {
                    return $route->visiteur !== null ? ($route->visiteur)($requete) : new RedirectResponse('/connexion', 303);
                }
                $requete->attributes->set('_artisan', $artisan);
            }
            if (in_array($route->droit, Route::RESERVES, true)) {
                // 404 et non 403 ni renvoi à la connexion : la page n'existe pour personne d'autre.
                $artisan = $this->garde->artisan($requete);
                $role = $artisan === null ? null : $this->garde->role($artisan);
                if ($artisan === null || $role === null || !self::admis($role, $route->droit)) {
                    return $this->erreur(404, 'Page introuvable');
                }
                $requete->attributes->set('_artisan', $artisan);
                $requete->attributes->set('_role', $role);
            }
            if ($requete->isMethod('POST')) {
                if ($this->corpsPerdu($requete)) {
                    return $this->erreur(413, 'Fichier trop gros');
                }
                if (!$this->garde->jetonValide($requete)) {
                    return $morceaux !== null
                        ? self::refusMorceaux(403, Morceaux::JETON, 'refuse', 'Formulaire expiré, rechargez la page.')
                        : $this->erreur(403, 'Formulaire expiré, rechargez la page');
                }
            }

            // Un geste fait dans la page d'un autre compte (même téléphone, autre session depuis) ne part pas dans celui-ci
            // (revue du 2026-11-23, sécurité I1) : le téléphone le garde pour son compte.
            $compte = $requete->headers->get(Morceaux::COMPTE);
            if ($compte !== null && $requete->isMethod('POST') && $requete->attributes->has('_artisan') && $compte !== (string) $requete->attributes->get('_artisan')) {
                return self::refusMorceaux(409, Morceaux::COMPTE_AUTRE, 'autre', 'Ce geste a été fait par un autre compte.');
            }

            unset($parametres['_route']);
            /** @var array<string, string> $parametres */
            $reponse = $this->executer($route, $requete, $parametres);
            if ($morceaux !== null && $reponse instanceof RedirectResponse) {
                // Une relecture de morceaux (GET) qui redirige mène à un autre écran (brouillon émis entre-temps).
                if ($requete->isMethod('GET') || Morceaux::ailleurs($requete, $reponse->getTargetUrl())) {
                    return Morceaux::aller($reponse->getTargetUrl());
                }
                // Aucun morceau demandé (relecture, geste rejoué d'un autre écran) : la page n'est pas rendue pour rien, ses
                // messages restent pour la page suivante (revue du 2026-11-23 : coût de la relecture, messages perdus).
                if ($morceaux === []) {
                    return Morceaux::reponse('', $reponse->getTargetUrl());
                }
                return $this->decouper($requete, $reponse->getTargetUrl(), $morceaux);
            }
            if ($morceaux !== null && $requete->isMethod('GET') && $reponse->getStatusCode() === 200
                && str_contains((string) $reponse->headers->get('Content-Type', 'text/html'), 'html')) {
                return Morceaux::reponse(Morceaux::extraire((string) $reponse->getContent(), $morceaux), $requete->getRequestUri());
            }
            return $reponse;
        } catch (PageIntrouvable) {
            return $this->erreur(404, 'Page introuvable');
        } catch (Conflit $e) {
            return $this->erreur(409, $e->getMessage());
        } catch (\Throwable $e) {
            ($this->journal)('offreGen, erreur sur ' . $route->nom . ' : ' . $e->getMessage());
            return $this->erreur(500, 'Erreur du serveur');
        } finally {
            $this->garde->sortir();
        }
    }

    /**
     * L'action de la route. Un envoi d'un artisan avec sa clé (X-Cle-Envoi, chantier sans rechargement, lot 4) n'est fait
     * qu'une fois : rejoué (réponse perdue), il reçoit la redirection d'alors et les messages d'alors (« Annuler », un
     * refus), sans rien refaire ; encore en cours, un 409 qu'il réessaiera ; réservé puis abandonné (processus tué), il
     * repart. Une action qui ne mène nulle part (refus, erreur) libère la clé.
     *
     * @param array<string, string> $parametres
     */
    private function executer(Route $route, Request $requete, array $parametres): Response
    {
        $artisan = $requete->attributes->get('_artisan');
        $cle = $requete->isMethod('POST') && is_int($artisan) && $this->envois !== null && $this->horloge !== null ? Morceaux::cle($requete) : null;
        if ($cle === null) {
            return ($route->action)($requete, $parametres);
        }
        $envois = ($this->envois)($artisan);
        $maintenant = ($this->horloge)();
        $deja = $envois->deja($cle, $maintenant);
        if ($deja !== null && $deja['adresse'] !== '') {
            $sac = self::messagesDe($requete);
            foreach ($deja['messages'] as $genre => $liste) {
                foreach ($liste as $message) {
                    $sac?->add($genre, $message);
                }
            }
            return new RedirectResponse($deja['adresse'], 303);
        }
        if ($deja !== null || !$envois->reserver($cle, $maintenant)) {
            throw new Conflit(Morceaux::EN_COURS);
        }
        try {
            $reponse = ($route->action)($requete, $parametres);
        } catch (\Throwable $e) {
            $envois->liberer($cle);
            throw $e;
        }
        try {
            if ($reponse instanceof RedirectResponse) {
                $envois->noter($cle, $reponse->getTargetUrl(), self::messagesDe($requete)?->peekAll() ?? []);
            } else {
                $envois->liberer($cle);
            }
        } catch (\Throwable $e) {
            // L'action est faite : la réponse part. La clé restée vide sera libérée comme abandonnée (DepotEnvois).
            ($this->journal)('offreGen, clé d\'envoi non notée : ' . $e->getMessage());
        }
        return $reponse;
    }

    private static function messagesDe(Request $requete): ?FlashBagInterface
    {
        $session = $requete->hasSession() ? $requete->getSession() : null;
        return $session instanceof FlashBagAwareSessionInterface ? $session->getFlashBag() : null;
    }

    /**
     * La page où menait la redirection (ou celle de X-Morceaux-Page), rendue par une requête interne avec la même
     * session, puis découpée. Une page qui renvoie encore ailleurs (assistant du premier lancement, fin de démo) ou ne
     * se rend pas : le navigateur y va lui-même.
     *
     * @param list<string> $ids
     */
    private function decouper(Request $requete, string $cible, array $ids): Response
    {
        $chemin = Morceaux::page($requete, $cible);
        if ($chemin === null) {
            return Morceaux::aller('/');
        }
        $serveur = array_filter($requete->server->all(), static fn (string $cle): bool => !in_array($cle, ['CONTENT_TYPE', 'CONTENT_LENGTH', 'REQUEST_URI', 'QUERY_STRING'], true)
            && !str_starts_with($cle, 'HTTP_X_MORCEAUX') && !str_starts_with($cle, 'HTTP_X_CLE') && $cle !== 'HTTP_X_COMPTE' && !str_starts_with($cle, 'HTTP_CONTENT_'), ARRAY_FILTER_USE_KEY);
        $interne = Request::create($chemin, 'GET', [], $requete->cookies->all(), [], $serveur);
        $interne->setSession($requete->getSession());
        $page = $this->repondre($interne);
        $type = (string) $page->headers->get('Content-Type', 'text/html');
        if ($page->getStatusCode() !== 200 || !str_contains($type, 'html')) {
            $ailleurs = $page->isRedirection() ? (string) $page->headers->get('Location', $cible) : $cible;
            return Morceaux::aller(Morceaux::local($ailleurs) ? $ailleurs : $cible);
        }
        return Morceaux::reponse(Morceaux::extraire((string) $page->getContent(), $ids), $cible);
    }

    private static function refusMorceaux(int $code, string $entete, string $valeur, string $message): Response
    {
        return new Response($message, $code, ['Content-Type' => 'text/plain; charset=UTF-8', 'Cache-Control' => 'no-store', $entete => $valeur]);
    }

    /** Qui passe une page réservée : l'administrateur partout, le modérateur sauf l'argent et l'équipe, le commercial le reste. */
    private static function admis(Role $role, string $droit): bool
    {
        return match ($droit) {
            Route::ADMIN => $role === Role::Administrateur,
            Route::MODERATION => $role !== Role::Commercial,
            default => true,
        };
    }

    private function existeHorsAdmin(Request $requete): bool
    {
        try {
            (new UrlMatcher($this->collectionHorsAdmin, (new RequestContext())->fromRequest($requete)))->match($requete->getPathInfo());
            return true;
        } catch (MethodNotAllowedException) {
            return true;
        } catch (ResourceNotFoundException) {
            return false;
        }
    }

    public function erreur(int $code, string $titre): Response
    {
        return new Response(
            $this->twig->render('erreur.html.twig', ['code' => $code, 'titre' => $titre]),
            $code,
        );
    }

    /** @return array<string, Route> */
    public function routes(): array
    {
        return $this->routes;
    }

    /**
     * Au-delà de post_max_size, PHP jette le corps entier : plus de fichier, plus de jeton.
     * Sans ce contrôle, l'artisan lirait « formulaire expiré » au lieu de « fichier trop gros ».
     */
    private function corpsPerdu(Request $requete): bool
    {
        $longueur = (int) $requete->server->get('CONTENT_LENGTH', 0);
        return $longueur > 0 && $requete->request->count() === 0 && $requete->files->count() === 0;
    }
}
