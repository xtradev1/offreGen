<?php

declare(strict_types=1);

namespace OffreGen\Donnees;

use OffreGen\Compte\Marque;
use OffreGen\Compte\Reglages;

/**
 * Les réglages du compte connecté, et de lui seul : la portée désigne la ligne de la table artisan.
 */
final class DepotReglages
{
    public function __construct(private readonly Base $base, private readonly Portee $portee)
    {
    }

    public function lire(): Reglages
    {
        $l = $this->base->un(
            'SELECT entreprise, adresse, telephone, courriel_contact, ide, tva_pour_dix_mille, arrondi_cinq_centimes, validite_jours,
                    paiement_type, paiement_jours, paiement_acomptes
             FROM artisan WHERE id = :a',
            ['a' => $this->portee->artisanId],
        ) ?? throw new \RuntimeException('Compte introuvable.');
        /** @var array{entreprise: string, adresse: string|null, telephone: string|null, courriel_contact: string|null, ide: string|null, tva_pour_dix_mille: int, arrondi_cinq_centimes: int, validite_jours: int, paiement_type: string, paiement_jours: int, paiement_acomptes: string|null} $l */
        $acomptes = $l['paiement_type'] === 'acomptes' ? (Reglages::acomptes($l['paiement_acomptes'] ?? '') ?? []) : [];
        // Des acomptes illisibles en base ne cassent pas l'écran : retour au paiement à N jours.
        $type = $l['paiement_type'] === 'acomptes' && $acomptes === [] ? 'jours' : $l['paiement_type'];
        return new Reglages(
            $l['entreprise'], $l['tva_pour_dix_mille'], $l['arrondi_cinq_centimes'] === 1,
            $l['adresse'] ?? '', $l['telephone'] ?? '', $l['courriel_contact'] ?? '', $l['ide'] ?? '',
            $l['validite_jours'], $type, $l['paiement_jours'], $acomptes,
        );
    }

    /** Vrai si l'assistant du premier lancement a été fait ou passé. */
    public function assistantFait(): bool
    {
        return $this->base->valeur('SELECT reglages_faits_le FROM artisan WHERE id = :a', ['a' => $this->portee->artisanId]) !== null;
    }

    public function marquerAssistantFait(\DateTimeImmutable $quand): void
    {
        $this->base->ecrire('UPDATE artisan SET reglages_faits_le = :q WHERE id = :a', ['q' => Dates::versBase($quand), 'a' => $this->portee->artisanId]);
    }

    /** L'assistant s'ouvrira de nouveau (remise à zéro d'un compte de démonstration). */
    public function assistantARefaire(): void
    {
        $this->base->ecrire('UPDATE artisan SET reglages_faits_le = NULL WHERE id = :a', ['a' => $this->portee->artisanId]);
    }

    public function lireMarque(): Marque
    {
        /** @var array{modele: string, couleur: string|null, logo_fichier: string|null, couleur_logo: string|null}|null $l */
        $l = $this->base->un('SELECT modele, couleur, logo_fichier, couleur_logo FROM artisan WHERE id = :a', ['a' => $this->portee->artisanId]);
        if ($l === null) {
            throw new \RuntimeException('Compte introuvable.');
        }
        return new Marque(Marque::modeleValide($l['modele']), $l['couleur'], $l['logo_fichier'], $l['couleur_logo']);
    }

    public function enregistrerMarque(string $modele, ?string $couleur): void
    {
        $this->base->ecrire(
            'UPDATE artisan SET modele = :m, couleur = :c WHERE id = :a',
            ['m' => Marque::modeleValide($modele), 'c' => $couleur, 'a' => $this->portee->artisanId],
        );
    }

    /** Nouveau logo (ou aucun) ; sa couleur devient celle des documents, modifiable ensuite. */
    public function changerLogo(?string $fichier, ?string $couleurDuLogo): void
    {
        $this->base->ecrire(
            'UPDATE artisan SET logo_fichier = :f, couleur_logo = :cl, couleur = COALESCE(:c, couleur) WHERE id = :a',
            ['f' => $fichier, 'cl' => $couleurDuLogo, 'c' => $couleurDuLogo, 'a' => $this->portee->artisanId],
        );
    }

    public function enregistrer(Reglages $r): void
    {
        $vide = static fn (string $s): ?string => $s === '' ? null : $s;
        $this->base->ecrire(
            'UPDATE artisan SET entreprise = :e, adresse = :ad, telephone = :te, courriel_contact = :co, ide = :ide,
                    tva_pour_dix_mille = :t, arrondi_cinq_centimes = :r, validite_jours = :v,
                    paiement_type = :pt, paiement_jours = :pj, paiement_acomptes = :pa
             WHERE id = :a',
            ['e' => $r->entreprise, 'ad' => $vide($r->adresse), 'te' => $vide($r->telephone), 'co' => $vide($r->courrielContact),
             'ide' => $vide($r->ide), 't' => $r->tvaPourDixMille, 'r' => $r->arrondiCinqCentimes ? 1 : 0,
             'v' => $r->validiteJours, 'pt' => $r->paiementType, 'pj' => $r->paiementJours,
             'pa' => $r->paiementAcomptes === [] ? null : implode(',', $r->paiementAcomptes), 'a' => $this->portee->artisanId],
        );
    }
}
