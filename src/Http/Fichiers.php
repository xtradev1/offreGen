<?php

declare(strict_types=1);

namespace OffreGen\Http;

/**
 * Adresse des fichiers de public/ (feuilles de style, scripts, images), suivie de l'empreinte de leur contenu
 * (fonction Twig fichier()). Un fichier changé change d'adresse : le navigateur garde chaque fichier un an
 * (public/.htaccess) et prend le nouveau dès la page suivante, sans version à tenir à la main (Xavier, D1 du
 * chantier sans rechargement, 2026-11-23 : « je veux pas devoir à chaque fois gérer les emmerdes avec le cache »).
 */
final class Fichiers
{
    /** @var array<string, string> chemin => empreinte, une lecture par fichier et par requête */
    private array $empreintes = [];

    public function __construct(private readonly string $dossierPublic)
    {
    }

    public function adresse(string $chemin): string
    {
        if (preg_match('#^/[A-Za-z0-9._/-]+$#', $chemin) !== 1 || str_contains($chemin, '..')) {
            throw new \InvalidArgumentException("Chemin de fichier refusé : $chemin");
        }
        if (!isset($this->empreintes[$chemin])) {
            $fichier = $this->dossierPublic . $chemin;
            $empreinte = is_file($fichier) ? hash_file('xxh3', $fichier) : false;
            // Un fichier absent garde son adresse nue : la page s'affiche, le 404 se voit dans le navigateur.
            $this->empreintes[$chemin] = $empreinte === false ? '' : substr($empreinte, 0, 12);
        }
        return $this->empreintes[$chemin] === '' ? $chemin : $chemin . '?v=' . $this->empreintes[$chemin];
    }
}
