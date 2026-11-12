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

    /** Durées de validité proposées d'un toucher à l'écran ; toute autre durée de 1 à 365 jours reste permise. */
    public const VALIDITES_PROPOSEES = [15, 30, 60, 90];

    /** Conditions de paiement proposées d'un toucher ; le texte reste libre. La première est la valeur par défaut. */
    public const PAIEMENTS_PROPOSES = [
        'À 30 jours dès la fin des travaux.',
        'Net à 10 jours dès réception de la facture.',
        '30 % à la commande, solde à 30 jours dès la fin des travaux.',
        'Comptant à la fin des travaux.',
    ];

    public function __construct(
        public string $entreprise,
        public int $tvaPourDixMille,
        public bool $arrondiCinqCentimes,
        public string $adresse = '',
        public string $telephone = '',
        public string $courrielContact = '',
        public string $ide = '',
        public int $validiteJours = 30,
        public string $conditionsPaiement = 'À 30 jours dès la fin des travaux.',
    ) {
    }

    /**
     * Le formulaire de l'écran Réglages ; SaisieRefusee si un champ est faux.
     *
     * @param array<string, string> $champs entreprise, adresse, telephone, courriel_contact, ide, tva, validite_jours, conditions_paiement
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
        $validite = preg_match('/^\d{1,3}$/', trim($c('validite_jours'))) === 1 ? (int) trim($c('validite_jours')) : 0;
        if ($validite < 1 || $validite > 365) {
            $erreurs['validite_jours'] = 'Un nombre de jours, de 1 à 365.';
        }
        $conditions = Saisie::texte($c('conditions_paiement'), 255);
        if ($conditions === null) {
            $erreurs['conditions_paiement'] = 'Indiquez les conditions de paiement (255 caractères au plus).';
        }
        if ($nom === null || $adresse === null || $ide === null || $conditions === null || $erreurs !== []) {
            throw new SaisieRefusee($erreurs);
        }
        return new self($nom, $taux, $arrondi, $adresse, $telephone, $courriel, $ide, $validite, $conditions);
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
