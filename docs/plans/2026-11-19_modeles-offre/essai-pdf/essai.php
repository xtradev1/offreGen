<?php

declare(strict_types=1);

// Essai du 2026-11-20 : les feuilles de la maquette passées par dompdf, avec les réglages de DocumentPdf
// (aucune ressource distante, pas de PHP dans le HTML, fichiers lus sous ce dossier seulement).
// Preuve, pas du code à reprendre. Lancer : php docs/plans/2026-11-19_modeles-offre/essai-pdf/essai.php

use Dompdf\Dompdf;
use Dompdf\Options;

$ici = __DIR__;
require $ici . '/../../../../vendor/autoload.php';

$tmp = sys_get_temp_dir() . '/essai-modeles-' . getmypid();
mkdir($tmp, 0700, true);
@mkdir($ici . '/pdf');

$nouveau = static function () use ($ici, $tmp): Dompdf {
    $o = new Options();
    $o->setIsRemoteEnabled(false);
    $o->setIsPhpEnabled(false);
    $o->setIsJavascriptEnabled(false);
    $o->setChroot([$ici]);
    $o->setTempDir($tmp);
    $o->setFontCache($tmp);
    $o->setDefaultFont('DejaVu Sans');
    $o->setDefaultPaperSize('a4');
    return new Dompdf($o);
};

echo 'dompdf, sous-ensemble des polices : ', (new Options())->getIsFontSubsettingEnabled() ? 'oui' : 'non', ' ; dpi ', (new Options())->getDpi(), "\n";

foreach (glob($ici . '/html/*.html') as $fichier) {
    $nom = basename($fichier, '.html');
    $debut = microtime(true);
    $pdf = $nouveau();
    $pdf->loadHtml((string) file_get_contents($fichier), 'UTF-8');
    $pdf->render();
    $canvas = $pdf->getCanvas();
    $pages = $canvas->get_page_count();
    if (str_ends_with($nom, '-long')) {
        // « Page n / N » : dompdf ne connaît le nombre de pages qu'après le rendu.
        $police = $pdf->getFontMetrics()->getFont('Roboto', 'normal');
        $canvas->page_text(497, 812, 'Page {PAGE_NUM} / {PAGE_COUNT}', $police, 7.1, [0.35, 0.38, 0.42]);
    }
    $sortie = (string) $pdf->output();
    file_put_contents("$ici/pdf/$nom.pdf", $sortie);
    printf("%-16s %2d page(s) %4d Ko %5.1f s\n", $nom, $pages, strlen($sortie) / 1024, microtime(true) - $debut);
}

// Chasse des chiffres mesurée par dompdf lui-même : même largeur pour « 1111 » et « 0000 » = montants alignés.
$pdf = $nouveau();
$pdf->loadHtml('<style>' . implode("\n", array_filter(explode("\n", (string) file_get_contents($ici . '/html/sobre.html')), static fn (string $l): bool => str_starts_with($l, '@font-face')))
    . "@font-face { font-family: 'Inter'; font-style: normal; font-weight: 400; src: url('file://$ici/polices/inter-400.ttf') format('truetype'); }</style><p>x</p>", 'UTF-8');
$pdf->render();
$m = $pdf->getFontMetrics();
foreach (['Inter', 'Roboto', 'Archivo', 'Barlow Condensed', 'IBM Plex Sans', 'IBM Plex Mono', 'Source Serif 4'] as $famille) {
    $f = $m->getFont($famille, $famille === 'Archivo' ? 'bold' : 'normal');
    printf("%-18s %-40s 1111 = %5.2f pt, 0000 = %5.2f pt\n", $famille, basename((string) $f), $m->getTextWidth('1111', $f, 10), $m->getTextWidth('0000', $f, 10));
}
array_map('unlink', glob("$tmp/*") ?: []);
@rmdir($tmp);
