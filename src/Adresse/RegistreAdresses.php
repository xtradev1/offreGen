<?php

declare(strict_types=1);

namespace OffreGen\Adresse;

/**
 * Le registre officiel des adresses de bâtiments, interrogé pendant la frappe (choix de Xavier du 2026-11-13 :
 * « Tu tapes ton adresse et puis ça te dit oui, tu es ici ou pas »).
 */
interface RegistreAdresses
{
    /**
     * Les adresses qui commencent comme le texte tapé, la plus probable d'abord.
     *
     * @return list<Adresse>
     * @throws RegistreIndisponible si le service ne répond pas ou répond mal
     */
    public function chercher(string $texte, int $nombre): array;
}
