<?php

declare(strict_types=1);

namespace OffreGen\Compte;

use OffreGen\Catalogue\CatalogueFictif;
use OffreGen\Donnees\Base;
use OffreGen\Donnees\Dates;
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
    /** Démos d'entraînement qu'un membre peut ouvrir en 24 heures (chantier onboarding, lot 8). */
    public const ENTRAINEMENTS_PAR_JOUR = 3;

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
    public function creer(PorteeEquipe $p, Role $role, string $metier, string $metierSaisi, string $entreprise, string $courriel, bool $entrainement = false): int
    {
        $maintenant = \DateTimeImmutable::createFromInterface($this->horloge->now());
        // La démo d'entraînement d'un membre (chantier onboarding, point 2 accepté par Xavier) : hors de la limite de
        // dix, mais trois en 24 heures au plus, chacune pouvant coûter des dictées d'IA.
        if ($entrainement && $this->depot->gestes($p->acteurId, 'demo_entrainement', $maintenant->modify('-24 hours')) >= self::ENTRAINEMENTS_PAR_JOUR) {
            throw new \OverflowException('Vous avez fait ' . self::ENTRAINEMENTS_PAR_JOUR . ' démos d\'entraînement en 24 heures : c\'est la limite. Réessayez dans 24 heures.');
        }
        if (!$entrainement && $role === Role::Commercial && $this->depot->demosCreees($p->acteurId, $maintenant->modify('-24 hours')) >= self::PAR_JOUR) {
            throw new \OverflowException('Vous avez créé ' . self::PAR_JOUR . ' démos en 24 heures : c\'est la limite. Réessayez demain, ou demandez à un administrateur.');
        }
        if ($entrainement) {
            // Le membre entre dans sa démo sans rien taper : son propre courriel.
            $courriel = $this->artisans->parId($p->acteurId)['courriel'] ?? '';
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
        return $this->base->transaction(function () use ($p, $metier, $metierSaisi, $entreprise, $courriel, $adresse, $maintenant, $entrainement): int {
            $this->comptes->creer($adresse, $entreprise, true, null, self::OFFRES, $metier, $metierSaisi === '' ? null : $metierSaisi);
            $id = $this->artisans->parCourriel($adresse)['id'] ?? throw new \LogicException('Démo créée introuvable.');
            $this->depot->poserDemo($id, $p->acteurId, $maintenant, $maintenant->modify('+' . self::JOURS . ' days'), $courriel);
            if ($entrainement) {
                // Ni « amené par » ni commission ; supprimée avec les autres démos.
                $this->depot->marquerEntrainement($id);
            }
            $this->journal->noter($p->acteurId, $id, $entrainement ? 'demo_entrainement' : 'demo_creee', $metier, $maintenant);
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

    /**
     * La démo est finie : sa date de fin est passée (chantier onboarding, lot 2). Dictée et import sont refusés.
     */
    public static function terminee(?string $demoFin, \DateTimeInterface $maintenant): bool
    {
        return $demoFin !== null && Dates::depuisBase($demoFin)->getTimestamp() <= $maintenant->getTimestamp();
    }

    /**
     * Jours de calendrier, à Zurich, jusqu'au jour de fin : 2 « se termine dans 2 jours », 1 « demain », 0 le jour
     * même ou après. La fenêtre des deux derniers jours se montre à 2 et à 1 (règles R5 de l'administration).
     */
    public static function joursAvantLaFin(string $demoFin, \DateTimeInterface $maintenant): int
    {
        $zone = new \DateTimeZone('Europe/Zurich');
        $fin = Dates::depuisBase($demoFin)->setTimezone($zone)->setTime(0, 0);
        $jour = \DateTimeImmutable::createFromInterface($maintenant)->setTimezone($zone)->setTime(0, 0);
        return max(0, (int) $jour->diff($fin)->format('%r%a'));
    }

    /**
     * L'entreprise porte encore le nom provisoire d'une démo, « Démo » suivi du métier (même formule que creer()) :
     * l'envoi d'une offre est précédé d'un avertissement (chantier onboarding, lot 5, O7 et O16 de Xavier).
     */
    public static function estNomProvisoire(string $entreprise, ?string $metier, ?string $metierSaisi): bool
    {
        if ($metier === null || !isset(CatalogueFictif::libelles()[$metier])) {
            return false;
        }
        return $entreprise === 'Démo ' . ($metierSaisi !== null && $metierSaisi !== '' ? $metierSaisi : CatalogueFictif::libelle($metier));
    }
}
