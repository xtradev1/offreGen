<?php

declare(strict_types=1);

namespace OffreGen\Donnees;

/**
 * Le catalogue d'un artisan.
 *
 * @phpstan-type Produit array{id: int, categorie: string, designation: string, unite: string, prix_centimes: int, synonymes: string|null}
 * @phpstan-type ProduitNeuf array{categorie: string, designation: string, unite: string, prix_centimes: int, synonymes?: string|null}
 */
final class DepotProduits
{
    public function __construct(private readonly Base $base, private readonly Portee $portee)
    {
    }

    /** @return list<Produit> */
    public function tous(): array
    {
        /** @var list<Produit> */
        return $this->base->lire(
            'SELECT id, categorie, designation, unite, prix_centimes, synonymes FROM produit WHERE artisan_id = :a ORDER BY categorie, designation, id',
            ['a' => $this->portee->artisanId],
        );
    }

    /** @return Produit|null */
    public function parId(int $id): ?array
    {
        /** @var Produit|null */
        return $this->base->un(
            'SELECT id, categorie, designation, unite, prix_centimes, synonymes FROM produit WHERE id = :id AND artisan_id = :a',
            ['id' => $id, 'a' => $this->portee->artisanId],
        );
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
        $this->base->ecrire(
            'INSERT INTO produit (artisan_id, categorie, designation, unite, prix_centimes, synonymes) VALUES (:a, :c, :d, :u, :p, :s)',
            ['a' => $this->portee->artisanId, 'c' => $p['categorie'], 'd' => $p['designation'], 'u' => $p['unite'],
             'p' => $p['prix_centimes'], 's' => $p['synonymes'] ?? null],
        );
        return $this->base->dernierId();
    }

    /** @param ProduitNeuf $p */
    public function modifier(int $id, array $p): bool
    {
        // MariaDB compte 0 ligne touchée quand rien ne change : l'existence se vérifie à part.
        if ($this->parId($id) === null) {
            return false;
        }
        $this->base->ecrire(
            'UPDATE produit SET categorie = :c, designation = :d, unite = :u, prix_centimes = :p, synonymes = :s WHERE id = :id AND artisan_id = :a',
            ['c' => $p['categorie'], 'd' => $p['designation'], 'u' => $p['unite'], 'p' => $p['prix_centimes'],
             's' => $p['synonymes'] ?? null, 'id' => $id, 'a' => $this->portee->artisanId],
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
                $this->base->ecrire(
                    'INSERT INTO produit (artisan_id, categorie, designation, unite, prix_centimes, synonymes) VALUES (:a, :c, :d, :u, :p, :s)',
                    ['a' => $this->portee->artisanId, 'c' => $p['categorie'], 'd' => $p['designation'], 'u' => $p['unite'],
                     'p' => $p['prix_centimes'], 's' => $p['synonymes'] ?? null],
                );
            }
        });
    }
}
