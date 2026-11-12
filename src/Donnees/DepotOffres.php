<?php

declare(strict_types=1);

namespace OffreGen\Donnees;

use OffreGen\Offre\LigneExtraite;

/**
 * Les offres d'un artisan et leurs lignes. Une ligne supprimée garde sa place (supprimee_le) :
 * « Annuler » la remet ; les lectures l'ignorent.
 *
 * @phpstan-type Offre array{id: int, numero: int, enregistrement_id: int|null, titre: string, client_nom: string|null, client_adresse: string|null, cree_le: string, modifiee_le: string|null}
 * @phpstan-type Ligne array{id: int, offre_id: int, rang: int, designation: string, quantite_milliemes: int, unite: string, prix_centimes: int, a_verifier: int, entendu: string|null, produit_id: int|null, categorie: string|null}
 */
final class DepotOffres
{
    private const COLONNES_OFFRE = 'id, numero, enregistrement_id, titre, client_nom, client_adresse, cree_le, modifiee_le';

    /** Colonnes d'une ligne ; la catégorie vient du produit du catalogue, s'il existe encore. */
    private const COLONNES_LIGNE = 'l.id, l.offre_id, l.rang, l.designation, l.quantite_milliemes, l.unite, l.prix_centimes, l.a_verifier, l.entendu, l.produit_id, p.categorie
         FROM ligne l LEFT JOIN produit p ON p.id = l.produit_id AND p.artisan_id = l.artisan_id';

    public function __construct(private readonly Base $base, private readonly Portee $portee)
    {
    }

    /**
     * Crée l'offre et ses lignes. L'index unique sur enregistrement_id interdit une deuxième offre
     * pour le même enregistrement, même si une étape est rejouée. Le numéro suit le dernier de l'artisan ;
     * l'index unique (artisan_id, numero) refuse un doublon si deux offres naissent en même temps.
     *
     * @param list<LigneExtraite> $lignes
     */
    public function creerDepuisEnregistrement(int $enregistrementId, string $titre, array $lignes, \DateTimeImmutable $quand, ?string $clientNom = null, ?string $clientAdresse = null): int
    {
        return $this->creer($enregistrementId, $titre, $lignes, $quand, $clientNom, $clientAdresse);
    }

    /**
     * Offre d'exemple d'un compte de démonstration : elle ne vient d'aucun enregistrement.
     *
     * @param list<LigneExtraite> $lignes
     */
    public function creerExemple(string $titre, array $lignes, \DateTimeImmutable $quand, string $clientNom, string $clientAdresse): int
    {
        return $this->creer(null, $titre, $lignes, $quand, $clientNom, $clientAdresse);
    }

    /** @param list<LigneExtraite> $lignes */
    private function creer(?int $enregistrementId, string $titre, array $lignes, \DateTimeImmutable $quand, ?string $clientNom, ?string $clientAdresse): int
    {
        return $this->base->transaction(function () use ($enregistrementId, $titre, $lignes, $quand, $clientNom, $clientAdresse): int {
            $numero = (int) $this->base->valeur('SELECT COALESCE(MAX(numero), 0) + 1 FROM offre WHERE artisan_id = :a', ['a' => $this->portee->artisanId]);
            $this->base->ecrire(
                'INSERT INTO offre (artisan_id, numero, enregistrement_id, titre, client_nom, client_adresse, cree_le) VALUES (:a, :n, :e, :t, :cn, :ca, :q)',
                ['a' => $this->portee->artisanId, 'n' => $numero, 'e' => $enregistrementId, 't' => mb_substr($titre, 0, 190),
                 'cn' => $clientNom === null ? null : mb_substr($clientNom, 0, 190), 'ca' => $clientAdresse === null ? null : mb_substr($clientAdresse, 0, 255),
                 'q' => Dates::versBase($quand)],
            );
            $offreId = $this->base->dernierId();
            foreach ($lignes as $rang => $l) {
                $this->base->ecrire(
                    'INSERT INTO ligne (artisan_id, offre_id, rang, designation, quantite_milliemes, unite, prix_centimes, a_verifier, entendu, produit_id)
                     VALUES (:a, :o, :r, :d, :q, :u, :p, :v, :e, :pr)',
                    ['a' => $this->portee->artisanId, 'o' => $offreId, 'r' => $rang + 1, 'd' => mb_substr($l->designation, 0, 255),
                     'q' => $l->quantiteMilliemes, 'u' => mb_substr($l->unite, 0, 20), 'p' => $l->prixCentimes, 'v' => $l->aVerifier ? 1 : 0,
                     'e' => $l->entendu === null ? null : mb_substr($l->entendu, 0, 500),
                     'pr' => $this->produitDeLArtisan($l->produitId)],
                );
            }
            return $offreId;
        });
    }

    /** @return Offre|null */
    public function parId(int $id): ?array
    {
        /** @var Offre|null */
        return $this->base->un(
            'SELECT ' . self::COLONNES_OFFRE . ' FROM offre WHERE id = :id AND artisan_id = :a',
            ['id' => $id, 'a' => $this->portee->artisanId],
        );
    }

    /** Titre et client, corrigés par l'artisan. Faux si l'offre n'est pas à lui. */
    public function modifierEntete(int $id, string $titre, ?string $clientNom, ?string $clientAdresse, \DateTimeImmutable $quand): bool
    {
        if ($this->parId($id) === null) {
            return false;
        }
        $this->base->ecrire(
            'UPDATE offre SET titre = :t, client_nom = :cn, client_adresse = :ca, modifiee_le = :q WHERE id = :id AND artisan_id = :a',
            ['t' => mb_substr($titre, 0, 190), 'cn' => $clientNom, 'ca' => $clientAdresse, 'q' => Dates::versBase($quand),
             'id' => $id, 'a' => $this->portee->artisanId],
        );
        return true;
    }

    /** Toute correction d'une ligne date l'offre : la liste la remonte et l'affiche « modifiée le ». */
    public function marquerModifiee(int $id, \DateTimeImmutable $quand): void
    {
        $this->base->ecrire(
            'UPDATE offre SET modifiee_le = :q WHERE id = :id AND artisan_id = :a',
            ['q' => Dates::versBase($quand), 'id' => $id, 'a' => $this->portee->artisanId],
        );
    }

    public function idParEnregistrement(int $enregistrementId): ?int
    {
        $id = $this->base->valeur(
            'SELECT id FROM offre WHERE enregistrement_id = :e AND artisan_id = :a',
            ['e' => $enregistrementId, 'a' => $this->portee->artisanId],
        );
        return $id === false ? null : (int) $id;
    }

    /** @return list<Ligne> */
    public function lignes(int $offreId): array
    {
        /** @var list<Ligne> */
        return $this->base->lire(
            'SELECT ' . self::COLONNES_LIGNE . '
             WHERE l.offre_id = :o AND l.artisan_id = :a AND l.supprimee_le IS NULL ORDER BY l.rang, l.id',
            ['o' => $offreId, 'a' => $this->portee->artisanId],
        );
    }

    /** @return Ligne|null une ligne de l'offre, si elle n'est pas supprimée */
    public function ligne(int $offreId, int $ligneId): ?array
    {
        /** @var Ligne|null */
        return $this->base->un(
            'SELECT ' . self::COLONNES_LIGNE . '
             WHERE l.id = :l AND l.offre_id = :o AND l.artisan_id = :a AND l.supprimee_le IS NULL',
            ['l' => $ligneId, 'o' => $offreId, 'a' => $this->portee->artisanId],
        );
    }

    /** @return list<Offre> de la dernière travaillée à la plus ancienne */
    public function liste(int $limite = 50): array
    {
        /** @var list<Offre> */
        return $this->base->lire(
            'SELECT ' . self::COLONNES_OFFRE . ' FROM offre WHERE artisan_id = :a ORDER BY COALESCE(modifiee_le, cree_le) DESC, id DESC LIMIT ' . max(1, $limite),
            ['a' => $this->portee->artisanId],
        );
    }

    /**
     * Les lignes de plusieurs offres d'un coup, pour les totaux de la liste.
     *
     * @param list<int> $offreIds
     * @return array<int, list<Ligne>>
     */
    public function lignesDe(array $offreIds): array
    {
        $parOffre = array_fill_keys($offreIds, []);
        if ($offreIds === []) {
            return $parOffre;
        }
        $ids = implode(',', array_map('intval', $offreIds));
        /** @var list<Ligne> $lignes */
        $lignes = $this->base->lire(
            'SELECT ' . self::COLONNES_LIGNE . "
             WHERE l.artisan_id = :a AND l.offre_id IN ($ids) AND l.supprimee_le IS NULL ORDER BY l.offre_id, l.rang, l.id",
            ['a' => $this->portee->artisanId],
        );
        foreach ($lignes as $l) {
            $parOffre[$l['offre_id']][] = $l;
        }
        return $parOffre;
    }

    /**
     * Correction faite par l'artisan : la ligne n'est plus « à vérifier ».
     * Faux si la ligne n'existe pas pour cet artisan, ou est supprimée.
     */
    public function corrigerLigne(int $offreId, int $ligneId, string $designation, int $quantiteMilliemes, string $unite, int $prixCentimes): bool
    {
        if ($this->ligne($offreId, $ligneId) === null) {
            return false;
        }
        $this->base->ecrire(
            'UPDATE ligne SET designation = :d, quantite_milliemes = :q, unite = :u, prix_centimes = :p, a_verifier = 0
             WHERE id = :l AND offre_id = :o AND artisan_id = :a AND supprimee_le IS NULL',
            ['d' => mb_substr($designation, 0, 255), 'q' => $quantiteMilliemes, 'u' => mb_substr($unite, 0, 20), 'p' => $prixCentimes,
             'l' => $ligneId, 'o' => $offreId, 'a' => $this->portee->artisanId],
        );
        return true;
    }

    /** « C'est juste » : l'artisan confirme une ligne à vérifier, sans la changer. */
    public function confirmerLigne(int $offreId, int $ligneId): bool
    {
        if ($this->ligne($offreId, $ligneId) === null) {
            return false;
        }
        $this->base->ecrire(
            'UPDATE ligne SET a_verifier = 0 WHERE id = :l AND offre_id = :o AND artisan_id = :a',
            ['l' => $ligneId, 'o' => $offreId, 'a' => $this->portee->artisanId],
        );
        return true;
    }

    public function supprimerLigne(int $offreId, int $ligneId, \DateTimeImmutable $quand): bool
    {
        return $this->base->ecrire(
            'UPDATE ligne SET supprimee_le = :q WHERE id = :l AND offre_id = :o AND artisan_id = :a AND supprimee_le IS NULL',
            ['q' => Dates::versBase($quand), 'l' => $ligneId, 'o' => $offreId, 'a' => $this->portee->artisanId],
        ) === 1;
    }

    public function restaurerLigne(int $offreId, int $ligneId): bool
    {
        return $this->base->ecrire(
            'UPDATE ligne SET supprimee_le = NULL WHERE id = :l AND offre_id = :o AND artisan_id = :a AND supprimee_le IS NOT NULL',
            ['l' => $ligneId, 'o' => $offreId, 'a' => $this->portee->artisanId],
        ) === 1;
    }

    /** Ajoute une ligne en fin d'offre ; 0 si l'offre n'est pas à l'artisan. */
    public function ajouterLigne(int $offreId, string $designation, int $quantiteMilliemes, string $unite, int $prixCentimes, ?int $produitId): int
    {
        return $this->base->transaction(function () use ($offreId, $designation, $quantiteMilliemes, $unite, $prixCentimes, $produitId): int {
            $rang = (int) $this->base->valeur(
                'SELECT COALESCE(MAX(rang), 0) + 1 FROM ligne WHERE offre_id = :o AND artisan_id = :a',
                ['o' => $offreId, 'a' => $this->portee->artisanId],
            );
            $ajoutees = $this->base->ecrire(
                'INSERT INTO ligne (artisan_id, offre_id, rang, designation, quantite_milliemes, unite, prix_centimes, a_verifier, produit_id)
                 SELECT :a, o.id, :r, :d, :q, :u, :p, 0, :pr FROM offre o WHERE o.id = :o AND o.artisan_id = :a2',
                ['a' => $this->portee->artisanId, 'a2' => $this->portee->artisanId, 'o' => $offreId, 'r' => $rang,
                 'd' => mb_substr($designation, 0, 255), 'q' => $quantiteMilliemes, 'u' => mb_substr($unite, 0, 20), 'p' => $prixCentimes,
                 'pr' => $this->produitDeLArtisan($produitId)],
            );
            return $ajoutees === 1 ? $this->base->dernierId() : 0;
        });
    }

    /** Une ligne libre gardée au catalogue devient la ligne de ce produit. */
    public function rattacherProduit(int $offreId, int $ligneId, int $produitId): void
    {
        $this->base->ecrire(
            'UPDATE ligne SET produit_id = :pr WHERE id = :l AND offre_id = :o AND artisan_id = :a',
            ['pr' => $this->produitDeLArtisan($produitId), 'l' => $ligneId, 'o' => $offreId, 'a' => $this->portee->artisanId],
        );
    }

    /** Efface toutes les offres de l'artisan, lignes comprises (réinitialisation d'un compte démo). */
    public function toutEffacer(): void
    {
        $this->base->ecrire('DELETE FROM offre WHERE artisan_id = :a', ['a' => $this->portee->artisanId]);
    }

    /** Un produit proposé par l'extraction n'est gardé que s'il appartient à l'artisan. */
    private function produitDeLArtisan(?int $produitId): ?int
    {
        if ($produitId === null) {
            return null;
        }
        $trouve = $this->base->valeur(
            'SELECT id FROM produit WHERE id = :p AND artisan_id = :a',
            ['p' => $produitId, 'a' => $this->portee->artisanId],
        );
        return $trouve === false ? null : (int) $trouve;
    }
}
