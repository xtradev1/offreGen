<?php

declare(strict_types=1);

namespace OffreGen\Audio;

/** Identifiant public d'un enregistrement : impossible à deviner, contrairement à l'id numérique. */
final class Uuid
{
    public static function v4(): string
    {
        $o = random_bytes(16);
        $o[6] = chr((ord($o[6]) & 0x0f) | 0x40);
        $o[8] = chr((ord($o[8]) & 0x3f) | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($o), 4));
    }

    public static function valide(string $uuid): bool
    {
        return preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/', $uuid) === 1;
    }
}
