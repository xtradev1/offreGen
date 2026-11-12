<?php

declare(strict_types=1);

namespace OffreGen\Donnees;

use OffreGen\Offre\LigneExtraite;

/**
 * Les offres d'un artisan et leurs lignes. Une ligne supprimée garde sa place (supprimee_le) :
 * « Annuler » la remet ; les lectures l'ignorent. Une offre se range de même : archivée (archivee_le)
 * ou supprimée (supprimee_le), elle reste en base et compte dans les offres du mois.
 *
 * Le client d'une offre : sa fiche (client_id), sinon le nom et le lieu dictés, proposition à confirmer.
 * client_nom et client_adresse donnent l'un ou l'autre, pour l'écran et le document.
 *
 * @phpstan-type Offre array{id: int, numero: int, enregistrement_id: int|null, titre: string, client_id: int|null, client_nom: string|null, client_adresse: string|null, client_dicte: string|null, lieu_dicte: string|null, cree_le: string, modifiee_le: string|null}
 * @phpstan-type Ligne array{id: int, offre_id: int, rang: int, designation: string, quantite_milliemes: int, unite: string, prix_centimes: int, a_verifier: int, entendu: string|null, produit_id: int|null, categorie: string|null}
 */
final class DepotOffres
{
    private const SELECT_OFFRE = 'SELECT o.id, o.numero, o.enregistrement_id, o.titre, o.client_id,
            COALESCE(c.nom, o.client_dicte) AS client_nom, CASE WHEN c.id IS NULL THEN o.lieu_dicte ELSE c.adresse END AS client_adresse,
            o.client_dicte, o.lieu_dicte, o.cree_le, o.modifiee_le
         FROM offre o LEFT JOIN client c ON c.id = o.client_id AND c.artisan_id = o.artisan_id';

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
    public function creerDepuisEnregistrement(int $enregistrementId, string $titre, array $lignes, \DateTimeImmutable $quand, ?int $clientId = null, ?string $clientDicte = null, ?string $lieuDicte = null): int
    {
        return $this->creer($enregistrementId, $titre, $lignes, $quand, $clientId, $clientDicte, $lieuDicte);
    }

    /**
     * Offre d'exemple d'un compte de démonstration : elle ne vient d'aucun enregistrement.
     *
     * @param list<LigneExtraite> $lignes
     */
    public function creerExemple(string $titre, array $lignes, \DateTimeImmutable $quand, int $clientId): int
    {
        return $this->creer(null, $titre, $lignes, $quand, $clientId, null, null);
    }

    /** @param list<LigneExtraite> $lignes */
    private function creer(?int $enregistrementId, string $titre, array $lignes, \DateTimeImmutable $quand, ?int $clientId, ?string $clientDicte, ?string $lieuDicte): int
    {
        return $this->base->transaction(function () use ($enregistrementId, $titre, $lignes, $quand, $clientId, $clientDicte, $lieuDicte): int {
            $numero = (int) $this->base->valeur('SELECT COALESCE(MAX(numero), 0) + 1 FROM offre WHERE artisan_id = :a', ['a' => $this->portee->artisanId]);
            $this->base->ecrire(
                'INSERT INTO offre (artisan_id, numero, enregistrement_id, titre, client_id, client_dicte, lieu_dicte, cree_le) VALUES (:a, :n, :e, :t, :ci, :cn, :ca, :q)',
                ['a' => $this->portee->artisanId, 'n' => $numero, 'e' => $enregistrementId, 't' => mb_substr($titre, 0, 190),
                 'ci' => $this->clientDeLArtisan($clientId),
                 'cn' => $clientDicte === null ? null : mb_substr($clientDicte, 0, 190), 'ca' => $lieuDicte === null ? null : mb_substr($lieuDicte, 0, 255),
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

    /** @return Offre|null l'offre, archivée comprise ; une offre supprimée est introuvable */
    public function parId(int $id): ?array
    {
        /** @var Offre|null */
        return $this->base->un(
            self::SELECT_OFFRE . ' WHERE o.id = :id AND o.artisan_id = :a AND o.supprimee_le IS NULL',
            ['id' => $id, 'a' => $this->portee->artisanId],
        );
    }

    /** Descend dans les archives. Faux si l'offre n'est pas à lui. */
    public function archiver(int $id, \DateTimeImmutable $quand): bool
    {
        return $this->marquer($id, 'archivee_le = :q', $quand);
    }

    /** Retirée de la vue pour de bon, sauf « Annuler ». Elle reste dans le compte du mois. */
    public function supprimer(int $id, \DateTimeImmutable $quand): bool
    {
        return $this->marquer($id, 'supprimee_le = :q', $quand);
    }

    /** Sortie des archives, ou suppression annulée. Faux si l'offre n'est pas à lui. */
    public function restaurer(int $id): bool
    {
        $aLui = $this->base->valeur('SELECT id FROM offre WHERE id = :id AND artisan_id = :a', ['id' => $id, 'a' => $this->portee->artisanId]);
        if ($aLui === false) {
            return false;
        }
        $this->base->ecrire(
            'UPDATE offre SET archivee_le = NULL, supprimee_le = NULL WHERE id = :id AND artisan_id = :a',
            ['id' => $id, 'a' => $this->portee->artisanId],
        );
        return true;
    }

    /** Les offres créées depuis cette date, archivées et supprimées comprises (règle de Xavier). */
    public function creeesDepuis(\DateTimeImmutable $depuis): int
    {
        return (int) $this->base->valeur(
            'SELECT COUNT(*) FROM offre WHERE artisan_id = :a AND cree_le >= :d',
            ['a' => $this->portee->artisanId, 'd' => Dates::versBase($depuis)],
        );
    }

    private function marquer(int $id, string $affectation, \DateTimeImmutable $quand): bool
    {
        if ($this->parId($id) === null) {
            return false;
        }
        $this->base->ecrire(
            "UPDATE offre SET $affectation WHERE id = :id AND artisan_id = :a",
            ['q' => Dates::versBase($quand), 'id' => $id, 'a' => $this->portee->artisanId],
        );
        return true;
    }

    /** L'objet de l'offre, corrigé par l'artisan. Faux si l'offre n'est pas à lui. */
    public function modifierTitre(int $id, string $titre, \DateTimeImmutable $quand): bool
    {
        if ($this->parId($id) === null) {
            return false;
        }
        $this->base->ecrire(
            'UPDATE offre SET titre = :t, modifiee_le = :q WHERE id = :id AND artisan_id = :a',
            ['t' => mb_substr($titre, 0, 190), 'q' => Dates::versBase($quand), 'id' => $id, 'a' => $this->portee->artisanId],
        );
        return true;
    }

    /** L'offre désigne cette fiche. Faux si l'offre ou la fiche n'est pas à l'artisan. */
    public function rattacherClient(int $id, int $clientId, \DateTimeImmutable $quand): bool
    {
        $client = $this->clientDeLArtisan($clientId);
        if ($client === null || $this->parId($id) === null) {
            return false;
        }
        $this->base->ecrire(
            'UPDATE offre SET client_id = :c, modifiee_le = :q WHERE id = :id AND artisan_id = :a',
            ['c' => $client, 'q' => Dates::versBase($quand), 'id' => $id, 'a' => $this->portee->artisanId],
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

    /** @return list<Offre> les offres ni archivées ni supprimées, de la dernière travaillée à la plus ancienne */
    public function liste(int $limite = 500): array
    {
        /** @var list<Offre> */
        return $this->base->lire(
            self::SELECT_OFFRE . ' WHERE o.artisan_id = :a AND o.archivee_le IS NULL AND o.supprimee_le IS NULL
             ORDER BY COALESCE(o.modifiee_le, o.cree_le) DESC, o.id DESC LIMIT ' . max(1, $limite),
            ['a' => $this->portee->artisanId],
        );
    }

    /** @return list<Offre> les offres archivées, de la dernière archivée à la plus ancienne */
    public function archives(int $limite = 200): array
    {
        /** @var list<Offre> */
        return $this->base->lire(
            self::SELECT_OFFRE . ' WHERE o.artisan_id = :a AND o.archivee_le IS NOT NULL AND o.supprimee_le IS NULL
             ORDER BY o.archivee_le DESC, o.id DESC LIMIT ' . max(1, $limite),
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

    /** Une fiche client n'est désignée que si elle appartient à l'artisan. */
    private function clientDeLArtisan(?int $clientId): ?int
    {
        if ($clientId === null) {
            return null;
        }
        $trouve = $this->base->valeur('SELECT id FROM client WHERE id = :c AND artisan_id = :a', ['c' => $clientId, 'a' => $this->portee->artisanId]);
        return $trouve === false ? null : (int) $trouve;
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
