<?php

declare(strict_types=1);

namespace OffreGen\Http;

use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

/**
 * Affichage des montants, quantités et dates, au format de la maquette (de-CH : 1’234.50).
 * Ne calcule rien : les totaux viennent de Totaux.
 */
final class Formats extends AbstractExtension
{
    private const NB = "\u{00A0}";

    public function getFilters(): array
    {
        return [
            new TwigFilter('chf', [self::class, 'chf']),
            new TwigFilter('montant', [self::class, 'montant']),
            new TwigFilter('quantite', [self::class, 'quantite']),
            new TwigFilter('date_locale', [self::class, 'date']),
            new TwigFilter('jour', [self::class, 'jour']),
            new TwigFilter('taux', [self::class, 'taux']),
            new TwigFilter('saisie_quantite', [self::class, 'saisieQuantite']),
            new TwigFilter('saisie_prix', [self::class, 'saisiePrix']),
        ];
    }

    public static function chf(int $centimes): string
    {
        return 'CHF' . self::NB . self::montant($centimes);
    }

    /** Montant sans devise, pour les colonnes de prix : « 1’234.50 ». */
    public static function montant(int $centimes): string
    {
        $f = new \NumberFormatter('de_CH', \NumberFormatter::DECIMAL);
        $f->setAttribute(\NumberFormatter::MIN_FRACTION_DIGITS, 2);
        $f->setAttribute(\NumberFormatter::MAX_FRACTION_DIGITS, 2);
        return (string) $f->format($centimes / 100);
    }

    public static function quantite(int $milliemes): string
    {
        $f = new \NumberFormatter('de_CH', \NumberFormatter::DECIMAL);
        $f->setAttribute(\NumberFormatter::MAX_FRACTION_DIGITS, 3);
        return (string) $f->format($milliemes / 1000);
    }

    /** Date UTC de la base, jour seulement, à l'heure de Zurich : « 07.11.2026 ». */
    public static function jour(string $utc): string
    {
        return (new \DateTimeImmutable($utc, new \DateTimeZone('UTC')))->setTimezone(new \DateTimeZone('Europe/Zurich'))->format('d.m.Y');
    }

    /** Taux en dix-millièmes : 810 donne « 8,1 % ». */
    public static function taux(int $pourDixMille): string
    {
        $f = new \NumberFormatter('fr_CH', \NumberFormatter::DECIMAL);
        $f->setAttribute(\NumberFormatter::MAX_FRACTION_DIGITS, 2);
        return $f->format($pourDixMille / 100) . self::NB . '%';
    }

    /** Valeur d'un champ de quantité : « 12.5 », sans séparateur de milliers, relue par Offre\Saisie. */
    public static function saisieQuantite(int $milliemes): string
    {
        $texte = sprintf('%d.%03d', intdiv($milliemes, 1000), abs($milliemes % 1000));
        return rtrim(rtrim($texte, '0'), '.');
    }

    /** Valeur d'un champ de prix : « 28 », « 28.50 », vide pour un prix manquant. */
    public static function saisiePrix(int $centimes): string
    {
        if ($centimes === 0) {
            return '';
        }
        return $centimes % 100 === 0 ? (string) intdiv($centimes, 100) : sprintf('%d.%02d', intdiv($centimes, 100), abs($centimes % 100));
    }

    /** Date UTC de la base, affichée à l'heure de Zurich : « 07.11.2026 14:05 ». */
    public static function date(string $utc): string
    {
        $d = new \DateTimeImmutable($utc, new \DateTimeZone('UTC'));
        return $d->setTimezone(new \DateTimeZone('Europe/Zurich'))->format('d.m.Y H:i');
    }
}
