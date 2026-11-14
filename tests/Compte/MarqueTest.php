<?php

declare(strict_types=1);

namespace OffreGen\Tests\Compte;

use OffreGen\Compte\Marque;
use PHPUnit\Framework\TestCase;

final class MarqueTest extends TestCase
{
    public function testCouleurSaisie(): void
    {
        self::assertSame('#1D4E89', Marque::couleurValide('#1d4e89'));
        self::assertSame('#1D4E89', Marque::couleurValide(' #1D4E89 '));
        foreach (['', 'bleu', '#12345', '#GGGGGG', 'red', '#1d4e89;background:url(x)'] as $faux) {
            self::assertNull(Marque::couleurValide($faux), $faux);
        }
    }

    public function testUneCouleurClaireResteLisibleSurLeBlanc(): void
    {
        // Jaune pâle tiré d'un logo : illisible tel quel sur du papier blanc.
        $jaune = new Marque('sobre', '#F5E663');
        self::assertLessThan(4.5, Marque::contraste('#F5E663', '#FFFFFF'));
        self::assertGreaterThanOrEqual(4.5, Marque::contraste($jaune->principale(), '#FFFFFF'));
        // Une couleur déjà lisible ne change pas.
        self::assertSame('#1D4E89', (new Marque('sobre', '#1D4E89'))->principale());
    }

    public function testTeinteEtDefauts(): void
    {
        self::assertSame('#E8EDF3', (new Marque('sobre', '#1D4E89'))->teinte(0.9));
        $defaut = new Marque();
        self::assertSame(['sobre', Marque::COULEUR_DEFAUT], [$defaut->modele, $defaut->couleur()]);
        self::assertSame('sobre', Marque::modeleValide('inconnu'));
        self::assertSame('moderne', Marque::modeleValide('moderne'));
        self::assertCount(6, Marque::MODELES);
    }
}
