<?php

declare(strict_types=1);

namespace OffreGen\Offre;

use Dompdf\Dompdf;
use Dompdf\Options;

/**
 * Transforme le HTML du document d'offre en PDF. Le HTML vient de nos gabarits, données échappées par Twig.
 * Verrouillé : aucune ressource distante, pas de PHP dans le HTML, fichiers lus seulement sous $racine.
 */
final class DocumentPdf
{
    public function __construct(private readonly string $racine, private readonly string $dossierTemporaire)
    {
    }

    public function rendre(string $html): string
    {
        return (string) $this->dompdf($html)->output();
    }

    /** Le nombre de pages du rendu, sans produire le PDF : premier des deux rendus d'une facture (placement de la section de paiement). */
    public function pages(string $html): int
    {
        return $this->dompdf($html)->getCanvas()->get_page_count();
    }

    private function dompdf(string $html): Dompdf
    {
        if (!is_dir($this->dossierTemporaire)) {
            mkdir($this->dossierTemporaire, 0700, true);
        }
        $options = new Options();
        $options->setIsRemoteEnabled(false);
        $options->setIsPhpEnabled(false);
        $options->setIsJavascriptEnabled(false);
        $options->setChroot([$this->racine]);
        $options->setTempDir($this->dossierTemporaire);
        $options->setFontCache($this->dossierTemporaire);
        $options->setDefaultFont('DejaVu Sans');
        $options->setDefaultPaperSize('a4');
        $pdf = new Dompdf($options);
        $pdf->loadHtml($html, 'UTF-8');
        $pdf->render();
        return $pdf;
    }
}
