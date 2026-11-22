<?php

declare(strict_types=1);

namespace OffreGen\Compte;

use OffreGen\Offre\Saisie;

/**
 * Les conditions d'une offre : validité, paiement, escompte. Celles du compte (Réglages) remplissent chaque offre ; une
 * offre peut avoir les siennes (décision D5 A de Xavier, 2026-11-21), gardées en JSON sur l'offre. Sans conditions à
 * elle, une offre suit celles du compte, comme avant ce chantier.
 *
 * Escompte : un pourcentage du montant à payer si le client paie dans le délai ; 0, pas d'escompte.
 */
final readonly class Conditions
{
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
        public int $validiteJours = 30,
        public string $paiementType = 'jours',
        public int $paiementJours = 30,
        public array $paiementAcomptes = [],
        public int $escomptePourDixMille = 0,
        public int $escompteJours = 0,
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

    /** Le paiement en quelques mots, pour un sommaire (une ligne de téléphone). */
    public function resumePaiement(): string
    {
        return match ($this->paiementType) {
            'net' => "net à {$this->paiementJours} jours",
            'acomptes' => 'acomptes ' . implode(' / ', $this->paiementAcomptes) . ' %',
            default => "à {$this->paiementJours} jours",
        };
    }

    public function aEscompte(): bool
    {
        return $this->escomptePourDixMille > 0 && $this->escompteJours > 0;
    }

    /** « Escompte 2 % à 10 jours. », ou null sans escompte. */
    public function phraseEscompte(): ?string
    {
        if (!$this->aEscompte()) {
            return null;
        }
        return 'Escompte ' . self::pourcent($this->escomptePourDixMille) . "\u{00A0}% à {$this->escompteJours} jours.";
    }

    /** 200 donne « 2 », 150 « 1,5 ». */
    public static function pourcent(int $pourDixMille): string
    {
        $texte = sprintf('%d.%02d', intdiv($pourDixMille, 100), $pourDixMille % 100);
        return str_replace('.', ',', rtrim(rtrim($texte, '0'), '.'));
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

    /**
     * Les champs du formulaire des conditions (Réglages, ou la fenêtre de l'offre) : validite_choix et validite_jours,
     * paiement_type, paiement_jours_choix et paiement_jours, paiement_acomptes_choix et paiement_acomptes,
     * escompte_pourcent et escompte_jours. Un « …_choix » proposé l'emporte ; « autre » ou absent : la valeur tapée.
     * Escompte vide : pas d'escompte.
     *
     * @param array<string, string> $champs
     * @return array{self|null, array<string, string>}
     */
    public static function depuisSaisie(array $champs): array
    {
        $c = static fn (string $cle): string => $champs[$cle] ?? '';
        $erreurs = [];
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
        $escompte = 0;
        $escompteJours = 0;
        if (trim($c('escompte_pourcent')) !== '') {
            // Deux décimales, comme un prix : 1.5 % se lit 150 dix-millièmes.
            $e = Saisie::centimes($c('escompte_pourcent'));
            if ($e === null || $e <= 0 || $e >= 10000) {
                $erreurs['escompte_pourcent'] = "Un escompte de plus de 0 et de moins de 100\u{00A0}%, par exemple 2.";
            } else {
                $escompte = $e;
            }
            $escompteJours = self::entier($c('escompte_jours'), 1, 120) ?? 0;
            if ($escompteJours === 0) {
                $erreurs['escompte_jours'] = "Le délai de l'escompte : de 1 à 120 jours.";
            }
        }
        if ($validite === null || $jours === null || $acomptes === null || $erreurs !== []) {
            return [null, $erreurs];
        }
        return [new self($validite, $type, $jours, $acomptes, $escompte, $escompteJours), []];
    }

    /** @return array<string, string> les conditions sous la forme des champs du formulaire */
    public function versChamps(): array
    {
        $acomptes = implode(',', $this->paiementAcomptes);
        $proposes = array_map(static fn (array $a): string => implode(',', $a), self::ACOMPTES_PROPOSES);
        return [
            // Une valeur proposée se montre cochée ; une autre, sous « Autre ».
            'validite_choix' => in_array($this->validiteJours, self::VALIDITES_PROPOSEES, true) ? (string) $this->validiteJours : 'autre',
            'validite_jours' => (string) $this->validiteJours,
            'paiement_type' => $this->paiementType,
            'paiement_jours_choix' => in_array($this->paiementJours, self::DELAIS_PROPOSES, true) ? (string) $this->paiementJours : 'autre',
            'paiement_jours' => (string) $this->paiementJours,
            'paiement_acomptes_choix' => $acomptes === '' ? '30,70' : (in_array($acomptes, $proposes, true) ? $acomptes : 'autre'),
            'paiement_acomptes' => str_replace(',', ', ', $acomptes),
            'escompte_pourcent' => $this->aEscompte() ? str_replace(',', '.', self::pourcent($this->escomptePourDixMille)) : '',
            'escompte_jours' => $this->aEscompte() ? (string) $this->escompteJours : '',
        ];
    }

    /**
     * Les conditions gardées sur une offre, ou celles du compte si elle n'en a pas. Un JSON abîmé vaut « pas de
     * conditions à elle » : l'offre s'ouvre toujours.
     */
    public static function depuisJson(?string $json, self $duCompte): self
    {
        if ($json === null || $json === '') {
            return $duCompte;
        }
        $champs = json_decode($json, true);
        if (!is_array($champs)) {
            return $duCompte;
        }
        $texte = [];
        foreach ($champs as $cle => $valeur) {
            if (is_string($cle) && (is_string($valeur) || is_int($valeur))) {
                $texte[$cle] = (string) $valeur;
            }
        }
        [$c] = self::depuisSaisie($texte);
        return $c ?? $duCompte;
    }

    /** Le JSON gardé sur l'offre : les champs du formulaire, relus par depuisSaisie. */
    public function versJson(): string
    {
        $champs = $this->versChamps();
        // Le choix « autre » et la valeur tapée suffisent : la relecture retrouve la même valeur.
        return json_encode(['validite_choix' => 'autre', 'validite_jours' => $champs['validite_jours'], 'paiement_type' => $this->paiementType,
            'paiement_jours_choix' => 'autre', 'paiement_jours' => $champs['paiement_jours'],
            'paiement_acomptes_choix' => 'autre', 'paiement_acomptes' => $champs['paiement_acomptes'],
            'escompte_pourcent' => $champs['escompte_pourcent'], 'escompte_jours' => $champs['escompte_jours']], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
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
}
