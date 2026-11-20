<?php

declare(strict_types=1);

namespace OffreGen\Donnees;

/**
 * Le catalogue d'un artisan.
 *
 * prix_exemple_centimes : null pour un produit de l'artisan ; une valeur pour un produit d'exemple jamais modifié,
 * son prix de démonstration (0 : exemple sans prix). C'est la marque « exemple » et le prix que « Vos prix » propose.
 *
 * @phpstan-type Produit array{id: int, categorie: string, designation: string, unite: string, prix_centimes: int, tva_pour_dix_mille: int|null, synonymes: string|null, prix_exemple_centimes: int|null}
 * @phpstan-type ProduitNeuf array{categorie: string, designation: string, unite: string, prix_centimes: int, tva_pour_dix_mille?: int|null, synonymes?: string|null, prix_exemple_centimes?: int|null}
 */
final class DepotProduits
{
    private const COLONNES = 'id, categorie, designation, unite, prix_centimes, tva_pour_dix_mille, synonymes, prix_exemple_centimes';

    public function __construct(private readonly Base $base, private readonly Portee $portee)
    {
    }

    /** @return list<Produit> */
    public function tous(): array
    {
        /** @var list<Produit> */
        return $this->base->lire(
            'SELECT ' . self::COLONNES . ' FROM produit WHERE artisan_id = :a ORDER BY categorie, designation, id',
            ['a' => $this->portee->artisanId],
        );
    }

    /** @return Produit|null */
    public function parId(int $id): ?array
    {
        /** @var Produit|null */
        return $this->base->un(
            'SELECT ' . self::COLONNES . ' FROM produit WHERE id = :id AND artisan_id = :a',
            ['id' => $id, 'a' => $this->portee->artisanId],
        );
    }

    /** @return list<Produit> les produits au prix 0, dans l'ordre du catalogue (écran « Vos prix ») */
    public function sansPrix(): array
    {
        /** @var list<Produit> */
        return $this->base->lire(
            'SELECT ' . self::COLONNES . ' FROM produit WHERE artisan_id = :a AND prix_centimes = 0 ORDER BY categorie, designation, id',
            ['a' => $this->portee->artisanId],
        );
    }

    /** Le prix donné par l'artisan sur l'écran « Vos prix » : le produit devient le sien. */
    public function fixerPrix(int $id, int $prixCentimes): bool
    {
        if ($this->parId($id) === null) {
            return false;
        }
        $this->base->ecrire(
            'UPDATE produit SET prix_centimes = :p, prix_exemple_centimes = NULL WHERE id = :id AND artisan_id = :a',
            ['p' => $prixCentimes, 'id' => $id, 'a' => $this->portee->artisanId],
        );
        return true;
    }

    /** Annule « Reprendre » : le prix repart à 0, le prix de la démonstration revient à côté. */
    public function remettreExemple(int $id, int $prixExempleCentimes): bool
    {
        if ($this->parId($id) === null) {
            return false;
        }
        $this->base->ecrire(
            'UPDATE produit SET prix_centimes = 0, prix_exemple_centimes = :e WHERE id = :id AND artisan_id = :a',
            ['e' => $prixExempleCentimes, 'id' => $id, 'a' => $this->portee->artisanId],
        );
        return true;
    }

    /** Les mots reconnus d'un produit, remplacés (import du catalogue, 2026-11-20). */
    public function changerSynonymes(int $id, ?string $synonymes): bool
    {
        if ($this->parId($id) === null) {
            return false;
        }
        $this->base->ecrire('UPDATE produit SET synonymes = :s WHERE id = :id AND artisan_id = :a', ['s' => $synonymes, 'id' => $id, 'a' => $this->portee->artisanId]);
        return true;
    }

    /** Annulation d'un import : le produit retrouve le prix, la marque « exemple » et les mots reconnus d'avant. */
    public function restaurer(int $id, int $prixCentimes, ?int $prixExempleCentimes, ?string $synonymes): bool
    {
        if ($this->parId($id) === null) {
            return false;
        }
        $this->base->ecrire(
            'UPDATE produit SET prix_centimes = :p, prix_exemple_centimes = :e, synonymes = :s WHERE id = :id AND artisan_id = :a',
            ['p' => $prixCentimes, 'e' => $prixExempleCentimes, 's' => $synonymes, 'id' => $id, 'a' => $this->portee->artisanId],
        );
        return true;
    }

    /** @return list<string> les catégories du catalogue, dans l'ordre alphabétique */
    public function categories(): array
    {
        /** @var list<string> */
        return array_map(
            static fn (array $l): string => (string) $l['categorie'],
            $this->base->lire('SELECT DISTINCT categorie FROM produit WHERE artisan_id = :a ORDER BY categorie', ['a' => $this->portee->artisanId]),
        );
    }

    /** @param ProduitNeuf $p */
    public function creer(array $p): int
    {
        $this->inserer($p);
        return $this->base->dernierId();
    }

    /**
     * Un produit d'exemple dont l'artisan change le prix devient le sien : il perd sa marque « exemple ». Le prix
     * inchangé (un synonyme ajouté par le rattachement d'une ligne, une catégorie corrigée), la marque reste :
     * le prix est toujours celui de la démonstration (revue du 2026-11-20).
     *
     * @param ProduitNeuf $p
     */
    public function modifier(int $id, array $p): bool
    {
        // MariaDB compte 0 ligne touchée quand rien ne change : l'existence se vérifie à part.
        if ($this->parId($id) === null) {
            return false;
        }
        $this->base->ecrire(
            // La marque avant le prix : MariaDB applique les affectations dans l'ordre, et lirait sinon le prix neuf.
            'UPDATE produit SET prix_exemple_centimes = IF(prix_centimes = :p2, prix_exemple_centimes, NULL),
                    categorie = :c, designation = :d, unite = :u, prix_centimes = :p, tva_pour_dix_mille = :t, synonymes = :s
             WHERE id = :id AND artisan_id = :a',
            ['p2' => $p['prix_centimes'], 'c' => $p['categorie'], 'd' => $p['designation'], 'u' => $p['unite'], 'p' => $p['prix_centimes'],
             't' => $p['tva_pour_dix_mille'] ?? null, 's' => $p['synonymes'] ?? null, 'id' => $id, 'a' => $this->portee->artisanId],
        );
        return true;
    }

    /** Les lignes d'offre qui le citaient gardent leur désignation et leur prix ; leur lien passe à NULL. */
    public function supprimer(int $id): bool
    {
        return $this->base->ecrire('DELETE FROM produit WHERE id = :id AND artisan_id = :a', ['id' => $id, 'a' => $this->portee->artisanId]) === 1;
    }

    /** @param list<ProduitNeuf> $produits */
    public function remplacerTout(array $produits): void
    {
        $this->base->transaction(function () use ($produits): void {
            $this->base->ecrire('DELETE FROM produit WHERE artisan_id = :a', ['a' => $this->portee->artisanId]);
            foreach ($produits as $p) {
                $this->inserer($p);
            }
        });
    }

    /**
     * Passage d'une démonstration à l'essai : les produits d'exemple jamais modifiés gardent désignation et unité,
     * leur prix repart à 0 ; le prix de la démonstration reste à côté, pour être proposé.
     *
     * @return int nombre de produits d'exemple remis à 0
     */
    public function remettreExemplesAZero(): int
    {
        $exemples = (int) $this->base->valeur(
            'SELECT COUNT(*) FROM produit WHERE artisan_id = :a AND prix_exemple_centimes IS NOT NULL',
            ['a' => $this->portee->artisanId],
        );
        $this->base->ecrire(
            'UPDATE produit SET prix_centimes = 0 WHERE artisan_id = :a AND prix_exemple_centimes IS NOT NULL',
            ['a' => $this->portee->artisanId],
        );
        return $exemples;
    }

    /** @param ProduitNeuf $p */
    private function inserer(array $p): void
    {
        $this->base->ecrire(
            'INSERT INTO produit (artisan_id, categorie, designation, unite, prix_centimes, tva_pour_dix_mille, synonymes, prix_exemple_centimes)
             VALUES (:a, :c, :d, :u, :p, :t, :s, :e)',
            ['a' => $this->portee->artisanId, 'c' => $p['categorie'], 'd' => $p['designation'], 'u' => $p['unite'],
             'p' => $p['prix_centimes'], 't' => $p['tva_pour_dix_mille'] ?? null, 's' => $p['synonymes'] ?? null,
             'e' => $p['prix_exemple_centimes'] ?? null],
        );
    }
}
