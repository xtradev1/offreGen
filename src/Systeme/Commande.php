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
    /** Arrêté au bout du délai (revue du 2026-11-18, H2 : un fichier piégé bloquait ffmpeg pour toujours). */
    public const DEPASSE = -2;
    /** Le programme n'a pas pu démarrer (absent, ou non exécutable). */
    public const ABSENT = 127;
    /** Délai par défaut, en secondes. */
    public const DELAI = 120;

    private readonly bool $possible;

    public function __construct(?bool $possible = null)
    {
        $this->possible = $possible ?? function_exists('proc_open');
    }

    /**
     * Les deux sorties sont lues en même temps : un programme qui remplit d'abord sa sortie d'erreur ne reste pas
     * bloqué. Au-delà du délai, il est arrêté et le code vaut DEPASSE.
     *
     * @param list<string> $commande
     */
    public function lancer(array $commande, int $delaiSecondes = self::DELAI, ?\Closure $pendant = null): ResultatCommande
    {
        if (!$this->possible) {
            return new ResultatCommande(self::IMPOSSIBLE, '', '');
        }
        $tubes = [];
        $processus = @proc_open($commande, [0 => ['file', '/dev/null', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $tubes);
        if (!is_resource($processus)) {
            return new ResultatCommande(self::ABSENT, '', '');
        }
        stream_set_blocking($tubes[1], false);
        stream_set_blocking($tubes[2], false);
        $lu = [1 => '', 2 => ''];
        $ouverts = [1 => $tubes[1], 2 => $tubes[2]];
        $limite = microtime(true) + max(1, $delaiSecondes);
        while ($ouverts !== []) {
            $reste = $limite - microtime(true);
            if ($reste <= 0) {
                proc_terminate($processus, 9);
                foreach ($ouverts as $tube) {
                    fclose($tube);
                }
                proc_close($processus);
                return new ResultatCommande(self::DEPASSE, $lu[1], $lu[2]);
            }
            // $pendant : appelé au moins toutes les 5 s pendant que la commande tourne (pouls du verrou, sous-chantier 31,
            // revue T1). S'il lève une exception, la commande est arrêtée et l'exception remonte.
            if ($pendant !== null) {
                try {
                    $pendant();
                } catch (\Throwable $e) {
                    proc_terminate($processus, 9);
                    foreach ($ouverts as $tube) {
                        fclose($tube);
                    }
                    proc_close($processus);
                    throw $e;
                }
                $reste = min($reste, 5.0);
            }
            $lecture = array_values($ouverts);
            $rien = null;
            $prets = @stream_select($lecture, $rien, $rien, (int) $reste, (int) (fmod($reste, 1) * 1_000_000));
            if ($prets === false) {
                break;
            }
            foreach ($ouverts as $n => $tube) {
                if (!in_array($tube, $lecture, true)) {
                    continue;
                }
                $morceau = fread($tube, 65536);
                if ($morceau !== false && $morceau !== '') {
                    $lu[$n] .= $morceau;
                } elseif (feof($tube)) {
                    fclose($tube);
                    unset($ouverts[$n]);
                }
            }
        }
        foreach ($ouverts as $tube) {
            fclose($tube);
        }
        return new ResultatCommande(proc_close($processus), $lu[1], $lu[2]);
    }
}
