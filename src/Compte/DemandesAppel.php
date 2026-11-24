<?php

declare(strict_types=1);

namespace OffreGen\Compte;

use OffreGen\Donnees\Dates;
use OffreGen\Donnees\DepotAppels;
use OffreGen\Donnees\DepotEquipe;
use OffreGen\Donnees\JournalAdmin;
use OffreGen\Systeme\Courriel;
use OffreGen\Systeme\Messagerie;
use Psr\Clock\ClockInterface;

/**
 * « Demander un appel » (K1 et S1 de la maquette v4 de l'administration, validés le 2026-11-19 ; chantier onboarding,
 * E1 du 2026-11-23). Le client laisse un numéro, un moment, une question s'il veut. La demande va au membre qui suit
 * le compte (cree_par), aux administrateurs sinon ; un courriel les prévient. Trois demandes par 24 heures au plus :
 * chacune envoie un courriel, la boîte du commercial ne doit pas pouvoir être noyée.
 */
final class DemandesAppel
{
    public const MAX_PAR_JOUR = 3;
    public const GESTE = 'demande_appel';
    public const QUESTION_MAX = 500;

    public function __construct(
        private readonly DepotAppels $appels,
        private readonly DepotEquipe $equipe,
        private readonly JournalAdmin $journal,
        private readonly Messagerie $messagerie,
        private readonly ClockInterface $horloge,
        private readonly string $nomApplication,
        private readonly string $adresseApplication,
    ) {
    }

    /**
     * Un numéro suisse ou étranger, tel que tapé : chiffres, espaces, points, barres obliques, tirets, parenthèses,
     * un « + » en tête ; de 9 à 15 chiffres.
     *
     * @throws \InvalidArgumentException avec la phrase à montrer
     */
    public static function telephone(string $saisi): string
    {
        $tel = trim((string) preg_replace('/\s+/u', ' ', $saisi));
        if ($tel === '') {
            throw new \InvalidArgumentException('Indiquez votre numéro de téléphone.');
        }
        $chiffres = strlen((string) preg_replace('/\D/', '', $tel));
        if (mb_strlen($tel) > 32 || preg_match('#^\+?[0-9 ./()-]+$#', $tel) !== 1 || $chiffres < 9 || $chiffres > 15) {
            throw new \InvalidArgumentException("Vérifiez ce numéro. Exemple\u{00A0}: 079 123 45 67.");
        }
        return $tel;
    }

    /**
     * Dépose la demande et la note au journal du compte. Le courriel part par prevenir(), après la réponse.
     *
     * @throws \InvalidArgumentException avec la phrase à montrer
     */
    public function deposer(int $artisanId, string $telephone, string $moment, string $question): void
    {
        $telephone = self::telephone($telephone);
        if (!isset(DepotAppels::MOMENTS[$moment])) {
            throw new \InvalidArgumentException('Choisissez quand vous joindre.');
        }
        // Le champ compte un retour à la ligne pour un caractère ; le navigateur en envoie deux.
        $question = trim(str_replace("\r\n", "\n", $question));
        if (mb_strlen($question) > self::QUESTION_MAX) {
            throw new \InvalidArgumentException('Votre question dépasse ' . self::QUESTION_MAX . ' caractères. Raccourcissez-la, vous pourrez en dire plus au téléphone.');
        }
        $maintenant = \DateTimeImmutable::createFromInterface($this->horloge->now());
        if ($this->equipe->gestes($artisanId, self::GESTE, $maintenant->modify('-24 hours')) >= self::MAX_PAR_JOUR) {
            throw new \InvalidArgumentException('Vous avez déjà envoyé trois demandes en 24 heures. Celle-ci n\'est pas enregistrée, la précédente est bien notée.');
        }
        $this->appels->deposer($artisanId, $telephone, $moment, $question === '' ? null : $question, $maintenant);
        $this->journal->noter($artisanId, $artisanId, self::GESTE, DepotAppels::MOMENTS[$moment], $maintenant);
    }

    /** Le courriel « Demande d'appel » au membre qui suit le compte, aux administrateurs sinon. */
    public function prevenir(int $artisanId): void
    {
        $demande = $this->appels->enAttente($artisanId);
        $compte = $this->equipe->compteAppel($artisanId);
        if ($demande === null || $compte === null) {
            return;
        }
        $apres = [];
        if ($demande['question'] !== null) {
            $apres[] = "Sa question\u{00A0}: «\u{00A0}{$demande['question']}\u{00A0}»";
        }
        $apres[] = self::etat($compte, \DateTimeImmutable::createFromInterface($this->horloge->now()));
        $courriel = new Courriel(
            "Demande d'appel\u{00A0}: {$compte['entreprise']}",
            "{$compte['entreprise']} demande un appel",
            "Téléphone\u{00A0}: {$demande['telephone']}, " . DepotAppels::MOMENTS[$demande['moment']] . '.',
            'Voir sa fiche',
            rtrim($this->adresseApplication, '/') . '/admin/artisans/' . $artisanId,
            $apres,
        );
        foreach ($this->equipe->destinatairesAppel($artisanId)['courriels'] as $destinataire) {
            $courriel->envoyer($this->messagerie, $destinataire, $this->nomApplication, $this->adresseApplication);
        }
    }

    /**
     * Où en est le compte, en une ligne, pour le courriel et « À rappeler ».
     *
     * @param array{demo: int, demo_fin: string|null, offres_max: int|null, dictees: int} $compte
     */
    public static function etat(array $compte, \DateTimeImmutable $maintenant): string
    {
        $offres = $compte['dictees'] === 0 ? 'aucune offre dictée' : ($compte['dictees'] === 1 ? '1 offre dictée' : "{$compte['dictees']} offres dictées");
        if ($compte['demo'] === 1 && $compte['demo_fin'] !== null) {
            $fin = Dates::depuisBase($compte['demo_fin'])->setTimezone(new \DateTimeZone('Europe/Zurich'))->format('d.m.Y');
            $etat = Demos::terminee($compte['demo_fin'], $maintenant) ? "Démo terminée le $fin" : "Démo jusqu'au $fin";
        } elseif ($compte['demo'] === 1) {
            $etat = 'Démo';
        } else {
            $etat = $compte['offres_max'] !== null ? "À l'essai" : 'Client';
        }
        return "$etat, $offres.";
    }
}
