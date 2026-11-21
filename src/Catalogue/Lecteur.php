<?php

declare(strict_types=1);

namespace OffreGen\Catalogue;

/** Lit une ancienne offre (son texte, ligne par ligne) et en relève les lignes chiffrées : un candidat au catalogue chacune. */
interface Lecteur
{
    /** @param list<string> $texte les lignes du PDF, dans l'ordre de lecture */
    public function lire(array $texte): LectureOffre;
}
