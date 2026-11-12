<?php

declare(strict_types=1);

namespace OffreGen\Compte;

/**
 * Le logo envoyé par l'artisan devient un JPEG propre : PNG ou JPEG seulement (un SVG peut porter du script),
 * réencodé par ffmpeg (rien du fichier d'origine ne passe tel quel), réduit à 600 x 240 au plus, fond
 * transparent aplati sur blanc. Le JPEG s'intègre au PDF sans l'extension GD, absente du serveur.
 */
final class TraitementLogo
{
    public const TAILLE_MAX = 5 * 1024 * 1024;
    private const COTE_MAX = 6000;
    private const TYPES = [IMAGETYPE_PNG => 'image/png', IMAGETYPE_JPEG => 'image/jpeg'];

    public function __construct(private readonly string $ffmpeg = 'ffmpeg', private readonly int $tailleMax = self::TAILLE_MAX)
    {
    }

    /** @throws LogoRefuse */
    public function preparer(string $source, string $destination): void
    {
        $taille = is_file($source) ? (int) filesize($source) : 0;
        if ($taille > $this->tailleMax) {
            throw new LogoRefuse('Le logo pèse plus de ' . intdiv(self::TAILLE_MAX, 1024 * 1024) . ' Mo.');
        }
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($source);
        $image = @getimagesize($source);
        if ($taille === 0 || $image === false || !isset(self::TYPES[$image[2]]) || self::TYPES[$image[2]] !== $mime) {
            throw new LogoRefuse('Le logo doit être une image PNG ou JPEG.');
        }
        if ($image[0] > self::COTE_MAX || $image[1] > self::COTE_MAX) {
            throw new LogoRefuse('Le logo est trop grand : ' . self::COTE_MAX . ' pixels de côté au plus.');
        }
        // Composé en RVB puis converti en pleine plage : sinon le blanc sortait teinté (255, 253, 255).
        $filtre = "[0:v]scale=w='min(600,iw)':h='min(240,ih)':force_original_aspect_ratio=decrease,format=rgba[l];"
            . 'color=c=white,format=rgb24[c];[c][l]scale2ref[c2][l2];[c2][l2]overlay=format=rgb,format=rgb24,scale=out_range=full,format=yuvj444p';
        $code = $this->executer([$this->ffmpeg, '-v', 'error', '-y', '-i', $source, '-filter_complex', $filtre,
            '-frames:v', '1', '-q:v', '2', '-f', 'image2', '-c:v', 'mjpeg', $destination], $sortie);
        $resultat = is_file($destination) ? @getimagesize($destination) : false;
        if ($code !== 0 || $resultat === false || $resultat[2] !== IMAGETYPE_JPEG) {
            if (is_file($destination)) {
                unlink($destination);
            }
            throw new LogoRefuse('Le logo n\'a pas pu être lu. Essayez une autre image PNG ou JPEG.');
        }
    }

    /**
     * La couleur du logo : la plus fréquente parmi les pixels colorés (ni blancs, ni gris). Null s'il n'y a
     * que du noir, du blanc et du gris : la couleur par défaut reste alors.
     */
    public function couleurDominante(string $jpeg, int $cote = 32): ?string
    {
        $code = $this->executer([$this->ffmpeg, '-v', 'error', '-i', $jpeg, '-vf', "scale=$cote:$cote:flags=neighbor",
            '-f', 'rawvideo', '-pix_fmt', 'rgb24', '-'], $octets);
        if ($code !== 0 || strlen($octets) < 3) {
            return null;
        }
        $cases = [];
        for ($i = 0; $i + 2 < strlen($octets); $i += 3) {
            [$r, $v, $b] = [ord($octets[$i]), ord($octets[$i + 1]), ord($octets[$i + 2])];
            if (max($r, $v, $b) - min($r, $v, $b) < 40) {
                continue;
            }
            $cle = ($r >> 4) . ',' . ($v >> 4) . ',' . ($b >> 4);
            $cases[$cle] ??= [0, 0, 0, 0];
            $cases[$cle] = [$cases[$cle][0] + 1, $cases[$cle][1] + $r, $cases[$cle][2] + $v, $cases[$cle][3] + $b];
        }
        if ($cases === []) {
            return null;
        }
        usort($cases, static fn (array $x, array $y): int => $y[0] <=> $x[0]);
        [$n, $r, $v, $b] = $cases[0];
        return sprintf('#%02X%02X%02X', (int) round($r / $n), (int) round($v / $n), (int) round($b / $n));
    }

    /**
     * @param list<string> $commande sans shell : aucun nom de fichier n'est interprété
     * @param-out string $sortie ce que la commande a écrit
     */
    private function executer(array $commande, ?string &$sortie): int
    {
        $tubes = [];
        $processus = @proc_open($commande, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $tubes);
        if (!is_resource($processus)) {
            $sortie = '';
            return 127;
        }
        $sortie = (string) stream_get_contents($tubes[1]);
        stream_get_contents($tubes[2]);
        fclose($tubes[1]);
        fclose($tubes[2]);
        return proc_close($processus);
    }
}
