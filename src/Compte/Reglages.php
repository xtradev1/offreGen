<?php

declare(strict_types=1);

namespace OffreGen\Compte;

use OffreGen\Offre\Saisie;
use OffreGen\Offre\SaisieRefusee;

/**
 * Les réglages d'un compte qui changent une offre : l'en-tête du document (entreprise, adresse,
 * téléphone, courriel, IDE), la TVA, l'arrondi du total, la validité et les conditions de paiement.
 */
final readonly class Reglages
{
    /** Taux proposés à l'écran, en dix-millièmes : 8,1 % (taux normal suisse) ou non assujetti. */
    public const TAUX_PERMIS = [810, 0];

    /**
     * Durées de validité proposées d'un toucher ; « Autre » ouvre un nombre de 1 à 365 jours. Une offre sans
     * validité (vide ou 0) est refusée : Xavier la juge dangereuse (2026-11-12).
     */
    public const VALIDITES_PROPOSEES = [15, 30, 60];

    /** Types de paiement : à N jours dès la fin des travaux, net à N jours dès la facture, ou par acomptes. */
    public const TYPES_PAIEMENT = ['jours' => 'À … jours', 'net' => 'Net à … jours', 'acomptes' => 'Acomptes'];

    /** Délais proposés d'un toucher pour « à N jours » et « net à N jours » ; « Autre » ouvre de 1 à 120 jours. */
    public const DELAIS_PROPOSES = [10, 15, 20, 30];

    /** Acomptes proposés d'un toucher, en pour-cent ; « Autre » ouvre 2 à 4 tranches qui font 100. */
    public const ACOMPTES_PROPOSES = [[30, 70], [50, 50], [30, 30, 40]];

    /** @param list<int> $paiementAcomptes en pour-cent, vide sauf pour le type « acomptes » */
    public function __construct(
        public string $entreprise,
        public int $tvaPourDixMille,
        public bool $arrondiCinqCentimes,
        public string $adresse = '',
        public string $telephone = '',
        public string $courrielContact = '',
        public string $ide = '',
        public int $validiteJours = 30,
        public string $paiementType = 'jours',
        public int $paiementJours = 30,
        public array $paiementAcomptes = [],
    ) {
    }

    /** La phrase « Paiement : … » du document, fabriquée ici seulement. */
    public function conditionsPaiement(): string
    {
        return match ($this->paiementType) {
            'net' => "Net à {$this->paiementJours} jours dès réception de la facture.",
            'acomptes' => self::phraseAcomptes($this->paiementAcomptes),
            default => "À {$this->paiementJours} jours dès la fin des travaux.",
        };
    }

    /**
     * La première tranche à la commande, la dernière à la fin des travaux, celles du milieu en cours de travaux.
     *
     * @param list<int> $tranches
     */
    public static function phraseAcomptes(array $tranches): string
    {
        $n = count($tranches);
        $morceaux = [];
        foreach ($tranches as $i => $pourcent) {
            $moment = $i === 0 ? 'à la commande' : ($i === $n - 1 ? 'à la fin des travaux' : 'en cours de travaux');
            $morceaux[] = "$pourcent\u{00A0}% $moment";
        }
        return implode(', ', $morceaux) . '.';
    }

    /**
     * « 30, 30, 40 », « 30/30/40 » ou « 30 % 70 % » donnent [30, 30, 40] ; null s'il n'y a pas 2 à 4 tranches
     * de 1 à 99 qui font 100.
     *
     * @return list<int>|null
     */
    public static function acomptes(string $saisie): ?array
    {
        preg_match_all('/\d+/', $saisie, $trouves);
        $tranches = array_map('intval', $trouves[0]);
        $bornees = array_filter($tranches, static fn (int $t): bool => $t >= 1 && $t <= 99);
        if (count($tranches) < 2 || count($tranches) > 4 || count($bornees) !== count($tranches) || array_sum($tranches) !== 100) {
            return null;
        }
        return $tranches;
    }

    /** Un choix proposé l'emporte ; « autre » ou pas de choix : la valeur tapée. */
    private static function choix(string $choix, string $tape): string
    {
        return $choix === '' || $choix === 'autre' ? $tape : $choix;
    }

    /** Un entier de $min à $max, sinon null. */
    private static function entier(string $saisie, int $min, int $max): ?int
    {
        $t = trim($saisie);
        if (preg_match('/^\d{1,3}$/', $t) !== 1) {
            return null;
        }
        return (int) $t >= $min && (int) $t <= $max ? (int) $t : null;
    }

    /**
     * Le formulaire de l'écran Réglages ; SaisieRefusee si un champ est faux.
     *
     * @param array<string, string> $champs entreprise, adresse, telephone, courriel_contact, ide, tva,
     *        validite_choix et validite_jours, paiement_type, paiement_jours_choix et paiement_jours,
     *        paiement_acomptes_choix et paiement_acomptes. Un « …_choix » proposé l'emporte ; « autre » ou
     *        absent : la valeur tapée.
     */
    public static function depuisSaisie(array $champs, bool $arrondi): self
    {
        $c = static fn (string $cle): string => $champs[$cle] ?? '';
        $erreurs = [];
        $nom = Saisie::texte($c('entreprise'), 190);
        if ($nom === null) {
            $erreurs['entreprise'] = "Indiquez le nom de l'entreprise (190 caractères au plus).";
        }
        $adresse = Saisie::lignes($c('adresse'), 4, 255);
        if ($adresse === null) {
            $erreurs['adresse'] = 'Quatre lignes et 255 caractères au plus.';
        }
        $telephone = trim((string) preg_replace('/\s+/u', ' ', $c('telephone')));
        if ($telephone !== '' && preg_match('/^\+?[0-9 ().\/-]{6,40}$/', $telephone) !== 1) {
            $erreurs['telephone'] = 'Un numéro de téléphone, par exemple 032 422 00 00.';
        }
        $courriel = trim($c('courriel_contact'));
        if ($courriel !== '' && (mb_strlen($courriel) > 190 || filter_var($courriel, FILTER_VALIDATE_EMAIL) === false)) {
            $erreurs['courriel_contact'] = 'Une adresse de courriel, par exemple info@exemple.ch.';
        }
        $ide = self::ide($c('ide'));
        if ($ide === null) {
            $erreurs['ide'] = 'Le numéro IDE a la forme CHE-123.456.789.';
        }
        $taux = preg_match('/^\d{1,4}$/', $c('tva')) === 1 ? (int) $c('tva') : -1;
        if (!in_array($taux, self::TAUX_PERMIS, true)) {
            $erreurs['tva'] = 'Choisissez un taux de TVA.';
        }
        $choixValidite = $c('validite_choix');
        $validite = $choixValidite !== '' && $choixValidite !== 'autre' && !in_array((int) $choixValidite, self::VALIDITES_PROPOSEES, true)
            ? null
            : self::entier(self::choix($choixValidite, $c('validite_jours')), 1, 365);
        if ($validite === null) {
            $erreurs['validite_jours'] = "L'offre doit avoir une durée de validité : de 1 à 365 jours.";
        }
        $type = $c('paiement_type') === '' ? 'jours' : $c('paiement_type');
        $jours = 30;
        $acomptes = [];
        if (!array_key_exists($type, self::TYPES_PAIEMENT)) {
            $erreurs['paiement_type'] = 'Choisissez comment le client paie.';
        } elseif ($type === 'acomptes') {
            $acomptes = self::acomptes(self::choix($c('paiement_acomptes_choix'), $c('paiement_acomptes')));
            if ($acomptes === null) {
                $erreurs['paiement_acomptes'] = 'De 2 à 4 acomptes qui font 100 %, par exemple 30, 30, 40.';
            }
        } else {
            $jours = self::entier(self::choix($c('paiement_jours_choix'), $c('paiement_jours')), 1, 120);
            if ($jours === null) {
                $erreurs['paiement_jours'] = 'Un délai de 1 à 120 jours.';
            }
        }
        if ($nom === null || $adresse === null || $ide === null || $validite === null || $jours === null || $acomptes === null || $erreurs !== []) {
            throw new SaisieRefusee($erreurs);
        }
        return new self($nom, $taux, $arrondi, $adresse, $telephone, $courriel, $ide, $validite, $type, $jours, $acomptes);
    }

    /** « che 123 456 789 tva » donne « CHE-123.456.789 » ; vide reste vide ; null si ce n'est pas un IDE. */
    public static function ide(string $saisie): ?string
    {
        $t = strtoupper(trim($saisie));
        if ($t === '') {
            return '';
        }
        $chiffres = (string) preg_replace('/\D/', '', (string) preg_replace('/\s*(TVA|MWST|IVA)$/', '', $t));
        if (!str_starts_with($t, 'CHE') || strlen($chiffres) !== 9) {
            return null;
        }
        return 'CHE-' . substr($chiffres, 0, 3) . '.' . substr($chiffres, 3, 3) . '.' . substr($chiffres, 6, 3);
    }

    /** Dernière ligne de l'adresse sans le numéro postal : « 2800 Delémont » donne « Delémont ». */
    public function localite(): string
    {
        $lignes = $this->adresse === '' ? [] : explode("\n", $this->adresse);
        $derniere = trim((string) end($lignes));
        return trim((string) preg_replace('/^(CH-)?\d{4}\s+/', '', $derniere));
    }
}
