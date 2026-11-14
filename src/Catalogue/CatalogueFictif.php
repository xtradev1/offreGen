<?php

declare(strict_types=1);

namespace OffreGen\Catalogue;

/**
 * Catalogues inventés des trois métiers de la maquette v4, pour les comptes de démonstration.
 * Prix fictifs, calés sur les repères relevés le 2026-11-06 (sources dans la maquette, constante SOURCES).
 * Un prix à 0 veut dire « prix manquant » : l'écran le montre à compléter.
 */
final class CatalogueFictif
{
    /** [catégorie, désignation, unité, prix en francs, synonymes] */
    private const METIERS = [
        'peintre' => [
            ['Préparation', 'Protection des sols et des meubles', 'forfait', 180, 'bâcher, protéger'],
            ['Préparation', 'Rebouchage et ponçage des murs', 'm²', 9, 'reboucher, enduit'],
            ['Préparation', "Couche d'impression", 'm²', 5, 'fond, primaire'],
            ['Peinture intérieure', 'Peinture des murs, 2 couches', 'm²', 28, 'murs, dispersion'],
            ['Peinture intérieure', 'Peinture des plafonds, 2 couches', 'm²', 32, 'plafond'],
            ['Peinture intérieure', 'Laquage des plinthes', 'm', 12, 'plinthes'],
            ['Peinture intérieure', 'Laquage de porte, 2 faces', 'pce', 160, 'porte'],
            ['Peinture intérieure', 'Peinture anti-moisissure', 'm²', 0, 'anti-moisissure'],
            ['Peinture extérieure', 'Peinture de façade, 2 couches', 'm²', 45, 'façade'],
            ['Peinture extérieure', 'Échafaudage, montage et location', 'm²', 20, 'échafaudage'],
            ['Divers', 'Déplacement', 'forfait', 60, 'déplacement'],
            ['Divers', 'Heure de régie', 'h', 80, 'régie, heure'],
        ],
        'menuisier' => [
            ['Démontage', "Dépose de l'ancien revêtement", 'm²', 15, 'dépose, arracher'],
            ['Parquet', 'Sous-couche isolante', 'm²', 8, 'sous-couche'],
            ['Parquet', 'Parquet chêne contrecollé, pose collée', 'm²', 95, 'parquet, chêne'],
            ['Parquet', 'Stratifié, pose flottante', 'm²', 48, 'stratifié'],
            ['Parquet', 'Ponçage et vitrification', 'm²', 38, 'poncer, vitrifier'],
            ['Finitions', 'Plinthes chêne, fournies et posées', 'm', 22, 'plinthes'],
            ['Finitions', 'Barre de seuil', 'pce', 45, 'seuil'],
            ['Portes', 'Porte intérieure complète, posée', 'pce', 850, 'porte'],
            ['Portes', 'Ajustage de porte', 'pce', 70, 'raboter, ajuster'],
            ['Divers', 'Évacuation des déchets', 'forfait', 150, 'déchets, benne'],
            ['Divers', 'Heure de régie', 'h', 90, 'régie, heure'],
            ['Divers', 'Déplacement', 'forfait', 60, 'déplacement'],
        ],
        'sanitaire' => [
            ['Démontage', 'Démontage des anciens appareils', 'forfait', 450, 'démonter, démontage'],
            ['Pose', 'Pose WC suspendu avec bâti', 'pce', 480, 'wc, toilettes'],
            ['Pose', 'Pose lavabo et meuble', 'pce', 320, 'lavabo, meuble'],
            ['Pose', 'Pose douche de plain-pied, receveur et écoulement', 'pce', 1200, 'douche, italienne'],
            ['Pose', 'Pose paroi de douche', 'pce', 280, 'paroi, vitre'],
            ['Pose', 'Pose mitigeur', 'pce', 120, 'mitigeur, robinet, thermostatique'],
            ['Conduites', "Conduites d'alimentation, multicouche", 'm', 65, 'conduites, tuyaux'],
            ['Conduites', 'Écoulement PE', 'm', 75, 'écoulement'],
            ['Fournitures', 'Chauffe-eau électrique 200 L, fourni et posé', 'pce', 2600, 'boiler, chauffe-eau'],
            ['Divers', 'Heure de monteur', 'h', 105, 'heure, monteur'],
            ['Divers', 'Déplacement', 'forfait', 70, 'déplacement'],
            ['Divers', 'Évacuation des déchets', 'forfait', 180, 'déchets, benne'],
        ],
    ];

    /** @return list<string> */
    public static function metiers(): array
    {
        return array_keys(self::METIERS);
    }

    /** @return list<array{categorie: string, designation: string, unite: string, prix_centimes: int, synonymes: string}> */
    public static function produits(string $metier): array
    {
        if (!isset(self::METIERS[$metier])) {
            throw new \InvalidArgumentException("Métier inconnu : $metier. Choix : " . implode(', ', self::metiers()) . '.');
        }
        return array_values(array_map(static fn (array $p): array => [
            'categorie' => $p[0],
            'designation' => $p[1],
            'unite' => $p[2],
            'prix_centimes' => $p[3] * 100,
            'synonymes' => $p[4],
        ], self::METIERS[$metier]));
    }

    /**
     * Deux offres d'exemple par métier, pour qu'un compte de démonstration montre tout de suite des offres.
     * Clients et adresses inventés. Les lignes citent le catalogue par sa désignation, avec la quantité.
     */
    private const OFFRES = [
        'peintre' => [
            ['Rafraîchissement du séjour', 'Famille Exemple', "Chemin des Essais 4\n1700 Fribourg", 2, [
                'Protection des sols et des meubles' => 1, 'Rebouchage et ponçage des murs' => 42, 'Peinture des murs, 2 couches' => 42,
                'Peinture des plafonds, 2 couches' => 24, 'Laquage des plinthes' => 26, 'Déplacement' => 1]],
            ['Façade de la maison', 'Monsieur Modèle', "Route du Test 18\n2000 Neuchâtel", 6, [
                'Échafaudage, montage et location' => 120, 'Peinture de façade, 2 couches' => 120, 'Déplacement' => 1]],
        ],
        'menuisier' => [
            ['Parquet de la chambre', 'Famille Exemple', "Chemin des Essais 4\n1700 Fribourg", 2, [
                "Dépose de l'ancien revêtement" => 16, 'Sous-couche isolante' => 16, 'Parquet chêne contrecollé, pose collée' => 16,
                'Plinthes chêne, fournies et posées' => 18, 'Barre de seuil' => 1, 'Évacuation des déchets' => 1]],
            ["Portes de l'appartement", 'Madame Essai', "Rue du Modèle 7\n1950 Sion", 6, [
                'Porte intérieure complète, posée' => 3, 'Ajustage de porte' => 2, 'Déplacement' => 1]],
        ],
        'sanitaire' => [
            ['Salle de bains, rénovation', 'Famille Exemple', "Chemin des Essais 4\n1700 Fribourg", 2, [
                'Démontage des anciens appareils' => 1, 'Pose WC suspendu avec bâti' => 1, 'Pose lavabo et meuble' => 1,
                'Pose douche de plain-pied, receveur et écoulement' => 1, 'Pose paroi de douche' => 1, 'Pose mitigeur' => 2,
                "Conduites d'alimentation, multicouche" => 8, 'Évacuation des déchets' => 1]],
            ['Remplacement du chauffe-eau', 'Monsieur Modèle', "Route du Test 18\n2000 Neuchâtel", 6, [
                'Chauffe-eau électrique 200 L, fourni et posé' => 1, 'Heure de monteur' => 3, 'Déplacement' => 1]],
        ],
    ];

    /** @return list<array{titre: string, client: string, adresse: string, il_y_a_jours: int, lignes: array<string, int>}> */
    public static function offres(string $metier): array
    {
        self::produits($metier);
        return array_values(array_map(static fn (array $o): array => [
            'titre' => $o[0],
            'client' => $o[1],
            'adresse' => $o[2],
            'il_y_a_jours' => $o[3],
            'lignes' => $o[4],
        ], self::OFFRES[$metier]));
    }
}
