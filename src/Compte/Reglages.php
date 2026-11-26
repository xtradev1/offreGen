<?php

declare(strict_types=1);

namespace OffreGen\Compte;

use OffreGen\Adresse\Adresse;
use OffreGen\Adresse\SaisieAdresse;
use OffreGen\Offre\Saisie;
use OffreGen\Offre\SaisieRefusee;

/**
 * Les réglages d'un compte qui changent une offre : l'en-tête du document (entreprise, adresse en champs séparés,
 * téléphone, courriel, IDE), la TVA, l'arrondi du total, la validité et les conditions de paiement ;
 * l'IBAN, pour la section de paiement des factures (forme compacte, vide s'il n'est pas donné) ;
 * la personne de contact, citée sur l'offre et sous la signature de l'entreprise (modèles du bâtiment, 2026-11-20) ;
 * et les phrases du modèle Lettre (PhrasesLettre, même jour).
 */
final readonly class Reglages
{
    /** Taux proposés à l'écran, en dix-millièmes : les taux suisses (Offre\Tva::TAUX), ou non assujetti. */
    public const TAUX_PERMIS = [810, 260, 380, 0];

    public const VALIDITES_PROPOSEES = Conditions::VALIDITES_PROPOSEES;
    public const TYPES_PAIEMENT = Conditions::TYPES_PAIEMENT;
    public const DELAIS_PROPOSES = Conditions::DELAIS_PROPOSES;
    public const ACOMPTES_PROPOSES = Conditions::ACOMPTES_PROPOSES;

    /** @param list<int> $paiementAcomptes en pour-cent, vide sauf pour le type « acomptes » */
    public function __construct(
        public string $entreprise,
        public int $tvaPourDixMille,
        public bool $arrondiCinqCentimes,
        public Adresse $adresse = new Adresse(),
        public string $telephone = '',
        public string $courrielContact = '',
        public string $ide = '',
        public int $validiteJours = 30,
        public string $paiementType = 'jours',
        public int $paiementJours = 30,
        public array $paiementAcomptes = [],
        public string $iban = '',
        public string $contactNom = '',
        public PhrasesLettre $lettre = new PhrasesLettre(),
        public int $escomptePourDixMille = 0,
        public int $escompteJours = 0,
    ) {
    }

    /** Validité, paiement et escompte du compte : ceux que prend une offre qui n'a pas les siens. */
    public function conditions(): Conditions
    {
        return new Conditions($this->validiteJours, $this->paiementType, $this->paiementJours, $this->paiementAcomptes,
            $this->escomptePourDixMille, $this->escompteJours);
    }

    /** Les mêmes réglages, avec ces conditions-là. */
    public function avecConditions(Conditions $c): self
    {
        return new self($this->entreprise, $this->tvaPourDixMille, $this->arrondiCinqCentimes, $this->adresse, $this->telephone,
            $this->courrielContact, $this->ide, $c->validiteJours, $c->paiementType, $c->paiementJours, $c->paiementAcomptes,
            $this->iban, $this->contactNom, $this->lettre, $c->escomptePourDixMille, $c->escompteJours);
    }

    /**
     * Les réglages qui valent pour une offre : ses conditions à elle (colonne conditions, D5 A du 2026-11-21), sinon
     * celles du compte.
     */
    public function pourOffre(?string $conditions): self
    {
        return $conditions === null ? $this : $this->avecConditions(Conditions::depuisJson($conditions, $this->conditions()));
    }

    /** La phrase « Paiement : … » du document, fabriquée ici seulement. */
    public function conditionsPaiement(): string
    {
        return $this->conditions()->conditionsPaiement();
    }

    /** « Escompte 2 % à 10 jours », ou null sans escompte. */
    public function phraseEscompte(): ?string
    {
        return $this->conditions()->phraseEscompte();
    }

    /** La phrase « Paiement : … » du document, escompte compris. */
    public function paiementEtEscompte(): string
    {
        return $this->conditions()->paiementEtEscompte();
    }

    /** Le paiement en quelques mots, pour le sommaire des réglages (une ligne de téléphone). */
    public function resumePaiement(): string
    {
        $escompte = $this->conditions()->aEscompte()
            ? ', escompte ' . Conditions::pourcent($this->escomptePourDixMille) . "\u{00A0}% à {$this->escompteJours} jours" : '';
        return $this->conditions()->resumePaiement() . $escompte;
    }

    /** @param list<int> $tranches */
    public static function phraseAcomptes(array $tranches): string
    {
        return Conditions::phraseAcomptes($tranches);
    }

    /** @return list<int>|null voir Conditions::acomptes */
    public static function acomptes(string $saisie): ?array
    {
        return Conditions::acomptes($saisie);
    }

    /**
     * Le formulaire de l'écran Réglages ; SaisieRefusee si un champ est faux.
     *
     * @param array<string, string> $champs entreprise, contact_nom, adresse_rue, adresse_numero, adresse_npa, adresse_localite,
     *        adresse_pays, telephone, courriel_contact, ide, iban, tva,
     *        validite_choix et validite_jours, paiement_type, paiement_jours_choix et paiement_jours,
     *        paiement_acomptes_choix et paiement_acomptes, escompte_pourcent et escompte_jours (voir
     *        Conditions::depuisSaisie). Les phrases de Lettre : voir PhrasesLettre::depuisSaisie.
     * @param Activite $activite celle du compte, pour reconnaître une phrase de Lettre laissée telle que l'écran la montre
     */
    public static function depuisSaisie(array $champs, bool $arrondi, Activite $activite = Activite::Batiment): self
    {
        $c = static fn (string $cle): string => $champs[$cle] ?? '';
        $erreurs = [];
        $nom = Saisie::texte($c('entreprise'), 190);
        if ($nom === null) {
            $erreurs['entreprise'] = "Indiquez le nom de l'entreprise (190 caractères au plus).";
        }
        $contact = trim((string) preg_replace('/\s+/u', ' ', $c('contact_nom')));
        if (mb_strlen($contact) > 120) {
            $erreurs['contact_nom'] = 'Le nom du contact : 120 caractères au plus.';
        }
        [$adresse, $erreursAdresse] = SaisieAdresse::lire($champs, 'adresse_');
        $erreurs += $erreursAdresse;
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
        $iban = Iban::lire($c('iban'));
        if ($iban === null) {
            $erreurs['iban'] = "Un IBAN suisse, par exemple CH93\u{00A0}0076\u{00A0}2011\u{00A0}6238\u{00A0}5295\u{00A0}7.";
        }
        $taux = preg_match('/^\d{1,4}$/', $c('tva')) === 1 ? (int) $c('tva') : -1;
        if (!in_array($taux, self::TAUX_PERMIS, true)) {
            $erreurs['tva'] = 'Choisissez un taux de TVA.';
        }
        [$conditions, $erreursConditions] = Conditions::depuisSaisie($champs);
        $erreurs += $erreursConditions;
        [$lettre, $erreursLettre] = PhrasesLettre::depuisSaisie($champs, $activite);
        $erreurs += $erreursLettre;
        if ($nom === null || $adresse === null || $ide === null || $iban === null || $conditions === null || $lettre === null || $erreurs !== []) {
            throw new SaisieRefusee($erreurs);
        }
        return (new self($nom, $taux, $arrondi, $adresse, $telephone, $courriel, $ide, iban: $iban, contactNom: $contact, lettre: $lettre))->avecConditions($conditions);
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

    /** La localité de l'entreprise : « Delémont, le… » sur le document, et le sommaire des réglages. */
    public function localite(): string
    {
        return $this->adresse->localite;
    }
}
