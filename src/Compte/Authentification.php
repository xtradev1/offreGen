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

        if ($this->journal->echecsRecents($courriel, $ip, $depuis) >= self::ESSAIS_MAX) {
            $this->journal->noter(null, $courriel, $ip, false, $maintenant);
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
        // Nouvel identifiant de session à la connexion : une session posée avant par un tiers ne sert à rien.
        $requete->getSession()->migrate(true);
        $requete->getSession()->set(self::CLE_SESSION, $artisan['id']);
        $this->journal->noter($artisan['id'], $courriel, $ip, true, $maintenant);
        return ResultatConnexion::Reussie;
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
        if ($this->artisans->parId($id) === null) {
            $requete->getSession()->invalidate();
            return null;
        }
        return $id;
    }
}
