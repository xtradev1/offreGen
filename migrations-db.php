<?php

declare(strict_types=1);

use OffreGen\Donnees\FabriqueConnexion;
use OffreGen\Http\Conteneur;

// Connexion des migrations : utilisateur de structure, base choisie par APP_ENV
// (APP_ENV=test vendor/bin/doctrine-migrations migrate vise la base de test).
require_once __DIR__ . '/vendor/autoload.php';

return (new FabriqueConnexion(Conteneur::lireEnvironnement(__DIR__)))->parametresMigrations();
