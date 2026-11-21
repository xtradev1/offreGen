<?php

declare(strict_types=1);

namespace OffreGen\Tests\Outils;

/**
 * Le texte d'un PDF sorti par dompdf, page par page : les flux sont décompressés, les chaînes (UTF-16BE) recollées
 * dans l'ordre où elles sont dessinées. Suffit pour chercher une mention ; ce n'est pas un lecteur de PDF.
 */
final class TextePdf
{
    /** @return list<string> le texte de chaque page */
    public static function pages(string $pdf): array
    {
        preg_match_all('/stream\r?\n(.*?)\r?\nendstream/s', $pdf, $flux);
        $pages = [];
        foreach ($flux[1] as $brut) {
            $clair = @gzuncompress($brut);
            if ($clair === false || !str_contains($clair, ' TJ')) {
                continue;
            }
            preg_match_all('/\[\(((?:\\\\.|[^\\\\)])*)\)\] TJ/s', $clair, $chaines);
            $texte = '';
            foreach ($chaines[1] as $chaine) {
                $texte .= mb_convert_encoding(stripcslashes($chaine), 'UTF-8', 'UTF-16BE');
            }
            $pages[] = $texte;
        }
        return $pages;
    }
}
