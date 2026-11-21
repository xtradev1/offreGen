<?php

declare(strict_types=1);

namespace OffreGen\Catalogue;

/** Dit, paire par paire, si deux libellés désignent le même travail, vendu au même prix. */
interface Comparateur
{
    /**
     * @param list<array{string, string}> $paires
     * @return list<bool> une réponse par paire, dans l'ordre ; dans le doute, false
     */
    public function memes(array $paires): array;

    /** @return array{int, int} jetons envoyés et reçus depuis la création, pour le coût de l'import */
    public function jetonsConsommes(): array;
}
