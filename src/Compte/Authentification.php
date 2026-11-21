<?php

declare(strict_types=1);

namespace OffreGen\Compte;

use OffreGen\Donnees\DepotArtisans;
use OffreGen\Donnees\JournalConnexions;
use Psr\Clock\ClockInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PasswordHasher\PasswordHasherInterface;

/**
 * Connexion par courriel et mot de passe, gardée dans la session.
 * Après ESSAIS_MAX échecs en FENETRE_MINUTES (même courriel ou même adresse IP), plus aucun essai n'est vérifié.
 */
final class Authentification
{
    public const ESSAIS_MAX = 5;
    public const FENETRE_MINUTES = 15;
    private const CLE_SESSION = 'artisan_id';
    private const CLE_VERSION = 'version_session';

    /**
     * Hachage d'un mot de passe aléatoire jeté, aux paramètres des vrais : un courriel inconnu coûte
     * le même temps de vérification qu'un courriel connu, la durée de la réponse ne trahit pas les comptes.
     */
    private const HACHE_FACTICE = '$argon2id$v=19$m=65536,t=4,p=1$U1Z0UWkvdlFWd1lHZnM4cg$tTQEvQsxa297hBuIaVx4x9ek9OdJWOfMSVfHkv+qBUY';

    public function __construct(
        private readonly DepotArtisans $artisans,
        private readonly JournalConnexions $journal,
        private readonly PasswordHasherInterface $hacheur,
        private readonly ClockInterface $horloge,
    ) {
    }

    public function connecter(Request $requete, string $courriel, string $motDePasse): ResultatConnexion
    {
        $ip = $requete->getClientIp() ?? '0.0.0.0';
        $maintenant = $this->horloge->now();
        $depuis = $maintenant->modify('-' . self::FENETRE_MINUTES . ' minutes');

        // Un essai refusé pendant le blocage n'est pas noté (revue du 2026-11-18, M3) : noté comme un échec, il
        // prolongeait le blocage, et quiconque connaît le courriel d'un artisan pouvait le tenir dehors.
        if ($this->journal->echecsRecents($courriel, $ip, $depuis) >= self::ESSAIS_MAX) {
            return ResultatConnexion::TropDEssais;
        }

        $artisan = $this->artisans->parCourriel($courriel);
        $hache = $artisan['mot_de_passe'] ?? self::HACHE_FACTICE;
        // Vérifier d'abord, toujours : même coût que le compte existe ou non.
        $correct = $this->hacheur->verify($hache, $motDePasse);
        if (!$correct || $artisan === null) {
            $this->journal->noter($artisan['id'] ?? null, $courriel, $ip, false, $maintenant);
            return ResultatConnexion::Refusee;
        }

        if ($this->hacheur->needsRehash($hache)) {
            $this->artisans->changerMotDePasse($artisan['id'], $this->hacheur->hash($motDePasse));
        }
        $this->ouvrirSession($requete, $artisan['id'], $artisan['version_session']);
        $this->journal->noter($artisan['id'], $courriel, $ip, true, $maintenant);
        return ResultatConnexion::Reussie;
    }

    /**
     * Session neuve : une session posée avant par un tiers ne sert à rien, et rien de la session d'avant (message en
     * attente, jeton CSRF) ne passe au compte qui entre (revue du 2026-11-18, M5). Sert aussi après un lien d'accès.
     */
    public function ouvrirSession(Request $requete, int $artisanId, int $versionSession): void
    {
        $requete->getSession()->invalidate();
        $requete->getSession()->set(self::CLE_SESSION, $artisanId);
        $requete->getSession()->set(self::CLE_VERSION, $versionSession);
    }

    /**
     * Le mot de passe actuel, redemandé avant d'en changer. Freiné comme la connexion (revue du 2026-11-18) : avec une
     * session volée, il ne se devine pas par essais successifs, et chaque essai coûte un hachage.
     */
    public function verifierMotDePasseActuel(Request $requete, int $artisanId, string $motDePasse): ResultatConnexion
    {
        $artisan = $this->artisans->parId($artisanId);
        if ($artisan === null) {
            return ResultatConnexion::Refusee;
        }
        $ip = $requete->getClientIp() ?? '0.0.0.0';
        $maintenant = $this->horloge->now();
        if ($this->journal->echecsRecents($artisan['courriel'], $ip, $maintenant->modify('-' . self::FENETRE_MINUTES . ' minutes')) >= self::ESSAIS_MAX) {
            return ResultatConnexion::TropDEssais;
        }
        if (!$this->hacheur->verify($artisan['mot_de_passe'], $motDePasse)) {
            $this->journal->noter($artisanId, $artisan['courriel'], $ip, false, $maintenant);
            return ResultatConnexion::Refusee;
        }
        return ResultatConnexion::Reussie;
    }

    /**
     * Nouveau mot de passe choisi dans les réglages : cette session reste ouverte, les autres tombent (M6).
     * Refusé si le numéro de session a changé depuis l'ouverture de cette session (un lien d'oubli a servi).
     */
    public function remplacerMotDePasse(Request $requete, int $artisanId, string $nouveau): bool
    {
        $attendue = $requete->getSession()->get(self::CLE_VERSION, 0);
        $version = $this->artisans->remplacerMotDePasse($artisanId, $this->hacheur->hash($nouveau), is_int($attendue) ? $attendue : 0);
        if ($version === null) {
            return false;
        }
        $requete->getSession()->migrate(true);
        $requete->getSession()->set(self::CLE_VERSION, $version);
        return true;
    }

    public function deconnecter(Request $requete): void
    {
        $requete->getSession()->invalidate();
    }

    /** L'artisan de la session, s'il existe encore. */
    public function artisanConnecte(Request $requete): ?int
    {
        if (!$requete->hasSession()) {
            return null;
        }
        $id = $requete->getSession()->get(self::CLE_SESSION);
        if (!is_int($id)) {
            return null;
        }
        $artisan = $this->artisans->parId($id);
        // Sans numéro (session ouverte avant le 2026-11-18) : 0, celui de tout compte qui n'a jamais changé de mot de passe.
        $version = $requete->getSession()->get(self::CLE_VERSION, 0);
        if ($artisan === null || $version !== $artisan['version_session']) {
            $requete->getSession()->invalidate();
            return null;
        }
        return $id;
    }

    /** Relu en base à chaque requête : un droit retiré ferme les pages au clic suivant. */
    public function estAdministrateur(int $artisanId): bool
    {
        return ($this->artisans->parId($artisanId)['administrateur'] ?? 0) === 1;
    }

    /**
     * Le rôle d'équipe du compte, relu en base à chaque requête. Le droit administrateur ne vient que de sa colonne,
     * donnée par bin/administrateur : la colonne « role » ne porte que modérateur ou commercial.
     */
    public function role(int $artisanId): ?Role
    {
        $artisan = $this->artisans->parId($artisanId);
        if ($artisan === null) {
            return null;
        }
        if ($artisan['administrateur'] === 1) {
            return Role::Administrateur;
        }
        $role = Role::tryFrom((string) $artisan['role']);
        return $role === Role::Administrateur ? null : $role;
    }
}
