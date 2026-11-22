<?php

declare(strict_types=1);

namespace OffreGen\Donnees;

use OffreGen\Compte\Conditions;
use OffreGen\Offre\Chantier;
use OffreGen\Offre\LigneExtraite;
use OffreGen\Offre\Options;
use OffreGen\Offre\Rabais;

/**
 * Les offres d'un artisan et leurs lignes. Une ligne supprimée garde sa place (supprimee_le) :
 * « Annuler » la remet ; les lectures l'ignorent. Une offre se range de même : archivée (archivee_le)
 * ou supprimée (supprimee_le), elle reste en base et compte dans les offres du mois.
 *
 * Le client d'une offre : sa fiche (client_id), sinon le nom et le lieu dictés, proposition à confirmer.
 * client_nom et client_adresse donnent l'un ou l'autre, pour l'écran et le document.
 *
 * Le représentant (régie, gérance, architecte : D8 A, 2026-11-21) est une autre fiche de l'artisan. offre_adressee :
 * « representant » (D9 C, par défaut) ou « client » ; factures_adressees : « pa » (au client, p.a. le représentant,
 * par défaut) ou « client » (chez lui) (D10 C).
 *
 * @phpstan-type Offre array{id: int, numero: int, enregistrement_id: int|null, titre: string, client_id: int|null, client_nom: string|null, client_adresse: string|null, client_dicte: string|null, lieu_dicte: string|null, cree_le: string, modifiee_le: string|null, envoyee_le: string|null, postes_coupes: int, chantier_adresse: string|null, chantier_contact: string|null, chantier_acces: string|null, debut_travaux: string|null, duree_travaux: string|null, non_compris: string|null, visite_le: string|null, rabais: string|null, conditions: string|null, texte_avant: string|null, texte_apres: string|null, representant_id: int|null, representant_nom: string|null, representant_adresse: string|null, offre_adressee: string, factures_adressees: string}
 * @phpstan-type Ligne array{id: int, offre_id: int, rang: int, designation: string, quantite_milliemes: int, unite: string, prix_centimes: int, offerte: int, a_verifier: int, entendu: string|null, produit_id: int|null, categorie: string|null, tva_pour_dix_mille: int|null, tva_ligne: int|null, prix_dicte_centimes: int|null, prix_catalogue: int|null, piece: string|null, nature: string, remplace_id: int|null, retenue: int}
 */
final class DepotOffres implements SupportLignes
{
    private const SELECT_OFFRE = 'SELECT o.id, o.numero, o.enregistrement_id, o.titre, o.client_id,
            COALESCE(c.nom, o.client_dicte) AS client_nom, CASE WHEN c.id IS NULL THEN o.lieu_dicte ELSE c.adresse END AS client_adresse,
            o.client_dicte, o.lieu_dicte, o.cree_le, o.modifiee_le, o.envoyee_le, o.postes_coupes,
            o.chantier_adresse, o.chantier_contact, o.chantier_acces, o.debut_travaux, o.duree_travaux, o.non_compris, o.visite_le, o.rabais, o.conditions, o.texte_avant, o.texte_apres,
            rp.id AS representant_id, rp.nom AS representant_nom, rp.adresse AS representant_adresse, o.offre_adressee, o.factures_adressees
         FROM offre o LEFT JOIN client c ON c.id = o.client_id AND c.artisan_id = o.artisan_id
         LEFT JOIN client rp ON rp.id = o.representant_id AND rp.artisan_id = o.artisan_id';

    /** Colonnes d'une ligne ; la catégorie vient du produit du catalogue, s'il existe encore. */
    private const COLONNES_LIGNE = 'l.id, l.offre_id, l.rang, l.designation, l.quantite_milliemes, l.unite, l.prix_centimes, l.offerte, l.a_verifier, l.entendu, l.produit_id, p.categorie,
            COALESCE(l.tva_pour_dix_mille, p.tva_pour_dix_mille) AS tva_pour_dix_mille, l.tva_pour_dix_mille AS tva_ligne,
            l.prix_dicte_centimes, p.prix_centimes AS prix_catalogue, l.piece, l.nature, l.remplace_id, l.retenue
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
    public function creerDepuisEnregistrement(int $enregistrementId, string $titre, array $lignes, \DateTimeImmutable $quand, ?int $clientId = null, ?string $clientDicte = null, ?string $lieuDicte = null, int $postesCoupes = 0): int
    {
        return $this->creer($enregistrementId, $titre, $lignes, $quand, $clientId, $clientDicte, $lieuDicte, $postesCoupes);
    }

    /**
     * Offre d'exemple d'un compte de démonstration : elle ne vient d'aucun enregistrement.
     *
     * @param list<LigneExtraite> $lignes
     */
    public function creerExemple(string $titre, array $lignes, \DateTimeImmutable $quand, int $clientId): int
    {
        return $this->creer(null, $titre, $lignes, $quand, $clientId, null, null, 0, true);
    }

    /** @param list<LigneExtraite> $lignes */
    private function creer(?int $enregistrementId, string $titre, array $lignes, \DateTimeImmutable $quand, ?int $clientId, ?string $clientDicte, ?string $lieuDicte, int $postesCoupes = 0, bool $exemple = false): int
    {
        return $this->base->transaction(function () use ($enregistrementId, $titre, $lignes, $quand, $clientId, $clientDicte, $lieuDicte, $postesCoupes, $exemple): int {
            $numero = (int) $this->base->valeur('SELECT COALESCE(MAX(numero), 0) + 1 FROM offre WHERE artisan_id = :a', ['a' => $this->portee->artisanId]);
            $this->base->ecrire(
                // Le lieu dicté est aussi l'adresse du chantier (modèles du bâtiment, 2026-11-20) : l'artisan la corrige sur l'en-tête.
                'INSERT INTO offre (artisan_id, numero, enregistrement_id, titre, client_id, client_dicte, lieu_dicte, chantier_adresse, cree_le, postes_coupes, exemple) VALUES (:a, :n, :e, :t, :ci, :cn, :ca, :ch, :q, :pc, :ex)',
                ['a' => $this->portee->artisanId, 'n' => $numero, 'e' => $enregistrementId, 't' => mb_substr($titre, 0, 190),
                 'ci' => $this->clientDeLArtisan($clientId),
                 'cn' => $clientDicte === null ? null : mb_substr($clientDicte, 0, 190), 'ca' => $lieuDicte === null ? null : mb_substr($lieuDicte, 0, 255),
                 'ch' => $lieuDicte === null ? null : mb_substr($lieuDicte, 0, 255),
                 'q' => Dates::versBase($quand), 'pc' => min(max(0, $postesCoupes), 65535), 'ex' => $exemple ? 1 : 0],
            );
            $offreId = $this->base->dernierId();
            $ids = [];
            foreach ($lignes as $rang => $l) {
                $this->base->ecrire(
                    'INSERT INTO ligne (artisan_id, offre_id, rang, designation, quantite_milliemes, unite, prix_centimes, a_verifier, entendu, produit_id, prix_dicte_centimes, piece, nature)
                     VALUES (:a, :o, :r, :d, :q, :u, :p, :v, :e, :pr, :pd, :pi, :n)',
                    ['a' => $this->portee->artisanId, 'o' => $offreId, 'r' => $rang + 1, 'd' => mb_substr($l->designation, 0, 255),
                     'q' => $l->quantiteMilliemes, 'u' => mb_substr($l->unite, 0, 20), 'p' => $l->prixCentimes, 'v' => $l->aVerifier ? 1 : 0,
                     'e' => $l->entendu === null ? null : mb_substr($l->entendu, 0, 500),
                     'pr' => $this->produitDeLArtisan($l->produitId), 'pd' => $l->prixDicteCentimes, 'pi' => $l->piece,
                     'n' => in_array($l->nature, Options::NATURES, true) ? $l->nature : Options::LIGNE],
                );
                $ids[$rang] = $this->base->dernierId();
            }
            // Une variante désigne sa ligne par son rang dans la liste : son identifiant n'existe qu'une fois tout inséré.
            // Une base qui n'est pas une ligne du total : la variante reste une option à vérifier (O3).
            foreach ($lignes as $rang => $l) {
                if ($l->nature !== Options::VARIANTE) {
                    continue;
                }
                $base = $l->remplace;
                $valable = $base !== null && $base !== $rang && isset($ids[$base]) && ($lignes[$base]->nature ?? null) === Options::LIGNE;
                $this->base->ecrire(
                    $valable ? 'UPDATE ligne SET remplace_id = :b WHERE id = :l AND artisan_id = :a'
                        : "UPDATE ligne SET nature = 'option', a_verifier = 1 WHERE id = :l AND artisan_id = :a",
                    ($valable ? ['b' => $ids[$base]] : []) + ['l' => $ids[$rang], 'a' => $this->portee->artisanId],
                );
            }
            return $offreId;
        });
    }

    /**
     * Verrouille l'offre jusqu'à la fin de la transaction en cours (SELECT … FOR UPDATE) : deux facturations de la même
     * offre se suivent au lieu de se croiser (revue du sous-chantier 25). Sans transaction, ne retient rien.
     */
    public function verrouiller(int $id): void
    {
        $this->base->valeur('SELECT id FROM offre WHERE id = :id AND artisan_id = :a FOR UPDATE', ['id' => $id, 'a' => $this->portee->artisanId]);
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

    /** Le chantier de l'offre, tel que l'artisan l'a rempli ; un champ vide retourne à NULL. Faux si l'offre n'est pas à lui. */
    public function modifierChantier(int $id, Chantier $c, \DateTimeImmutable $quand): bool
    {
        if ($this->parId($id) === null) {
            return false;
        }
        $vide = static fn (string $s): ?string => $s === '' ? null : $s;
        $this->base->ecrire(
            'UPDATE offre SET chantier_adresse = :ad, chantier_contact = :co, chantier_acces = :ac, debut_travaux = :de, duree_travaux = :du,
                    non_compris = :nc, visite_le = :vi, modifiee_le = :q
             WHERE id = :id AND artisan_id = :a',
            ['ad' => $vide($c->adresse), 'co' => $vide($c->contact), 'ac' => $vide($c->acces), 'de' => $vide($c->debut), 'du' => $vide($c->duree),
             'nc' => $vide(implode("\n", $c->nonCompris)), 'vi' => $c->visite?->format('Y-m-d'), 'q' => Dates::versBase($quand), 'id' => $id, 'a' => $this->portee->artisanId],
        );
        return true;
    }

    /**
     * Les rabais de l'offre (Offre\Rabais), tous ensemble ; une liste vide retourne à NULL. Faux si l'offre n'est pas à lui.
     *
     * @param list<Rabais> $rabais
     */
    public function changerRabais(int $id, array $rabais, \DateTimeImmutable $quand): bool
    {
        if ($this->parId($id) === null) {
            return false;
        }
        $this->base->ecrire(
            'UPDATE offre SET rabais = :r, modifiee_le = :q WHERE id = :id AND artisan_id = :a',
            ['r' => Rabais::versJson(array_slice($rabais, 0, Rabais::MAX)), 'q' => Dates::versBase($quand), 'id' => $id, 'a' => $this->portee->artisanId],
        );
        return true;
    }

    /** Les conditions propres à l'offre (Compte\Conditions en JSON) ; null, celles du compte. Faux si l'offre n'est pas à lui. */
    public function changerConditions(int $id, ?Conditions $c, \DateTimeImmutable $quand): bool
    {
        if ($this->parId($id) === null) {
            return false;
        }
        $this->base->ecrire(
            'UPDATE offre SET conditions = :c, modifiee_le = :q WHERE id = :id AND artisan_id = :a',
            ['c' => $c?->versJson(), 'q' => Dates::versBase($quand), 'id' => $id, 'a' => $this->portee->artisanId],
        );
        return true;
    }

    /** Le texte avant ou après les lignes (« avant », « apres ») ; vide, NULL. Faux si l'offre n'est pas à lui. */
    public function changerTexte(int $id, string $ou, string $texte, \DateTimeImmutable $quand): bool
    {
        if ($this->parId($id) === null) {
            return false;
        }
        $colonne = $ou === 'avant' ? 'texte_avant' : 'texte_apres';
        $this->base->ecrire(
            "UPDATE offre SET $colonne = :t, modifiee_le = :q WHERE id = :id AND artisan_id = :a",
            ['t' => $texte === '' ? null : $texte, 'q' => Dates::versBase($quand), 'id' => $id, 'a' => $this->portee->artisanId],
        );
        return true;
    }

    public const ADRESSEE = ['representant', 'client'];
    public const FACTURES_ADRESSEES = ['pa', 'client'];

    /**
     * Le représentant du client et à qui vont l'offre et les factures. Faux si l'offre ou la fiche n'est pas à l'artisan,
     * ou si la fiche est celle du client de l'offre.
     */
    public function changerRepresentant(int $id, ?int $representantId, string $offreAdressee, string $facturesAdressees, \DateTimeImmutable $quand): bool
    {
        $offre = $this->parId($id);
        $fiche = $this->clientDeLArtisan($representantId);
        if ($offre === null || ($representantId !== null && ($fiche === null || $fiche === $offre['client_id']))
            || !in_array($offreAdressee, self::ADRESSEE, true) || !in_array($facturesAdressees, self::FACTURES_ADRESSEES, true)) {
            return false;
        }
        $this->base->ecrire(
            'UPDATE offre SET representant_id = :r, offre_adressee = :oa, factures_adressees = :fa, modifiee_le = :q WHERE id = :id AND artisan_id = :a',
            ['r' => $fiche, 'oa' => $offreAdressee, 'fa' => $facturesAdressees, 'q' => Dates::versBase($quand), 'id' => $id, 'a' => $this->portee->artisanId],
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
            // La fiche devenue client ne reste pas son propre représentant.
            'UPDATE offre SET client_id = :c, representant_id = IF(representant_id = :c2, NULL, representant_id), modifiee_le = :q WHERE id = :id AND artisan_id = :a',
            ['c' => $client, 'c2' => $client, 'q' => Dates::versBase($quand), 'id' => $id, 'a' => $this->portee->artisanId],
        );
        return true;
    }

    /** Toute correction d'une ligne date l'offre : la liste la remonte et l'affiche « modifiée le ». */
    /** L'offre est partie chez le client (décision D8, 2026-11-15) : le partage du téléphone a réussi. */
    public function marquerEnvoyee(int $id, \DateTimeImmutable $quand): void
    {
        $this->base->ecrire(
            'UPDATE offre SET envoyee_le = :q WHERE id = :id AND artisan_id = :a',
            ['q' => Dates::versBase($quand), 'id' => $id, 'a' => $this->portee->artisanId],
        );
    }

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
        // « C'est juste » garde la ligne telle quelle : un prix dicté resté à côté n'a plus lieu d'être montré.
        $this->base->ecrire(
            'UPDATE ligne SET a_verifier = 0, prix_dicte_centimes = NULL WHERE id = :l AND offre_id = :o AND artisan_id = :a',
            ['l' => $ligneId, 'o' => $offreId, 'a' => $this->portee->artisanId],
        );
        return true;
    }

    /** « Prendre » : le prix dicté devient celui de la ligne ; il reste noté, pour la question du catalogue. */
    public function prendrePrixDicte(int $offreId, int $ligneId): bool
    {
        return $this->base->ecrire(
            'UPDATE ligne SET prix_centimes = prix_dicte_centimes, a_verifier = 0
             WHERE id = :l AND offre_id = :o AND artisan_id = :a AND supprimee_le IS NULL AND prix_dicte_centimes IS NOT NULL',
            ['l' => $ligneId, 'o' => $offreId, 'a' => $this->portee->artisanId],
        ) === 1;
    }

    /** La question du prix dicté est réglée (catalogue changé ou non) : la note disparaît. */
    public function oublierPrixDicte(int $offreId, int $ligneId): void
    {
        $this->base->ecrire(
            'UPDATE ligne SET prix_dicte_centimes = NULL WHERE id = :l AND offre_id = :o AND artisan_id = :a',
            ['l' => $ligneId, 'o' => $offreId, 'a' => $this->portee->artisanId],
        );
    }

    /** Déplace une ligne dans une pièce (P5) ; null : « Général ». */
    public function changerPiece(int $offreId, int $ligneId, ?string $piece): void
    {
        $this->base->ecrire(
            'UPDATE ligne SET piece = :p WHERE id = :l AND offre_id = :o AND artisan_id = :a AND supprimee_le IS NULL',
            ['p' => $piece, 'l' => $ligneId, 'o' => $offreId, 'a' => $this->portee->artisanId],
        );
    }

    /**
     * Renomme une pièce de l'offre (P5) : toutes ses lignes, sans tenir compte de la casse (« chambre » est
     * « Chambre »). Rend le nombre de lignes touchées.
     */
    public function renommerPiece(int $offreId, string $ancienne, string $nouvelle): int
    {
        return $this->base->ecrire(
            'UPDATE ligne SET piece = :n WHERE offre_id = :o AND artisan_id = :a AND LOWER(piece) = LOWER(:v)',
            ['n' => $nouvelle, 'v' => $ancienne, 'o' => $offreId, 'a' => $this->portee->artisanId],
        );
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

    /**
     * La nature d'une ligne (O4, E3) : dans le total, option, ou variante de la ligne $remplaceId. Faux, sans rien écrire,
     * si la ligne n'est pas à l'artisan, si la ligne remplacée n'est pas une ligne du total de la même offre (ou est la
     * ligne elle-même), ou si une ligne qui a des variantes cesserait d'être dans le total. Un changement remet le
     * choix du client à zéro.
     */
    public function changerNature(int $offreId, int $ligneId, string $nature, ?int $remplaceId): bool
    {
        if (!in_array($nature, Options::NATURES, true)) {
            return false;
        }
        return $this->base->transaction(function () use ($offreId, $ligneId, $nature, $remplaceId): bool {
            $l = $this->ligne($offreId, $ligneId);
            if ($l === null) {
                return false;
            }
            if ($nature !== Options::VARIANTE) {
                $remplaceId = null;
            } elseif ($remplaceId === null || $remplaceId === $ligneId || ($this->ligne($offreId, $remplaceId)['nature'] ?? null) !== Options::LIGNE) {
                return false;
            }
            if ($nature !== Options::LIGNE && $this->aDesVariantes($offreId, $ligneId)) {
                return false;
            }
            $retenue = $nature === $l['nature'] && $remplaceId === $l['remplace_id'] ? $l['retenue'] : 0;
            $this->base->ecrire(
                'UPDATE ligne SET nature = :n, remplace_id = :r, retenue = :t WHERE id = :l AND offre_id = :o AND artisan_id = :a',
                ['n' => $nature, 'r' => $remplaceId, 't' => $retenue, 'l' => $ligneId, 'o' => $offreId, 'a' => $this->portee->artisanId],
            );
            return true;
        });
    }

    /** Une variante active désigne cette ligne. */
    public function aDesVariantes(int $offreId, int $ligneId): bool
    {
        return (int) $this->base->valeur(
            "SELECT COUNT(*) FROM ligne WHERE offre_id = :o AND artisan_id = :a AND remplace_id = :l AND nature = 'variante' AND supprimee_le IS NULL",
            ['o' => $offreId, 'a' => $this->portee->artisanId, 'l' => $ligneId],
        ) > 0;
    }

    /**
     * Le choix du client sur une option ou une variante (O5) ; retenir une variante décoche les autres variantes de la
     * même ligne (point 3, 2026-11-22). Faux si la ligne n'est pas à l'artisan, ou n'est ni option ni variante.
     */
    public function retenir(int $offreId, int $ligneId, bool $retenue): bool
    {
        return $this->base->transaction(function () use ($offreId, $ligneId, $retenue): bool {
            $l = $this->ligne($offreId, $ligneId);
            if ($l === null || !in_array($l['nature'], [Options::OPTION, Options::VARIANTE], true)) {
                return false;
            }
            $this->base->ecrire(
                'UPDATE ligne SET retenue = :r WHERE id = :l AND offre_id = :o AND artisan_id = :a',
                ['r' => $retenue ? 1 : 0, 'l' => $ligneId, 'o' => $offreId, 'a' => $this->portee->artisanId],
            );
            if ($retenue && $l['nature'] === Options::VARIANTE && $l['remplace_id'] !== null) {
                $this->base->ecrire(
                    "UPDATE ligne SET retenue = 0 WHERE offre_id = :o AND artisan_id = :a AND nature = 'variante' AND remplace_id = :b AND id <> :l",
                    ['o' => $offreId, 'a' => $this->portee->artisanId, 'b' => $l['remplace_id'], 'l' => $ligneId],
                );
            }
            return true;
        });
    }

    /** Ligne offerte ou facturée (D1, 2026-11-21) ; le prix ne change pas. */
    public function changerOfferte(int $offreId, int $ligneId, bool $offerte): bool
    {
        return $this->base->ecrire(
            'UPDATE ligne SET offerte = :v WHERE id = :l AND offre_id = :o AND artisan_id = :a AND supprimee_le IS NULL',
            ['v' => $offerte ? 1 : 0, 'l' => $ligneId, 'o' => $offreId, 'a' => $this->portee->artisanId],
        ) === 1 || $this->ligne($offreId, $ligneId) !== null;
    }

    /** Le taux d'exception d'une ligne (D11) ; null : celui de son produit, ou de l'entreprise. */
    public function changerTauxLigne(int $offreId, int $ligneId, ?int $tauxPourDixMille): bool
    {
        return $this->base->ecrire(
            'UPDATE ligne SET tva_pour_dix_mille = :t WHERE id = :l AND offre_id = :o AND artisan_id = :a AND supprimee_le IS NULL',
            ['t' => $tauxPourDixMille, 'l' => $ligneId, 'o' => $offreId, 'a' => $this->portee->artisanId],
        ) === 1 || $this->ligne($offreId, $ligneId) !== null;
    }

    /**
     * Rattachée à un produit d'un autre prix (4b, 2026-11-20) : la ligne garde son prix d'avant en note (comme un prix
     * dicté, « Prendre ») et passe à vérifier.
     */
    public function garderPrixDeLaLigne(int $offreId, int $ligneId, int $prixCentimes): void
    {
        $this->base->ecrire(
            'UPDATE ligne SET prix_dicte_centimes = :p, a_verifier = 1 WHERE id = :l AND offre_id = :o AND artisan_id = :a AND supprimee_le IS NULL',
            ['p' => $prixCentimes, 'l' => $ligneId, 'o' => $offreId, 'a' => $this->portee->artisanId],
        );
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

    /**
     * Passage d'une démonstration à l'essai : les offres d'exemple partent, lignes comprises. Leurs factures
     * sont à effacer avant (DepotFactures::effacerDesOffresExemple).
     *
     * @return int nombre d'offres d'exemple effacées
     */
    public function effacerExemples(): int
    {
        return $this->base->ecrire('DELETE FROM offre WHERE artisan_id = :a AND exemple = 1', ['a' => $this->portee->artisanId]);
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
