<?php

declare(strict_types=1);

namespace OffreGen\Systeme;

/**
 * Lance un programme sans shell (aucun nom de fichier n'est interprété) et rend son code et sa sortie.
 *
 * Chez Infomaniak, proc_open est désactivée pour le site web et permise en ligne de commande (relevé du
 * 2026-11-15). Sans elle, rien n'est lancé et le code vaut IMPOSSIBLE : à chaque usage de se replier.
 */
final class Commande
{
    /** proc_open n'existe pas ici : rien n'a été lancé. */
    public const IMPOSSIBLE = -1;
    /** Le programme n'a pas pu démarrer (absent, ou non exécutable). */
    public const ABSENT = 127;

    private readonly bool $possible;

    public function __construct(?bool $possible = null)
    {
        $this->possible = $possible ?? function_exists('proc_open');
    }

    /** @param list<string> $commande */
    public function lancer(array $commande): ResultatCommande
    {
        if (!$this->possible) {
            return new ResultatCommande(self::IMPOSSIBLE, '', '');
        }
        $tubes = [];
        $processus = @proc_open($commande, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $tubes);
        if (!is_resource($processus)) {
            return new ResultatCommande(self::ABSENT, '', '');
        }
        $sortie = (string) stream_get_contents($tubes[1]);
        $erreur = (string) stream_get_contents($tubes[2]);
        fclose($tubes[1]);
        fclose($tubes[2]);
        return new ResultatCommande(proc_close($processus), $sortie, $erreur);
    }
}
