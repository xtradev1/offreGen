<?php

declare(strict_types=1);

namespace OffreGen\Tests\Systeme;

use OffreGen\Donnees\VerrouPerdu;
use OffreGen\Systeme\Commande;
use PHPUnit\Framework\TestCase;

/** Revue T1 du sous-chantier 31 : le pouls du verrou bat pendant ffmpeg et ffprobe, et peut les arrêter. */
final class CommandePoulsTest extends TestCase
{
    public function testLeRappelTourneAuMoinsToutesLesCinqSecondesPendantLaCommande(): void
    {
        $appels = 0;
        $r = (new Commande())->lancer(['sleep', '7'], 30, function () use (&$appels): void {
            $appels++;
        });
        self::assertSame(0, $r->code);
        self::assertGreaterThanOrEqual(2, $appels, 'au moins un appel au départ et un vers 5 s');
    }

    public function testUnPoulsQuiLeveArreteLaCommande(): void
    {
        $debut = microtime(true);
        $appels = 0;
        try {
            (new Commande())->lancer(['sleep', '30'], 60, function () use (&$appels): void {
                if (++$appels >= 2) {
                    throw new VerrouPerdu('verrou repris');
                }
            });
            self::fail('VerrouPerdu attendu.');
        } catch (VerrouPerdu) {
        }
        self::assertLessThan(15, microtime(true) - $debut, 'la commande a été arrêtée, pas attendue 30 s');
    }
}
