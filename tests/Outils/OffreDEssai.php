<?php

declare(strict_types=1);

namespace OffreGen\Tests\Outils;

use OffreGen\Compte\Marque;
use OffreGen\Donnees\Portee;
use OffreGen\Http\Conteneur;
use OffreGen\Http\Controleur\OffreControleur;

/**
 * Une offre fabriquée pour les tests du document : le nombre de lignes, les pièces et les champs facultatifs se
 * choisissent sans passer par la base. Les réglages de l'artisan, eux, viennent de la base (Jeu).
 *
 * @phpstan-import-type Offre from \OffreGen\Donnees\DepotOffres
 * @phpstan-import-type Ligne from \OffreGen\Donnees\DepotOffres
 */
final class OffreDEssai
{
    private const POSTES = [
        ['Protection des sols et des meubles', 1000, 'forfait', 18000],
        ['Rebouchage et ponçage des murs', 42000, 'm²', 900],
        ['Peinture des murs, 2 couches', 42000, 'm²', 2800],
        ['Peinture des plafonds, 2 couches', 24000, 'm²', 3200],
        ['Laquage des plinthes', 26000, 'm', 1200],
        ['Laquage des portes et des cadres', 2000, 'pce', 14000],
        ['Peinture des radiateurs', 2000, 'pce', 9500],
    ];

    private const GROUPES = ['Séjour', 'Cuisine', 'Chambre parentale', 'Chambre des enfants', 'Couloir et entrée', 'Salle de bains'];

    /**
     * @param array<string, mixed> $plus champs à remplacer
     * @return Offre
     */
    public static function offre(array $plus = []): array
    {
        /** @var Offre */
        return $plus + ['id' => 0, 'numero' => 42, 'enregistrement_id' => null, 'titre' => "Peinture complète de l'appartement", 'client_id' => null,
            'client_nom' => 'Famille Exemple', 'client_adresse' => "Chemin des Essais 4\n1700 Fribourg", 'client_dicte' => null, 'lieu_dicte' => null,
            'cree_le' => '2026-11-07 10:00:00', 'modifiee_le' => null, 'envoyee_le' => null, 'postes_coupes' => 0,
            'chantier_adresse' => null, 'chantier_contact' => null, 'chantier_acces' => null, 'debut_travaux' => null, 'duree_travaux' => null, 'non_compris' => null, 'visite_le' => null, 'rabais' => null, 'conditions' => null, 'texte_avant' => null, 'texte_apres' => null,
            'representant_id' => null, 'representant_nom' => null, 'representant_adresse' => null, 'offre_adressee' => 'representant', 'factures_adressees' => 'pa'];
    }

    /**
     * Des lignes rangées par groupes de sept au plus : par catégorie du catalogue, ou par pièce.
     *
     * @return list<Ligne>
     */
    public static function lignes(int $nombre, bool $parPiece = false): array
    {
        $lignes = [];
        for ($i = 0; $i < $nombre; $i++) {
            [$designation, $quantite, $unite, $prix] = self::POSTES[$i % 7];
            $groupe = self::GROUPES[intdiv($i, 7) % 6];
            $lignes[] = ['id' => $i + 1, 'offre_id' => 0, 'rang' => $i + 1, 'designation' => $designation, 'quantite_milliemes' => $quantite, 'unite' => $unite,
                'prix_centimes' => $prix, 'offerte' => 0, 'a_verifier' => 0, 'entendu' => null, 'produit_id' => null, 'categorie' => $parPiece ? 'Peinture' : $groupe,
                'tva_pour_dix_mille' => null, 'tva_ligne' => null, 'prix_dicte_centimes' => null, 'prix_catalogue' => null, 'piece' => $parPiece ? $groupe : null];
        }
        return $lignes;
    }

    /**
     * Le HTML que lit dompdf pour cette offre, dans la marque donnée.
     *
     * @param Offre $offre
     * @param list<Ligne> $lignes
     * @param array<string, mixed> $plus variables de plus pour le gabarit
     */
    public static function html(Conteneur $c, int $artisan, array $offre, array $lignes, Marque $m, array $plus = []): string
    {
        return OffreControleur::htmlPdf($c, $plus + (new OffreControleur($c))->vueDocument(new Portee($artisan), $offre, $lignes, $m));
    }
}
