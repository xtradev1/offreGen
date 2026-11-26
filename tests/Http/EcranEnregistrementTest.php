<?php

declare(strict_types=1);

namespace OffreGen\Tests\Http;

use OffreGen\Tests\Outils\Jeu;
use OffreGen\Tests\Outils\Monde;
use OffreGen\Tests\Outils\Navigateur;
use PHPUnit\Framework\TestCase;

/**
 * L'écran d'enregistrement (piste B de la maquette du parcours de la dictée, choisie par Xavier le 2026-11-26) : l'état,
 * le minuteur, l'onde de la voix, la carte du conseil, qui devient l'alerte de silence, et trois commandes : Abandonner,
 * Terminer au centre, Pause. Le comportement (onde, silence, pause) est celui d'app.js, joué par Playwright.
 */
final class EcranEnregistrementTest extends TestCase
{
    public function testLEcranPorteLOndeLaCarteEtLesTroisCommandes(): void
    {
        new Jeu();
        $a = new Navigateur(Monde::conteneur());
        $a->connecter('a@exemple.ch');
        $page = (string) $a->get('/')->getContent();
        self::assertSame(1, preg_match('#<div class="rec" data-etat="enregistrement" hidden>(.*?)</div>\s*<div class="attente"#s', $page, $m), 'le bloc de l\'enregistrement');
        $rec = $m[1] ?? '';
        self::assertStringContainsString('role="status" data-etat-micro data-ouverture="Le micro s\'ouvre…" data-en-cours="Enregistrement" data-en-pause="En pause">Enregistrement</span>', $rec);
        self::assertStringContainsString('<div class="timer" data-minuteur>00:00</div>', $rec);
        self::assertSame(1, preg_match('#<div class="onde" data-onde aria-hidden="true">(.*?)</div>#s', $rec, $onde));
        self::assertSame(34, substr_count($onde[1] ?? '', '<i></i>'), 'les barres de l\'onde');
        self::assertStringContainsString('data-conseil-titre data-silence="Le micro n\'entend rien">Conseil 1 sur 3</small>', $rec);
        self::assertStringContainsString('data-silence="Parlez plus près, ou vérifiez qu\'un casque Bluetooth ne l\'a pas pris."', $rec);
        // L'alerte se dit par une zone d'annonce à part : les conseils qui tournent ne sont pas lus (revue, M2).
        self::assertStringContainsString('<p class="rec-annonce" role="status" data-annonce></p>', $rec);
        // Les trois commandes, dans l'ordre de l'écran : Abandonner, Terminer, Pause.
        self::assertMatchesRegularExpression('#data-abandonner data-abandonner-texte="Abandonner" data-annuler="Annuler">.*<b>Abandonner</b></button>\s*'
            . '<button class="rec-stop" type="button" data-terminer aria-label="Terminer et créer l\'offre">.*<b>Terminer</b></button>\s*'
            . '<button class="rec-petit" type="button" data-pause data-pause-texte="Pause" data-reprendre="Reprendre" hidden>.*<b>Pause</b></button>#s', $rec);
    }
}
