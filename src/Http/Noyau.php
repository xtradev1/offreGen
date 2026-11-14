<?php

declare(strict_types=1);

namespace OffreGen\Http;

use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
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

    /** @var \Closure(string): void */
    private \Closure $journal;

    /**
     * @param list<Route> $routes
     * @param (\Closure(string): void)|null $journal reçoit les erreurs ; par défaut, error_log
     */
    public function __construct(array $routes, private readonly Environment $twig, private readonly Garde $garde, ?\Closure $journal = null)
    {
        $this->journal = $journal ?? static function (string $message): void {
            error_log($message);
        };
        $this->collection = new RouteCollection();
        foreach ($routes as $route) {
            if (isset($this->routes[$route->nom])) {
                throw new \LogicException("Route déclarée deux fois : {$route->nom}");
            }
            $this->routes[$route->nom] = $route;
            $this->collection->add($route->nom, new RouteSymfony($route->chemin, methods: $route->verbes));
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
        $reponse = $this->repondre($requete);
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
            return $this->erreur(405, 'Action non permise');
        }

        $route = $this->routes[(string) $parametres['_route']];
        if ($route->droit !== Route::PUBLIC && $route->droit !== Route::ARTISAN) {
            return $this->erreur(403, 'Accès refusé');
        }

        $this->garde->entrer($requete);
        try {
            if ($route->droit === Route::ARTISAN) {
                $artisan = $this->garde->artisan($requete);
                if ($artisan === null) {
                    return new RedirectResponse('/connexion', 303);
                }
                $requete->attributes->set('_artisan', $artisan);
            }
            if ($requete->isMethod('POST')) {
                if ($this->corpsPerdu($requete)) {
                    return $this->erreur(413, 'Fichier trop gros');
                }
                if (!$this->garde->jetonValide($requete)) {
                    return $this->erreur(403, 'Formulaire expiré, rechargez la page');
                }
            }

            unset($parametres['_route']);
            /** @var array<string, string> $parametres */
            return ($route->action)($requete, $parametres);
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
