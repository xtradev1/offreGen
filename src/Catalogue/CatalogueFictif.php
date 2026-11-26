<?php

declare(strict_types=1);

namespace OffreGen\Catalogue;

/**
 * Catalogues inventés des métiers proposés pour une démonstration ou un essai : huit métiers et un catalogue
 * général pour « Autre métier » (décision de Xavier du 2026-11-19).
 * Prix fictifs, placés dans les fourchettes publiées en Suisse quand il en existe : repères et sources dans
 * docs/plans/2026-11-19_metiers-demo/CATALOGUES.md (peintre et sanitaire : relevé du 2026-11-06).
 * Un prix à 0 veut dire « prix manquant » : l'écran le montre à compléter.
 */
final class CatalogueFictif
{
    /** Clé => libellé, dans l'ordre d'affichage. */
    private const LIBELLES = [
        'peintre' => 'Peintre',
        'platrier' => 'Plâtrier',
        'carreleur' => 'Carreleur',
        'macon' => 'Maçon',
        'sanitaire' => 'Sanitaire',
        'menuisier' => 'Menuisier',
        'sols' => 'Poseur de sols',
        'paysagiste' => 'Jardinier, paysagiste',
        'autre' => 'Autre métier',
        // Prestations (décision de Xavier du 2026-11-22) : leur activité est fixée par Compte\Activite::duMetier.
        'informatique' => 'Informatique',
        'conseil' => 'Conseil',
        'marketing' => 'Marketing',
        'prestation' => 'Autre prestation',
    ];

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
        'platrier' => [
            ['Préparation', 'Protection des sols et des accès', 'forfait', 180, 'bâcher, protéger'],
            ['Cloisons', 'Cloison en plaques de plâtre, posée', 'm²', 95, 'cloison, placo, alba'],
            ['Cloisons', 'Doublage isolant collé', 'm²', 75, 'doublage'],
            ['Plafonds', 'Faux plafond en plaques de plâtre', 'm²', 110, 'faux plafond, plafond suspendu'],
            ['Enduits', 'Lissage des murs, 2 passes', 'm²', 40, 'lisser, ratisser, glacis'],
            ['Enduits', 'Lissage des plafonds, 2 passes', 'm²', 45, 'plafond, lisser'],
            ['Enduits', 'Crépi de finition intérieur', 'm²', 40, 'crépi, ribé'],
            ['Enduits', 'Rhabillage de saignées', 'm', 18, 'rhabiller, saignée, reboucher'],
            ['Finitions', "Baguette d'angle", 'm', 12, 'angle, cornière'],
            ['Divers', 'Heure de régie', 'h', 88, 'régie, heure'],
            ['Divers', 'Déplacement', 'forfait', 60, 'déplacement'],
            ['Divers', 'Évacuation des déchets', 'forfait', 160, 'déchets, benne'],
        ],
        'carreleur' => [
            ['Démontage', "Dépose de l'ancien carrelage", 'm²', 35, 'dépose, piquer, démolir'],
            ['Préparation', "Primaire d'accrochage", 'm²', 10, 'primaire, fond'],
            ['Préparation', 'Ragréage du sol', 'm²', 28, 'ragréage, égaliser'],
            ['Préparation', 'Étanchéité sous carrelage', 'm²', 50, 'étanchéité, natte'],
            ['Pose', 'Carrelage de sol, pose seule', 'm²', 85, 'carrelage, sol, catelles'],
            ['Pose', 'Faïence murale, pose seule', 'm²', 80, 'faïence, murs, catelles'],
            ['Pose', 'Supplément grand format', 'm²', 25, 'grand format'],
            ['Fournitures', 'Carrelage grès cérame, fourniture', 'm²', 55, 'carreaux, fourniture'],
            ['Finitions', 'Plinthes carrelées', 'm', 28, 'plinthes'],
            ['Finitions', 'Joints silicone', 'm', 14, 'silicone, joints'],
            ['Divers', 'Heure de régie', 'h', 88, 'régie, heure'],
            ['Divers', 'Déplacement', 'forfait', 60, 'déplacement'],
        ],
        'macon' => [
            ['Démolition', 'Démolition de cloison non porteuse', 'm²', 45, 'démolir, abattre, cloison'],
            ['Démolition', 'Ouverture de porte dans un mur porteur, linteau compris', 'pce', 5500, 'ouverture, percement, linteau'],
            ['Maçonnerie', 'Cloison en briques, montée', 'm²', 95, 'cloison, briques, galandage'],
            ['Maçonnerie', 'Mur porteur en maçonnerie', 'm³', 580, 'mur porteur, plots'],
            ['Maçonnerie', 'Mur de jardin en béton', 'm²', 500, 'mur de jardin, muret'],
            ['Maçonnerie', 'Mur en pierre naturelle', 'm²', 230, 'pierre naturelle, mur en pierre'],
            ['Béton', 'Chape ciment', 'm²', 48, 'chape'],
            ['Béton', 'Béton coulé, coffrage compris', 'm³', 420, 'béton, coffrage, dalle'],
            ['Enduits', 'Crépi de mur', 'm²', 32, 'crépi, enduit'],
            ['Divers', 'Heure de maçon', 'h', 85, 'heure, régie'],
            ['Divers', 'Benne à gravats, pose et enlèvement', 'pce', 480, 'benne, gravats'],
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
        'menuisier' => [
            ['Portes', 'Porte intérieure complète, posée', 'pce', 850, 'porte'],
            ['Portes', 'Ajustage de porte', 'pce', 70, 'raboter, ajuster'],
            ['Portes', "Dépose d'une ancienne porte", 'pce', 60, 'dépose, démonter'],
            ['Fenêtres', 'Fenêtre bois, double vitrage, posée', 'pce', 1200, 'fenêtre'],
            ['Fenêtres', 'Porte-fenêtre bois, double vitrage, posée', 'pce', 1800, 'porte-fenêtre, baie'],
            ['Fenêtres', "Dépose et évacuation d'une ancienne fenêtre", 'pce', 120, 'dépose, ancienne fenêtre'],
            ['Agencement', 'Armoire sur mesure, toute hauteur', 'm', 2200, 'armoire, penderie, placard'],
            ['Agencement', 'Étagère sur mesure', 'm', 180, 'étagère, rayonnage'],
            ['Cuisine', 'Pose de cuisine', 'm', 350, 'cuisine, montage'],
            ['Cuisine', 'Plan de travail stratifié, fourni et posé', 'm', 320, 'plan de travail'],
            ['Divers', 'Heure de menuisier', 'h', 100, 'régie, heure'],
            ['Divers', 'Déplacement', 'forfait', 60, 'déplacement'],
        ],
        'sols' => [
            ['Démontage', "Dépose de l'ancien revêtement", 'm²', 15, 'dépose, arracher'],
            ['Préparation', 'Ragréage du sol', 'm²', 28, 'ragréage, égaliser'],
            ['Préparation', 'Sous-couche isolante', 'm²', 8, 'sous-couche'],
            ['Parquet', 'Parquet chêne contrecollé, pose collée', 'm²', 95, 'parquet, chêne'],
            ['Parquet', 'Stratifié, pose flottante', 'm²', 48, 'stratifié, laminé'],
            ['Parquet', 'Ponçage et vitrification', 'm²', 38, 'poncer, vitrifier'],
            ['Sols souples', 'Vinyle clipsé, fourni et posé', 'm²', 98, 'vinyle, PVC'],
            ['Sols souples', 'Moquette, fournie et posée', 'm²', 150, 'moquette, tapis'],
            ['Finitions', 'Plinthes, fournies et posées', 'm', 22, 'plinthes'],
            ['Finitions', 'Barre de seuil', 'pce', 45, 'seuil'],
            ['Divers', 'Évacuation des déchets', 'forfait', 150, 'déchets, benne'],
            ['Divers', 'Déplacement', 'forfait', 60, 'déplacement'],
        ],
        'paysagiste' => [
            // Tonte au forfait : le repère au mètre carré (0.25 à 0.40 franc) demanderait des centimes.
            ['Entretien', "Tonte du gazon, jusqu'à 300 m²", 'forfait', 90, 'tonte, tondre, gazon'],
            ['Entretien', "Taille de haie, jusqu'à 2 m de haut", 'm', 15, 'haie, tailler'],
            ['Entretien', "Taille d'arbuste", 'pce', 45, 'arbuste, tailler'],
            ['Plantation', 'Gazon en rouleau, fourni et posé', 'm²', 30, 'gazon, rouleau, pelouse'],
            ['Plantation', 'Semis de gazon, préparation comprise', 'm²', 10, 'semis, semer'],
            ['Plantation', 'Arbuste, fourni et planté', 'pce', 65, 'planter, arbuste'],
            ['Aménagement', 'Allée en pavés, fournie et posée', 'm²', 180, 'allée, pavés, dallage'],
            ['Aménagement', 'Surface en gravier, fournie et posée', 'm²', 85, 'gravier'],
            ['Aménagement', 'Bordure, fournie et posée', 'm', 48, 'bordure'],
            ['Divers', 'Heure de jardinier', 'h', 85, 'heure, régie'],
            ['Divers', 'Évacuation des déchets verts', 'forfait', 80, 'déchets verts, déchetterie'],
            ['Divers', 'Déplacement', 'forfait', 60, 'déplacement'],
        ],
        'autre' => [
            ["Main-d'œuvre", 'Heure de travail', 'h', 95, 'heure, régie'],
            ["Main-d'œuvre", "Heure d'aide", 'h', 65, 'aide, apprenti, manœuvre'],
            ['Préparation', 'Visite et relevé sur place', 'forfait', 90, 'visite, relevé, métré'],
            ['Préparation', 'Installation de chantier', 'forfait', 250, 'installation'],
            ['Préparation', 'Protection des sols et des accès', 'forfait', 150, 'bâcher, protéger'],
            ['Fournitures', 'Petit matériel et fournitures', 'forfait', 80, 'petit matériel, fournitures'],
            ['Machines', 'Location de machine, par jour', 'pce', 120, 'location, machine'],
            ['Machines', 'Échafaudage roulant, par jour', 'pce', 90, 'échafaudage'],
            ['Déchets', 'Évacuation des déchets', 'forfait', 150, 'déchets, déchetterie'],
            ['Déchets', 'Benne, pose et enlèvement', 'pce', 480, 'benne'],
            ['Finitions', 'Nettoyage de fin de chantier', 'forfait', 220, 'nettoyage'],
            ['Divers', 'Déplacement', 'forfait', 60, 'déplacement'],
        ],
        // Prestations : repères du 2026-11-22 dans CATALOGUES.md (S16 à S18), tarifs d'indépendants et de petites agences.
        'informatique' => [
            ['Conseil', 'Analyse des besoins et cahier des charges', 'forfait', 900, 'analyse, cahier des charges, besoins'],
            ['Conseil', 'Heure de conseil', 'h', 160, 'conseil, heure de conseil'],
            ['Développement', 'Heure de développement', 'h', 140, 'développement, programmation'],
            ['Développement', 'Journée de développement', 'jour', 1100, 'journée, jour de développement'],
            ['Développement', 'Site vitrine, cinq pages', 'forfait', 3500, 'site vitrine, site web, site internet'],
            ['Installation', "Installation d'un poste de travail", 'pce', 250, 'poste, ordinateur, PC'],
            ['Installation', 'Migration de la messagerie, par boîte', 'pce', 90, 'messagerie, boîte, mail'],
            ['Maintenance', 'Maintenance et mises à jour', 'mois', 180, 'maintenance, mises à jour, contrat'],
            ['Maintenance', 'Hébergement et nom de domaine', 'mois', 35, 'hébergement, domaine'],
            ['Maintenance', 'Sauvegarde externalisée', 'mois', 45, 'sauvegarde, backup'],
            ['Formation', 'Formation des utilisateurs, demi-journée', 'forfait', 650, 'formation'],
            ['Divers', 'Déplacement', 'forfait', 80, 'déplacement'],
        ],
        'conseil' => [
            ['Diagnostic', 'Entretien de cadrage', 'forfait', 450, 'cadrage, premier entretien'],
            ['Diagnostic', 'État des lieux et rapport', 'forfait', 2400, 'état des lieux, diagnostic, audit'],
            ['Accompagnement', 'Heure de conseil', 'h', 180, 'heure, conseil'],
            ['Accompagnement', 'Journée de conseil', 'jour', 1500, 'journée, jour'],
            ['Accompagnement', 'Demi-journée de conseil', 'pce', 800, 'demi-journée'],
            ['Accompagnement', 'Suivi mensuel', 'mois', 900, 'suivi, accompagnement'],
            ['Ateliers', "Animation d'un atelier, demi-journée", 'forfait', 1200, 'atelier, animation'],
            ['Ateliers', "Préparation d'un atelier", 'forfait', 600, 'préparation'],
            ['Livrables', 'Rédaction du rapport final', 'forfait', 1200, 'rapport final, rédaction'],
            ['Livrables', 'Présentation à la direction', 'forfait', 600, 'présentation, restitution'],
            ['Formation', 'Journée de formation', 'jour', 1800, 'formation'],
            ['Divers', 'Déplacement', 'forfait', 80, 'déplacement, frais'],
        ],
        'marketing' => [
            ['Stratégie', 'Plan de communication', 'forfait', 2400, 'plan de communication, stratégie'],
            ['Stratégie', 'Heure de conseil', 'h', 140, 'conseil, heure'],
            ['Création', 'Logo et identité visuelle', 'forfait', 2800, 'logo, identité, charte graphique'],
            ['Création', 'Flyer ou affiche, création', 'pce', 450, 'flyer, affiche, dépliant'],
            ['Création', 'Heure de graphisme', 'h', 120, 'graphisme, mise en page'],
            ['Contenus', "Rédaction d'un article", 'pce', 350, 'article, rédaction, texte'],
            ['Contenus', 'Reportage photo, demi-journée', 'forfait', 900, 'photo, reportage'],
            ['Contenus', "Lettre d'information, création et envoi", 'pce', 400, "newsletter, lettre d'information"],
            ['Réseaux sociaux', 'Gestion des réseaux sociaux', 'mois', 1500, 'réseaux sociaux, publications'],
            ['Réseaux sociaux', 'Publication, texte et visuel', 'pce', 120, 'publication, post'],
            ['Publicité', 'Gestion des campagnes en ligne', 'mois', 1200, 'campagne, publicité en ligne'],
            ['Divers', 'Déplacement', 'forfait', 80, 'déplacement'],
        ],
        // Général des prestations, pour « Autre prestation » : des estimations, comme le général du bâtiment.
        'prestation' => [
            ['Prestations', 'Heure de travail', 'h', 120, 'heure'],
            ['Prestations', 'Demi-journée', 'pce', 480, 'demi-journée'],
            ['Prestations', 'Journée', 'jour', 950, 'journée, jour'],
            ['Préparation', 'Premier entretien', 'forfait', 150, 'entretien, rencontre'],
            ['Préparation', 'Analyse de la demande', 'forfait', 400, 'analyse, étude'],
            ['Suivi', 'Suivi mensuel', 'mois', 300, 'suivi, abonnement'],
            ['Suivi', 'Séance de suivi', 'pce', 150, 'séance, point'],
            ['Livrables', 'Rapport écrit', 'forfait', 500, 'rapport, document'],
            ['Livrables', 'Présentation', 'forfait', 350, 'présentation'],
            ['Formation', 'Formation, demi-journée', 'forfait', 600, 'formation, cours'],
            ['Frais', 'Frais et fournitures', 'forfait', 80, 'frais, fournitures'],
            ['Frais', 'Déplacement', 'forfait', 80, 'déplacement'],
        ],
    ];

    /** @return list<string> les clés des métiers, dans l'ordre d'affichage */
    public static function metiers(): array
    {
        return array_keys(self::LIBELLES);
    }

    /** @return array<string, string> clé => libellé, dans l'ordre d'affichage */
    public static function libelles(): array
    {
        return self::LIBELLES;
    }

    public static function libelle(string $metier): string
    {
        return self::LIBELLES[$metier] ?? throw new \InvalidArgumentException("Métier inconnu : $metier. Choix : " . implode(', ', self::metiers()) . '.');
    }

    /** @return list<array{categorie: string, designation: string, unite: string, prix_centimes: int, synonymes: string}> */
    public static function produits(string $metier): array
    {
        if (!isset(self::METIERS[$metier])) {
            throw new \InvalidArgumentException("Métier inconnu : $metier. Choix : " . implode(', ', self::metiers()) . '.');
        }
        return array_map(static fn (array $p): array => [
            'categorie' => $p[0],
            'designation' => $p[1],
            'unite' => $p[2],
            'prix_centimes' => $p[3] * 100,
            'synonymes' => $p[4],
        ], self::METIERS[$metier]);
    }

    private const FAMILLE = ['Famille Exemple', "Chemin des Essais 4\n1700 Fribourg"];
    private const MONSIEUR = ['Monsieur Modèle', "Route du Test 18\n2000 Neuchâtel"];
    private const MADAME = ['Madame Essai', "Rue du Modèle 7\n1950 Sion"];

    /**
     * Deux offres d'exemple par métier, pour qu'un compte de démonstration montre tout de suite des offres.
     * Clients et adresses inventés. Les lignes citent le catalogue par sa désignation, avec la quantité.
     * [titre, [client, adresse], il y a tant de jours, [désignation => quantité]]
     */
    private const OFFRES = [
        'peintre' => [
            ['Rafraîchissement du séjour', self::FAMILLE, 2, [
                'Protection des sols et des meubles' => 1, 'Rebouchage et ponçage des murs' => 42, 'Peinture des murs, 2 couches' => 42,
                'Peinture des plafonds, 2 couches' => 24, 'Laquage des plinthes' => 26, 'Déplacement' => 1]],
            ['Façade de la maison', self::MONSIEUR, 6, [
                'Échafaudage, montage et location' => 120, 'Peinture de façade, 2 couches' => 120, 'Déplacement' => 1]],
        ],
        'platrier' => [
            ['Cloison et plafond du bureau', self::FAMILLE, 2, [
                'Protection des sols et des accès' => 1, 'Cloison en plaques de plâtre, posée' => 14, 'Faux plafond en plaques de plâtre' => 12,
                'Lissage des murs, 2 passes' => 28, "Baguette d'angle" => 6, 'Évacuation des déchets' => 1]],
            ['Lissage du séjour', self::MONSIEUR, 6, [
                'Lissage des murs, 2 passes' => 52, 'Lissage des plafonds, 2 passes' => 26, 'Rhabillage de saignées' => 8, 'Déplacement' => 1]],
        ],
        'carreleur' => [
            ['Salle de bains, sol et murs', self::FAMILLE, 2, [
                "Dépose de l'ancien carrelage" => 6, "Primaire d'accrochage" => 24, 'Étanchéité sous carrelage' => 9,
                'Carrelage de sol, pose seule' => 6, 'Faïence murale, pose seule' => 18, 'Joints silicone' => 12, 'Déplacement' => 1]],
            ['Carrelage de la cuisine', self::MADAME, 6, [
                'Ragréage du sol' => 14, 'Carrelage de sol, pose seule' => 14, 'Carrelage grès cérame, fourniture' => 15,
                'Plinthes carrelées' => 16, 'Déplacement' => 1]],
        ],
        'macon' => [
            ['Ouverture entre cuisine et séjour', self::FAMILLE, 2, [
                'Démolition de cloison non porteuse' => 9, 'Ouverture de porte dans un mur porteur, linteau compris' => 1,
                'Crépi de mur' => 12, 'Benne à gravats, pose et enlèvement' => 1, 'Déplacement' => 1]],
            ['Mur de jardin et chape du garage', self::MONSIEUR, 6, [
                'Mur de jardin en béton' => 8, 'Chape ciment' => 22, 'Heure de maçon' => 6, 'Déplacement' => 1]],
        ],
        'sanitaire' => [
            ['Salle de bains, rénovation', self::FAMILLE, 2, [
                'Démontage des anciens appareils' => 1, 'Pose WC suspendu avec bâti' => 1, 'Pose lavabo et meuble' => 1,
                'Pose douche de plain-pied, receveur et écoulement' => 1, 'Pose paroi de douche' => 1, 'Pose mitigeur' => 2,
                "Conduites d'alimentation, multicouche" => 8, 'Évacuation des déchets' => 1]],
            ['Remplacement du chauffe-eau', self::MONSIEUR, 6, [
                'Chauffe-eau électrique 200 L, fourni et posé' => 1, 'Heure de monteur' => 3, 'Déplacement' => 1]],
        ],
        'menuisier' => [
            ['Fenêtres du séjour et armoire', self::FAMILLE, 2, [
                'Fenêtre bois, double vitrage, posée' => 2, 'Porte-fenêtre bois, double vitrage, posée' => 1,
                "Dépose et évacuation d'une ancienne fenêtre" => 3, 'Armoire sur mesure, toute hauteur' => 3, 'Déplacement' => 1]],
            ["Portes de l'appartement", self::MADAME, 6, [
                "Dépose d'une ancienne porte" => 3, 'Porte intérieure complète, posée' => 3, 'Ajustage de porte' => 2, 'Déplacement' => 1]],
        ],
        'sols' => [
            ['Parquet de la chambre', self::FAMILLE, 2, [
                "Dépose de l'ancien revêtement" => 16, 'Sous-couche isolante' => 16, 'Parquet chêne contrecollé, pose collée' => 16,
                'Plinthes, fournies et posées' => 18, 'Barre de seuil' => 1, 'Évacuation des déchets' => 1]],
            ['Vinyle du couloir et de la cuisine', self::MADAME, 6, [
                "Dépose de l'ancien revêtement" => 22, 'Ragréage du sol' => 22, 'Vinyle clipsé, fourni et posé' => 22,
                'Plinthes, fournies et posées' => 24, 'Barre de seuil' => 2, 'Déplacement' => 1]],
        ],
        'paysagiste' => [
            ["Entretien d'automne", self::FAMILLE, 2, [
                "Tonte du gazon, jusqu'à 300 m²" => 1, "Taille de haie, jusqu'à 2 m de haut" => 38, "Taille d'arbuste" => 6,
                'Évacuation des déchets verts' => 1, 'Déplacement' => 1]],
            ['Allée et gazon', self::MONSIEUR, 6, [
                'Allée en pavés, fournie et posée' => 18, 'Bordure, fournie et posée' => 24, 'Gazon en rouleau, fourni et posé' => 60,
                'Heure de jardinier' => 4, 'Déplacement' => 1]],
        ],
        'autre' => [
            ['Petits travaux à la cave', self::FAMILLE, 2, [
                'Heure de travail' => 6, "Heure d'aide" => 6, 'Petit matériel et fournitures' => 1, 'Évacuation des déchets' => 1, 'Déplacement' => 1]],
            ['Débarras et remise en état', self::MONSIEUR, 6, [
                'Installation de chantier' => 1, 'Benne, pose et enlèvement' => 1, 'Heure de travail' => 12,
                'Location de machine, par jour' => 2, 'Nettoyage de fin de chantier' => 1, 'Déplacement' => 1]],
        ],
        'informatique' => [
            ['Site vitrine de la boutique', self::MADAME, 2, [
                'Analyse des besoins et cahier des charges' => 1, 'Site vitrine, cinq pages' => 1, 'Hébergement et nom de domaine' => 12,
                'Formation des utilisateurs, demi-journée' => 1]],
            ['Trois postes et la messagerie', self::MONSIEUR, 6, [
                "Installation d'un poste de travail" => 3, 'Migration de la messagerie, par boîte' => 3, 'Sauvegarde externalisée' => 12,
                'Heure de conseil' => 2, 'Déplacement' => 1]],
        ],
        'conseil' => [
            ["État des lieux de l'organisation", self::MONSIEUR, 2, [
                'Entretien de cadrage' => 1, 'État des lieux et rapport' => 1, 'Présentation à la direction' => 1, 'Déplacement' => 2]],
            ['Accompagnement de six mois', self::MADAME, 6, [
                'Journée de conseil' => 4, "Animation d'un atelier, demi-journée" => 2, "Préparation d'un atelier" => 2, 'Suivi mensuel' => 6]],
        ],
        'marketing' => [
            ['Identité visuelle et flyer', self::MADAME, 2, [
                'Logo et identité visuelle' => 1, 'Flyer ou affiche, création' => 2, 'Heure de graphisme' => 3]],
            ['Communication du trimestre', self::MONSIEUR, 6, [
                'Plan de communication' => 1, 'Gestion des réseaux sociaux' => 3, "Rédaction d'un article" => 3, 'Reportage photo, demi-journée' => 1]],
        ],
        'prestation' => [
            ['Mission de deux jours', self::MONSIEUR, 2, [
                'Premier entretien' => 1, 'Journée' => 2, 'Rapport écrit' => 1, 'Déplacement' => 2]],
            ['Suivi du semestre', self::MADAME, 6, [
                'Analyse de la demande' => 1, 'Suivi mensuel' => 6, 'Séance de suivi' => 2, 'Présentation' => 1]],
        ],
    ];

    /** @return list<array{titre: string, client: string, adresse: string, il_y_a_jours: int, lignes: array<string, int>}> */
    public static function offres(string $metier): array
    {
        self::produits($metier);
        return array_map(static fn (array $o): array => [
            'titre' => $o[0],
            'client' => $o[1][0],
            'adresse' => $o[1][1],
            'il_y_a_jours' => $o[2],
            'lignes' => $o[3],
        ], self::OFFRES[$metier]);
    }
}
