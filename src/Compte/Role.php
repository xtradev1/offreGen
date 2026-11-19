<?php

declare(strict_types=1);

namespace OffreGen\Compte;

/**
 * Les trois rôles de l'équipe (décision R1 de Xavier, 2026-11-19). L'administrateur voit tout ; le modérateur voit
 * tous les artisans pour les aider, sans l'argent ; le commercial ne voit que les artisans qu'il a créés.
 */
enum Role: string
{
    case Administrateur = 'administrateur';
    case Moderateur = 'moderateur';
    case Commercial = 'commercial';

    /** Le mot de la pastille, dans l'en-tête de l'administration. */
    public function libelle(): string
    {
        return match ($this) {
            self::Administrateur => 'Admin',
            self::Moderateur => 'Modérateur',
            self::Commercial => 'Commercial',
        };
    }

    /** Le nom de l'entrée, dans « Plus » et dans le menu de l'application. */
    public function espace(): string
    {
        return match ($this) {
            self::Administrateur => 'Administration',
            self::Moderateur => 'Espace modérateur',
            self::Commercial => 'Espace commercial',
        };
    }

    /** La page ouverte par /admin. */
    public function accueil(): string
    {
        return match ($this) {
            self::Administrateur => '/admin/tableau',
            self::Moderateur => '/admin/a-aider',
            self::Commercial => '/admin/suivi',
        };
    }
}
