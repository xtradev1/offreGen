<?php

declare(strict_types=1);

namespace OffreGen\Tests\Donnees;

use OffreGen\Donnees\DepotEnregistrements;
use OffreGen\Donnees\DepotOffres;
use OffreGen\Donnees\DepotProduits;
use OffreGen\Donnees\Portee;
use OffreGen\Offre\LigneExtraite;
use OffreGen\Tests\Outils\Jeu;
use PHPUnit\Framework\TestCase;

/**
 * Le vocabulaire d'une dictée (sous-chantier 30, lot 8) : les désignations du catalogue de l'artisan, les plus
 * utilisées dans ses offres d'abord, pour qu'un catalogue trop long pour le prompt garde ses produits courants.
 */
final class DepotProduitsVocabulaireTest extends TestCase
{
    public function testLesProduitsLesPlusUtilisesViennentDAbord(): void
    {
        $jeu = new Jeu();
        $portee = new Portee($jeu->a);
        $produits = new DepotProduits($jeu->base, $portee);
        $plinthes = $produits->creer(['categorie' => 'Peinture', 'designation' => 'Laquage des plinthes', 'unite' => 'm', 'prix_centimes' => 1200]);
        $produits->creer(['categorie' => 'Peinture', 'designation' => 'Abat-jour jamais dicté', 'unite' => 'pce', 'prix_centimes' => 100]);
        // Deux lignes sur les plinthes, une seule (celle du jeu) sur la peinture murale.
        $enregistrement = (new DepotEnregistrements($jeu->base, $portee))
            ->creer('00000000-0000-4000-8000-000000000009', 'autre.m4a', 'audio/mp4', 1000, "{$jeu->a}/autre.m4a", $jeu->maintenant);
        (new DepotOffres($jeu->base, $portee))->creerDepuisEnregistrement($enregistrement, 'Autre offre', [
            new LigneExtraite('Laquage des plinthes', 10000, 'm', 1200, false, $plinthes),
            new LigneExtraite('Laquage des plinthes', 5000, 'm', 1200, false, $plinthes),
        ], $jeu->maintenant);

        self::assertSame(['Laquage des plinthes', 'Peinture murale a', 'Abat-jour jamais dicté'], $produits->designationsParUsage());
    }

    public function testLeCatalogueDUnAutreArtisanNApparaitJamais(): void
    {
        $jeu = new Jeu();

        self::assertSame(['Peinture murale a'], (new DepotProduits($jeu->base, new Portee($jeu->a)))->designationsParUsage());
        self::assertSame(['Peinture murale b'], (new DepotProduits($jeu->base, new Portee($jeu->b)))->designationsParUsage());
    }
}
