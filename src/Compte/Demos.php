<?php

declare(strict_types=1);

namespace OffreGen\Compte;

use OffreGen\Catalogue\CatalogueFictif;
use OffreGen\Donnees\Base;
use OffreGen\Donnees\DepotArtisans;
use OffreGen\Donnees\DepotEquipe;
use OffreGen\Donnees\JournalAdmin;
use OffreGen\Donnees\PorteeEquipe;
use Psr\Clock\ClockInterface;

/**
 * Les démos créées par l'équipe (décisions D1 et D2 de Xavier du 2026-11-19, demande du 2026-11-20) : un compte neuf
 * par démo, posé tout de suite avec le catalogue et les offres d'exemple de son métier. On n'y entre que par un lien :
 * son adresse est fabriquée et son mot de passe n'est montré à personne.
 */
final class Demos
{
    public const OFFRES = 10;
    public const JOURS = 7;
    /** Offres d'un essai ouvert depuis une démo, en plus de celles déjà dictées pendant la démo. */
    public const OFFRES_ESSAI = 10;
    /** Démos qu'un commercial peut créer en 24 heures : chacune peut coûter jusqu'à dix dictées d'IA. */
    public const PAR_JOUR = 10;

    public function __construct(
        private readonly Base $base,
        private readonly Comptes $comptes,
        private readonly DepotArtisans $artisans,
        private readonly DepotEquipe $depot,
        private readonly JournalAdmin $journal,
        private readonly ClockInterface $horloge,
    ) {
    }

    /**
     * @param string $entreprise vide : un nom provisoire, « Démo » suivi du métier
     * @param string $courriel courriel du prospect, s'il est déjà connu ; vide sinon
     * @return int le compte créé
     * @throws \OverflowException un commercial a atteint sa limite de la journée
     * @throws \InvalidArgumentException métier inconnu, courriel faux
     */
    public function creer(PorteeEquipe $p, Role $role, string $metier, string $metierSaisi, string $entreprise, string $courriel): int
    {
        $maintenant = \DateTimeImmutable::createFromInterface($this->horloge->now());
        if ($role === Role::Commercial && $this->depot->demosCreees($p->acteurId, $maintenant->modify('-24 hours')) >= self::PAR_JOUR) {
            throw new \OverflowException('Vous avez créé ' . self::PAR_JOUR . ' démos en 24 heures : c\'est la limite. Réessayez demain, ou demandez à un administrateur.');
        }
        if (!isset(CatalogueFictif::libelles()[$metier])) {
            throw new \InvalidArgumentException('Choisissez un métier.');
        }
        // Le texte du métier ne va qu'avec « Autre métier » : ailleurs, un reste de saisie est ignoré.
        $metierSaisi = $metier === 'autre' ? trim($metierSaisi) : '';
        $courriel = trim($courriel) === '' ? null : self::courriel($courriel);
        $entreprise = mb_substr(trim($entreprise), 0, 120);
        if ($entreprise === '') {
            $entreprise = 'Démo ' . ($metierSaisi !== '' ? $metierSaisi : CatalogueFictif::libelle($metier));
        }
        $adresse = 'demo-' . bin2hex(random_bytes(8)) . '@demo.invalid';
        return $this->base->transaction(function () use ($p, $metier, $metierSaisi, $entreprise, $courriel, $adresse, $maintenant): int {
            $this->comptes->creer($adresse, $entreprise, true, null, self::OFFRES, $metier, $metierSaisi === '' ? null : $metierSaisi);
            $id = $this->artisans->parCourriel($adresse)['id'] ?? throw new \LogicException('Démo créée introuvable.');
            $this->depot->poserDemo($id, $p->acteurId, $maintenant, $maintenant->modify('+' . self::JOURS . ' days'), $courriel);
            $this->journal->noter($p->acteurId, $id, 'demo_creee', $metier, $maintenant);
            return $id;
        });
    }

    /**
     * Un courriel tapé, mis en forme. Rien ne prouve qu'il appartient à celui qui le tape.
     *
     * @throws \InvalidArgumentException
     */
    public static function courriel(string $saisi): string
    {
        $courriel = DepotArtisans::normaliser($saisi);
        if (mb_strlen($courriel) > 190 || filter_var($courriel, FILTER_VALIDATE_EMAIL) === false) {
            throw new \InvalidArgumentException('Ce courriel ne semble pas valide.');
        }
        return $courriel;
    }
}
