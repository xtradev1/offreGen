<?php

declare(strict_types=1);

namespace OffreGen\Donnees;

/**
 * Ce qui porte des lignes corrigeables : une offre, ou une facture en brouillon. LigneControleur sert les deux par
 * ces seules méthodes ; chaque implémentation vérifie l'artisan (et le brouillon) dans ses requêtes.
 */
interface SupportLignes
{
    /** @return array{id: int, designation: string, quantite_milliemes: int, unite: string, prix_centimes: int, offerte: int, produit_id: int|null, categorie: string|null, tva_pour_dix_mille: int|null, tva_ligne: int|null}|null */
    public function ligne(int $parentId, int $ligneId): ?array;

    public function corrigerLigne(int $parentId, int $ligneId, string $designation, int $quantiteMilliemes, string $unite, int $prixCentimes): bool;

    public function confirmerLigne(int $parentId, int $ligneId): bool;

    public function supprimerLigne(int $parentId, int $ligneId, \DateTimeImmutable $quand): bool;

    public function restaurerLigne(int $parentId, int $ligneId): bool;

    /** @return int l'identifiant de la ligne ajoutée, 0 si le parent n'est pas à l'artisan */
    public function ajouterLigne(int $parentId, string $designation, int $quantiteMilliemes, string $unite, int $prixCentimes, ?int $produitId): int;

    public function rattacherProduit(int $parentId, int $ligneId, int $produitId): void;

    /** Le taux d'exception de la ligne, en dix-millièmes ; null : le taux par défaut (D11, 2026-11-15). */
    public function changerTauxLigne(int $parentId, int $ligneId, ?int $tauxPourDixMille): bool;

    /** Ligne offerte (compte 0, garde son prix) ou facturée (D1, 2026-11-21). */
    public function changerOfferte(int $parentId, int $ligneId, bool $offerte): bool;

    public function marquerModifiee(int $parentId, \DateTimeImmutable $quand): void;
}
