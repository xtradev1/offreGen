<?php

declare(strict_types=1);

namespace OffreGen\Donnees;

/**
 * Conversion des dates entre PHP et la base : toujours en UTC, au format DATETIME.
 */
final class Dates
{
    public const FORMAT = 'Y-m-d H:i:s';

    public static function versBase(\DateTimeImmutable $d): string
    {
        return $d->setTimezone(new \DateTimeZone('UTC'))->format(self::FORMAT);
    }

    public static function depuisBase(string $d): \DateTimeImmutable
    {
        $date = \DateTimeImmutable::createFromFormat(self::FORMAT, $d, new \DateTimeZone('UTC'));
        if ($date === false) {
            throw new \UnexpectedValueException("Date illisible : $d");
        }
        return $date;
    }
}
