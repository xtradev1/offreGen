<?php

declare(strict_types=1);

namespace OffreGen\Compte;

use OffreGen\Donnees\DepotArtisans;
use OffreGen\Donnees\DepotEquipe;
use Psr\Clock\ClockInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Le retour direct d'un membre de l'équipe dans son espace, depuis sa démo d'entraînement, sans retaper son mot de
 * passe (chantier onboarding, lot 8 ; O12 B de Xavier ; PLAN.md, décision fragile 3).
 *
 * Quand un membre connecté ouvre le lien de SA démo d'entraînement, la session de la démo garde une marque : qui il
 * est, et le numéro de session de son compte à ce moment. « Revenir à mon espace » ne rouvre son compte que si tout
 * concorde encore : la session est celle de cette démo, la démo est une démo d'entraînement qu'il a créée, il est
 * toujours membre de l'équipe, et son numéro de session n'a pas bougé (un mot de passe changé, un accès coupé, une
 * déconnexion de partout le font avancer), et la marque a moins de deux heures (revue du 2026-11-22 : un téléphone
 * tendu à un prospect ne doit pas lui ouvrir l'espace du commercial des jours plus tard). Une démo de prospect ne porte
 * jamais cette marque.
 */
final class RetourEquipe
{
    public const CLE = 'retour_equipe';
    /** Durée de la marque, en secondes : le temps d'une démo d'entraînement. */
    public const DUREE = 7200;

    public function __construct(private readonly DepotArtisans $artisans, private readonly DepotEquipe $equipe, private readonly ClockInterface $horloge)
    {
    }

    /** Après Authentification::ouvrirSession sur la démo : la session est neuve, la marque s'y pose. */
    public function poser(Request $requete, int $membreId, int $version, int $demoId): void
    {
        $requete->getSession()->set(self::CLE, ['membre' => $membreId, 'version' => $version, 'demo' => $demoId, 'pose' => $this->horloge->now()->getTimestamp()]);
    }

    /** La session de cette démo porte-t-elle un retour ? (le ruban montre alors « Revenir à mon espace »). */
    public function present(Request $requete, int $artisanConnecte): bool
    {
        $m = $this->marque($requete);
        return $m !== null && $m['demo'] === $artisanConnecte;
    }

    /**
     * Le membre à rouvrir, ou null si quoi que ce soit ne concorde plus.
     *
     * @return array{id: int, version: int}|null
     */
    public function membreARouvrir(Request $requete, int $artisanConnecte): ?array
    {
        $m = $this->marque($requete);
        if ($m === null || $m['demo'] !== $artisanConnecte) {
            return null;
        }
        $demo = $this->equipe->demo($artisanConnecte);
        $membre = $this->artisans->parId($m['membre']);
        if ($demo === null || $demo['entrainement'] !== 1 || $demo['cree_par'] !== $m['membre'] || $membre === null
            || ($membre['administrateur'] !== 1 && $membre['role'] === null) || $membre['version_session'] !== $m['version']) {
            return null;
        }
        return ['id' => $membre['id'], 'version' => $membre['version_session']];
    }

    /**
     * La marque de la session, si elle est entière et a moins de DUREE secondes.
     *
     * @return array{membre: int, version: int, demo: int}|null
     */
    private function marque(Request $requete): ?array
    {
        if (!$requete->hasSession()) {
            return null;
        }
        $m = $requete->getSession()->get(self::CLE);
        if (!is_array($m) || !is_int($m['membre'] ?? null) || !is_int($m['version'] ?? null) || !is_int($m['demo'] ?? null) || !is_int($m['pose'] ?? null)) {
            return null;
        }
        if ($this->horloge->now()->getTimestamp() - $m['pose'] > self::DUREE) {
            return null;
        }
        return ['membre' => $m['membre'], 'version' => $m['version'], 'demo' => $m['demo']];
    }
}
