<?php

declare(strict_types=1);

namespace OffreGen\Tests\Offre;

use OffreGen\Offre\Chantier;
use PHPUnit\Framework\TestCase;

/** Modèles du bâtiment, lot 4 : ce que l'artisan dit du chantier sur l'en-tête de l'offre. Tout est facultatif. */
final class ChantierTest extends TestCase
{
    private const SAISIE = ['chantier_adresse' => '  Rue du Test 9,  2900 Porrentruy ', 'chantier_contact' => 'Mme Essai, 079 000 00 00', 'chantier_acces' => 'Clé chez la concierge',
        'debut_travaux' => 'semaine 3, 2027', 'duree_travaux' => '6 jours ouvrables', 'non_compris' => "Peinture de finition\n\n  Installations électriques  \n", 'visite_le' => '2026-11-12'];

    public function testUneSaisieJusteDonneLeChantier(): void
    {
        [$chantier, $erreurs] = Chantier::depuisSaisie(self::SAISIE);
        self::assertSame([], $erreurs);
        self::assertNotNull($chantier);
        self::assertSame('Rue du Test 9, 2900 Porrentruy', $chantier->adresse, 'espaces réduits');
        self::assertSame(['Peinture de finition', 'Installations électriques'], $chantier->nonCompris, 'lignes vides retirées');
        self::assertSame('2026-11-12', $chantier->visite?->format('Y-m-d'));
        self::assertFalse($chantier->estVide());
        self::assertSame(['chantier_adresse' => 'Rue du Test 9, 2900 Porrentruy', 'chantier_contact' => 'Mme Essai, 079 000 00 00', 'chantier_acces' => 'Clé chez la concierge',
            'debut_travaux' => 'semaine 3, 2027', 'duree_travaux' => '6 jours ouvrables', 'non_compris' => "Peinture de finition\nInstallations électriques", 'visite_le' => '2026-11-12'], $chantier->versChamps());
    }

    public function testToutEstFacultatif(): void
    {
        [$chantier, $erreurs] = Chantier::depuisSaisie([]);
        self::assertSame([], $erreurs);
        self::assertTrue($chantier?->estVide());
        self::assertTrue((new Chantier())->estVide());
    }

    public function testUnChampTropLongEstRefuseEtNomme(): void
    {
        foreach (['chantier_adresse' => 255, 'chantier_contact' => 190, 'chantier_acces' => 255, 'debut_travaux' => 80, 'duree_travaux' => 80] as $champ => $max) {
            [$chantier, $erreurs] = Chantier::depuisSaisie([$champ => str_repeat('a', $max + 1)] + self::SAISIE);
            self::assertNull($chantier, $champ);
            self::assertSame([$champ], array_keys($erreurs));
            [$chantier] = Chantier::depuisSaisie([$champ => str_repeat('a', $max)] + self::SAISIE);
            self::assertNotNull($chantier, "$champ : $max caractères passent");
        }
    }

    public function testOnzeLignesDeNonComprisSontRefusees(): void
    {
        [$chantier, $erreurs] = Chantier::depuisSaisie(['non_compris' => implode("\n", array_fill(0, 11, 'Un poste'))] + self::SAISIE);
        self::assertNull($chantier);
        self::assertSame(['non_compris'], array_keys($erreurs));
        [$chantier] = Chantier::depuisSaisie(['non_compris' => implode("\n", array_fill(0, 10, 'Un poste'))] + self::SAISIE);
        self::assertNotNull($chantier);
        self::assertCount(10, $chantier->nonCompris);
    }

    public function testUneDateIllisibleEstRefusee(): void
    {
        // Une année hors de 2000 à 2100 est une faute de frappe : la lettre imprimerait « visite du 3 janvier 1 ».
        foreach (['demain', '12.11.2026', '2026-02-30', '2026-13-01', '0000-01-01', '0001-01-01', '1999-12-31', '2101-01-01'] as $faux) {
            [$chantier, $erreurs] = Chantier::depuisSaisie(['visite_le' => $faux] + self::SAISIE);
            self::assertNull($chantier, $faux);
            self::assertSame(['visite_le'], array_keys($erreurs), $faux);
        }
    }

    public function testLeChantierSeLitDansUneOffre(): void
    {
        $chantier = Chantier::depuisOffre(['chantier_adresse' => 'Rue du Test 9', 'chantier_contact' => null, 'chantier_acces' => null, 'debut_travaux' => 'mars 2027',
            'duree_travaux' => null, 'non_compris' => "Peinture\nÉlectricité", 'visite_le' => '2026-11-12']);
        self::assertSame(['Rue du Test 9', '', 'mars 2027', ['Peinture', 'Électricité'], '2026-11-12'],
            [$chantier->adresse, $chantier->contact, $chantier->debut, $chantier->nonCompris, $chantier->visite?->format('Y-m-d')]);
        // Une offre d'avant la migration, ou fabriquée sans ces colonnes : un chantier vide.
        self::assertTrue(Chantier::depuisOffre([])->estVide());
    }
}
