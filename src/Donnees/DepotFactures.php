<?php

declare(strict_types=1);

namespace OffreGen\Donnees;

/**
 * Les factures d'un artisan (sous-chantier 18). Une facture naît d'une offre de l'artisan : ses lignes y sont copiées,
 * catégorie comprise. Brouillon, elle se corrige ; émise ou annulée, plus rien ne l'écrit ici, sauf l'émission et
 * l'annulation elles-mêmes (Xavier, 2026-11-13 : « la facture devrait être figée »). Toute écriture de ligne vérifie
 * le statut dans la même requête : une facture émise entre deux clics reste intacte.
 *
 * Les lignes ont la forme de celles de l'offre (a_verifier toujours 0, entendu toujours null) : les mêmes gabarits
 * et le même calcul des totaux les servent.
 *
 * @phpstan-type Facture array{id: int, offre_id: int, offre_numero: int, offre_cree_le: string, numero: string, genre: string, acompte_rang: int|null, acompte_nombre: int|null, acompte_pourcent: int|null, statut: string, date_facture: string, echeance: string, titre: string, client_nom: string, client_adresse: string|null, tva_pour_dix_mille: int|null, arrondi_cinq: int|null, iban: string|null, reference: string|null, ht: int|null, tva: int|null, arrondi: int|null, ttc: int|null, deja_facture: int|null, a_payer: int|null, cree_le: string, modifiee_le: string|null, emise_le: string|null, annulee_le: string|null, refaite_depuis: int|null}
 * @phpstan-type FactureLigne array{id: int, facture_id: int, rang: int, designation: string, quantite_milliemes: int, unite: string, prix_centimes: int, a_verifier: int, entendu: null, produit_id: int|null, categorie: string|null}
 * @phpstan-type LigneACopier array{designation: string, quantite_milliemes: int, unite: string, prix_centimes: int, categorie: string|null, produit_id: int|null}
 * @phpstan-type Entete array{offre_id: int, numero: string, genre: string, acompte_rang: int|null, acompte_nombre: int|null, acompte_pourcent: int|null, date_facture: \DateTimeImmutable, echeance: \DateTimeImmutable, titre: string, client_nom: string, client_adresse: string|null, refaite_depuis?: int|null}
 */
final class DepotFactures implements SupportLignes
{
    public const BROUILLON = 'brouillon';
    public const EMISE = 'emise';
    public const ANNULEE = 'annulee';

    private const SELECT_FACTURE = 'SELECT f.id, f.offre_id, o.numero AS offre_numero, o.cree_le AS offre_cree_le, f.numero, f.genre,
            f.acompte_rang, f.acompte_nombre, f.acompte_pourcent, f.statut, f.date_facture, f.echeance, f.titre, f.client_nom,
            f.client_adresse, f.tva_pour_dix_mille, f.arrondi_cinq, f.iban, f.reference, f.ht, f.tva, f.arrondi, f.ttc,
            f.deja_facture, f.a_payer, f.cree_le, f.modifiee_le, f.emise_le, f.annulee_le, f.refaite_depuis
         FROM facture f JOIN offre o ON o.id = f.offre_id AND o.artisan_id = f.artisan_id';

    private const COLONNES_LIGNE = 'l.id, l.facture_id, l.rang, l.designation, l.quantite_milliemes, l.unite, l.prix_centimes,
            0 AS a_verifier, NULL AS entendu, l.produit_id, l.categorie
         FROM facture_ligne l';

    /** Jointure qui ne laisse passer que la ligne d'un brouillon de l'artisan. */
    private const DU_BROUILLON = 'JOIN facture f ON f.id = l.facture_id AND f.artisan_id = l.artisan_id AND f.statut = \'brouillon\'';

    public function __construct(private readonly Base $base, private readonly Portee $portee)
    {
    }

    /**
     * Crée le brouillon et ses lignes. InvalidArgumentException si l'offre n'est pas à l'artisan ; PDOException si
     * le numéro est déjà pris (index unique), même si deux factures naissent en même temps.
     *
     * @param Entete $e
     * @param list<LigneACopier> $lignes
     */
    public function creer(array $e, array $lignes, \DateTimeImmutable $quand): int
    {
        return $this->base->transaction(function () use ($e, $lignes, $quand): int {
            $offre = $this->base->valeur('SELECT id FROM offre WHERE id = :o AND artisan_id = :a', ['o' => $e['offre_id'], 'a' => $this->portee->artisanId]);
            if ($offre === false) {
                throw new \InvalidArgumentException("Cette offre n'est pas à l'artisan.");
            }
            $this->base->ecrire(
                'INSERT INTO facture (artisan_id, offre_id, numero, genre, acompte_rang, acompte_nombre, acompte_pourcent, statut,
                     date_facture, echeance, titre, client_nom, client_adresse, cree_le, refaite_depuis)
                 VALUES (:a, :o, :n, :g, :ar, :an, :ap, :s, :d, :ec, :t, :cn, :ca, :q, :rd)',
                ['a' => $this->portee->artisanId, 'o' => $e['offre_id'], 'n' => $e['numero'], 'g' => $e['genre'],
                 'ar' => $e['acompte_rang'], 'an' => $e['acompte_nombre'], 'ap' => $e['acompte_pourcent'], 's' => self::BROUILLON,
                 'd' => $e['date_facture']->format('Y-m-d'), 'ec' => $e['echeance']->format('Y-m-d'),
                 't' => mb_substr($e['titre'], 0, 190), 'cn' => mb_substr($e['client_nom'], 0, 190),
                 'ca' => $e['client_adresse'] === null ? null : mb_substr($e['client_adresse'], 0, 255),
                 'q' => Dates::versBase($quand), 'rd' => $this->factureDeLArtisan($e['refaite_depuis'] ?? null)],
            );
            $id = $this->base->dernierId();
            foreach ($lignes as $rang => $l) {
                $this->base->ecrire(
                    'INSERT INTO facture_ligne (artisan_id, facture_id, rang, designation, quantite_milliemes, unite, prix_centimes, categorie, produit_id)
                     VALUES (:a, :f, :r, :d, :q, :u, :p, :c, :pr)',
                    ['a' => $this->portee->artisanId, 'f' => $id, 'r' => $rang + 1, 'd' => mb_substr($l['designation'], 0, 255),
                     'q' => $l['quantite_milliemes'], 'u' => mb_substr($l['unite'], 0, 20), 'p' => $l['prix_centimes'],
                     'c' => $l['categorie'] === null ? null : mb_substr($l['categorie'], 0, 100),
                     'pr' => $this->produitDeLArtisan($l['produit_id'])],
                );
            }
            return $id;
        });
    }

    /** @return Facture|null */
    public function parId(int $id): ?array
    {
        /** @var Facture|null */
        return $this->base->un(self::SELECT_FACTURE . ' WHERE f.id = :id AND f.artisan_id = :a', ['id' => $id, 'a' => $this->portee->artisanId]);
    }

    /** @return list<Facture> de la plus récente à la plus ancienne */
    public function liste(int $limite = 500): array
    {
        /** @var list<Facture> */
        return $this->base->lire(
            self::SELECT_FACTURE . ' WHERE f.artisan_id = :a ORDER BY f.date_facture DESC, f.id DESC LIMIT ' . max(1, $limite),
            ['a' => $this->portee->artisanId],
        );
    }

    /** @return list<Facture> les factures d'une offre, dans l'ordre où elles sont nées */
    public function deOffre(int $offreId): array
    {
        /** @var list<Facture> */
        return $this->base->lire(
            self::SELECT_FACTURE . ' WHERE f.offre_id = :o AND f.artisan_id = :a ORDER BY f.id',
            ['o' => $offreId, 'a' => $this->portee->artisanId],
        );
    }

    /** Le numéro de la dernière facture créée, d'où part le suivant ; null s'il n'y en a aucune. */
    public function dernierNumero(): ?string
    {
        $n = $this->base->valeur('SELECT numero FROM facture WHERE artisan_id = :a ORDER BY cree_le DESC, id DESC LIMIT 1', ['a' => $this->portee->artisanId]);
        return $n === false ? null : (string) $n;
    }

    /** Vrai si une autre facture de l'artisan porte ce numéro (comparaison de MySQL : sans casse ni espaces de fin). */
    public function numeroPris(string $numero, ?int $sauf = null): bool
    {
        return $this->base->valeur(
            'SELECT id FROM facture WHERE artisan_id = :a AND numero = :n AND id <> :s LIMIT 1',
            ['a' => $this->portee->artisanId, 'n' => $numero, 's' => $sauf ?? 0],
        ) !== false;
    }

    /** Les acomptes non annulés de l'offre, brouillons compris : ils fixent le rang du suivant. */
    public function acomptesFaits(int $offreId): int
    {
        return (int) $this->base->valeur(
            "SELECT COUNT(*) FROM facture WHERE offre_id = :o AND artisan_id = :a AND genre = 'acompte' AND statut <> 'annulee'",
            ['o' => $offreId, 'a' => $this->portee->artisanId],
        );
    }

    public function finaleFaite(int $offreId): bool
    {
        return $this->base->valeur(
            "SELECT id FROM facture WHERE offre_id = :o AND artisan_id = :a AND genre = 'finale' AND statut <> 'annulee' LIMIT 1",
            ['o' => $offreId, 'a' => $this->portee->artisanId],
        ) !== false;
    }

    /** Le TTC des acomptes émis de l'offre, que la facture finale déduit. */
    public function dejaFacture(int $offreId): int
    {
        return (int) $this->base->valeur(
            "SELECT COALESCE(SUM(ttc), 0) FROM facture WHERE offre_id = :o AND artisan_id = :a AND genre = 'acompte' AND statut = 'emise'",
            ['o' => $offreId, 'a' => $this->portee->artisanId],
        );
    }

    /**
     * Émet le brouillon : fige ce qui fait le montant et garde le PDF, dans une transaction. Faux si la facture n'est
     * pas (ou plus) un brouillon de l'artisan : rien n'est écrit.
     *
     * @param array{tva_pour_dix_mille: int, arrondi_cinq: int, iban: string|null, reference: string|null, ht: int, tva: int, arrondi: int, ttc: int, deja_facture: int, a_payer: int} $fige
     */
    public function emettre(int $id, array $fige, string $pdf, \DateTimeImmutable $quand): bool
    {
        return $this->base->transaction(function () use ($id, $fige, $pdf, $quand): bool {
            $emise = $this->base->ecrire(
                "UPDATE facture SET statut = 'emise', emise_le = :q, tva_pour_dix_mille = :tx, arrondi_cinq = :ar5, iban = :ib, reference = :ref,
                     ht = :ht, tva = :tva, arrondi = :arr, ttc = :ttc, deja_facture = :deja, a_payer = :ap
                 WHERE id = :id AND artisan_id = :a AND statut = 'brouillon'",
                ['q' => Dates::versBase($quand), 'tx' => $fige['tva_pour_dix_mille'], 'ar5' => $fige['arrondi_cinq'], 'ib' => $fige['iban'],
                 'ref' => $fige['reference'], 'ht' => $fige['ht'], 'tva' => $fige['tva'], 'arr' => $fige['arrondi'], 'ttc' => $fige['ttc'],
                 'deja' => $fige['deja_facture'], 'ap' => $fige['a_payer'], 'id' => $id, 'a' => $this->portee->artisanId],
            ) === 1;
            if ($emise) {
                $this->base->ecrire(
                    'INSERT INTO facture_pdf (facture_id, artisan_id, contenu, cree_le) VALUES (:f, :a, :c, :q)',
                    ['f' => $id, 'a' => $this->portee->artisanId, 'c' => $pdf, 'q' => Dates::versBase($quand)],
                );
            }
            return $emise;
        });
    }

    /** Annule une facture émise : elle reste, son numéro aussi. Faux si elle n'est pas émise chez l'artisan. */
    public function annuler(int $id, \DateTimeImmutable $quand): bool
    {
        return $this->base->ecrire(
            "UPDATE facture SET statut = 'annulee', annulee_le = :q WHERE id = :id AND artisan_id = :a AND statut = 'emise'",
            ['q' => Dates::versBase($quand), 'id' => $id, 'a' => $this->portee->artisanId],
        ) === 1;
    }

    /** Le PDF gardé à l'émission ; null pour un brouillon ou une facture d'un autre artisan. */
    public function pdf(int $id): ?string
    {
        $c = $this->base->valeur('SELECT contenu FROM facture_pdf WHERE facture_id = :f AND artisan_id = :a', ['f' => $id, 'a' => $this->portee->artisanId]);
        return $c === false ? null : (string) $c;
    }

    /** Vrai si une facture finale émise de l'offre a déjà déduit ses acomptes. */
    public function finaleEmise(int $offreId): bool
    {
        return $this->base->valeur(
            "SELECT id FROM facture WHERE offre_id = :o AND artisan_id = :a AND genre = 'finale' AND statut = 'emise' LIMIT 1",
            ['o' => $offreId, 'a' => $this->portee->artisanId],
        ) !== false;
    }

    /** Numéro, date et échéance d'un brouillon. Faux si la facture n'est pas un brouillon de l'artisan. */
    public function modifierEntete(int $id, string $numero, \DateTimeImmutable $date, \DateTimeImmutable $echeance, \DateTimeImmutable $quand): bool
    {
        return $this->base->ecrire(
            "UPDATE facture SET numero = :n, date_facture = :d, echeance = :e, modifiee_le = :q WHERE id = :id AND artisan_id = :a AND statut = 'brouillon'",
            ['n' => $numero, 'd' => $date->format('Y-m-d'), 'e' => $echeance->format('Y-m-d'), 'q' => Dates::versBase($quand),
             'id' => $id, 'a' => $this->portee->artisanId],
        ) === 1;
    }

    /** Efface un brouillon et ses lignes : son numéro se libère. Faux s'il n'est pas un brouillon de l'artisan. */
    public function supprimerBrouillon(int $id): bool
    {
        return $this->base->ecrire(
            "DELETE FROM facture WHERE id = :id AND artisan_id = :a AND statut = 'brouillon'",
            ['id' => $id, 'a' => $this->portee->artisanId],
        ) === 1;
    }

    /** Toute correction d'une ligne date le brouillon. */
    public function marquerModifiee(int $id, \DateTimeImmutable $quand): void
    {
        $this->base->ecrire(
            "UPDATE facture SET modifiee_le = :q WHERE id = :id AND artisan_id = :a AND statut = 'brouillon'",
            ['q' => Dates::versBase($quand), 'id' => $id, 'a' => $this->portee->artisanId],
        );
    }

    /** @return list<FactureLigne> */
    public function lignes(int $factureId): array
    {
        /** @var list<FactureLigne> */
        return $this->base->lire(
            'SELECT ' . self::COLONNES_LIGNE . ' WHERE l.facture_id = :f AND l.artisan_id = :a AND l.supprimee_le IS NULL ORDER BY l.rang, l.id',
            ['f' => $factureId, 'a' => $this->portee->artisanId],
        );
    }

    /**
     * Les lignes de plusieurs factures d'un coup, pour les totaux de la liste.
     *
     * @param list<int> $factureIds
     * @return array<int, list<FactureLigne>>
     */
    public function lignesDe(array $factureIds): array
    {
        $parFacture = array_fill_keys($factureIds, []);
        if ($factureIds === []) {
            return $parFacture;
        }
        $ids = implode(',', array_map('intval', $factureIds));
        /** @var list<FactureLigne> $lignes */
        $lignes = $this->base->lire(
            'SELECT ' . self::COLONNES_LIGNE . " WHERE l.artisan_id = :a AND l.facture_id IN ($ids) AND l.supprimee_le IS NULL ORDER BY l.facture_id, l.rang, l.id",
            ['a' => $this->portee->artisanId],
        );
        foreach ($lignes as $l) {
            $parFacture[$l['facture_id']][] = $l;
        }
        return $parFacture;
    }

    /** @return FactureLigne|null une ligne visible de la facture */
    public function ligne(int $factureId, int $ligneId): ?array
    {
        /** @var FactureLigne|null */
        return $this->base->un(
            'SELECT ' . self::COLONNES_LIGNE . ' WHERE l.id = :l AND l.facture_id = :f AND l.artisan_id = :a AND l.supprimee_le IS NULL',
            ['l' => $ligneId, 'f' => $factureId, 'a' => $this->portee->artisanId],
        );
    }

    /** Faux si la ligne n'est pas dans un brouillon de l'artisan ; la jointure protège encore si la facture est émise entre-temps. */
    public function corrigerLigne(int $factureId, int $ligneId, string $designation, int $quantiteMilliemes, string $unite, int $prixCentimes): bool
    {
        if (!$this->ligneDuBrouillon($factureId, $ligneId)) {
            return false;
        }
        $this->base->ecrire(
            'UPDATE facture_ligne l ' . self::DU_BROUILLON . ' SET l.designation = :d, l.quantite_milliemes = :q, l.unite = :u, l.prix_centimes = :p
             WHERE l.id = :l AND l.facture_id = :f AND l.artisan_id = :a AND l.supprimee_le IS NULL',
            ['d' => mb_substr($designation, 0, 255), 'q' => $quantiteMilliemes, 'u' => mb_substr($unite, 0, 20), 'p' => $prixCentimes,
             'l' => $ligneId, 'f' => $factureId, 'a' => $this->portee->artisanId],
        );
        return true;
    }

    /** Une ligne de facture n'est jamais « à vérifier » : rien à confirmer. Vrai si elle existe dans un brouillon. */
    public function confirmerLigne(int $factureId, int $ligneId): bool
    {
        return $this->ligneDuBrouillon($factureId, $ligneId);
    }

    public function supprimerLigne(int $factureId, int $ligneId, \DateTimeImmutable $quand): bool
    {
        return $this->base->ecrire(
            'UPDATE facture_ligne l ' . self::DU_BROUILLON . ' SET l.supprimee_le = :q
             WHERE l.id = :l AND l.facture_id = :f AND l.artisan_id = :a AND l.supprimee_le IS NULL',
            ['q' => Dates::versBase($quand), 'l' => $ligneId, 'f' => $factureId, 'a' => $this->portee->artisanId],
        ) === 1;
    }

    public function restaurerLigne(int $factureId, int $ligneId): bool
    {
        return $this->base->ecrire(
            'UPDATE facture_ligne l ' . self::DU_BROUILLON . ' SET l.supprimee_le = NULL
             WHERE l.id = :l AND l.facture_id = :f AND l.artisan_id = :a AND l.supprimee_le IS NOT NULL',
            ['l' => $ligneId, 'f' => $factureId, 'a' => $this->portee->artisanId],
        ) === 1;
    }

    /** Ajoute une ligne en fin de brouillon, avec la catégorie du produit ; 0 si la facture n'est pas un brouillon de l'artisan. */
    public function ajouterLigne(int $factureId, string $designation, int $quantiteMilliemes, string $unite, int $prixCentimes, ?int $produitId): int
    {
        return $this->base->transaction(function () use ($factureId, $designation, $quantiteMilliemes, $unite, $prixCentimes, $produitId): int {
            $produit = $this->produitDeLArtisan($produitId);
            $rang = (int) $this->base->valeur(
                'SELECT COALESCE(MAX(rang), 0) + 1 FROM facture_ligne WHERE facture_id = :f AND artisan_id = :a',
                ['f' => $factureId, 'a' => $this->portee->artisanId],
            );
            $ajoutees = $this->base->ecrire(
                "INSERT INTO facture_ligne (artisan_id, facture_id, rang, designation, quantite_milliemes, unite, prix_centimes, categorie, produit_id)
                 SELECT :a, f.id, :r, :d, :q, :u, :p, (SELECT categorie FROM produit WHERE id = :pr1 AND artisan_id = :a3), :pr2
                 FROM facture f WHERE f.id = :f AND f.artisan_id = :a2 AND f.statut = 'brouillon'",
                ['a' => $this->portee->artisanId, 'a2' => $this->portee->artisanId, 'a3' => $this->portee->artisanId, 'f' => $factureId,
                 'r' => $rang, 'd' => mb_substr($designation, 0, 255), 'q' => $quantiteMilliemes, 'u' => mb_substr($unite, 0, 20),
                 'p' => $prixCentimes, 'pr1' => $produit, 'pr2' => $produit],
            );
            return $ajoutees === 1 ? $this->base->dernierId() : 0;
        });
    }

    /** Une ligne libre gardée au catalogue devient la ligne de ce produit, et prend sa catégorie. */
    public function rattacherProduit(int $factureId, int $ligneId, int $produitId): void
    {
        $produit = $this->produitDeLArtisan($produitId);
        $this->base->ecrire(
            'UPDATE facture_ligne l ' . self::DU_BROUILLON . '
             SET l.produit_id = :pr1, l.categorie = (SELECT categorie FROM produit WHERE id = :pr2 AND artisan_id = :a2)
             WHERE l.id = :l AND l.facture_id = :f AND l.artisan_id = :a',
            ['pr1' => $produit, 'pr2' => $produit, 'a2' => $this->portee->artisanId, 'l' => $ligneId, 'f' => $factureId, 'a' => $this->portee->artisanId],
        );
    }

    /** Efface toutes les factures de l'artisan (réinitialisation d'un compte démo), PDF compris. */
    public function toutEffacer(): void
    {
        $this->base->ecrire('DELETE FROM facture_pdf WHERE artisan_id = :a', ['a' => $this->portee->artisanId]);
        $this->base->ecrire('UPDATE facture SET refaite_depuis = NULL WHERE artisan_id = :a', ['a' => $this->portee->artisanId]);
        $this->base->ecrire('DELETE FROM facture WHERE artisan_id = :a', ['a' => $this->portee->artisanId]);
    }

    private function ligneDuBrouillon(int $factureId, int $ligneId): bool
    {
        return $this->base->valeur(
            'SELECT l.id FROM facture_ligne l ' . self::DU_BROUILLON . '
             WHERE l.id = :l AND l.facture_id = :f AND l.artisan_id = :a AND l.supprimee_le IS NULL',
            ['l' => $ligneId, 'f' => $factureId, 'a' => $this->portee->artisanId],
        ) !== false;
    }

    private function factureDeLArtisan(?int $id): ?int
    {
        if ($id === null) {
            return null;
        }
        $trouve = $this->base->valeur('SELECT id FROM facture WHERE id = :f AND artisan_id = :a', ['f' => $id, 'a' => $this->portee->artisanId]);
        return $trouve === false ? null : (int) $trouve;
    }

    private function produitDeLArtisan(?int $produitId): ?int
    {
        if ($produitId === null) {
            return null;
        }
        $trouve = $this->base->valeur('SELECT id FROM produit WHERE id = :p AND artisan_id = :a', ['p' => $produitId, 'a' => $this->portee->artisanId]);
        return $trouve === false ? null : (int) $trouve;
    }
}
