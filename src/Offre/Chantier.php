<?php

declare(strict_types=1);

namespace OffreGen\Offre;

/**
 * Ce que l'offre dit du chantier (modèles du bâtiment, décisions C2 et C3 de Xavier, 2026-11-19) : où il est, qui est
 * sur place, comment on entre, quand les travaux commencent et combien ils durent, ce qui n'est pas compris, et la
 * date de la visite. Tout est facultatif : un champ vide ne paraît pas sur le document.
 */
final readonly class Chantier
{
    /** Longueur de chaque champ d'une ligne : celle de sa colonne. */
    private const LONGUEURS = ['chantier_adresse' => 255, 'chantier_contact' => 190, 'chantier_acces' => 255, 'debut_travaux' => 80, 'duree_travaux' => 80];

    public const NON_COMPRIS_MAX = 10;

    /** @param list<string> $nonCompris */
    public function __construct(
        public string $adresse = '',
        public string $contact = '',
        public string $acces = '',
        public string $debut = '',
        public string $duree = '',
        public array $nonCompris = [],
        public ?\DateTimeImmutable $visite = null,
    ) {
    }

    /**
     * Les champs de l'en-tête de l'offre : chantier_adresse, chantier_contact, chantier_acces, debut_travaux,
     * duree_travaux, non_compris (une entrée par ligne), visite_le (AAAA-MM-JJ, celle d'un champ de date).
     *
     * @param array<string, string> $champs
     * @return array{0: self|null, 1: array<string, string>} le chantier, ou les erreurs par champ
     */
    public static function depuisSaisie(array $champs): array
    {
        $erreurs = [];
        $textes = [];
        foreach (self::LONGUEURS as $champ => $max) {
            $saisi = $champs[$champ] ?? '';
            // Un texte mal encodé ferait rendre null à preg_replace : il serait enregistré vide, sans rien dire.
            $textes[$champ] = mb_check_encoding($saisi, 'UTF-8') ? trim((string) preg_replace('/\s+/u', ' ', $saisi)) : '';
            if (!mb_check_encoding($saisi, 'UTF-8')) {
                $erreurs[$champ] = 'Ce texte contient des caractères illisibles.';
            } elseif (mb_strlen($textes[$champ]) > $max) {
                $erreurs[$champ] = "$max caractères au plus.";
            }
        }
        $nonCompris = Saisie::lignes($champs['non_compris'] ?? '', self::NON_COMPRIS_MAX, 1000);
        if ($nonCompris === null) {
            $erreurs['non_compris'] = self::NON_COMPRIS_MAX . ' lignes au plus, 1000 caractères en tout.';
        }
        $saisie = trim($champs['visite_le'] ?? '');
        $visite = $saisie === '' ? null : \DateTimeImmutable::createFromFormat('!Y-m-d', $saisie);
        // Hors de 2000 à 2100, c'est une faute de frappe : « 0001-01-01 » passait, et Lettre imprimait « visite du 3 janvier 1 ».
        if ($visite === false || ($visite !== null && ($visite->format('Y-m-d') !== $saisie || $visite->format('Y') < '2000' || $visite->format('Y') > '2100'))) {
            $erreurs['visite_le'] = 'Une date, par exemple le 12 novembre 2026.';
            $visite = null;
        }
        if ($erreurs !== [] || $nonCompris === null) {
            return [null, $erreurs];
        }
        return [new self($textes['chantier_adresse'], $textes['chantier_contact'], $textes['chantier_acces'], $textes['debut_travaux'], $textes['duree_travaux'],
            $nonCompris === '' ? [] : explode("\n", $nonCompris), $visite), []];
    }

    /** @param array<string, mixed> $offre une ligne de DepotOffres::parId ; sans les colonnes du chantier, un chantier vide */
    public static function depuisOffre(array $offre): self
    {
        $texte = static fn (string $cle): string => is_string($offre[$cle] ?? null) ? $offre[$cle] : '';
        $visite = $texte('visite_le') === '' ? false : \DateTimeImmutable::createFromFormat('!Y-m-d', $texte('visite_le'));
        return new self($texte('chantier_adresse'), $texte('chantier_contact'), $texte('chantier_acces'), $texte('debut_travaux'), $texte('duree_travaux'),
            $texte('non_compris') === '' ? [] : explode("\n", $texte('non_compris')), $visite === false ? null : $visite);
    }

    public function estVide(): bool
    {
        return $this->adresse === '' && $this->contact === '' && $this->acces === '' && $this->debut === '' && $this->duree === ''
            && $this->nonCompris === [] && $this->visite === null;
    }

    /** @return array<string, string> les champs de l'écran, sous leur nom de formulaire */
    public function versChamps(): array
    {
        return ['chantier_adresse' => $this->adresse, 'chantier_contact' => $this->contact, 'chantier_acces' => $this->acces, 'debut_travaux' => $this->debut,
            'duree_travaux' => $this->duree, 'non_compris' => implode("\n", $this->nonCompris), 'visite_le' => $this->visite?->format('Y-m-d') ?? ''];
    }
}
