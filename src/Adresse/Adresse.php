<?php

declare(strict_types=1);

namespace OffreGen\Adresse;

/**
 * Une adresse postale en champs séparés, comme l'exige la QR-facture (adresse structurée, type S) : rue, numéro,
 * NPA, localité, pays (code ISO à deux lettres). Toute vide : pas d'adresse. Les valeurs sont déjà contrôlées
 * (SaisieAdresse) ; cette classe ne fait que les porter et les écrire.
 */
final readonly class Adresse
{
    /** Pays proposés à l'écran ; la Suisse d'abord. */
    public const PAYS = ['CH' => 'Suisse', 'LI' => 'Liechtenstein', 'FR' => 'France', 'DE' => 'Allemagne', 'IT' => 'Italie', 'AT' => 'Autriche'];

    /** Longueurs maximales de la norme de la QR-facture. */
    public const MAX = ['rue' => 70, 'numero' => 16, 'npa' => 16, 'localite' => 35];

    public function __construct(
        public string $rue = '',
        public string $numero = '',
        public string $npa = '',
        public string $localite = '',
        public string $pays = 'CH',
    ) {
    }

    public function estVide(): bool
    {
        return $this->rue === '' && $this->numero === '' && $this->npa === '' && $this->localite === '';
    }

    /** Le NPA et la localité servent l'un et l'autre aux registres de Suisse et du Liechtenstein. */
    public function estSuisse(): bool
    {
        return $this->pays === 'CH' || $this->pays === 'LI';
    }

    /** « Rue de l'Exemple 12 » puis « 2800 Delémont », plus le pays hors de Suisse ; vide pour une adresse vide. */
    public function lignes(): string
    {
        if ($this->estVide()) {
            return '';
        }
        $lignes = array_filter([trim($this->rue . ' ' . $this->numero), trim($this->npa . ' ' . $this->localite)], static fn (string $l): bool => $l !== '');
        if ($this->pays !== 'CH') {
            $lignes[] = self::PAYS[$this->pays] ?? $this->pays;
        }
        return implode("\n", $lignes);
    }

    /** Même bâtiment : rue, numéro et NPA égaux sans tenir compte de la casse ni des accents. */
    public function memeQue(self $autre): bool
    {
        return Localites::cle($this->rue) === Localites::cle($autre->rue)
            && Localites::cle($this->numero) === Localites::cle($autre->numero)
            && $this->npa === $autre->npa;
    }

    /**
     * Les colonnes d'une table (préfixe « adresse_ » pour l'artisan, « client_ » pour la facture, aucun pour le
     * client), null pour un champ vide.
     *
     * @return array<string, string|null>
     */
    public function versBase(string $prefixe): array
    {
        $vide = $this->estVide();
        $n = static fn (string $s): ?string => $s === '' ? null : $s;
        return [$prefixe . 'rue' => $n($this->rue), $prefixe . 'numero' => $n($this->numero), $prefixe . 'npa' => $n($this->npa),
                $prefixe . 'localite' => $n($this->localite), $prefixe . 'pays' => $vide ? null : $this->pays];
    }

    /** @param array<string, mixed> $ligne une ligne lue en base, colonnes nommées comme dans versBase() */
    public static function depuisBase(array $ligne, string $prefixe): self
    {
        $t = static fn (string $cle): string => is_string($ligne[$prefixe . $cle] ?? null) ? $ligne[$prefixe . $cle] : '';
        return new self($t('rue'), $t('numero'), $t('npa'), $t('localite'), $t('pays') === '' ? 'CH' : $t('pays'));
    }

    /**
     * Le découpage d'une adresse écrite en texte libre (fiches d'avant les champs séparés, lieu dicté) : la
     * dernière ligne « NPA Localité » (ou une localité seule dont le NPA est unique), la précédente « Rue Numéro ».
     * Rendue seulement si elle passe le contrôle de SaisieAdresse ; null sinon, à reprendre à la main
     * (trois lignes et plus, NPA inconnu, localité qui ne correspond pas).
     */
    public static function depuisTexte(?string $texte): ?self
    {
        $parts = array_values(array_filter(array_map('trim', preg_split('/\R/u', (string) $texte) ?: []), static fn (string $l): bool => $l !== ''));
        if (count($parts) === 1 && str_contains($parts[0], ',')) {
            $parts = array_values(array_filter(array_map('trim', explode(',', $parts[0])), static fn (string $l): bool => $l !== ''));
        }
        if ($parts === [] || count($parts) > 2) {
            return null;
        }
        $derniere = (string) preg_replace('/\s+/u', ' ', $parts[count($parts) - 1]);
        if (preg_match('/^(?:CH\s*-?\s*)?(\d{4})\s+(.+)$/iu', $derniere, $m) === 1) {
            [$npa, $localite] = [$m[1], $m[2]];
        } elseif (($npa = Localites::npaDe($derniere)) !== null) {
            $localite = $derniere;
        } else {
            return null;
        }
        // La rue garde son numéro : SaisieAdresse le sépare (SaisieAdresse::NUMERO_AU_BOUT).
        $rue = count($parts) === 2 ? $parts[0] : '';
        [$adresse, $erreurs] = SaisieAdresse::lire(['rue' => $rue, 'numero' => '', 'npa' => $npa, 'localite' => $localite, 'pays' => 'CH'], '');
        return $erreurs === [] ? $adresse : null;
    }

    public function __toString(): string
    {
        return $this->lignes();
    }
}
