<?php

declare(strict_types=1);

namespace OffreGen\Tests\Compte;

use OffreGen\Tests\Outils\Dossiers;
use OffreGen\Tests\Outils\Jeu;
use OffreGen\Tests\Outils\Monde;
use PHPUnit\Framework\TestCase;

/** Les métiers tapés sous « Autre métier », comptés : ils disent quel catalogue ajouter ensuite. */
final class MetiersSaisisTest extends TestCase
{
    private Dossiers $dossiers;

    protected function setUp(): void
    {
        new Jeu();
        $this->dossiers = new Dossiers();
    }

    protected function tearDown(): void
    {
        $this->dossiers->effacer();
    }

    public function testLesMetiersSaisisSontComptesDuPlusFrequentAuPlusRare(): void
    {
        $c = Monde::conteneur(env: $this->dossiers->env());
        $comptes = $c->comptes();
        $comptes->creer('r@exemple.ch', 'Ramonage R', false, null, 10, 'autre', 'Ramoneur');
        $comptes->creer('e1@exemple.ch', 'Ébénisterie 1', false, null, 10, 'autre', 'Ébéniste');
        $comptes->creer('e2@exemple.ch', 'Ébénisterie 2', false, null, 10, 'autre', ' ébéniste ');
        // Sans texte, ou avec un métier de la liste : hors du compte.
        $comptes->creer('s@exemple.ch', 'Sans texte', false, null, 10, 'autre');
        $comptes->creer('p@exemple.ch', 'Peinture P', false, null, 10, 'peintre');

        $saisis = $c->artisans()->metiersSaisis();

        self::assertSame([2, 1], array_column($saisis, 'nombre'));
        self::assertSame(['ébéniste', 'ramoneur'], array_map('mb_strtolower', array_column($saisis, 'texte')));
    }
}
