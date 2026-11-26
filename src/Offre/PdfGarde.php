<?php

declare(strict_types=1);

namespace OffreGen\Offre;

/**
 * Le PDF d'une offre, gardé tant que son contenu ne change pas (chantier sans rechargement, lot 5). dompdf le fabriquait
 * à chaque ouverture de l'aperçu, une à trois fois (pages comptées, mise en page resserrée). L'empreinte couvre le HTML
 * du document (lignes, totaux, marque, polices, feuille de style) et le code qui le rend : un changement de l'un ou de
 * l'autre fait un nouveau PDF, sans rien à vider à la main. Rangé par artisan dans var/cache/offres-pdf, hors de public/ ; une
 * offre n'y garde que son dernier PDF.
 */
final class PdfGarde
{
    public function __construct(private readonly string $dossier, private readonly string $versionDuCode)
    {
    }

    public function empreinte(string $html): string
    {
        return substr(hash('xxh3', $this->versionDuCode . "\0" . $html), 0, 16);
    }

    /** @param \Closure(): string $fabriquer le rendu par dompdf, quand le PDF n'est pas gardé */
    public function obtenir(int $artisanId, int $offreId, string $empreinte, \Closure $fabriquer): string
    {
        $dossier = $this->dossier . '/' . $artisanId;
        $fichier = $dossier . '/' . $offreId . '-' . $empreinte . '.pdf';
        if (is_file($fichier)) {
            $garde = file_get_contents($fichier);
            if (is_string($garde) && $garde !== '') {
                return $garde;
            }
        }
        $pdf = $fabriquer();
        // Le PDF sert même si le disque refuse de le garder : le prochain sera refait.
        if (!is_dir($dossier) && !@mkdir($dossier, 0700, true) && !is_dir($dossier)) {
            return $pdf;
        }
        foreach (glob($dossier . '/' . $offreId . '-*.pdf') ?: [] as $ancien) {
            @unlink($ancien);
        }
        // Écrit à côté puis renommé : un rendu concurrent ne lit jamais un PDF à moitié écrit. Raté, le provisoire part.
        $provisoire = $fichier . '.' . bin2hex(random_bytes(4)) . '.tmp';
        if (@file_put_contents($provisoire, $pdf) === false || !@rename($provisoire, $fichier)) {
            @unlink($provisoire);
        }
        return $pdf;
    }
}
