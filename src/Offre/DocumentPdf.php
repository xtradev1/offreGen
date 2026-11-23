<?php

declare(strict_types=1);

namespace OffreGen\Offre;

use Dompdf\Dompdf;
use Dompdf\Options;

/**
 * Transforme le HTML du document d'offre en PDF. Le HTML vient de nos gabarits, données échappées par Twig.
 * Verrouillé : aucune ressource distante, pas de PHP dans le HTML, fichiers lus seulement sous $racine et dans le
 * dossier des polices.
 */
final class DocumentPdf
{
    public function __construct(private readonly string $racine, private readonly string $dossierTemporaire, private readonly string $polices)
    {
    }

    /**
     * Les faces des modèles du bâtiment (2026-11-20) : famille => fichier de public/fonts, sans extension => graisse et
     * style. Fichiers statiques complets (sources et empreintes : public/fonts/SOURCES.md) : dompdf ne lit ni le WOFF2
     * ni les polices variables, et un sous-ensemble latin n'a pas š, č, ć, đ, ž, ł, ř, ğ, ş. Une graisse demandée sans
     * sa face retombe sur DejaVu Sans (essai du 2026-11-20) : la feuille n'emploie que les graisses listées ici.
     * Montants en Roboto, IBM Plex ou Source Serif 4, dont les chiffres ont tous la même largeur.
     *
     * @var array<string, array<string, array{int, string}>>
     */
    public const FACES = [
        'Roboto' => ['roboto-400' => [400, 'normal'], 'roboto-500' => [500, 'normal'], 'roboto-700' => [700, 'normal'], 'roboto-400-italic' => [400, 'italic']],
        'Archivo' => ['archivo-600' => [600, 'normal'], 'archivo-700' => [700, 'normal'], 'archivo-800' => [800, 'normal']],
        'Barlow Condensed' => ['barlow-condensed-500' => [500, 'normal'], 'barlow-condensed-600' => [600, 'normal'], 'barlow-condensed-700' => [700, 'normal']],
        'IBM Plex Sans' => ['ibm-plex-sans-400' => [400, 'normal'], 'ibm-plex-sans-500' => [500, 'normal'], 'ibm-plex-sans-600' => [600, 'normal'], 'ibm-plex-sans-700' => [700, 'normal'],
            'ibm-plex-sans-400-italic' => [400, 'italic']],
        'IBM Plex Mono' => ['ibm-plex-mono-400' => [400, 'normal'], 'ibm-plex-mono-500' => [500, 'normal'], 'ibm-plex-mono-600' => [600, 'normal'], 'ibm-plex-mono-700' => [700, 'normal'],
            'ibm-plex-mono-400-italic' => [400, 'italic']],
        'Source Serif 4' => ['source-serif-4-400' => [400, 'normal'], 'source-serif-4-600' => [600, 'normal'], 'source-serif-4-700' => [700, 'normal'],
            'source-serif-4-400-italic' => [400, 'italic'], 'source-serif-4-600-italic' => [600, 'italic']],
    ];

    /** Les polices du document, en règles @font-face. Chemins absolus : la feuille est en ligne, sans adresse de base. */
    public function policesCss(): string
    {
        $css = '';
        foreach (self::FACES as $famille => $faces) {
            foreach ($faces as $fichier => [$graisse, $style]) {
                $css .= "@font-face { font-family: '$famille'; font-style: $style; font-weight: $graisse; src: url('file://$this->polices/$fichier.ttf') format('truetype'); }\n";
            }
        }
        return $css;
    }

    public function rendre(string $html): string
    {
        return $this->rendreEtCompter($html)[0];
    }

    /** @return array{string, int} le PDF et son nombre de pages */
    public function rendreEtCompter(string $html): array
    {
        $pdf = $this->dompdf($html);
        return [(string) $pdf->output(), $pdf->getCanvas()->get_page_count()];
    }

    /** Le nombre de pages du rendu, sans produire le PDF : premier des deux rendus d'une facture (placement de la section de paiement). */
    public function pages(string $html): int
    {
        return $this->dompdf($html)->getCanvas()->get_page_count();
    }

    /**
     * Où tombe chaque bloc après le rendu, pour les tests des modèles : un bloc mal placé (signature sur le pied,
     * colonne absente d'une page) se refuse sans ouvrir le PDF. Relevé au fil du rendu : dompdf ne garde pas les
     * positions une fois la page finie.
     *
     * @param list<string> $classes classes CSS à relever
     * @return array<string, list<array{page: int, haut: float, bas: float, gauche: float, droite: float}>> en points, depuis le coin haut gauche de la page
     */
    public function blocs(string $html, array $classes): array
    {
        $vus = array_fill_keys($classes, []);
        $this->dompdf($html, static function (Dompdf $pdf) use (&$vus, $classes): void {
            $pdf->setCallbacks([['event' => 'end_frame', 'f' => static function (\Dompdf\Frame $cadre) use (&$vus, $pdf, $classes): void {
                $noeud = $cadre->get_node();
                if (!$noeud instanceof \DOMElement) {
                    return;
                }
                $b = $cadre->get_border_box();
                foreach (array_intersect($classes, explode(' ', $noeud->getAttribute('class'))) as $classe) {
                    $vus[$classe][] = ['page' => $pdf->getCanvas()->get_page_number(), 'haut' => (float) $b['y'], 'bas' => (float) ($b['y'] + $b['h']),
                        'gauche' => (float) $b['x'], 'droite' => (float) ($b['x'] + $b['w'])];
                }
            }]]);
        });
        return $vus;
    }

    /**
     * Le dossier où dompdf range ses copies des polices et leurs mesures. Il garde chaque copie sous le nom de son
     * adresse, sans regarder son contenu : un fichier remplacé sous le même nom resterait servi par l'ancienne copie
     * (vu le 2026-11-20 avec archivo-700.ttf). Le dossier porte donc l'empreinte des tailles des fichiers : un jeu de
     * polices changé repart d'un dossier neuf.
     */
    private function cacheDesPolices(): string
    {
        $tailles = array_map(static fn (string $f): int => (int) @filesize($f), glob($this->polices . '/*.ttf') ?: []);
        $dossier = $this->dossierTemporaire . '/polices-' . hash('crc32b', implode(',', $tailles));
        // Deux requêtes peuvent le créer au même instant : la seconde trouve le dossier fait.
        if (!is_dir($dossier) && !@mkdir($dossier, 0700, true) && !is_dir($dossier)) {
            throw new \RuntimeException("Dossier des polices impossible à créer : $dossier");
        }
        return $dossier;
    }

    /**
     * Construit le document et le rend. Tant que le dossier des polices n'est pas complet (premier rendu après une
     * mise en ligne, ou après un changement de police), un verrou fait passer les rendus un par un : dompdf écrit ses
     * copies de polices et leur registre sans verrou, et un rendu lancé au même instant lirait un fichier à moitié écrit
     * (revue du 2026-11-20). Chaque document déclare toutes les faces : après un rendu réussi, le dossier est complet.
     *
     * @param (\Closure(Dompdf): void)|null $avant réglages de plus, posés avant le rendu
     */
    private function dompdf(string $html, ?\Closure $avant = null): Dompdf
    {
        $dossier = $this->cacheDesPolices();
        $verrou = is_file("$dossier/pret") ? false : fopen("$dossier/verrou", 'c');
        if ($verrou !== false) {
            flock($verrou, LOCK_EX);
        }
        try {
            $pdf = $this->preparer($html);
            if ($avant !== null) {
                $avant($pdf);
            }
            $pdf->render();
            if ($verrou !== false) {
                touch("$dossier/pret");
            }
            return $pdf;
        } finally {
            if ($verrou !== false) {
                flock($verrou, LOCK_UN);
                fclose($verrou);
            }
        }
    }

    private function preparer(string $html): Dompdf
    {
        $options = new Options();
        $options->setIsRemoteEnabled(false);
        $options->setIsPhpEnabled(false);
        $options->setIsJavascriptEnabled(false);
        $options->setChroot([$this->racine, $this->polices]);
        $options->setTempDir($this->dossierTemporaire);
        // Sans dossier de polices à nous, dompdf copie chaque face dans son propre dossier, sous vendor/ : partagé entre
        // les versions du code, et en lecture seule sur un hébergement bien réglé.
        $options->setFontDir($this->cacheDesPolices());
        $options->setFontCache($this->cacheDesPolices());
        $options->setDefaultFont('DejaVu Sans');
        $options->setDefaultPaperSize('a4');
        $pdf = new Dompdf($options);
        $pdf->loadHtml($html, 'UTF-8');
        return $pdf;
    }
}
