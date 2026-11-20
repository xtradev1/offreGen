<?php

declare(strict_types=1);

namespace OffreGen\Tests\Offre;

use OffreGen\Compte\Marque;
use OffreGen\Http\Formats;
use PHPUnit\Framework\TestCase;

/**
 * Règles du document que la revue du 2026-11-20 a fait poser (docs/plans/2026-11-19_modeles-offre/REVUE.md).
 */
final class ReglesDuDocumentTest extends TestCase
{
    private const RACINE = __DIR__ . '/../..';

    /**
     * document.css se charge dans les écrans de l'application (document à côté, « Ma marque », Facturer) : une classe
     * du document qui porte le nom d'une classe de l'application la déforme. Vu à la revue : la colonne de Latéral
     * s'appelait « doc-cote », comme le bouton « Document à côté », qui en sortait de son en-tête.
     */
    public function testLesClassesDuDocumentNeServentQuAuDocument(): void
    {
        $feuille = (string) preg_replace('#/\*.*?\*/#s', '', (string) file_get_contents(self::RACINE . '/public/css/document.css'));
        preg_match_all('/\.([a-zA-Z][\w-]*)/', $feuille, $trouvees);
        $classes = array_values(array_unique($trouvees[1]));
        $application = (string) file_get_contents(self::RACINE . '/public/css/app.css');
        $gabarits = '';
        foreach (glob(self::RACINE . '/templates/*.twig') ?: [] as $fichier) {
            $gabarits .= (string) file_get_contents($fichier);
        }
        self::assertNotSame('', $gabarits);
        foreach ($classes as $classe) {
            // Le papier, la feuille, le PDF, les montants alignés à droite, les couleurs de la marque, les modèles et
            // la section de paiement : noms partagés à dessein.
            if (in_array($classe, ['doc', 'paper', 'pdf', 'n'], true) || preg_match('/^(m|c|qr)-|^t\d+$/', $classe) === 1) {
                continue;
            }
            self::assertStringStartsWith('doc-', $classe, "« .$classe » : une classe du document commence par doc-");
            self::assertDoesNotMatchRegularExpression('/\.' . preg_quote($classe, '/') . '(?![\w-])/', $application, "« .$classe » existe aussi dans app.css");
            self::assertDoesNotMatchRegularExpression('/class="[^"]*(?<![\w-])' . preg_quote($classe, '/') . '(?![\w-])[^"]*"/', $gabarits, "« $classe » est aussi la classe d'un écran de l'application");
        }
        self::assertContains('doc-colonne', $classes);
    }

    public function testLaDateEnToutesLettresDitPremier(): void
    {
        self::assertSame('1er décembre 2026', Formats::dateLongue('2026-12-01'));
        self::assertSame('12 novembre 2026', Formats::dateLongue(new \DateTimeImmutable('2026-11-12')));
        self::assertSame('21 mars 2027', Formats::dateLongue('2027-03-21 08:00:00'));
        self::assertSame('31 janvier 2027', Formats::dateLongue('2027-01-31'));
    }

    /** Le gabarit inclus porte le nom du modèle : une marque ne se fabrique pas avec un modèle hors de la liste. */
    public function testUneMarqueNAQueDesModelesDeLaListe(): void
    {
        self::assertSame('sobre', (new Marque('../base'))->modele);
        self::assertSame('sobre', (new Marque('inconnu', '#1D4E89'))->modele);
        self::assertSame('lettre', (new Marque('lettre'))->modele);
    }
}
