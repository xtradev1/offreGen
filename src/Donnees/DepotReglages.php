<?php

declare(strict_types=1);

namespace OffreGen\Donnees;

use OffreGen\Adresse\Adresse;
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
            'SELECT entreprise, adresse_rue, adresse_numero, adresse_npa, adresse_localite, adresse_pays, telephone, courriel_contact,
                    ide, iban, tva_pour_dix_mille, arrondi_cinq_centimes, validite_jours, paiement_type, paiement_jours, paiement_acomptes
             FROM artisan WHERE id = :a',
            ['a' => $this->portee->artisanId],
        ) ?? throw new \RuntimeException('Compte introuvable.');
        /** @var array{entreprise: string, adresse_rue: string|null, adresse_numero: string|null, adresse_npa: string|null, adresse_localite: string|null, adresse_pays: string|null, telephone: string|null, courriel_contact: string|null, ide: string|null, iban: string|null, tva_pour_dix_mille: int, arrondi_cinq_centimes: int, validite_jours: int, paiement_type: string, paiement_jours: int, paiement_acomptes: string|null} $l */
        $acomptes = $l['paiement_type'] === 'acomptes' ? (Reglages::acomptes($l['paiement_acomptes'] ?? '') ?? []) : [];
        // Des acomptes illisibles en base ne cassent pas l'écran : retour au paiement à N jours.
        $type = $l['paiement_type'] === 'acomptes' && $acomptes === [] ? 'jours' : $l['paiement_type'];
        return new Reglages(
            $l['entreprise'], $l['tva_pour_dix_mille'], $l['arrondi_cinq_centimes'] === 1,
            Adresse::depuisBase($l, 'adresse_'), $l['telephone'] ?? '', $l['courriel_contact'] ?? '', $l['ide'] ?? '',
            $l['validite_jours'], $type, $l['paiement_jours'], $acomptes, $l['iban'] ?? '',
        );
    }

    /**
     * L'adresse d'avant les champs séparés, que la migration 10 n'a pas su découper : l'écran la montre pour
     * qu'elle soit reportée dans les champs. Vide dès que l'adresse en champs existe.
     */
    public function adresseAReprendre(): string
    {
        $v = $this->base->valeur('SELECT adresse FROM artisan WHERE id = :a AND adresse_npa IS NULL', ['a' => $this->portee->artisanId]);
        return is_string($v) ? $v : '';
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

    /**
     * L'assistant s'ouvrira de nouveau (remise à zéro d'un compte de démonstration). L'IBAN tapé par un visiteur
     * s'efface : le suivant ne doit pas voir son compte bancaire.
     */
    public function assistantARefaire(): void
    {
        $this->base->ecrire('UPDATE artisan SET reglages_faits_le = NULL, iban = NULL WHERE id = :a', ['a' => $this->portee->artisanId]);
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
        // `adresse` garde le texte de l'adresse (l'ancienne version de l'application le lit). Sans adresse en champs, ni
        // avant ni maintenant, le texte d'avant les champs séparés reste, à reporter.
        $texte = $r->adresse->estVide() && $this->base->valeur('SELECT adresse_npa FROM artisan WHERE id = :a', ['a' => $this->portee->artisanId]) === null
            ? $this->adresseAReprendre()
            : $r->adresse->lignes();
        $this->base->ecrire(
            'UPDATE artisan SET entreprise = :e, adresse = :texte,
                    adresse_rue = :arue, adresse_numero = :anumero, adresse_npa = :anpa, adresse_localite = :alocalite, adresse_pays = :apays,
                    telephone = :te, courriel_contact = :co, ide = :ide, iban = :ib,
                    tva_pour_dix_mille = :t, arrondi_cinq_centimes = :r, validite_jours = :v,
                    paiement_type = :pt, paiement_jours = :pj, paiement_acomptes = :pa
             WHERE id = :a',
            self::colonnesAdresse($r->adresse) + ['texte' => $vide($texte), 'e' => $r->entreprise, 'te' => $vide($r->telephone), 'co' => $vide($r->courrielContact),
             'ide' => $vide($r->ide), 'ib' => $vide($r->iban), 't' => $r->tvaPourDixMille, 'r' => $r->arrondiCinqCentimes ? 1 : 0,
             'v' => $r->validiteJours, 'pt' => $r->paiementType, 'pj' => $r->paiementJours,
             'pa' => $r->paiementAcomptes === [] ? null : implode(',', $r->paiementAcomptes), 'a' => $this->portee->artisanId],
        );
    }

    /** @return array<string, string|null> arue, anumero, anpa, alocalite, apays */
    private static function colonnesAdresse(Adresse $a): array
    {
        $parametres = [];
        foreach ($a->versBase('') as $cle => $valeur) {
            $parametres['a' . $cle] = $valeur;
        }
        return $parametres;
    }
}
