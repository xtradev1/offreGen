<?php

declare(strict_types=1);

namespace OffreGen\Http\Controleur;

use OffreGen\Http\PageIntrouvable;
use Symfony\Component\HttpFoundation\Response;

/**
 * La page où un correcteur relit les textes de Dicta (chantier 2026-11-24_relecture-textes) : captures des écrans et
 * textes numérotés, construite hors de l'application (relecture/index.html, public/relecture/). Une page statique :
 * aucune donnée lue ni écrite. Les corrections restent dans le navigateur du correcteur.
 *
 * La route n'existe que là où RELECTURE_TEXTES=1 (le site de test, config/routes.php) : la production ne la sert pas.
 */
final class TextesARelireControleur
{
    public function __construct(private readonly string $fichier)
    {
    }

    public function page(): Response
    {
        $html = is_file($this->fichier) ? file_get_contents($this->fichier) : false;
        if ($html === false) {
            throw new PageIntrouvable();
        }
        return new Response($html, 200, ['Content-Type' => 'text/html; charset=UTF-8', 'X-Robots-Tag' => 'noindex, nofollow']);
    }
}
