<?php

declare(strict_types=1);

namespace OffreGen\Http;

use OffreGen\Compte\Authentification;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\FlashBagAwareSessionInterface;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\SessionStorageInterface;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

/**
 * Ce que le noyau contrôle avant tout contrôleur : la session, le jeton CSRF des POST, l'artisan connecté.
 */
final class Garde
{
    public const JETON = 'offregen';

    /** @param \Closure(): SessionStorageInterface $stockage */
    public function __construct(
        private readonly Authentification $authentification,
        private readonly CsrfTokenManagerInterface $csrf,
        private readonly RequestStack $pile,
        private readonly \Closure $stockage,
    ) {
    }

    public function entrer(Request $requete): void
    {
        if (!$requete->hasSession()) {
            $requete->setSession(new Session(($this->stockage)()));
        }
        $this->pile->push($requete);
    }

    public function sortir(): void
    {
        $this->pile->pop();
    }

    public function jetonValide(Request $requete): bool
    {
        $valeur = $requete->request->get('_csrf');
        return is_string($valeur) && $valeur !== '' && $this->csrf->isTokenValid(new CsrfToken(self::JETON, $valeur));
    }

    /** Valeur du jeton à mettre dans chaque formulaire (fonction Twig csrf()). */
    public function jeton(): string
    {
        return $this->csrf->getToken(self::JETON)->getValue();
    }

    /**
     * Les messages laissés par la requête précédente (Flash::dire), lus une seule fois.
     *
     * @return array<string, list<mixed>>
     */
    public function messages(): array
    {
        $session = $this->pile->getCurrentRequest()?->getSession();
        /** @var array<string, list<mixed>> */
        return $session instanceof FlashBagAwareSessionInterface ? $session->getFlashBag()->all() : [];
    }

    public function artisan(Request $requete): ?int
    {
        return $this->authentification->artisanConnecte($requete);
    }
}
