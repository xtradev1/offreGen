<?php

declare(strict_types=1);

namespace OffreGen\Compte;

enum ResultatConnexion
{
    case Reussie;
    case Refusee;
    case TropDEssais;
}
